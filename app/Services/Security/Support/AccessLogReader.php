<?php

namespace App\Services\Security\Support;

use App\Services\Security\State\FileStateStore;
use Carbon\CarbonImmutable;

/**
 * Lector incremental del access log de nginx/OpenResty.
 *
 * Guarda el offset de la ultima lectura en el estado, asi cada corrida procesa
 * solo lo nuevo. Si el archivo encogio (rotacion de logs) vuelve a empezar
 * desde cero. Nunca carga el archivo entero en memoria: lee linea a linea y se
 * detiene en `max_lines`.
 */
final class AccessLogReader
{
    /** Formato combined de nginx. */
    private const PATTERN = '/^(?P<ip>\S+) \S+ (?P<user>\S+) \[(?P<time>[^\]]+)\] "(?P<method>[A-Z]+) (?P<path>[^ "]*) ?(?P<protocol>[^"]*)" (?P<status>\d{3}) (?P<bytes>\S+)(?: "(?P<referer>[^"]*)" "(?P<agent>[^"]*)")?/';

    public function __construct(
        private readonly FileStateStore $state,
        private readonly string $stateKey = 'default',
    ) {}

    /**
     * Devuelve la primera ruta que exista y sea legible, o null.
     *
     * @param  string[]  $candidates
     */
    public static function resolvePath(array $candidates): ?string
    {
        foreach ($candidates as $path) {
            if ($path && is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array{entries:array<int,array>,lines:int,truncated:bool}
     */
    public function read(string $path, int $maxLines = 200000): array
    {
        $entries   = [];
        $lines     = 0;
        $truncated = false;

        $size   = (int) @filesize($path);
        $offset = (int) $this->state->get('access_log', "{$this->stateKey}.offset", 0);

        // Rotacion: el archivo es mas pequeno que la ultima vez.
        if ($size < $offset) {
            $offset = 0;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("No se puede abrir el access log: {$path}");
        }

        try {
            fseek($handle, $offset);

            while (($line = fgets($handle)) !== false) {
                $lines++;

                if ($lines > $maxLines) {
                    $truncated = true;
                    break;
                }

                $entry = self::parseLine($line);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }

            $this->state->put('access_log', "{$this->stateKey}.offset", ftell($handle));
            $this->state->put('access_log', "{$this->stateKey}.read_at", CarbonImmutable::now()->toIso8601String());
        } finally {
            fclose($handle);
        }

        return ['entries' => $entries, 'lines' => $lines, 'truncated' => $truncated];
    }

    /**
     * @return array{ip:string,time:CarbonImmutable,method:string,path:string,status:int,bytes:int,referer:?string,agent:?string,raw:string}|null
     */
    public static function parseLine(string $line): ?array
    {
        $line = rtrim($line, "\r\n");
        if ($line === '') return null;

        if (!preg_match(self::PATTERN, $line, $m)) {
            return null;
        }

        try {
            $time = CarbonImmutable::createFromFormat('d/M/Y:H:i:s O', $m['time']);
        } catch (\Throwable $e) {
            $time = CarbonImmutable::now();
        }

        return [
            'ip'      => $m['ip'],
            'time'    => $time,
            'method'  => $m['method'],
            'path'    => $m['path'],
            'status'  => (int) $m['status'],
            'bytes'   => is_numeric($m['bytes']) ? (int) $m['bytes'] : 0,
            'referer' => ($m['referer'] ?? '') !== '' ? $m['referer'] : null,
            'agent'   => ($m['agent'] ?? '') !== '' ? $m['agent'] : null,
            'raw'     => $line,
        ];
    }
}
