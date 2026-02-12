<?php

namespace App\Mail\Tenant;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReclamoEmail extends Mailable
{
    use Queueable, SerializesModels;
    use StorageDocument;

    public $company;
    public $datos_formulario;

    public function __construct($company, $datos_formulario)
    {
        $this->company = $company;
        $this->datos_formulario = $datos_formulario;
    }

    public function build()
    {
        $mail = $this->subject('Nuevo Reclamo - Libro de Reclamaciones')
            ->from(config('mail.username'), 'Libro de Reclamaciones')
            ->view('tenant.templates.email.reclamo')
            ->with([
                'company' => $this->company,
                'datos_formulario' => $this->datos_formulario
            ]);
            

        // Adjuntos
        if (!empty($this->datos_formulario['archivos']) && is_array($this->datos_formulario['archivos'])) {

            foreach ($this->datos_formulario['archivos'] as $archivo) {

                $path = storage_path('app/public/' . $archivo);

                if (file_exists($path)) {

                    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                    $mimeTypes = [
                        'pdf'  => 'application/pdf',
                        'jpg'  => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png'  => 'image/png'
                    ];

                    if (isset($mimeTypes[$extension])) {

                        $mail->attach($path, [
                            'as'   => basename($archivo),
                            'mime' => $mimeTypes[$extension]
                        ]);
                    }
                }
            }
        }

        return $mail;
    }
}
