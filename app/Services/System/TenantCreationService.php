<?php

namespace App\Services\System;

use App\Models\System\Client;
use Carbon\Carbon;
use Exception;
use Hyn\Tenancy\Contracts\Repositories\HostnameRepository;
use Hyn\Tenancy\Contracts\Repositories\WebsiteRepository;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\MobileApp\Models\System\AppModule;
use App\Models\System\Plan;

/**
 * Service encargado de crear un tenant completo (Hyn infra + Client +
 * bootstrap de la BD tenant).
 *
 * Extraído de App\Http\Controllers\System\ClientController::store() para
 * permitir reutilización desde el flujo de aprobación de sellers
 * (SellerApplicationService::approve) sin duplicar lógica.
 *
 * Preserva el comportamiento exacto del flujo anterior: mismo orden de
 * operaciones, mismos logs, mismo formato de retorno. Añade dos mejoras
 * defensivas sobre el original:
 *
 *   1. Los inserts en la BD tenant se envuelven en DB::transaction para
 *      evitar estado inconsistente si falla alguno de los pasos.
 *   2. El cleanup en caso de error borra también el registro `clients`
 *      (system), no solo Website/Hostname. Antes quedaba huérfano.
 *
 * El procesamiento del certificado digital (.pfx → .pem) permanece en
 * el controller porque depende del upload temp_path que es propio del
 * request HTTP y no del flujo de negocio.
 */
class TenantCreationService
{
    private WebsiteRepository $websiteRepo;
    private HostnameRepository $hostnameRepo;
    private Environment $tenancy;

    public function __construct()
    {
        // Resolución lazy desde el container para respetar el binding que
        // Hyn Tenancy registra en su service provider (no funciona bien con
        // constructor injection fuera del contexto HTTP completo).
        $this->websiteRepo  = app(WebsiteRepository::class);
        $this->hostnameRepo = app(HostnameRepository::class);
        $this->tenancy      = app(Environment::class);
    }

