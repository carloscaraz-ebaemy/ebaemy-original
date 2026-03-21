{{--
    ╔══════════════════════════════════════════════════════════════╗
    ║  SNIPPET — Datos de envío en el voucher PDF / reimpresión    ║
    ║                                                              ║
    ║  Incluir en el template PDF de la Nota de Venta:            ║
    ║    @include('tenant.sale_notes._logistic_courier_section',   ║
    ║               ['sale_note' => $sale_note])                   ║
    ╚══════════════════════════════════════════════════════════════╝
--}}
@if(isset($sale_note) && $sale_note->logistic_status?->value === 'DESPACHADO')
<div style="
    margin-top: 12px;
    padding: 10px 14px;
    border: 1.5px solid #28a745;
    border-radius: 6px;
    background: #f0fff4;
    font-size: 11px;
    font-family: 'DejaVu Sans', Arial, sans-serif;
">
    <div style="
        font-weight: bold;
        font-size: 12px;
        color: #155724;
        border-bottom: 1px solid #28a745;
        padding-bottom: 4px;
        margin-bottom: 8px;
        letter-spacing: 0.5px;
    ">
        &#128666; DATOS DE ENVÍO
    </div>

    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 38%; color: #555; padding: 2px 0;">Courier / Transportista:</td>
            <td style="font-weight: 600; padding: 2px 0;">
                {{ $sale_note->courier_name ?? '—' }}
            </td>
        </tr>
        <tr>
            <td style="color: #555; padding: 2px 0;">N° Guía / Tracking:</td>
            <td style="font-weight: 600; padding: 2px 0;">
                {{ $sale_note->tracking_number ?? '—' }}
            </td>
        </tr>
        <tr>
            <td style="color: #555; padding: 2px 0;">Fecha de Despacho:</td>
            <td style="font-weight: 600; padding: 2px 0;">
                {{ $sale_note->dispatch_date?->format('d/m/Y H:i') ?? '—' }}
            </td>
        </tr>
    </table>
</div>
@endif
