<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Tenant\Configuration;


/**
 * Class CompanyResource
 *
 * @package App\Http\Resources\Tenant
 * @mixin JsonResource
 */
class CompanyResource extends JsonResource
{
    protected function canViewSensitive($request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        return in_array($user->type ?? '', ['admin', 'superadmin'], true);
    }

    protected function maskedSecret($value)
    {
        return filled($value) ? '********' : null;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        $configuration = Configuration::first();
        $canViewSensitive = $this->canViewSensitive($request);
        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'trade_name' => $this->trade_name,
            'soap_send_id' => $this->soap_send_id,
            'soap_type_id' => $this->soap_type_id,
            'soap_username' => $this->soap_username,
            'soap_password' => $canViewSensitive ? $this->soap_password : $this->maskedSecret($this->soap_password),
            'soap_password_configured' => filled($this->soap_password),
            'soap_url' => $this->soap_url,
            'certificate' => $this->certificate,
            'certificate_due' => $this->certificate_due,
            'logo' => $this->logo,
            'logo_dark' => $this->logo_dark,
            'detraction_account' => $this->detraction_account,
            'logo_store' => $this->logo_store,
            'operation_amazonia' => (bool) $this->operation_amazonia,
            'config_system_env' => (bool) $configuration->config_system_env,
            'img_firm' => $this->img_firm,
            'favicon' => $this->favicon,
            'cod_digemid' => $this->cod_digemid,
            'is_pharmacy' => $configuration->isPharmacy(),
            'integrated_query_client_id' => $this->integrated_query_client_id,
            'integrated_query_client_secret' => $canViewSensitive ? $this->integrated_query_client_secret : $this->maskedSecret($this->integrated_query_client_secret),
            'integrated_query_client_secret_configured' => filled($this->integrated_query_client_secret),
            'send_document_to_pse' => $this->send_document_to_pse,
            'url_send_cdr_pse' => $this->url_send_cdr_pse,
            'url_signature_pse' => $this->url_signature_pse,
            'client_id_pse' => $canViewSensitive ? $this->client_id_pse : $this->maskedSecret($this->client_id_pse),
            'client_id_pse_configured' => filled($this->client_id_pse),
            'app_logo' => $this->app_logo,
            'soap_sunat_username' => $this->soap_sunat_username,
            'soap_sunat_password' => $canViewSensitive ? $this->soap_sunat_password : $this->maskedSecret($this->soap_sunat_password),
            'soap_sunat_password_configured' => filled($this->soap_sunat_password),
            'api_sunat_id' => $this->api_sunat_id,
            'api_sunat_secret' => $canViewSensitive ? $this->api_sunat_secret : $this->maskedSecret($this->api_sunat_secret),
            'api_sunat_secret_configured' => filled($this->api_sunat_secret),
            'title_web' => $this->title_web,
            'pse_provider_id' => $this->pse_provider_id,
            'qr_api_enable_ws' => $configuration->qr_api_enable,
            'qr_api_url_ws' => $configuration->qr_api_url,
            'qr_api_key_ws' => $configuration->qr_api_apiKey,
            'mtc_code' => $this->mtc_code
        ];
    }
}
