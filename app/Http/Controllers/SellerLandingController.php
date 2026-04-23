<?php

namespace App\Http\Controllers;

/**
 * Landing pública para captar vendedores del marketplace ebaemy.
 *
 * Ruta: GET /seller
 *
 * No requiere autenticación. Esta página reemplaza el comportamiento
 * anterior del botón "Vender en ebaemy" del marketplace, que llevaba
 * erróneamente al login del SuperAdmin.
 *
 * El formulario de pre-registro (/seller/register) y el portal de
 * seguimiento (/seller/application/{token}) se implementan en fases
 * posteriores.
 */
class SellerLandingController extends Controller
{
    public function show()
    {
        return view('seller.landing');
    }
}
