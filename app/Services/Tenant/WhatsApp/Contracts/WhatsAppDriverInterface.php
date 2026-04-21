<?php

namespace App\Services\Tenant\WhatsApp\Contracts;

/**
 * Contrato para drivers de WhatsApp.
 *
 * Cada driver encapsula cómo hablar con un proveedor específico:
 *   - MetaCloudDriver → Meta Cloud API oficial (graph.facebook.com)
 *   - QrApiDriver     → gateway tipo WhatsApp-Web (QR scan)
 *   - NoneDriver      → no-op silencioso cuando no hay configuración
 *
 * Los drivers son elegidos por WhatsAppDriverFactory según la
 * configuración del tenant. Agregar un driver nuevo es trivial:
 * implementar esta interface y registrarlo en el factory.
 */
interface WhatsAppDriverInterface
{
    /**
     * Identificador único del driver (ej: 'meta_cloud', 'qr_api').
     */
    public function name(): string;

    /**
     * ¿Está configurado y listo para enviar?
     */
    public function isConfigured(): bool;

    /**
     * Envía un mensaje de texto simple.
     *
     * @param  string  $phone    Número en formato internacional (51XXXXXXXXX).
     * @param  string  $message  Texto del mensaje.
     * @return bool   true si el proveedor aceptó el envío.
     */
    public function send(string $phone, string $message): bool;

    /**
     * Envía un mensaje usando una plantilla aprobada por Meta.
     * Solo soportado por Meta Cloud API — otros drivers pueden fallback
     * a send() con el texto renderizado.
     *
     * @param  string  $phone
     * @param  string  $templateName  Nombre de la plantilla aprobada.
     * @param  array   $parameters    Parámetros en orden que remplazan {{1}}, {{2}}, etc.
     * @param  string  $languageCode  Idioma de la plantilla (default 'es').
     * @return bool
     */
    public function sendTemplate(string $phone, string $templateName, array $parameters = [], string $languageCode = 'es'): bool;

    /**
     * Último error que ocurrió durante un envío (para diagnóstico).
     */
    public function lastError(): ?string;
}
