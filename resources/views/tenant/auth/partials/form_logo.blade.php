{{-- Logo arriba del formulario — SIEMPRE prioriza el logo del tenant (identidad
     de cada empresa), independientemente de si la imagen de fondo viene de la
     configuración global del super admin o del propio tenant.

     Orden de prioridad:
     1. Logo del tenant (company->logo)         → cada empresa ve su propio logo
     2. Logo global + show_logo_in_form=true    → override del super admin
     3. Fallback tulogo.png                     → genérico si no hay nada --}}
@if (!empty($company->logo))
    <img class="auth__logo-form" src="{{ asset('storage/uploads/logos/' . $company->logo) }}" alt="Logo de {{ $company->trade_name ?? '' }}" width="250" />
@elseif ($useLoginGlobal && ($login->logo ?? false) && ($login->show_logo_in_form ?? false))
    <img class="auth__logo-form" src="{{ $login->logo }}" alt="Logo formulario" />
@else
    <img class="auth__logo-form" src="{{ asset('logo/tulogo.png') }}" alt="Logo formulario" width="250" />
@endif
<br>
