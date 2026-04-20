@if ($useLoginGlobal)
    @if ($login->logo ?? false)
        {{-- En login global respetamos la preferencia show_logo_in_form --}}
        @if ($login->show_logo_in_form)
            <img class="auth__logo-form" src="{{ $login->logo }}" alt="Logo formulario" />
        @endif
    @endif
@else
    {{-- En el login propio del tenant, SIEMPRE mostrar el logo arriba del
         formulario — es la identidad visual de la marca en el login. --}}
    @if($company->logo)
    <img class="auth__logo-form" src="{{ asset('storage/uploads/logos/' . $company->logo) }}" alt="Logo formulario" width="250" />
    @else
    <img class="auth__logo-form" src="{{asset('logo/tulogo.png')}}" alt="Logo formulario" width="250" />
    @endif
@endif
<br>
