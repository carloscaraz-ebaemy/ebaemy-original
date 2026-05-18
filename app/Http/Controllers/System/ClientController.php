<?php

    namespace App\Http\Controllers\System;

    use App\CoreFacturalo\Helpers\Certificate\GenerateCertificate;
    use App\Http\Controllers\Controller;
    use App\Http\Requests\System\ClientRequest;
    use App\Http\Resources\System\ClientCollection;
    use App\Http\Resources\System\ClientResource;
    use App\Models\System\Client;
    use App\Models\System\Configuration;
    use App\Models\System\Module;
    use App\Models\System\Plan;
    use Carbon\Carbon;
    use Exception;
    use Hyn\Tenancy\Contracts\Repositories\HostnameRepository;
    use Hyn\Tenancy\Contracts\Repositories\WebsiteRepository;
    use Hyn\Tenancy\Environment;
    use Hyn\Tenancy\Models\Hostname;
    use Hyn\Tenancy\Models\Website;
    use Illuminate\Http\Request;
    use Illuminate\Support\Collection;
    use Illuminate\Support\Facades\DB;
    use Modules\Document\Helpers\DocumentHelper;
    use Modules\MobileApp\Models\System\AppModule;
    use App\CoreFacturalo\ClientHelper;
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Cache;
    use App\Helpers\GuestRegisterHelper;
