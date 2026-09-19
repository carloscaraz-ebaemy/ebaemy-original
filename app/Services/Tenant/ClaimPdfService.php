<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Claim;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;

/**
 * Copia en PDF de la hoja de reclamación — lo que el reglamento llama «copia
 * del registro» y que el consumidor debe poder imprimir o recibir por correo.
 */
class ClaimPdfService
{
    /**
     * Devuelve el PDF como string binario.
     *
     * `tempDir` apunta a `storage/` y no al de la librería a propósito:
     * `vendor/mpdf/mpdf/tmp` pierde los permisos de escritura en cada
     * despliegue y se lleva por delante toda la emisión.
     */
    public function render(Claim $claim, array $provider): string
    {
        $tmp = storage_path('app/mpdf');

        if (!File::isDirectory($tmp)) {
            File::makeDirectory($tmp, 0775, true);
        }

        $html = view('tenant.claims.pdf', [
            'claim'    => $claim,
            'provider' => $provider,
        ])->render();

        $pdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 12,
            'margin_bottom' => 12,
            'margin_left'   => 12,
            'margin_right'  => 12,
            'tempDir'       => $tmp,
        ]);

        $pdf->SetTitle('Hoja de Reclamación ' . $claim->code);
        $pdf->WriteHTML($html);

        return $pdf->Output('', 'S');
    }

    public function filename(Claim $claim): string
    {
        return 'hoja-reclamacion-' . $claim->code . '.pdf';
    }
}
