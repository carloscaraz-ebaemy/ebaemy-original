<?php

namespace App\Services\Tenant\Carrier;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integración con la API de Chazki.
 *
 * Documentación de referencia:
 *   https://developers.chazki.com/
 *
 * Autenticación: Bearer Token (api_key = token)
 * Formato: REST / JSON
 *
 * Variables en courier_companies:
 *   api_driver   = 'chazki'
 *   api_key      = Bearer token de la API
 *   api_endpoint = 'https://api.chazki.com/v2'   (o sandbox)
 *   api_sandbox  = false (prod) / true (sandbox)
 *   api_meta     = {"account_id": "...", "service_type": "express"}  (opcional)
 */
class ChizkiCarrierService implements CarrierServiceInterface
{
    private string $baseUrl;
    private string $apiKey;
    private array  $meta;
    private int    $timeout = 15;

    public function __construct(
        string $apiKey,
        string $apiEndpoint,
        bool   $sandbox = false,
        array  $meta = []
    ) {
        $this->apiKey  = $apiKey;
        $this->baseUrl = rtrim($apiEndpoint ?: ($sandbox
            ? 'https://sandbox.api.chazki.com/v2'
            : 'https://api.chazki.com/v2'), '/');
        $this->meta = $meta;
    }

    public function getDriver(): string { return 'chazki'; }

    public function hasApiIntegration(): bool { return true; }

    // ──────────────────────────────────────────────────────────────────────────

    public function createShipment(ShipmentRequest $request): ShipmentResult
    {
        $payload = [
            'reference'    => $request->orderReference,
            'service_type' => $this->meta['service_type'] ?? 'estandar',
            'origin'       => [
                'name'    => $request->senderName,
                'phone'   => $request->senderPhone,
                'address' => $request->senderAddress,
                'district'=> $request->senderDistrict,
                'city'    => $request->senderCity,
            ],
            'destination'  => [
                'name'    => $request->recipientName,
                'phone'   => $request->recipientPhone,
                'address' => $request->recipientAddress,
                'district'=> $request->recipientDistrict,
                'city'    => $request->recipientCity,
                'email'   => $request->recipientEmail,
            ],
            'packages'     => $request->packages,
            'weight'       => $request->weightKg,
            'declared_value' => $request->declaredValue,
            'notes'        => $request->notes,
            'payment_mode' => $request->paymentMode,
            'shipping_cost'=> $request->shippingCost,
        ];

        $response = $this->post('/shipments', $payload);

        return new ShipmentResult(
            trackingCode:      $response['tracking_code']     ?? $response['id']             ?? '',
            labelUrl:          $response['label_url']         ?? $response['pdf_url']         ?? null,
            externalId:        $response['id']                ?? null,
            quotedCost:        isset($response['cost']) ? (float) $response['cost'] : null,
            estimatedDelivery: $response['estimated_delivery'] ?? null,
            raw:               $response,
        );
    }

    public function getTracking(string $trackingCode): TrackingStatus
    {
        $response = $this->get("/shipments/{$trackingCode}/tracking");

        $status = $this->normalizeStatus($response['status'] ?? 'unknown');
        $events = array_map(fn($e) => [
            'date'        => $e['timestamp']   ?? $e['date']  ?? null,
            'description' => $e['description'] ?? $e['event'] ?? null,
            'location'    => $e['location']    ?? null,
        ], $response['events'] ?? []);

        return new TrackingStatus(
            trackingCode:      $trackingCode,
            status:            $status,
            statusLabel:       $this->statusLabel($status),
            lastUpdate:        $response['updated_at'] ?? null,
            location:          $response['current_location'] ?? null,
            estimatedDelivery: $response['estimated_delivery'] ?? null,
            events:            $events,
            raw:               $response,
        );
    }

    public function cancelShipment(string $trackingCode): bool
    {
        try {
            $this->post("/shipments/{$trackingCode}/cancel", []);
            return true;
        } catch (CarrierApiException $e) {
            Log::warning('[Chazki] No se pudo cancelar envío.', [
                'tracking' => $trackingCode,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HTTP helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function post(string $endpoint, array $payload): array
    {
        $url = $this->baseUrl . $endpoint;

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->post($url, $payload);

            if ($response->failed()) {
                throw new CarrierApiException(
                    "Chazki API error [{$response->status()}]: " . $response->body(),
                    'chazki',
                    $response->status(),
                    $response->json() ?? []
                );
            }

            return $response->json() ?? [];

        } catch (CarrierApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new CarrierApiException("Chazki HTTP error: " . $e->getMessage(), 'chazki', 0, [], $e);
        }
    }

    private function get(string $endpoint): array
    {
        $url = $this->baseUrl . $endpoint;

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->get($url);

            if ($response->failed()) {
                throw new CarrierApiException(
                    "Chazki API error [{$response->status()}]: " . $response->body(),
                    'chazki',
                    $response->status(),
                    $response->json() ?? []
                );
            }

            return $response->json() ?? [];

        } catch (CarrierApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new CarrierApiException("Chazki HTTP error: " . $e->getMessage(), 'chazki', 0, [], $e);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Normalización de estados
    // ──────────────────────────────────────────────────────────────────────────

    private function normalizeStatus(string $raw): string
    {
        return match (strtolower($raw)) {
            'created', 'registered', 'pendiente'        => 'created',
            'picked_up', 'recogido', 'picked'           => 'picked_up',
            'in_transit', 'en_transito', 'transit'      => 'in_transit',
            'out_for_delivery', 'en_reparto', 'reparto' => 'out_for_delivery',
            'delivered', 'entregado'                    => 'delivered',
            'failed', 'no_entregado', 'fallido'         => 'failed',
            'returned', 'devuelto'                      => 'returned',
            'cancelled', 'cancelado'                    => 'cancelled',
            default                                     => 'unknown',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'created'           => 'Envío creado',
            'picked_up'         => 'Recogido por mensajero',
            'in_transit'        => 'En tránsito',
            'out_for_delivery'  => 'En reparto',
            'delivered'         => 'Entregado',
            'failed'            => 'Intento fallido de entrega',
            'returned'          => 'Devuelto al remitente',
            'cancelled'         => 'Cancelado',
            default             => 'Estado desconocido',
        };
    }
}
