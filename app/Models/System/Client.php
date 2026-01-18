<?php

namespace App\Models\System;

use App\Mail\PaymentOrderEmail;
use Carbon\Carbon;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * App\Models\System\Client
 *
 * @property int $id
 * @property int|null $hostname_id
 * @property string $number
 * @property string $name
 * @property string $email
 * @property string $token
 * @property bool $locked
 * @property bool $locked_users
 * @property bool $locked_tenant
 * @property bool $locked_emission
 * @property int $plan_id
 * @property \Illuminate\Support\Carbon|null $start_billing_cycle
 * @property string|null $smtp_encryption Tipo de cifrado de correo
 * @property string|null $smtp_password contraseña de usuario para el envio de correo
 * @property string|null $smtp_user Nombre de usuario para el envio de correo
 * @property int $smtp_port Puerto de correo del cliente
 * @property string|null $smtp_host Host de correo del cliente
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property Hostname|null $hostname
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\System\ClientPayment[] $payments
 * @property int|null $payments_count
 * @property \App\Models\System\Plan $plan
 * @method static \Illuminate\Database\Eloquent\Builder|Client newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Client newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Client query()
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereHostnameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereLockedEmission($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereLockedTenant($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereLockedUsers($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client wherePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereSmtpEncryption($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereSmtpHost($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereSmtpPassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereSmtpPort($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereSmtpUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereStartBillingCycle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereUpdatedAt($value)
 * @property bool $enable_list_product
 * @mixin \Eloquent
 */
class Client extends Model
{
    use UsesSystemConnection;

    protected $with = ['hostname','plan'];

    protected $fillable = [
        'hostname_id',
        'number',
        'name',
        'email',
        'token',
        'locked',
        'locked_emission',
        'locked_tenant',
        'locked_users',
        'plan_id',
        'price',
        'plan_period_id',
        'start_billing_cycle',
        'smtp_host',
        'smtp_port',
        'smtp_user',
        'smtp_password',
        'smtp_encryption',
        'locked_create_establishments',
        'restrict_sales_limit',
        'enable_list_product',
        'restore_dbname_bkdemo',
        'restore_type_bkdemo',
        'enabled_cron_restore_bkdemo',
        'from_guest_register',
        'ending_billing_cycle',
        'phone_ws',
        'client_name',
        'contact_email',
    ];


    protected $casts = [
        'start_billing_cycle' => 'date',
        'ending_billing_cycle' => 'date',
        'smtp_port' => 'int',
        'locked_create_establishments' => 'boolean',
        'restrict_sales_limit' => 'boolean',
        'enable_list_product' => 'boolean',
        'from_guest_register' => 'boolean',
    ];


    /**
     * @return mixed
     */
    public function getSmtpHost()
    {
        return empty($this->smtp_host)?Config::get('mail.host'):$this->smtp_host;
    }

    /**
     * @param mixed $smtp_host
     *
     * @return Client
     */
    public function setSmtpHost($smtp_host)
    {
        $this->smtp_host = $smtp_host;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSmtpPort()
    {
        if($this->smtp_port == 0)$this->smtp_port = null;
        return empty($this->smtp_port)?Config::get('mail.port'):$this->smtp_port;
    }

    /**
     * @param mixed $smtp_port
     *
     * @return Client
     */
    public function setSmtpPort($smtp_port)
    {
        $this->smtp_port = (int) trim($smtp_port);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSmtpUser()
    {
        return empty($this->smtp_user)?Config::get('mail.username'):$this->smtp_user;
    }

    /**
     * @param mixed $smtp_user
     *
     * @return Client
     */
    public function setSmtpUser($smtp_user)
    {
        $this->smtp_user = trim($smtp_user);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSmtpPassword()
    {
        return empty($this->smtp_password)?Config::get('mail.password'):$this->smtp_password;
    }

    /**
     * @param mixed $smtp_password
     *
     * @return Client
     */
    public function setSmtpPassword($smtp_password)
    {
        $this->smtp_password = trim($smtp_password);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSmtpEncryption()
    {
        return empty($this->smtp_encryption)?Config::get('mail.encryption'):$this->smtp_encryption;
    }

    /**
     * @param mixed $smtp_encryption
     *
     * @return Client
     */
    public function setSmtpEncryption($smtp_encryption)
    {
        $this->smtp_encryption = strtolower(trim($smtp_encryption));
        return $this;
    }

    public function hostname()
    {
        return $this->belongsTo(Hostname::class)->with(['website']);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function period()
    {
        return $this->belongsTo(PlanPeriod::class, 'plan_period_id');
    }

    public function payments()
    {
        return $this->hasMany(ClientPayment::class);
    }

    public function orders()
    {
        return $this->hasMany(PaymentOrder::class, 'client_id');
    }

    /**
     *
     * Filtro para no incluir relaciones en consulta
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWhereFilterWithOutRelations($query)
    {
        return $query->withOut(['hostname','plan']);
    }


    /**
     *
     * @return string
     */
    public function getFullName()
    {
        return "{$this->number} - {$this->name}";
    }


    /**
     *
     * @return array
     */
    public function getDataToSelect()
    {
        return [
            'id' => $this->id,
            'full_name' => $this->getFullName(),
        ];
    }


    /**
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeFilterDataMultiUser($query)
    {
        return $query->select([
                    'id',
                    'hostname_id',
                    'number',
                    'name'
                ])
                ->whereFilterWithOutRelations()
                ->with([
                    'hostname' => function($query){
                        return $query->select(['id', 'fqdn', 'website_id'])
                                    ->with(['website']);
                    }
                ]);
    }


    /**
     *
     * @param  builder $query
     * @return builder
     */
    public function scopecurrentclientbywebsite($query, $website)
    {
        return $query->wherehas('hostname', function($q) use($website) {
                    return $q->where('website_id', $website->id);
                })
                ->wherefilterwithoutrelations()
                ->with(['hostname'])
                ->select([
                    'id',
                    'number',
                    'name',
                    'hostname_id'
                ]);
    }

    public function getPricePlan()
    {
        return isset($this->price) ? $this->price : $this->plan->pricing;
    }

    public function createPayemtnOrder()
    {
        $price = $this->getPricePlan();

        $payments_order = PaymentOrder::create([
            'order' => str_pad((PaymentOrder::count() + 1), 6, '0', STR_PAD_LEFT),
            'date_of_due' => $this->ending_billing_cycle,
            'amount' => $price,
            'order_state_id' => 1,
            'client_id' => $this->id,
            'description' => null,
            'created_by' => 'Sistema',
        ]);

        return $payments_order->id;

    }

    public function dayNotification()
    {

        $config = Configuration::first(); 
        $day_notification = Carbon::parse($this->ending_billing_cycle)
                    ->subDays($config->day_before_due)->day;

        return $day_notification;
    }


    public function getVariablesWs()
    {
        $ending = $this->ending_billing_cycle;
        return [
            '@variable_nombre' => $this->name,
            '@variable_plan' => $this->plan->name,
            '@variable_precios' => $this->getPricePlan(),
            '@variable_fecha_vencimiento' => $ending
        ];
    }

    public function notifyOrder($id)
    {
        $confg = Configuration::first();
        $model = PaymentOrder::find($id);

        try {
            $validate = $confg->validationConfigNotify($confg);
            if (!is_null($validate['email']) && !is_null($validate['ws'])) {
                return response()->json([
                    'success' => false,
                    'message' =>  "Tiene que configurar al menos un servicio (WhatsApp o Email) para enviar las notificaciones.",
                ], 400);
            }

            if (is_null($validate['ws'])) {
                if (is_null($this->phone_ws)) {
                    return [
                        'success' => false,
                        'message' =>  "El cliente no tiene un número de WhatsApp configurado.",
                    ];
                }
                $variablesWs = $model->client->getVariablesWs();
                $msg = $confg->qr_api_msg;

                foreach ($variablesWs as $key => $value) {
                    $msg = str_replace($key, $value, $msg);
                }

                $client = new HttpClient();
                    $response = $client->post(
                        $confg->qr_api_url . '/api/message/send-text',
                        [
                            'headers' => [
                                'Authorization' => 'Bearer '. $confg->qr_api_token,
                                'Accept'        => 'application/json',  
                            ],
                            'json' => [
                                "number" => "51".$this->phone_ws,
                                "message" => $msg 
                            ],
                            'verify' => false, 
                        ]
                    );
            }

            if (is_null($validate['email'])) {
                if (is_null($this->contact_email)) {
                    return [
                        'success' => false,
                        'message' =>  "El cliente no tiene un correo de contacto configurado.",
                    ];
                }

                $maillable = new PaymentOrderEmail($model);
                $confg::setConfigSmtpMail();
                Mail::to($this->contact_email)->send($maillable);
            }
            
        } catch (\Throwable $th) {
            Log::error("Error al enviar notificación de orden de pago: " . $th->getMessage());
            return 
            ['success' => false,
                'message' => 'Error al enviar notificación de orden de pago: ' . $th->getMessage()];
        }

        return 
            ['success' => true,
                'message' => 'Notificaciones de email y WhatsApp enviada con éxito'];


    }

    public function activeService()
    {
        if ($this->locked_tenant) {
            return false;
        }
        return Carbon::parse($this->ending_billing_cycle)->greaterThanOrEqualTo(now()->toDateString());
    }
}
