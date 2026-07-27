<?php

namespace App\Services\Tenant\Raffles\Sources;

use App\Models\Tenant\Person;
use App\Models\Tenant\Raffle;
use App\Services\Tenant\Raffles\ParticipantSource;
use Illuminate\Support\Collection;

/**
 * Lista personalizada: el administrador pega o importa los participantes.
 *
 * Acepta una línea por persona con separador `;`, `,` o tabulación:
 *     Nombre ; Documento ; Correo ; Teléfono
 * Solo el nombre es obligatorio, pero hace falta al menos un dato de
 * identificación (documento, correo o teléfono) para poder deduplicar y
 * contactar. Si el documento coincide con un cliente del ERP, se enlaza su
 * `person_id` para que la insignia de ganador aparezca en su ficha.
 */
class CustomListSource extends ParticipantSource
{
    public function key(): string
    {
        return 'custom_list';
    }

    public function label(): string
    {
        return 'Importar lista personalizada';
    }

    public function description(): string
    {
        return 'Pega tu propia lista de participantes (nombre, documento, correo, teléfono).';
    }

    public function icon(): string
    {
        return '📝';
    }

    public function filters(): array
    {
        return [
            [
                'key'   => 'list',
                'type'  => 'textarea',
                'rows'  => 10,
                'label' => 'Participantes (uno por línea)',
                'help'  => 'Formato: Nombre ; Documento ; Correo ; Teléfono — separador ";", "," o tabulación. '
                         . 'Puedes pegar directamente desde Excel. La primera línea se ignora si es un encabezado.',
                'placeholder' => "Juana Pérez;45678912;juana@correo.com;987654321\nLuis Gómez;;luis@correo.com;912345678",
            ],
        ];
    }

    public function resolve(Raffle $raffle): Collection
    {
        $raw = (string) $this->filter($raffle, 'list', '');

        if (trim($raw) === '') {
            return collect();
        }

        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', preg_split('/[;,\t]/', $line));

            // Encabezado típico de Excel: se salta.
            if ($i === 0 && preg_match('/nombre|name/i', $parts[0] ?? '') && count($parts) > 1) {
                continue;
            }

            [$name, $document, $email, $phone] = array_pad($parts, 4, null);

            $document = preg_replace('/\D+/', '', (string) $document) ?: null;
            $email    = filter_var((string) $email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null;
            $phone    = preg_replace('/\D+/', '', (string) $phone) ?: null;

            // Sin ningún identificador no se puede deduplicar ni contactar.
            if (!$document && !$email && !$phone) {
                continue;
            }

            $rows[] = $this->row(null, $name ?: 'Cliente', $document, $email, $phone);
        }

        return $this->linkToPersons(collect($rows));
    }

    /** Enlaza por documento con los clientes del ERP cuando existen. */
    private function linkToPersons(Collection $rows): Collection
    {
        $documents = $rows->pluck('document')->filter()->unique()->values();

        if ($documents->isEmpty()) {
            return $rows;
        }

        $people = Person::whereIn('number', $documents->all())
                        ->get(['id', 'number', 'name', 'email', 'telephone'])
                        ->keyBy('number');

        return $rows->map(function ($row) use ($people) {
            $person = $row['document'] ? $people->get($row['document']) : null;

            if ($person) {
                $row['person_id'] = $person->id;
                $row['email']   = $row['email'] ?: $person->email;
                $row['phone']   = $row['phone'] ?: $person->telephone;
            }

            return $row;
        })->values();
    }
}
