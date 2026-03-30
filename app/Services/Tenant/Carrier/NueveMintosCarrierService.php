<?php

namespace App\Services\Tenant\Carrier;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integración con la API de 99Minutos.
 *
 * Documentación de referencia:
 *   https://developers.99minutos.com/
 *
 * Autenticación: API Key en header X-API-Key
 * Formato: REST / JSON
 *
 * Variables en courier_companies:
 *   api_driver   = '99minutos'
 *   api_key      = API Key de la cuenta
 *   api_endpoint = 'https://api.99minutos.com/v1'   (o sandbox)
 *   api_sandbox  = false (prod) / true (sandbox)
 *   api_meta     = {"account_id": "...", "warehouse_id": "..."}  (opcional)
 */
class NueveMintosCarrierService implements CarrierServiceInterface
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
            ? 'https://sandbox.api.99minutos.com/v1'
            : 'https://api.99minutos.com/v1'), '/');
        $this->meta = $meta;
    }

    public function getDriver(): string { return '99minutos'; }

    public function hasApiIntegration(): bool { return true; }

    // ──────────────────────────────────────────────────────────────────────────

    public function createShipment(ShipmentRequest $request): ShipmentResult
    {
        $payload = [
            'orderNumber'   => $request->orderReference,
            'serviceType'   => $this->meta['service_type'] ?? 'next_day',
            'origin' => [
                'name'    => $request->senderName,
                'phone'   => $request->senderPhone,
                'address' => $request->senderAddress,
                'city'    => $request->senderCity,
            ],
            'destination' => [
                'name'    => $request->recipientName,
                'phone'   => $request->recipientPhone,
                'address' => $request->recipientAddress,
                'city'    => $request->recipientCity,
                'email'   => $request->recipientEmail,
            ],
            'packages' => [[
                'quantity' => $request->packages,
                'weight'   => $request->weightKg,
                'value'    => $request->declaredValue ?? 0,
            ]],
            'notes'       => $request->notes,
            'cashOnDelivery' => $request->paymentMode === 'collect'
                ? ['amount' => $request->shippingCost]
                : null,
        ];

        $response = $this->post('/orders', $payload);

        return new ShipmentResult(
            trackingCode:      $response['trackingNumber'] ?? $response['id'] ?? '',
            labelUrl:          $response['labelUrl']       ?? null,
            externalId:        (string) ($response['id']   ?? ''),
            quotedCost:        isset($response['price']) ? (float) $response['price'] : null,
            estimatedDelivery: $response['estimatedDelivery'] ?? null,
            raw:               $response,
        );
    }

    public function getTracking(string $trackingCode): TrackingStatus
    {
        $response = $this->get("/tracking/{$trackingCode}");

        $status = $this->normalizeStatus($response['status'] ?? 'unknown');
        $events = array_map(fn($e) => [
            'date'        => $e['createdAt']   ?? $e['date']  ?? null,
            'description' => $e['description'] ?? $e['status'] ?? null,
            'location'    => $e['location']    ?? null,
        ], $response['history'] ?? []);

        return new TrackingStatus(
            trackingCode:      $trackingCode,
            status:            $status,
            statusLabel:       $this->statusLabel($status),
            lastUpdate:        $response['updatedAt'] ?? null,
            location:          $response['currentLocation'] ?? null,
            estimatedDelivery: $response['estimatedDelivery'] ?? null,
            events:            $events,
            raw:               $response,
        );
    }

    public function cancelShipment(string $trackingCode): bool
    {
        try {
            $this->post("/orders/{$trackingCode}/cancel", []);
            return true;
        } catch (CarrierApiException $e) {
            Log::warning('[99Minutos] No se pudo cancelar envío.', [
                'tracking' => $trackingCode,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function post(string $endpoint, array $payload): array
    {
        $url = $this->baseUrl . $endpoint;

        try {
            $response = Http::withHeaders(['X-API-Key' => $this->apiKey])
                ->timeout($this->timeout)
                ->post($url, $payload);

            if ($response->failed()) {
                throw new CarrierApiException(
                    "99Minutos API error [{$response->status()}]: " . $response->body(),
                    '99minutos',
                    $response->status(),
                    $response->json() ?? []
                );
            }

            return $response->json() ?? [];

        } catch (CarrierApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new CarrierApiException("99Minutos HTTP error: " . $e->getMessage(), '99minutos', 0, [], $e);
        }
    }

    private function get(string $endpoint): array
    {
        $url = $this->baseUrl . $endpoint;

        try {
            $response = Http::withHeaders(['X-API-Key' => $this->apiKey])
                ->timeout($this->timeout)
                ->get($url);

            if ($response->failed()) {
                throw new CarrierApiException(
                    "99Minutos API error [{$response->status()}]: " . $response->body(),
                    '99minutos',
                    $response->status(),
                    $response->json() ?? []
                );
            }

            return $response->json() ?? [];

        } catch (CarrierApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new CarrierApiException("99Minutos HTTP error: " . $e->getMessage(), '99minutos', 0, [], $e);
        }
    }

    private function normalizeStatus(string $raw): string
    {
        return match (strtolower($raw)) {
            'created', 'pending', 'registered'     => 'created',
            'picked_up', 'collected'               => 'picked_up',
            'in_transit', 'on_the_way'             => 'in_transit',
            'out_for_delivery', 'delivering'       => 'out_for_delivery',
            'delivered', 'completed'               => 'delivered',
            'failed_attempt', 'not_delivered'      => 'failed',
            'returned'                             => 'returned',
            'cancelled'                            => 'cancelled',
            default                                => 'unknown',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'created'           => 'Envío registrado',
            'picked_up'         => 'Recogido',
            'in_transit'        => 'En tránsito',
            'out_for_delivery'  => 'En camino al destino',
            'delivered'         => 'Entregado',
            'failed'            => 'Intento fallido',
            'returned'          => 'Devuelto',
            'cancelled'         => 'Cancelado',
            default             => 'Sin información',
        };
    }
}
