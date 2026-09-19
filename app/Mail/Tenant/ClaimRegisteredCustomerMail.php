<?php

namespace App\Mail\Tenant;

use App\Models\Tenant\Claim;
use App\Services\Tenant\ClaimPdfService;
use Illuminate\Mail\Mailable;

/**
 * Copia del registro para el consumidor, con la hoja de reclamación adjunta.
 *
 * No usa `Queueable` a propósito: el envío es síncrono para que el estado del
 * correo quede grabado en la misma petición. Encolarlo obligaría a rehidratar
 * la conexión del tenant dentro del worker, y un correo enviado a la BD
 * equivocada es exactamente lo que este módulo no se puede permitir.
 */
class ClaimRegisteredCustomerMail extends Mailable
{
    public Claim $claim;
    public array $provider;

    public function __construct(Claim $claim, array $provider)
    {
        $this->claim    = $claim;
        $this->provider = $provider;
    }

    public function build()
    {
        $mail = $this->subject('Tu ' . strtolower($this->claim->typeLabel()) . ' ' . $this->claim->code . ' quedó registrado')
            ->from(config('mail.from.address'), $this->provider['trade_name'] ?: config('mail.from.name'))
            ->view('tenant.templates.email.claim_customer')
            ->with([
                'claim'    => $this->claim,
                'provider' => $this->provider,
            ]);

        if ($replyTo = $this->provider['email'] ?? null) {
            $mail->replyTo($replyTo, $this->provider['trade_name']);
        }

        // El PDF es una comodidad, no el registro: si mPDF falla, el correo
        // sale igual con todos los datos en el cuerpo.
        try {
            $pdf = app(ClaimPdfService::class);
            $mail->attachData(
                $pdf->render($this->claim, $this->provider),
                $pdf->filename($this->claim),
                ['mime' => 'application/pdf']
            );
        } catch (\Throwable $e) {
            \Log::warning('Libro de Reclamaciones: no se pudo adjuntar el PDF', [
                'claim' => $this->claim->code,
                'error' => $e->getMessage(),
            ]);
        }

        return $mail;
    }
}
