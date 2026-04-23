<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\System\Configuration as SystemConfiguration;
use App\Models\Tenant\Company;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Skin;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    protected function redirectTo()
    {
        if (auth()->user() && auth()->user()->type === 'warehouse') {
            return '/logistic/sale-notes/queue';
        }
        return '/dashboard';
    }

    // protected $maxAttempts = 1;
    // protected $decayMinutes = 1;

    protected $maxAttempts = 3;
    protected $decayMinutes = 5;


    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Show the application's login form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showLoginForm()
    {
        $systemConfig = SystemConfiguration::first();
        $useLoginGlobal = $systemConfig && $systemConfig->use_login_global ? true : false;
        $config = $useLoginGlobal ? $systemConfig : Configuration::first();

        $login = $config && $config->login ? $config->login : (object)[
            'type'              => 'image',
            'image'             => asset('images/login-v2.svg'),
            'position_form'     => 'right',
            'show_logo_in_form' => false,
            'position_logo'     => 'top-left',
            'show_socials'      => false,
            'padding_in_form'   => false,
        ];
        $loginBgColor = ($config && isset($config->login_bg_color) && $config->login_bg_color)
            ? $config->login_bg_color
            : '#ffffff';
        $company = Company::first();
        
        // Obtener el tema seleccionado
        $tenantConfig = Configuration::first();
        $selectedSkin = null;
        $themeColors = null;
        $selectedTheme = 'white'; // tema por defecto
        
        if ($tenantConfig && $tenantConfig->skin_id) {
            $selectedSkin = $tenantConfig->skin;
        }
        
        // Obtener la configuración visual y el color de tema
        if ($tenantConfig && $tenantConfig->visual) {
            $visualData = $tenantConfig->visual;
            if (isset($visualData->sidebar_theme)) {
                $selectedTheme = $visualData->sidebar_theme;
            }
        }
        
        // Cargar los colores del tema desde themes.json
        $themesJsonPath = public_path('json/themes/themes.json');
        if (file_exists($themesJsonPath)) {
            $themesData = json_decode(file_get_contents($themesJsonPath), true);
            if (isset($themesData[$selectedTheme])) {
                $themeColors = $themesData[$selectedTheme];
                
                // El tema "white" tiene una estructura especial con sub-temas
                if ($selectedTheme === 'white') {
                    // Determinar qué sub-tema usar basado en el skin seleccionado
                    $subTheme = 'default'; // por defecto
                    if ($selectedSkin && $selectedSkin->filename === 'light.css') {
                        $subTheme = 'light';
                    }
                    $themeColors = $themesData['white'][$subTheme];
                }
            }
        }
        
        return view('tenant.auth.login', compact('company', 'login', 'useLoginGlobal', 'loginBgColor', 'selectedSkin', 'themeColors', 'selectedTheme'));
    }
}
