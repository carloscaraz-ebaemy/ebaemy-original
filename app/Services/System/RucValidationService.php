<?php

namespace App\Services\System;

use App\CoreFacturalo\Services\Ruc\Sunat as SunatScraper;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Valida un RUC peruano contra SUNAT (API externa) y normaliza la respuesta.
 *
 * Configurable vía env:
 *   - RUC_VALIDATION_API_URL   (opcional; si no está, solo valida formato)
 *   - RUC_VALIDATION_API_TOKEN (opcional; depende del provider)
 *
 * Providers compatibles por defecto:
 *   - apis.net.pe         → https://api.apis.net.pe/v2/sunat/ruc
 *   - decolecta.com       → https://api.decolecta.com/v1/sunat/ruc
 *   - factiliza           → https://api.factiliza.com/pe/v1/ruc/info
 *
 * Orden de consulta:
 *   1. API externa (si RUC_VALIDATION_API_URL está configurada)  — rápida, estable
 *   2. Scraper oficial SUNAT (App\CoreFacturalo\Services\Ruc\Sunat) — gratis, sin token
 *   3. Manual review — si ambos fallan, el SuperAdmin valida a ojo
 *
 * Si ambos providers fallan, el service NO bloquea la solicitud:
 * devuelve 'requires_manual_review' = true para que el SuperAdmin lo revise
 * manualmente desde el panel. Esto evita que la disponibilidad de un provider
 * externo bloquee el onboarding.
 *
 * La respuesta normalizada siempre incluye las mismas keys — el consumidor
 * no necesita saber qué provider respondió.
 */
class RucValidationService
{
    // Formato normalizado de la respuesta
    public const STATUS_ACTIVO     = 'ACTIVO';
    public const STATUS_SUSPENDIDO = 'SUSPENDIDO';
    public const STATUS_BAJA       = 'BAJA';
    public const STATUS_UNKNOWN    = 'UNKNOWN';

    public const CONDITION_HABIDO     = 'HABIDO';
    public const CONDITION_NO_HALLADO = 'NO_HALLADO';
    public const CONDITION_UNKNOWN    = 'UNKNOWN';

    /**
     * @return array{
     *   valid: bool,
     *   status: string,
     *   condition: string|null,
     *   business_name: string|null,
     *   fiscal_address: string|null,
     *   department: string|null,
     *   province: string|null,
     *   district: string|null,
     *   raw: array|null,
     *   error: string|null,
     *   requires_manual_review: bool,
     * }
     */
    public function validate(string $ruc): array
    {
        $ruc = trim($ruc);

        // 1. Formato: 11 dígitos numéricos
        if (!preg_match('/^\d{11}$/', $ruc)) {
            return $this->invalidFormat('RUC debe tener exactamente 11 dígitos numéricos');
        }

        // 2. Prefijo SUNAT válido: 10 (persona natural), 15/17 (no domiciliado),
        //    20 (persona jurídica)
        $prefix = substr($ruc, 0, 2);
        if (!in_array($prefix, ['10', '15', '17', '20'], true)) {
            return $this->invalidFormat("RUC con prefijo inválido ({$prefix}). Debe empezar con 10, 15, 17 o 20.");
        }

        // 3a. Intentar validación con API externa si está configurada
        $url = config('services.ruc_validation.url');
        if ($url) {
            try {
                $response = $this->callExternalApi($url, $ruc);
                return $this->normalizeResponse($response);
            } catch (Exception $e) {
                Log::warning('RucValidationService: API externa falló, intentando scraper SUNAT', [
                    'ruc'   => $ruc,
                    'url'   => $url,
                    'error' => $e->getMessage(),
                ]);
                // Cae al fallback del scraper
            }
        }

        // 3b. Fallback: scraper oficial SUNAT (App\CoreFacturalo\Services\Ruc\Sunat)
        // Es gratis, sin token, pero frágil (depende del HTML del portal SUNAT).
        try {
            $sunatResult = $this->callSunatScraper($ruc);
            if ($sunatResult !== null) {
                return $sunatResult;
            }
        } catch (Exception $e) {
            Log::warning('RucValidationService: scraper SUNAT falló', [
                'ruc'   => $ruc,
                'error' => $e->getMessage(),
            ]);
        }

        // 3c. Ambos fallaron → manual review
        Log::info('RucValidationService: ningún provider disponible, requiere revisión manual', ['ruc' => $ruc]);
        return $this->manualReview('No pudimos consultar SUNAT en este momento');
    }