use App\Models\System\PlanPeriod;

    class ClientController extends Controller
    {
        private const SECRET_MASK = '********';

        public function index()
        {
            return view('system.clients.index');
        }

        public function create()
        {
            return view('system.clients.form');
        }

        public function domains($clientId)
        {
            $client = \App\Models\System\Client::findOrFail($clientId);
            return view('system.clients.domains', compact('client'));
        }

        public function tables()
        {

            $url_base = '.' . config('tenant.app_url_base');
            $plans = Plan::all();
            $types = [['type' => 'admin', 'description' => 'Administrador'], ['type' => 'integrator', 'description' => 'Listar Documentos']];

            // ── UNA SOLA QUERY: cargar TODOS los módulos con levels (antes eran 25+ queries) ──
            $allModules = Module::with('levels')
                ->where('value', '!=', 'production_app')
                ->orderBy('sort')
                ->get()
                ->each(fn($m) => $this->prepareModules($m));

            $modules = $allModules->where('sort', '<', 14)->values();
            $apps    = $allModules->where('sort', '>', 13)->values();

            // Helper: filtrar en memoria sin queries adicionales
            $filterByIds = fn(array $ids) => $allModules->whereIn('id', $ids)->sortBy('sort')->values();

            // Grupos de módulos por giro de negocio
            // IDs: 1=Ventas, 2=Compras, 4=Reportes, 5=Config, 6=POS, 7=Dashboard
            //      8=Inventario, 9=Contabilidad, 12=Finanzas, 14=Establecimientos
            //      17=Productos, 18=Clientes, 50=PreVenta, 51=Guías de Remisión
            //      53=Cola Despacho (logistic)
            $base_ids       = [7, 1, 6, 17, 18, 5, 14];
            $base_full_ids  = [7, 1, 6, 17, 18, 5, 14, 8, 2, 4, 12];
            $base_guia_ids  = [7, 1, 6, 17, 18, 5, 14, 8, 2, 4, 12, 51];
            $base_prev_ids  = [7, 1, 6, 17, 18, 5, 14, 8, 2, 4, 12, 51, 50];
            $base_desp_ids  = [7, 1, 6, 17, 18, 5, 14, 8, 2, 4, 12, 51, 53];
            $base_full_desp = [7, 1, 6, 17, 18, 5, 14, 8, 2, 4, 12, 51, 50, 53];

            $group_basic         = $filterByIds($base_ids);
            $group_pharmacy      = $filterByIds($base_desp_ids);
            $group_hotel         = $filterByIds($base_full_ids);
            $group_restaurant    = $filterByIds([7, 1, 6, 17, 18, 5, 14, 8, 4, 12]);
            $group_ferreteria    = $filterByIds($base_full_desp);
            $group_distribuidora = $filterByIds($base_full_desp);
            $group_minimarket    = $filterByIds(array_merge($base_full_ids, [53]));
            $group_autopartes    = $filterByIds($base_desp_ids);
            $group_veterinaria   = $filterByIds($base_full_ids);
            $group_optica        = $filterByIds($base_full_ids);
            $group_clinica       = $filterByIds($base_full_ids);
            $group_panaderia     = $filterByIds($base_full_ids);
            $group_agricola      = $filterByIds($base_desp_ids);
            $group_lubricentro   = $filterByIds($base_full_ids);
            $group_inmobiliaria  = $filterByIds([7, 1, 17, 18, 5, 14, 4, 12]);
            $group_constructora  = $filterByIds($base_desp_ids);
            $group_transportes   = $filterByIds($base_desp_ids);
            $group_educacion     = $filterByIds([7, 1, 6, 17, 18, 5, 14, 4, 12]);
            $group_gimnasio      = $filterByIds([7, 1, 6, 17, 18, 5, 14, 4, 12]);
            $group_importacion   = $filterByIds($base_desp_ids);
            $group_hotel_apps      = $filterByIds([15]);
            $group_pharmacy_apps   = $filterByIds([19]);
            $group_restaurant_apps = $filterByIds([23]);
            $plan_periods = PlanPeriod::all();

            $config = Configuration::first();

            $certificate_admin = $config->certificate;
            $soap_username = $config->soap_username;
            $soap_password = self::SECRET_MASK;
            $soap_password_configured = !empty($config->soap_password);
            $regex_password_client = $config->regex_password_client;

            return compact(
                'url_base',
                'plans',
                'plan_periods',
                'types',
                'modules',
                'apps',
                'certificate_admin',
                'soap_username',
                'soap_password',
                'soap_password_configured',
                'regex_password_client',
                'group_basic',
                'group_pharmacy',
                'group_hotel',
                'group_restaurant',
                'group_ferreteria',
                'group_distribuidora',
                'group_minimarket',
                'group_autopartes',
                'group_veterinaria',
                'group_optica',
                'group_clinica',
                'group_panaderia',
                'group_agricola',
                'group_lubricentro',
                'group_inmobiliaria',
                'group_constructora',
                'group_transportes',
                'group_educacion',
                'group_gimnasio',
                'group_importacion',
                'group_hotel_apps',
                'group_pharmacy_apps',
                'group_restaurant_apps');
        }

        private function prepareModules(Module $module): Module
        {
            $levels = [];
            foreach ($module->levels as $level) {
                array_push($levels, [
                    'id' => "{$module->id}-{$level->id}",
                    'description' => $level->description,
                    'module_id' => $level->module_id,
                    'is_parent' => false,
                ]);
            }
            unset($module->levels);
            $module->is_parent = true;
            $module->childrens = $levels;
            return $module;
        }

        public function records()
        {
            $records = Client::latest()
                ->get();
            foreach ($records as &$row) {
                // Valores por defecto para que la tabla no se rompa si un tenant falla.
                $row->current_count_doc_month = 0;
                $row->count_doc_pse = 0;
                $row->count_doc = 0;
                $row->soap_type = null;
                $row->count_user = 0;
                $row->count_sales_notes = 0;
                $row->document_regularize_shipping = 0;
                $row->document_not_sent = 0;
                $row->document_to_be_canceled = 0;
                $row->monthly_sales_total = 0;
                $row->count_doc_month = 0;
                $row->sale_notes_quantity_if_include = 0;
                $row->count_sales_notes_month = 0;
                $row->quantity_establishments = 0;

                try {
                    if (!$row->hostname || !$row->hostname->website) {
                        throw new \RuntimeException('Cliente sin hostname/website asociado');
                    }

                    $tenancy = app(Environment::class);
                    $tenancy->tenant($row->hostname->website);

                    // #1256 aqui
                    $current_day = Carbon::now();
                    $current_month_start = $current_day->startOfMonth()->format('Y-m-d');
                    $current_month_end = $current_day->endOfMonth()->format('Y-m-d');
                    $row->current_count_doc_month = DB::connection('tenant')->table('documents')->whereBetween('date_of_issue', [$current_month_start, $current_month_end])->count(); // contador mensual
                    $row->count_doc_pse = DB::connection('tenant')->table('documents')->where('send_to_pse', true)->count();

                    $configuration = DB::connection('tenant')->table('configurations')->first();
                    $company = DB::connection('tenant')->table('companies')->first();

                    $row->count_doc = $configuration->quantity_documents ?? 0;
                    $row->soap_type = $company->soap_type_id ?? null;
                    $row->count_user = DB::connection('tenant')->table('users')->count();
                    $row->count_sales_notes = $configuration->quantity_sales_notes ?? 0;
                    $quantity_pending_documents = $this->getQuantityPendingDocuments();
                    $row->document_regularize_shipping = $quantity_pending_documents['document_regularize_shipping'];
                    $row->document_not_sent = $quantity_pending_documents['document_not_sent'];
                    $row->document_to_be_canceled = $quantity_pending_documents['document_to_be_canceled'];

                    if ($row->start_billing_cycle) {
                        $start_end_date = DocumentHelper::getStartEndDateForFilterDocument($row->start_billing_cycle);
                        $init = $start_end_date['start_date'];
                        $end = $start_end_date['end_date'];

                        $client_helper = new ClientHelper();

                        $row->count_doc_month = DB::connection('tenant')->table('documents')->whereBetween('date_of_issue', [$init, $end])->count();

                        if($row->plan->includeSaleNotesLimitDocuments())
                        {
                            $row->sale_notes_quantity_if_include = $client_helper->getQuantitySaleNotesByDates($init->format('Y-m-d'), $end->format('Y-m-d'));
                        }

                        $row->count_sales_notes_month = DB::connection('tenant')->table('sale_notes')->whereBetween('date_of_issue', [$init, $end])->count();

                        if ($row->count_sales_notes_month > 0 && $row->count_sales_notes != $row->count_sales_notes_month) {
                            DB::connection('tenant')
                                ->table('configurations')
                                ->where('id', 1)
                                ->update([
                                    'quantity_sales_notes' => $row->count_sales_notes_month
                                ]);

                            $row->count_sales_notes = $row->count_sales_notes_month;
                        }

                        $row->monthly_sales_total = $client_helper->getSalesTotal($init->format('Y-m-d'), $end->format('Y-m-d'), $row->plan);
                    }

                    $row->quantity_establishments = $this->getQuantityRecordsFromTable('establishments');
                } catch (\Throwable $e) {
                    \Log::warning("Error al cargar métricas del cliente {$row->id}: {$e->getMessage()}");
                }
            }

            return new ClientCollection($records);
        }


        /**
         *
         * @param  string $table
         * @return int
         */
        private function getQuantityRecordsFromTable($table)
        {
            return DB::connection('tenant')->table($table)->count();
        }


        private function getQuantityPendingDocuments()
        {

            return [
                'document_regularize_shipping' => DB::connection('tenant')->table('documents')->where('state_type_id', '01')->where('regularize_shipping', true)->count(),
                'document_not_sent' => DB::connection('tenant')->table('documents')->whereIn('state_type_id', ['01', '03'])->where('date_of_issue', '<=', date('Y-m-d'))->count(),
                'document_to_be_canceled' => DB::connection('tenant')->table('documents')->where('state_type_id', '13')->count(),
            ];

        }


        public function record($id)
        {
            $client = Client::findOrFail($id);
            $tenancy = app(Environment::class);
            $tenancy->tenant($client->hostname->website);
            $user_id = 1;
            // Se buscan los valores en las tablas de los clientes, luego se compara con las tablas de admin para mostrar
            // correctamente la seleccion en la seccion de modulos de permisos
            $modules = DB::connection('tenant')
                ->table('modules')
                ->where('modules.order_menu', '<=', 13)
                ->join('module_user', 'module_user.module_id', '=', 'modules.id')
                ->where('module_user.user_id', $user_id)
                ->select('modules.value as value')
                ->get()
                ->pluck('value');
            $client->modules = DB::connection('system')
                ->table('modules')
                ->wherein('value', $modules)
                ->select('id')
                ->distinct()
                ->get()
                ->pluck('id');

            // Se buscan los valores en las tablas de los clientes, luego se compara con las tablas de admin para mostrar
            // correctamente la seleccion en la seccion de modulos de permisos
            // Apps
            $apps = DB::connection('tenant')
                ->table('modules')
                ->where('modules.order_menu', '>', 13)
                ->join('module_user', 'module_user.module_id', '=', 'modules.id')
                ->where('module_user.user_id', $user_id)
                ->select('modules.value as value')
                ->get()
                ->pluck('value');

            $client->apps = DB::connection('system')
                ->table('modules')
                ->wherein('value', $apps)
                ->select('id')
                ->distinct()
                ->get()
                ->pluck('id');

            // Se buscan los valores en las tablas de los clientes, luego se compara con las tablas de admin para mostrar
            // correctamente la seleccion en la seccion de modulos de permisos
            $levels = DB::connection('tenant')
                ->table('module_level_user')
                ->where('module_level_user.user_id', $user_id)
                ->join('module_levels', 'module_levels.id', '=', 'module_level_user.module_level_id')
                ->get()
                ->pluck('value');

            $client->levels = DB::connection('system')
                ->table('module_levels')
                ->wherein('value', $levels)
                ->select('id')
                ->distinct()
                ->get()
                ->pluck('id');

            $config = DB::connection('tenant')
                ->table('configurations')
                ->first();

            $client->config_system_env = $config->config_system_env;

            $company = DB::connection('tenant')
                ->table('companies')
                ->first();

            $client->soap_send_id = $company->soap_send_id;
            $client->soap_type_id = $company->soap_type_id;
            $client->soap_username = $company->soap_username;
            $client->soap_password = $company->soap_password;
            $client->soap_url = $company->soap_url;
            $client->certificate = $company->certificate;
            $client->number = $company->number;

            return new ClientResource($client);

        }

        public function charts()
        {
            try {
                $records = Client::all();
                $count_documents = [];
                
                foreach ($records as $row) {
                    try {
                        // Verificar que el cliente tenga hostname y website válidos
                        if (!$row->hostname || !$row->hostname->website) {
                            \Log::warning("Cliente {$row->number} no tiene hostname válido");
                            continue;
                        }

                        $tenancy = app(Environment::class);
                        $tenancy->tenant($row->hostname->website);
                        
                        // Verificar que la conexión tenant esté disponible
                        if (!DB::connection('tenant')->getDatabaseName()) {
                            \Log::warning("Cliente {$row->number} no tiene base de datos configurada");
                            continue;
                        }

                        for ($i = 1; $i <= 12; $i++) {
                            
                            $date_initial = Carbon::create(null, $i)->startOfMonth();
                            $date_final = Carbon::create(null, $i)->endOfMonth();
                            
                            $count = DB::connection('tenant')
                                ->table('documents')
                                ->whereBetween('date_of_issue', [$date_initial, $date_final])
                                ->count();
                            
                            $count_documents[] = [
                                'client' => $row->number,
                                'month' => $i,
                                'count' => $count
                            ];
                        }
                    } catch (\Exception $e) {
                        // Registrar el error pero continuar con los demás clientes
                        \Log::warning("Error al procesar cliente {$row->number}: " . $e->getMessage());
                        continue;
                    }
                }

                $total_documents = collect($count_documents)->sum('count');

                $groups_by_month = collect($count_documents)->groupBy('month');
                $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'];
                $documents_by_month = [];
                
                foreach ($groups_by_month as $month => $group) {
                    $documents_by_month[] = $group->sum('count');
                }
                
                // Asegurarse de que siempre haya 12 meses (rellenar con 0 si falta alguno)
                for ($i = 0; $i < 12; $i++) {
                    if (!isset($documents_by_month[$i])) {
                        $documents_by_month[$i] = 0;
                    }
                }

                $line = [
                    'labels' => $labels,
                    'data' => $documents_by_month
                ];

                return compact('line', 'total_documents');
                
            } catch (\Exception $e) {
                \Log::error("Error general en charts(): " . $e->getMessage());
                
                // Devolver datos vacíos en caso de error
                $line = [
                    'labels' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'],
                    'data' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
                ];
                
                return [
                    'line' => $line,
                    'total_documents' => 0,
                    'error' => 'Error al cargar los datos del gráfico'
                ];
            }
        }

        /**
         * @param Request $request
         *
         * @return array
         */
        public function update(Request $request)
        {
            /**
             * @var Collection $valueModules
             * @var Collection $valueLevels
             */
            $user_id = 1;
            $array_modules = [];
            $array_levels = [];


            $smtp_host = ($request->has('smtp_host')) ? $request->smtp_host : null;
            $smtp_password = ($request->has('smtp_password')) ? $request->smtp_password : null;
            $smtp_port = ($request->has('smtp_port')) ? $request->smtp_port : null;
            $smtp_user = ($request->has('smtp_user')) ? $request->smtp_user : null;
            $smtp_encryption = ($request->has('smtp_encryption')) ? $request->smtp_encryption : null;
            try {

                $temp_path = $request->input('temp_path');

                $name_certificate = $request->input('certificate');

                if ($temp_path) {

                    try {
                        $password = $request->input('password_certificate');
                        $pfx = file_get_contents($temp_path);
                        $pem = GenerateCertificate::typePEM($pfx, $password);
                        $name = 'certificate_' . $request->input('number') . '.pem';
                        if (!file_exists(storage_path('app' . DIRECTORY_SEPARATOR . 'certificates'))) {
                            mkdir(storage_path('app' . DIRECTORY_SEPARATOR . 'certificates'));
                        }
                        file_put_contents(storage_path('app' . DIRECTORY_SEPARATOR . 'certificates' . DIRECTORY_SEPARATOR . $name), $pem);
                        $name_certificate = $name;

                    } catch (Exception $e) {
                        return [
                            'success' => false,
                            'message' => $e->getMessage()
                        ];
                    }
                }


                $client = Client::findOrFail($request->id);

                $client
                    ->setSmtpHost($smtp_host)
                    ->setSmtpPort($smtp_port)
                    ->setSmtpUser($smtp_user)
                    //    ->setSmtpPassword($smtp_password)
                    ->setSmtpEncryption($smtp_encryption);
                if (!empty($smtp_password)) {
                    $client->setSmtpPassword($smtp_password);
                }
                $client->plan_id = $request->plan_id;
                $client->price = $request->price;
                $client->plan_period_id = $request->plan_period_id;
                $client->phone_ws = $request->phone_ws;
                $client->client_name = $request->client_name;
                $client->contact_email = $request->contact_email;

                $client->enable_list_product = $request->enable_list_product;
                $client->save();

                $plan = Plan::find($request->plan_id);

                $tenancy = app(Environment::class);
                $tenancy->tenant($client->hostname->website);
                $clientData = [
                    'plan' => json_encode($plan),
                    'config_system_env' => $request->config_system_env,
                    'limit_documents' => $plan->limit_documents,
                    'smtp_host' => $client->smtp_host,
                    'smtp_port' => $client->smtp_port,
                    'smtp_user' => $client->smtp_user,
                    'smtp_password' => $client->smtp_password,
                    'smtp_encryption' => $client->smtp_encryption,
                    'enable_list_product' => $client->enable_list_product,
                ];
                if (empty($client->smtp_password)) unset($clientData['smtp_password']);
                DB::connection('tenant')
                    ->table('configurations')
                    ->where('id', 1)
                    ->update($clientData);

                $tenantCompany = DB::connection('tenant')
                    ->table('companies')
                    ->select('soap_password')
                    ->where('id', 1)
                    ->first();

                $resolvedSoapPassword = $this->resolveMaskedSecret(
                    $request->soap_password,
                    optional($tenantCompany)->soap_password
                );

                DB::connection('tenant')
                    ->table('companies')
                    ->where('id', 1)
                    ->update([
                        'soap_type_id' => $request->soap_type_id,
                        'soap_send_id' => $request->soap_send_id,
                        'soap_username' => $request->soap_username,
                        'soap_password' => $resolvedSoapPassword,
                        'soap_url' => $request->soap_url,
                        'certificate' => $name_certificate
                    ]);


                //modules
                DB::connection('tenant')
                    ->table('module_user')
                    ->where('user_id', $user_id)
                    ->delete();
                DB::connection('tenant')
                    ->table('module_level_user')
                    ->where('user_id', $user_id)
                    ->delete();

                // Obtenemos los value de las tablas
                $valueModules = DB::connection('system')
                    ->table('modules')
                    ->wherein('id', $request->modules)
                    ->get()
                    ->pluck('value');
                $valueLevels = DB::connection('system')
                    ->table('module_levels')
                    ->wherein('id', $request->levels)
                    ->get()
                    ->pluck('value');

                // Obtenemos el modelo del modulo, asi se obtendrá el id del elemento
                DB::connection('tenant')
                    ->table('modules')
                    ->wherein('value', $valueModules)
                    ->selectRaw('id as module_id, ? as user_id', [$user_id])
                    ->get()
                    ->transform(function ($module) use (&$array_modules) {
                        $array_modules[] = (array)$module;
                    });
                DB::connection('tenant')
                    ->table('module_levels')
                    ->wherein('value', $valueLevels)
                    ->selectRaw('id as module_level_id, ? as user_id', [$user_id])
                    ->get()
                    ->transform(function ($level) use (&$array_levels) {
                        $array_levels[] = (array)$level;
                    });

                // Se actualiza las tablas de permisos
                DB::connection('tenant')
                    ->table('module_user')
                    ->insert($array_modules);
                DB::connection('tenant')
                    ->table('module_level_user')
                    ->insert($array_levels);

                // Actualiza el modulo de farmacia.
                $config = (array)DB::connection('tenant')
                    ->table('configurations')
                    ->first();
                $config['is_pharmacy'] = (self::EnablePharmacy($user_id)) ? 1 : 0;
                DB::connection('tenant')
                    ->table('configurations')
                    ->update($config);
                return [
                    'success' => true,
                    'message' => 'Cliente Actualizado satisfactoriamente',
                    'modules' => $array_modules,
                    'levels' => $array_levels,
                ];

            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => $e->getMessage()
                ];

            }

        }

        /**
         * Devuelve la informacion si el modulo de farmacia esta habilitado o no para activar la configuracion
         * correspondiente
         *
         * @param int $user_id
         *
         * @return bool
         */
        public static function EnablePharmacy($user_id = 0)
        {
            $modulo_id = DB::connection('tenant')
                ->table('modules')
                ->where('value', 'digemid')
                ->first()->id;
            $modulo = DB::connection('tenant')
                ->table('module_user')
                ->where('module_id', $modulo_id)
                ->where('user_id', $user_id)
                ->first();

            return ($modulo == null) ? false : true;

        }

        public function store(ClientRequest $request)
        {
            // Establecer tiempo de ejecución manual para evitar timeout
            set_time_limit(3600); // 60 minutos
            ini_set('memory_limit', '2048M');
            \Log::info('=== INICIO STORE CLIENT ===', ['timestamp' => now()]);

            // Paso 1 — procesar certificado si vino en el upload.
            // Se mantiene aquí (fuera del service) porque depende del request
            // HTTP (temp_path apunta a un archivo temporal propio de la subida).
            try {
                $name_certificate = $this->resolveCertificateName($request);
            } catch (Exception $e) {
                \Log::error('Error procesando certificado', ['error' => $e->getMessage()]);
                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }

            // Paso 2 — armar payload y delegar al service.
            $subDom = strtolower($request->input('subdomain'));
            $payload = [
                'subdomain'           => $subDom,
                'uuid'                => config('tenant.prefix_database') . '_' . $subDom,
                'fqdn'                => $subDom . '.' . config('tenant.app_url_base'),
                'token'               => Str::random(50),
                'email'               => $request->input('email'),
                'name'                => $request->input('name'),
                'number'              => $request->input('number'),
                'plan_id'             => $request->input('plan_id'),
                'locked_emission'     => $request->input('locked_emission'),
                'enable_list_product' => $request->input('enable_list_product'),
                'price'               => $request->input('price'),
                'plan_period_id'      => $request->input('plan_period_id'),
                'client_name'         => $request->input('client_name'),
                'phone_ws'            => $request->input('phone_ws'),
                'contact_email'       => $request->input('contact_email'),
                'certificate_name'    => $name_certificate,
                'soap_type_id'        => $request->soap_type_id,
                'soap_send_id'        => $request->soap_send_id,
                'soap_username'       => $request->soap_username,
                'soap_password'       => $request->soap_password,
                'soap_url'            => $request->soap_url,
                'config_system_env'   => $request->config_system_env,
                'password'            => $request->input('password'),
                'type'                => $request->input('type'),
                'modules'             => $request->input('modules', []),
                'levels'              => $request->input('levels', []),
                'from_guest_register' => $request->input('from_guest_register', false),
            ];

            \Log::info('Variables de tenant creadas', ['uuid' => $payload['uuid'], 'fqdn' => $payload['fqdn']]);

            $result = app(\App\Services\System\TenantCreationService::class)->create($payload);

            // Compatibilidad: el response anterior solo devolvía success+message.
            // El service adjunta 'client' si la creación fue exitosa; lo quitamos
            // del payload de respuesta para no cambiar el contrato con el frontend.
            unset($result['client']);

            return $result;
        }

        /**
         * Procesa el certificado digital si el request trae un temp_path.
         * Si no hay temp_path, devuelve el certificado por defecto de la
         * configuración del sistema.
         *
         * @throws Exception si falla la conversión .pfx → .pem
         */
        private function resolveCertificateName(ClientRequest $request): ?string
        {
            $temp_path = $request->input('temp_path');
            $configuration = Configuration::first();
            \Log::info('Configuración obtenida', ['config_id' => $configuration->id ?? 'null']);

            $name_certificate = $configuration->certificate ?? null;

            if (!$temp_path) {
                return $name_certificate;
            }

            \Log::info('Procesando certificado', ['temp_path' => $temp_path]);

            $number   = $request->input('number');
            $password = $request->input('password_certificate');
            $pfx      = file_get_contents($temp_path);
            $pem      = GenerateCertificate::typePEM($pfx, $password);
            $name     = 'certificate_admin_tenant_' . $number . '.pem';

            $certDir = storage_path('app' . DIRECTORY_SEPARATOR . 'certificates');
            if (!file_exists($certDir)) {
                mkdir($certDir);
            }
            file_put_contents($certDir . DIRECTORY_SEPARATOR . $name, $pem);

            \Log::info('Certificado procesado exitosamente', ['name' => $name]);

            return $name;
        }

        public function validateWebsite($uuid, $website)
        {

            $exists = $website::where('uuid', $uuid)->first();

            if ($exists) {
                throw new Exception("El subdominio ya se encuentra registrado");
            }

        }


        /**
         *
         * Registrar modulos de la app al usuario principal
         *
         * @param  int $user_id
         * @return void
         */
        private function insertAppModules($user_id)
        {
            $all_app_modules = AppModule::get()->map(function($row) use($user_id){
                                    return [
                                        'app_module_id' => $row->id,
                                        'user_id' => $user_id,
                                    ];
                                })->toArray();

            DB::connection('tenant')->table('app_module_user')->insert($all_app_modules);
        }


        public function renewPlan(Request $request)
        {

            // dd($request->all());
            $client = Client::findOrFail($request->id);
            $tenancy = app(Environment::class);
            $tenancy->tenant($client->hostname->website);

            DB::connection('tenant')->table('billing_cycles')->insert([
                'date_time_start' => date('Y-m-d H:i:s'),
                'renew' => true,
                'quantity_documents' => DB::connection('tenant')->table('configurations')->where('id', 1)->first()->quantity_documents,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('tenant')->table('configurations')->where('id', 1)->update(['quantity_documents' => 0]);
            DB::connection('tenant')->table('configurations')->where('id', 1)->update(['quantity_sales_notes' => 0]);


            return [
                'success' => true,
                'message' => 'Plan renovado con exito'
            ];

        }


        public function lockedUser(Request $request)
        {

            $client = Client::findOrFail($request->id);
            $client->locked_users = $request->locked_users;
            $client->save();

            $tenancy = app(Environment::class);
            $tenancy->tenant($client->hostname->website);
            DB::connection('tenant')->table('configurations')->where('id', 1)->update(['locked_users' => $client->locked_users]);
            \App\Models\Tenant\Configuration::flushCache();

            return [
                'success' => true,
                'message' => ($client->locked_users) ? 'Limitar creación de usuarios activado' : 'Limitar creación de usuarios desactivado'
            ];

        }


        public function lockedEmission(Request $request)
        {

            $client = Client::findOrFail($request->id);
            $client->locked_emission = $request->locked_emission;
            $client->save();

            $tenancy = app(Environment::class);
            $tenancy->tenant($client->hostname->website);
            DB::connection('tenant')->table('configurations')->where('id', 1)->update(['locked_emission' => $client->locked_emission]);
            \App\Models\Tenant\Configuration::flushCache();

            return [
                'success' => true,
                'message' => ($client->locked_emission) ? 'Limitar emisión de documentos activado' : 'Limitar emisión de documentos desactivado'
            ];

        }


        public function lockedTenant(Request $request)
        {

            $client = Client::findOrFail($request->id);
            $client->locked_tenant = $request->locked_tenant;
            $client->save();

            $tenancy = app(Environment::class);
            $tenancy->tenant($client->hostname->website);
            DB::connection('tenant')->table('configurations')->where('id', 1)->update(['locked_tenant' => $client->locked_tenant]);
            // Invalidar cache del tenant: el middleware LockedTenant lee con
            // Configuration::firstCached() (TTL 10 min). Sin este flush, el
            // desbloqueo no surte efecto hasta que expire el cache (el
            // raw update via DB::table no dispara el evento saved() Eloquent
            // que normalmente invalidaría el cache).
            \App\Models\Tenant\Configuration::flushCache();

            return [
                'success' => true,
                'message' => ($client->locked_tenant) ? 'Cuenta bloqueada' : 'Cuenta desbloqueada'
            ];

        }


        /**
         *
         * Validar si el valor de confirmacion ingresado por el usuario es
         * igual al ruc o nombre de la empresa, para poder eliminar el cliente
         *
         * @param  Client $client
         * @param  string $input_validate
         * @return array
         */
        public function checkInputValidateDelete(Client $client, $input_validate)
        {

            if($input_validate === $client->name || $input_validate === $client->number)
            {
                return $this->generalResponse(true);
            }

            return $this->generalResponse(false, 'El valor ingresado no coincide con el nombre o número de ruc de la empresa.');

        }


        /**
         *
         * Eliminar cliente
         *
         * @param  int $id
         * @param  string $input_validate
         * @return array
         */
        public function destroy($id, $input_validate)
        {
            $client = Client::find($id);

            $check_input_validate_delete = $this->checkInputValidateDelete($client, $input_validate);
            if(!$check_input_validate_delete['success']) return $check_input_validate_delete;

            if ($client->locked) {
                return [
                    'success' => false,
                    'message' => 'Cliente bloqueado, no puede eliminarlo'
                ];
            }

            $hostname = Hostname::find($client->hostname_id);
            $website = Website::find($hostname->website_id);

            app(HostnameRepository::class)->delete($hostname, true);
            app(WebsiteRepository::class)->delete($website, true);

            return [
                'success' => true,
                'message' => 'Cliente eliminado con éxito'
            ];
        }

        public function password($id)
        {
            $client = Client::find($id);
            $website = Website::find($client->hostname->website_id);
            $tenancy = app(Environment::class);
            $tenancy->tenant($website);
            DB::connection('tenant')->table('users')
                ->where('id', 1)
                ->update(['password' => bcrypt($client->number)]);

            return [
                'success' => true,
                'message' => 'Clave cambiada con éxito'
            ];
        }

        public function startBillingCycle(Request $request)
        {
            $client = Client::findOrFail($request->id);
            $client->start_billing_cycle = $request->start_billing_cycle;
            $client->save();

            return [
                'success' => true,
                'message' => ($client->start_billing_cycle) ? 'Ciclo de Facturacion definido.' : 'No se pudieron guardar los cambios.'
            ];
        }

        public function upload(Request $request)
        {
            if ($request->hasFile('file')) {
                $new_request = [
                    'file' => $request->file('file'),
                    'type' => $request->input('type'),
                ];

                return $this->upload_certificate($new_request);
            }
            return [
                'success' => false,
                'message' => 'Error al subir file.',
            ];
        }

        public function upload_certificate($request)
        {
            $file = $request['file'];
            $type = $request['type'];

            $temp = tempnam(sys_get_temp_dir(), $type);
            file_put_contents($temp, file_get_contents($file));

            $mime = mime_content_type($temp);
            $data = file_get_contents($temp);

            return [
                'success' => true,
                'data' => [
                    'filename' => $file->getClientOriginalName(),
                    'temp_path' => $temp,
                    //'temp_image' => 'data:' . $mime . ';base64,' . base64_encode($data)
                ]
            ];
        }


        /**
         *
         * @param  Request $request
         * @return array
         */
        public function lockedByColumn(Request $request)
        {
            $column = $request->column;
            $client = Client::findOrFail($request->id);
            $client->{$column} = $request->{$column};
            $client->save();

            $tenancy = app(Environment::class);
            $tenancy->tenant($client->hostname->website);
            DB::connection('tenant')->table('configurations')->where('id', 1)->update([$column => $client->{$column}]);

            return $this->generalResponse(true, $client->{$column} ? 'Activado correctamente' : 'Desactivado correctamente');
        }

        public function confirmGuest(Request $request)
        {
            $client = Client::where('id',$request->id)->where('number',$request->number)->first();
        
            if(!$client){
                return $this->generalResponse(false, 'Empresa no encontrada');
            }
            $tenancy = app(Environment::class);
            $tenancy->tenant($client->hostname->website);
            $configuration = DB::connection('tenant')->table('configurations')->where('id', 1)->first();

            if($configuration->was_verified_guest_user){
                return $this->generalResponse(false,'Nuestro sistema indica que ya hemos verificado tu información anteriormente');
            }

            DB::connection('tenant')->table('users')->where('id', 1)->update(['email_verified_at' => now()]);
            DB::connection('tenant')->table('configurations')->where('id', 1)->update(['was_verified_guest_user' => true]);
            return [
                'success' => true,
                'data' => [
                    'message' => "Cliente verificado con éxito."
                ]
            ];

        }

        public function search(Request $request)
        {
            $query = $request->input('query');

            $clients = Client::where('name', 'like', "%{$query}%")
                        ->orWhere('number', 'like', "%{$query}%")
                        ->get();

            $clients = $clients->transform(function($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                ];
            });

            return compact('clients');
        }

        public function confirmLimitReseller(Request $request)
        {
            $limiteClientes = (int) config('app.limite_reseller' , 999);
            $totalClientes = Client::count();

            if($totalClientes >= $limiteClientes && $limiteClientes > 0){
                return $this->generalResponse(false, 'Ha alcanzado el límite de clientes permitidos');
            }

            return $this->generalResponse(true, 'Aun puede registrar más clientes');
        }

        private function resolveMaskedSecret($incoming, $current)
        {
            if (!is_string($incoming)) {
                return $current;
            }

            $value = trim($incoming);
            if ($value === '' || $value === self::SECRET_MASK) {
                return $current;
            }

            return $value;
        }
    }