    /**
     * Crea un tenant completo.
     *
     * Keys esperadas en $payload:
     *   subdomain, uuid, fqdn, token, email, name, number, plan_id,
     *   locked_emission, enable_list_product, price, plan_period_id,
     *   client_name, phone_ws, contact_email, certificate_name,
     *   soap_type_id, soap_send_id, soap_username, soap_password,
     *   soap_url, config_system_env, password, type, modules, levels,
     *   from_guest_register
     *
     * @return array{success: bool, message: string, client?: Client}
     */
    public function create(array $payload): array
    {
        $hostname = new Hostname();
        $website = new Website();
        $client = null;

        try {
            $this->validateUniqueSubdomain($payload['uuid']);

            Log::info('Creando website...');
            $website->uuid = $payload['uuid'];
            $this->websiteRepo->create($website);
            Log::info('Website creado', ['website_id' => $website->id]);

            Log::info('Creando y asociando hostname...');
            $hostname->fqdn = $payload['fqdn'];
            $hostname = $this->hostnameRepo->create($hostname);
            $this->hostnameRepo->attach($hostname, $website);
            Log::info('Hostname creado y asociado', ['hostname_id' => $hostname->id]);

            Log::info('Creando cliente...');
            $client = $this->createSystemClient($hostname->id, $payload);
            Log::info('Cliente creado', ['client_id' => $client->id]);

            $client->createPayemtnOrder();

            Log::info('Configurando tenancy...');
            $this->tenancy->tenant($website);
            Log::info('Tenancy configurado');

            Log::info('=== INICIANDO OPERACIONES EN TENANT DATABASE ===');

            // Todas las operaciones en BD tenant dentro de transacción.
            // Si cualquier insert falla, se revierte todo lo insertado en tenant.
            DB::connection('tenant')->transaction(function () use ($payload) {
                $this->bootstrapTenantDatabase($payload);
            });

            Log::info('=== CLIENTE REGISTRADO EXITOSAMENTE ===', ['timestamp' => now()]);

            return [
                'success' => true,
                'message' => 'Cliente Registrado satisfactoriamente',
                'client' => $client,
            ];
        } catch (Exception $e) {
            Log::error('Error en TenantCreationService::create', ['error' => $e->getMessage()]);
            $this->cleanup($client, $hostname, $website);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function validateUniqueSubdomain(string $uuid): void
    {
        if (Website::query()->where('uuid', $uuid)->exists()) {
            throw new Exception('El subdominio ya se encuentra registrado');
        }
    }

    private function createSystemClient(int $hostnameId, array $payload): Client
    {
        return Client::query()->create([
            'hostname_id'          => $hostnameId,
            'token'                => $payload['token'],
            'email'                => strtolower($payload['email']),
            'name'                 => $payload['name'],
            'number'               => $payload['number'],
            'plan_id'              => $payload['plan_id'],
            'locked_emission'      => $payload['locked_emission']      ?? false,
            'enable_list_product'  => $payload['enable_list_product']  ?? false,
            'price'                => $payload['price']                ?? null,
            'plan_period_id'       => $payload['plan_period_id']       ?? null,
            'start_billing_cycle'  => Carbon::now()->toDateString(),
            'ending_billing_cycle' => Carbon::now()->toDateString(),
            'client_name'          => !empty($payload['client_name']) ? $payload['client_name'] : $payload['name'],
            'phone_ws'             => $payload['phone_ws']             ?? null,
            'contact_email'        => !empty($payload['contact_email']) ? $payload['contact_email'] : $payload['email'],
        ]);
    }

    private function bootstrapTenantDatabase(array $payload): void
    {
        Log::info('Insertando company...');
        DB::connection('tenant')->table('companies')->insert([
            'identity_document_type_id' => '6',
            'number'       => $payload['number'],
            'name'         => $payload['name'],
            'trade_name'   => $payload['name'],
            'soap_type_id' => $payload['soap_type_id']   ?? null,
            'soap_send_id' => $payload['soap_send_id']   ?? null,
            'soap_username'=> $payload['soap_username']  ?? null,
            'soap_password'=> $payload['soap_password']  ?? null,
            'soap_url'     => $payload['soap_url']       ?? null,
            'certificate'  => $payload['certificate_name'] ?? null,
        ]);
        Log::info('Company insertada');

        $plan = Plan::findOrFail($payload['plan_id']);
        $http = config('tenant.force_https') === true ? 'https://' : 'http://';
        $fqdn = $payload['fqdn'];

        Log::info('Insertando configuración...');
        DB::connection('tenant')->table('configurations')->insert([
            'send_auto'           => true,
            'locked_emission'     => $payload['locked_emission']     ?? false,
            'enable_list_product' => $payload['enable_list_product'] ?? false,
            'locked_tenant'       => false,
            'locked_users'        => false,
            'limit_documents'     => $plan->limit_documents,
            'limit_users'         => $plan->limit_users,
            'plan'                => json_encode($plan),
            'date_time_start'     => date('Y-m-d H:i:s'),
            'quantity_documents'  => 0,
            'config_system_env'   => $payload['config_system_env'] ?? null,
            'login' => json_encode([
                'type'              => 'image',
                'image'             => $http . $fqdn . '/images/fondo-5.svg',
                'position_form'     => 'right',
                'show_logo_in_form' => false,
                'position_logo'     => 'top-left',
                'padding_in_form'   => '2.5%',
                'show_socials'      => false,
                'facebook'          => null,
                'twitter'           => null,
                'instagram'         => null,
                'linkedin'          => null,
                'tiktok'            => null,
            ]),
            'visual' => json_encode([
                'bg'            => 'white',
                'header'        => 'light',
                'navbar'        => 'fixed',
                'sidebars'      => 'light',
                'sidebar_theme' => 'white',
            ]),
            'skin_id'               => 2,
            'top_menu_a_id'         => 1,
            'top_menu_b_id'         => 15,
            'top_menu_c_id'         => 76,
            'quantity_sales_notes'  => 0,
            'from_guest_register'   => $payload['from_guest_register'] ?? false,
        ]);
        Log::info('Configuración insertada');

        Log::info('Insertando establishment...');
        $establishmentId = DB::connection('tenant')->table('establishments')->insertGetId([
            'description' => 'Oficina Principal',
            'country_id'  => 'PE',
            'department_id' => '15',
            'province_id' => '1501',
            'district_id' => '150101',
            'address'     => '-',
            'email'       => $payload['email'],
            'telephone'   => '-',
            'code'        => '0000',
        ]);
        Log::info('Establishment insertado', ['establishment_id' => $establishmentId]);

        Log::info('Insertando warehouse...');
        DB::connection('tenant')->table('warehouses')->insertGetId([
            'establishment_id' => $establishmentId,
            'description'      => 'Almacén Oficina Principal',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        Log::info('Warehouse insertado');

        Log::info('Insertando series...');
        // Series precargadas — se insertan con establishment_id=1 (legacy).
        // El primer establishment creado arriba tiene id=1 en tenants nuevos.
        DB::connection('tenant')->table('series')->insert([
            ['establishment_id' => 1, 'document_type_id' => '01', 'number' => 'F001'],
            ['establishment_id' => 1, 'document_type_id' => '03', 'number' => 'B001'],
            ['establishment_id' => 1, 'document_type_id' => '07', 'number' => 'FC01'],
            ['establishment_id' => 1, 'document_type_id' => '07', 'number' => 'BC01'],
            ['establishment_id' => 1, 'document_type_id' => '08', 'number' => 'FD01'],
            ['establishment_id' => 1, 'document_type_id' => '08', 'number' => 'BD01'],
            ['establishment_id' => 1, 'document_type_id' => '20', 'number' => 'R001'],
            ['establishment_id' => 1, 'document_type_id' => '09', 'number' => 'T001'],
            ['establishment_id' => 1, 'document_type_id' => '40', 'number' => 'P001'],
            ['establishment_id' => 1, 'document_type_id' => '80', 'number' => 'NV01'],
            ['establishment_id' => 1, 'document_type_id' => '04', 'number' => 'L001'],
            ['establishment_id' => 1, 'document_type_id' => '31', 'number' => 'V001'],
            ['establishment_id' => 1, 'document_type_id' => 'U2', 'number' => 'NIA1'],
            ['establishment_id' => 1, 'document_type_id' => 'U3', 'number' => 'NSA1'],
            ['establishment_id' => 1, 'document_type_id' => 'U4', 'number' => 'NTA1'],
        ]);
        Log::info('Series insertadas');

        Log::info('Insertando usuario...');
        $userId = DB::connection('tenant')->table('users')->insertGetId([
            'name'                 => 'Administrador',
            'email'                => $payload['email'],
            'password'             => bcrypt($payload['password']),
            'api_token'            => $payload['token'],
            'establishment_id'     => $establishmentId,
            'type'                 => $payload['type'] ?? 'admin',
            'locked'               => true,
            'permission_edit_cpe'  => true,
            'last_password_update' => date('Y-m-d H:i:s'),
            'from_guest_register'  => $payload['from_guest_register'] ?? false,
        ]);
        Log::info('Usuario insertado', ['user_id' => $userId]);

        Log::info('Configurando módulos y permisos...');
        $this->assignUserModules($userId, $payload);
    }

    private function assignUserModules(int $userId, array $payload): void
    {
        $type = $payload['type'] ?? 'admin';

        if ($type === 'admin') {
            $modules = array_map(
                fn ($moduleId) => ['module_id' => $moduleId, 'user_id' => $userId],
                $payload['modules'] ?? []
            );
            $levels = array_map(
                fn ($levelId) => ['module_level_id' => $levelId, 'user_id' => $userId],
                $payload['levels'] ?? []
            );

            if (!empty($modules)) {
                Log::info('Insertando módulos de usuario...');
                DB::connection('tenant')->table('module_user')->insert($modules);
            }
            if (!empty($levels)) {
                Log::info('Insertando niveles de usuario...');
                DB::connection('tenant')->table('module_level_user')->insert($levels);
            }

            Log::info('Insertando módulos de app...');
            $this->insertAppModules($userId);
            Log::info('Módulos de app insertados');
        } else {
            Log::info('Insertando módulos básicos para integrator...');
            DB::connection('tenant')->table('module_user')->insert([
                ['module_id' => 1, 'user_id' => $userId],
                ['module_id' => 3, 'user_id' => $userId],
                ['module_id' => 5, 'user_id' => $userId],
            ]);
            Log::info('Módulos básicos insertados');
        }
    }

    private function insertAppModules(int $userId): void
    {
        $rows = AppModule::query()->get()->map(fn ($row) => [
            'app_module_id' => $row->id,
            'user_id'       => $userId,
        ])->toArray();

        if (!empty($rows)) {
            DB::connection('tenant')->table('app_module_user')->insert($rows);
        }
    }

    /**
     * Best-effort cleanup: elimina Client, Hostname y Website si algo falla.
     * Cada eliminación es independiente — si una falla, el resto se intenta.
     * NO lanza excepción para evitar ocultar el error original.
     */
    private function cleanup(?Client $client, Hostname $hostname, Website $website): void
    {
        if ($client && $client->exists) {
            try {
                $client->delete();
            } catch (Exception $e) {
                Log::warning('Cleanup client failed', ['e' => $e->getMessage()]);
            }
        }
        if ($hostname->exists) {
            try {
                $this->hostnameRepo->delete($hostname, true);
            } catch (Exception $e) {
                Log::warning('Cleanup hostname failed', ['e' => $e->getMessage()]);
            }
        }
        if ($website->exists) {
            try {
                $this->websiteRepo->delete($website, true);
            } catch (Exception $e) {
                Log::warning('Cleanup website failed', ['e' => $e->getMessage()]);
            }
        }
    }
}