    /**
     * Fallback gratuito: consulta el portal público de SUNAT usando el
     * scraper ya implementado en App\CoreFacturalo\Services\Ruc\Sunat.
     *
     * Retorna null si el scraper no encuentra datos. Lanza Exception si
     * el portal cambia de estructura o falla la conexión.
     */
    private function callSunatScraper(string $ruc): ?array
    {
        $scraper = new SunatScraper();
        $company = $scraper->get($ruc);

        if ($company === false) {
            // RUC no encontrado o error parseo
            $error = $scraper->getError();
            if ($error && stripos($error, 'no se encontro') !== false) {
                return [
                    'valid'                  => false,
                    'status'                 => self::STATUS_UNKNOWN,
                    'condition'              => null,
                    'business_name'          => null,
                    'fiscal_address'         => null,
                    'department'             => null,
                    'province'               => null,
                    'district'               => null,
                    'raw'                    => null,
                    'error'                  => 'RUC no encontrado en SUNAT',
                    'requires_manual_review' => false,
                ];
            }
            return null; // error técnico → caller cae a manual review
        }

        // Normalizar respuesta del scraper al formato estándar del service
        return [
            'valid'                  => true,
            'status'                 => $this->mapStatus($company->estado    ?? null),
            'condition'              => $this->mapCondition($company->condicion ?? null),
            'business_name'          => $company->razonSocial ?? null,
            'fiscal_address'         => isset($company->direccion) ? trim((string) $company->direccion) : null,
            'department'             => $company->departamento ?? null,
            'province'               => $company->provincia   ?? null,
            'district'               => $company->distrito    ?? null,
            'raw'                    => [
                'provider'        => 'sunat_scraper',
                'tipo'            => $company->tipo ?? null,
                'nombre_comercial'=> $company->nombreComercial ?? null,
                'fecha_inscripcion' => $company->fechaInscripcion ?? null,
            ],
            'error'                  => null,
            'requires_manual_review' => $this->mapStatus($company->estado ?? null) !== self::STATUS_ACTIVO
                                     || $this->mapCondition($company->condicion ?? null) !== self::CONDITION_HABIDO,
        ];
    }

    /**
     * Combina status + condition para decidir si la solicitud puede avanzar
     * automáticamente o requiere revisión.
     *
     *  - ACTIVO + HABIDO → puede aprobarse sin revisión manual
     *  - cualquier otra combinación → requires_review
     */
    public function canAutoAdvance(array $normalizedResponse): bool
    {
        return ($normalizedResponse['status']    ?? null) === self::STATUS_ACTIVO
            && ($normalizedResponse['condition'] ?? null) === self::CONDITION_HABIDO;
    }

    // ─────────────────────────────────────────────────────────
    //  Llamadas externas + normalización
    // ─────────────────────────────────────────────────────────

