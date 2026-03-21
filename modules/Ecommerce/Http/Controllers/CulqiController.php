<?php
namespace Modules\Ecommerce\Http\Controllers;


use App\Http\Controllers\Tenant\EmailController;
use App\Models\Tenant\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
use Culqi\Culqi;
use Culqi\CulqiException;
use Illuminate\Support\Facades\Mail;
use App\Mail\Tenant\CulqiEmail;
use App\Mail\Tenant\OrderConfirmationEmail;
use stdClass;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant\Order;
use Illuminate\Support\Str;
use App\Models\Tenant\Person;
use Exception;
use App\Models\Tenant\ConfigurationEcommerce;
use Illuminate\Support\Facades\Validator;




class CulqiController extends Controller
{

    public function __construct()
    {
        // $this->middleware('input.request:document,web', ['only' => ['store']]);
    }

    public function index()
    {

    }

    public function payment(Request $request)
    {
      try{

        $customer = (array)json_decode($request->customer);

        $validator = Validator::make($customer, [
            'telefono' => 'required|numeric',
            'direccion' => 'required',
            'codigo_tipo_documento_identidad' => 'required|numeric',
            'numero_documento' => 'required|numeric',
            'identity_document_type_id' => 'required|numeric'
        ]);

        if ($validator->fails()) {
          return response()->json($validator->errors(), 422);
        }


        $user = auth()->user();
        $configuration = ConfigurationEcommerce::first();


        $SECRET_API_KEY = $configuration->token_private_culqui;

        $culqi = new Culqi(array('api_key' => $SECRET_API_KEY));

        $charge = $culqi->Charges->create(
            array(
                "amount" => $request->precio,
                "currency_code" => "PEN",
                "email" => $request->email,
                "description" =>  $request->producto,
                "source_id" => $request->token,
               //  "metadata" => array (
               //      "ruc" => $_POST['ruc'],
               //      "contacto" => $_POST['contacto'],
               //      "telefono" => $_POST['telefono']),
                "installments" => $request->installments
              )
        );

        $customerData = (array) json_decode($request->customer, true);
        $customer_name  = $user ? $user->name : ($customerData['apellidos_y_nombres_o_razon_social'] ?? 'Cliente');
        $customer_email = $request->email ?: ($customerData['correo_electronico'] ?? null);
        $shipping_addr  = $customerData['direccion'] ?? 'direccion 1';

        $order = Order::create([
            'external_id'       => Str::uuid()->toString(),
            'customer'          => json_decode($request->customer),
            'shipping_address'  => $shipping_addr,
            'items'             => json_decode($request->items),
            'total'             => $request->precio_culqi,
            'reference_payment' => 'culqi',
            'purchase'          => json_decode($request->purchase),
        ]);

        if ($customer_email) {
            try {
                Mail::to($customer_email)->send(
                    new \App\Mail\Tenant\OrderConfirmationEmail($order, $customer_name, $customer_email)
                );
            } catch (\Exception $mailEx) {
                \Log::warning('OrderConfirmationEmail (Culqi) failed: ' . $mailEx->getMessage());
            }
        }

        return [
            'success'        => true,
            'culqui'         => $charge,
            'order'          => $order,
            'redirect_route' => url('/ecommerce/order/confirmation/' . $order->external_id),
        ];
      //  return json_encode($charge);
      }
      catch(Exception $e)
      {
        return [
            'success' => false,
            'message' =>  $e->getMessage()
        ];
      }




    }



}
