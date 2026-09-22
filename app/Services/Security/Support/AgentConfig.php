<?php

namespace App\Services\Security\Support;

use Illuminate\Support\Arr;

/**
 * Configuracion del agente: los valores por defecto de config/security-agent.php
 * fusionados (merge profundo) con storage/app/security-agent/config.json.
 *
 * El JSON es el archivo que edita el operador. No hace falta que este completo:
 * solo las claves que quiera cambiar.
 */
final class AgentConfig
{
    private array $values;

    private function __construct(array $values)
    {
        $this->values = $values;
    }

    public static function load(?string $overridePath = null): self
    {
        $defaults = config('security-agent', []);
        $path     = $overridePath ?? self::overridePath();

        if (is_file($path) && is_readable($path)) {
            $raw = json_decode(self::stripComments((string) file_get_contents($path)), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException(sprintf(
                    'El archivo de configuracion %s no es JSON valido: %s',
                    $path,
                    json_last_error_msg()
                ));
            }

            if (is_array($raw)) {
                $defaults = self::mergeDeep($defaults, $raw);
            }
        }

        return new self($defaults);
    }

    public static function overridePath(): string
    {
        return storage_path('app/' . config('security-agent.storage_path', 'security-agent') . '/config.json');
    }

    public function get(string $key, $default = null)
    {
        return Arr::get($this->values, $key, $default);
    }

    public function all(): array
    {
        return $this->values;
    }

    public function moduleEnabled(string $module): bool
    {
        return (bool) $this->get("modules.{$module}", false);
    }

    /**
     * Merge recursivo. Las listas del JSON REEMPLAZAN a las de por defecto
     * (si pones "allowed_countries": ["PE","CL"] eso es la lista entera), los
     * mapas se fusionan clave a clave.
     */
    private static function mergeDeep(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !array_is_list($value)) {
                $base[$key] = self::mergeDeep($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Permite comentarios `//` en el JSON para que el archivo sea legible.
     * No toca las `//` que van dentro de una cadena (por ejemplo una URL).
     */
    private static function stripComments(string $json): string
    {
        $out      = '';
        $inString = false;
        $escaped  = false;
        $length   = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];

            if ($inString) {
                $out .= $char;
                if ($escaped)          { $escaped = false; continue; }
                if ($char === '\\')    { $escaped = true;  continue; }
                if ($char === '"')     { $inString = false; }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                $out .= $char;
                continue;
            }

            if ($char === '/' && $i + 1 < $length && $json[$i + 1] === '/') {
                while ($i < $length && $json[$i] !== "\n") $i++;
                $out .= "\n";
                continue;
            }

            $out .= $char;
        }

        return $out;
    }
}
