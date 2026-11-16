<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Company;
use App\Models\Tenant\Person;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

/**
 * Reporte PDF del historial de un cliente: ventas con su detalle, pagos,
 * saldos, anulaciones, cotizaciones, cuentas por cobrar y por pagar, y
 * cambios de datos. Los datos los arma CustomerHistoryService.
 */
class CustomerHistoryPdfService
{
    /**
     * Devuelve el PDF como string binario.
     *
     * `tempDir` apunta a `storage/` y no al de la librería a propósito:
     * `vendor/mpdf/mpdf/tmp` pierde los permisos de escritura en cada
     * despliegue y se lleva por delante toda la emisión.
     */
    public function render(array $history): string
    {
        $tmp = storage_path('app/mpdf');

        if (!File::isDirectory($tmp)) {
            File::makeDirectory($tmp, 0775, true);
        }

        $company = Company::active();
        $logo = $company && $company->logo
            ? storage_path('app/public/uploads/logos/' . $company->logo)
            : null;

        $html = view('tenant.persons.history_pdf', [
            'h'       => $history,
            'company' => $company,
            'logo'    => $logo && File::exists($logo) ? $logo : null,
            'printed' => now()->format('d/m/Y H:i'),
        ])->render();

        $pdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 12,
            'margin_bottom' => 14,
            'margin_left'   => 10,
            'margin_right'  => 10,
            'tempDir'       => $tmp,
        ]);

        $pdf->SetTitle('Historial de ' . $history['person']['name']);
        $pdf->SetFooter('Historial de ' . $history['person']['name'] . '||Página {PAGENO} de {nbpg}');
        $pdf->WriteHTML($html);

        return $pdf->Output('', 'S');
    }

    public function filename(Person $person): string
    {
        return 'historial-' . Str::slug($person->name ?: 'cliente') . '.pdf';
    }
}
