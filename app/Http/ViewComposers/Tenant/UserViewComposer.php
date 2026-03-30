<?php

namespace App\Http\ViewComposers\Tenant;

class UserViewComposer
{
    public function compose($view)
    {
        $user = auth()->user();
        if (!$user) {
            // Objeto dummy para evitar "property on null" en vistas
            $view->vc_user = (object) [
                'id' => 0,
                'name' => 'Invitado',
                'email' => '',
                'type' => 'guest',
                'establishment_id' => null,
            ];
            return;
        }
        $view->vc_user = $user;
    }
}
