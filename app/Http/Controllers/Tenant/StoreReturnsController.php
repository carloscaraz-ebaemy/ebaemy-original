<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\StoreReturnsPolicy;
use Illuminate\Http\Request;

/**
 * Formulario «Cambios y devoluciones»: datos → texto de la página pública de
 * la tienda y línea «Cambios hasta N días» en el marketplace.
 */
class StoreReturnsController extends Controller
{
    public function show(StoreReturnsPolicy $policy)
    {
        return $policy->formData();
    }

    /** El texto que se publicará, con los datos del formulario, sin guardar. */
    public function preview(Request $request, StoreReturnsPolicy $policy)
    {
        $d = $request->validate(StoreReturnsPolicy::rules());

        return ['html' => $policy->render($d + ['change_enabled' => false])];
    }

    public function save(Request $request, StoreReturnsPolicy $policy)
    {
        abort_unless(optional(auth()->user())->type === 'admin', 403, 'Solo el administrador puede cambiar esta política.');

        $d = $request->validate(StoreReturnsPolicy::rules(), [
            'change_days.required_if' => 'Indica en cuántos días aceptas cambios de talla o color.',
            'contact_email.email'     => 'El correo de contacto no es válido.',
        ]);
        $policy->save($d);

        return [
            'success' => true,
            'message' => 'Política de cambios publicada en tu tienda y en ebaemy.com.',
            'record'  => $policy->formData(),
        ];
    }
}
