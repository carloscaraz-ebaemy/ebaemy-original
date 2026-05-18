@php
    // Imgenes "decorativas" por defecto que el sistema setea
    // automticamente al provisionar un tenant. No las renderizamos como
    // fondo porque eran patrones pensados para verse con opacity 0.32 +
    // overlay; sin esos efectos se ven gigantes y desproporcionadas.
    // Si el tenant subi una imagen custom, esa s se renderiza.
    $defaultBgImages = ['fondo-5.svg', 'login-v2.svg'];
    $imgUrl = $login->image ?? null;
    $isDefaultBg = !$imgUrl || collect($defaultBgImages)->contains(fn($n) => str_ends_with($imgUrl, $n));
@endphp
<article class="auth__image" style="padding: {{ ($login->padding_in_form ?? false) ? '0' : '2.5%' }}; display: flex; justify-content: center; align-items: center; overflow: hidden;{{ $isDefaultBg ? '' : ' background-color: ' . ($loginBgColor ?? '#ffffff') . ';' }}">
    @unless ($isDefaultBg)
        <img
            src="{{ $imgUrl }}"
            alt="Background Image"
            style="width: 100%; height: 100%; object-fit: {{ ($login->padding_in_form ?? false) ? 'cover' : 'contain' }}"
        />
    @endunless
    {{-- Logo del side_left (marca de agua sobre el fondo).
         Regla: si hay imagen de fondo custom (no decorativa), NO mostramos
         ningn logo encima  evita el efecto "marca encima de marca" que
         se ve cuando el SuperAdmin sube un fondo con escena (laptop, oficina)
         y el logo global de ebaemy se renderiza superpuesto. El logo del
         tenant siempre aparece en el card (form_logo). --}}
    @if (!$isDefaultBg)
        {{-- Hay fondo custom  ocultar logo del side_left para no superponer --}}
    @elseif ($useLoginGlobal)
        @if ($login->logo ?? false)
            @if ($login->position_logo != 'none' && $login->position_logo != 'on-form')
                <img class="auth__logo {{ $login->position_logo }}" src="{{ $login->logo }}" alt="Logo" />
            @endif
        @endif
    @else
        @if($company->logo)
            @if ($login->position_logo != 'on-form')
                <img class="auth__logo {{ $login->position_logo }}" src="{{ asset('storage/uploads/logos/' . $company->logo) }}" alt="Logo" />
            @endif
        @endif
        {{-- Sin logo del tenant: NO renderizamos marca de agua, para evitar
             que el placeholder aparezca duplicado (uno en el side_left +
             otro en el form). El form_logo ya muestra el placeholder. --}}
    @endif
</article>