    private function callExternalApi(string $url, string $ruc): array
    {
        $token = config('services.ruc_validation.token');

        $request = Http::timeout(10)->retry(2, 500);

        if ($token) {
            $request = $request->withToken($token);
        }

        // Soportamos dos convenciones comunes:
        //   - query string: ?numero=20123456789
        //   - path: /20123456789
        // Si la URL ya trae query placeholder ({numero}), lo interpolamos.
        if (str_contains($url, '{numero}')) {
            $finalUrl = str_replace('{numero}', $ruc, $url);
            $response = $request->get($finalUrl);
        } else {
            $response = $request->get($url, ['numero' => $ruc]);
        }

        if (!$response->successful()) {
            throw new Exception("La API retornó HTTP {$response->status()}");
        }

        $data = $response->json();
        if (!is_array($data)) {
            throw new Exception('La API retornó un payload no-JSON o inválido');
        }

        return $data;
    }

    /**
     * Mapea claves comunes de distintos providers a nuestro formato
     * normalizado. No asume un provider específico.
     */
    private function normalizeResponse(array $data): array
    {
        // Estado
        $rawStatus = $data['estado'] ?? $data['status'] ?? null;
        $status = $this->mapStatus($rawStatus);

        // Condición
        $rawCondition = $data['condicion'] ?? $data['condition'] ?? null;
        $condition = $this->mapCondition($rawCondition);

        // Razón social / nombre
        $businessName = $data['razonSocial']
            ?? $data['razon_social']
            ?? $data['nombre']
            ?? $data['name']
            ?? null;

        // Dirección
        $address = $data['direccion']
            ?? $data['domicilio_fiscal']
            ?? $data['address']
            ?? null;

        return [
            'valid'                  => true,
            'status'                 => $status,
            'condition'              => $condition,
            'business_name'          => $businessName ? trim($businessName) : null,
            'fiscal_address'         => $address ? trim($address) : null,
            'department'             => $data['departamento'] ?? $data['department'] ?? null,
            'province'               => $data['provincia']    ?? $data['province']   ?? null,
            'district'               => $data['distrito']     ?? $data['district']   ?? null,
            'raw'                    => $data,
            'error'                  => null,
            'requires_manual_review' => !($status === self::STATUS_ACTIVO
                                        && $condition === self::CONDITION_HABIDO),
        ];
    }

    private function mapStatus(?string $raw): string
    {
        $normalized = strtoupper(trim((string) $raw));

        return match (true) {
            str_contains($normalized, 'ACTIVO')     => self::STATUS_ACTIVO,
            str_contains($normalized, 'SUSPEN')     => self::STATUS_SUSPENDIDO,
            str_contains($normalized, 'BAJA')       => self::STATUS_BAJA,
            default                                  => self::STATUS_UNKNOWN,
        };
    }

    private function mapCondition(?string $raw): string
    {
        $normalized = strtoupper(trim((string) $raw));

        return match (true) {
            $normalized === 'HABIDO'               => self::CONDITION_HABIDO,
            str_contains($normalized, 'NO HALLAD') => self::CONDITION_NO_HALLADO,
            str_contains($normalized, 'NO_HALLAD') => self::CONDITION_NO_HALLADO,
            default                                 => self::CONDITION_UNKNOWN,
        };
    }

    // ─────────────────────────────────────────────────────────
    //  Fallbacks
    // ─────────────────────────────────────────────────────────

    private function invalidFormat(string $reason): array
    {
        return [
            'valid'                  => false,
            'status'                 => self::STATUS_UNKNOWN,
            'condition'              => null,
            'business_name'          => null,
            'fiscal_address'         => null,
            'department'             => null,
            'province'               => null,
            'district'               => null,
            'raw'                    => null,
            'error'                  => $reason,
            'requires_manual_review' => false,
        ];
    }

    private function manualReview(string $reason): array
    {
        return [
            'valid'                  => true,   // formato OK; solo falta confirmar contra SUNAT
            'status'                 => self::STATUS_UNKNOWN,
            'condition'              => self::CONDITION_UNKNOWN,
            'business_name'          => null,
            'fiscal_address'         => null,
            'department'             => null,
            'province'               => null,
            'district'               => null,
            'raw'                    => null,
            'error'                  => $reason,
            'requires_manual_review' => true,
        ];
    }
}
