{{--
    Campo `_token` para formularios de paginas CACHEABLES.

    Sustituye a `@csrf` ahi: `@csrf` escribe el token dentro del HTML, y el
    token es distinto para cada visitante, asi que la pagina deja de poder
    cachearse — y si se cachea, se le entrega el token de una persona a otra.

    Aqui el campo sale VACIO y lo rellena el navegador con el token que pide a
    /marketplace/csrf (ver el bloque mpCsrfToken del layout). El envio del
    formulario espera a que llegue, asi que no hay carrera posible.

    En paginas que NO se cachean (login, registro, checkout, carrito) se sigue
    usando `@csrf` tal cual: es mas simple y no hay nada que ganar.
--}}
<input type="hidden" name="_token" value="" data-mp-token autocomplete="off">
