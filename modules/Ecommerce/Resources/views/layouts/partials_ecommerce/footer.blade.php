<style>
    .vl {
        border-left: 2px solid black;
        height: 100%;
        margin-left: 30%;
    }

</style>

<div class="footer-middle">
    <div class="container">
        <div class="row">
            <div class="col-lg-3">
                <div class="widget widget-info">
                    <h4 class="widget-title">Contáctanos</h4>
                    <ul class="contact-info">
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-phone"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2" /></svg>
                            <a href="tel:+51944999965" target="blank" style="font-size: 25px;">{{$information->information_contact_phone}}</a>
                        </li>
                        @if($information->information_contact_address)
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-map-pin"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0z" /></svg>
                            <a href="#" target="blank" style="font-size: 14px;">
                                {{$information->information_contact_address}}
                            </a>
                        </li>
                        @endif
                        <!-- correo -->
                        @if($information->information_contact_email)
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-mail"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10z" /><path d="M3 7l9 6l9 -6" /></svg>
                            <a href="mailto:{{$information->information_contact_email}}" target="blank" style="font-size: 14px;">{{$information->information_contact_email}}</a>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
            <div class="col-md-3">
                <div class="widget">
                    <h4 class="widget-title text-center">Enlaces de interés</h4>
                    <div class="row d-flex align-items-center justify-content-center">
                        <div class="col-sm-6 col-md-5 text-center">
                            <ul class="links">
                                <li><a href="{{ route("tenant.ecommerce.index") }}">Inicio</a></li>
                                <li><a href="{{ route('tenant_detail_cart') }}">Ver Carrito</a></li>
                                @guest
                                <li><a href="{{route('tenant_ecommerce_login')}}" class="login-link">Login</a></li>
                                @else
                                <li><a role="menuitem" href="{{ route('logout') }}" class="login-link" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    Salir
                                </a></li>
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                    @csrf
                                </form>
                                @endguest
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="widget">
                    <h4 class="widget-title">Términos y Condiciones</h4>
                    <div class="row">
                        <div class="col-12">
                            <ul class="links" style="padding: 0 ; margin:0; list-style: none; width: 100%;">
                                 <li style="width: 100%;"><a href="{{ route("tenant.politica_privacidad") }}" style="display: block; width: 100%;">Políticas de privacidad </a></li>
                                <li style="width: 100%;"><a href="{{ route("tenant.terminos_condiciones") }}" style="display: block; width: 100%;">Términos y condiciones del uso del sitio</a></li>
                                <li style="width: 100%;"><a href="{{ route("tenant.cambios_devolucion") }}" style="display: block; width: 100%;">cambios y Devoluciones</a></li>
                                <li style="width: 100%;"><a href="{{ route("tenant.politica_envio") }}" style="display: block; width: 100%;">políticas de  envío</a></li>
                                @if (Route::has('tenant.libro_reclamaciones'))
                                <li style="width: 100%;">
                                    <a href="{{ route('tenant.libro_reclamaciones') }}" style="display: block;">
                                        Libro de Reclamaciones
                                    </a>
                                </li>
                                @endif

                            </ul>
                        </div>
                    </div>
                </div>
            </div>
          
            <div class="col-md-3">
                <div class="widget">
                    <h4 class="widget-title text-end">Redes Sociales</h4>
                    <div class="social-icons d-flex justify-content-end">

                        <!-- @if($information->link_facebook)
                            <a href="{{$information->link_facebook}}" class="social-icon" target="_blank"></a>
                        @endif -->

                        <!-- @if($information->link_twitter)
                            <a href="{{$information->link_twitter}}" class="social-icon" target="_blank"><i class="icon-twitter"></i></a>
                        @endif -->

                        <!-- @if($information->link_instagram)
                            <a href="{{$information->link_instagram}}" class="social-icon" target="_blank"><i class="fab fa-youtube"></i></a>
                        @endif -->

                        <a href="{{$information->link_facebook}}" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-brand-facebook"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 10v4h3v7h4v-7h3l1 -4h-4v-2a1 1 0 0 1 1 -1h3v-4h-3a5 5 0 0 0 -5 5v2h-3" /></svg>
                        </a>
                        <a href="{{$information->link_twitter}}" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-brand-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 4l11.733 16h4.267l-11.733 -16z" /><path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772" /></svg>
                        </a>
                        <a href="{{$information->link_tiktok}}" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-brand-tiktok"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M21 7.917v4.034a9.948 9.948 0 0 1 -5 -1.951v4.5a6.5 6.5 0 1 1 -8 -6.326v4.326a2.5 2.5 0 1 0 4 2v-11.5h4.083a6.005 6.005 0 0 0 4.917 4.917z" /></svg>
                        </a>
                        <a href="{{$information->link_instagram}}" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-brand-instagram"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 8a4 4 0 0 1 4 -4h8a4 4 0 0 1 4 4v8a4 4 0 0 1 -4 4h-8a4 4 0 0 1 -4 -4z" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M16.5 7.5v.01" /></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container container-footer d-flex align-items-center justify-content-between">
    <p class="text-center copy-text mt-3 mb-3">&copy; Copyright {{ date('Y') }} {{ $company->name }}. Todos los derechos reservados</p>
    <div class="footer-bottom" style="padding-bottom: 2rem;">
        <!-- <p class="footer-copyright">Facturador Pro 4. &copy; {{ now()->year }}. Todos los Derechos Reservados</p> -->
        <img src="{{ asset('porto-ecommerce/assets/images/payments.svg') }}" alt="payment methods"
            class="footer-payments">
    </div>
</div>

@if($information->phone_whatsapp)
    @if(strlen($information->phone_whatsapp) > 0)
    <a class='ws-flotante' href='https://wa.me/{{$information->phone_whatsapp}}' target="BLANK" style="background-image: url('{{asset('logo/ws.png')}}'); background-size: 70px; background-repeat: no-repeat;" ></a>
    @endif
@endif

<div class="modal fade" id="moda-succes-add-product" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-body">

                <div class="alert alert-success" role="alert">
                    <i class="icon-ok"></i> Tu producto se agregó al carrito
                </div>
                <div class="row">
                    <div id="product_added_image" class="col-md-4">


                    </div>
                    <div class="col-md-8">
                        <div id="product_added" class="product-single-details-ecommerce">

                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <a href="{{ route('tenant_detail_cart') }}" class="btn btn-primary text-white">Ir a Carrito</a>
                <button type="button" class="btn btn-warning" data-dismiss="modal">Seguir Comprando</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modal-already-product" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">

            <div class="modal-body">

                <div style="font-size: 2em;" class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation"></i> Tu Producto ya está agregado al carrito.
                </div>
            </div>
            <div class="modal-footer">
                <a href="{{ route('tenant_detail_cart') }}" class="btn btn-primary text-white">Ir al Carrito</a>
                <button type="button" class="btn btn-warning" data-dismiss="modal">Seguir Comprando</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="login_register_modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div id="tony" class="modal-body-restaurant">
                    <div class="contenedor-form" id="contenedor-form">
                        <!-- contenedor de login -->
                         <!-- <div class="contenedor-column-form"> -->
                        <div id="first-column" class="first-column">
            <form action="#" id="form_login" class="iniciar-sesion" data-login-url="{{ route('tenant_ecommerce_login') }}">
                <h4 class="title mb-2">Iniciar sesión</h4>
                <div id="msg_login" class="alert alert-danger" role="alert" style="display: none;">
                                    Usuario o Contraseña Incorrectos.
                                </div>
                                <div class="form-group">
                                    <label for="email">Correo Electronico:</label>
                                    <input type="email" required class="form-control" id="email"
                                        placeholder="Enter email" name="email">
                                </div>
                                <div class="form-group">
                                    <label for="pwd">Contraseña:</label>
                                    <input type="password" required class="form-control" id="pwd"
                                        placeholder="Enter password" name="password">
                                </div>
                                <button type="submit" class="btn btn-primary">Ingresar</button>
                                <div class="forgot-password-container">
                                    <span class="forgot-password-title">
                                        ¿Olvidaste tu contraseña?
                                    </span>
                                    <p class="forgot-password-text">
                                        Ponte en contacto con tu administrador o proveedor para que te genere una nueva clave de acceso.
                                    </p>
                                </div>
                            </form>
                        </div>
                        <!-- contenedor de registro -->
                        <div id="second-column" class="second-column">
            <form autocomplete="off" action="#" id="form_register" class="registrarse" data-register-url="{{ route('tenant_ecommerce_store_user') }}">
                                <h4 class="title mb-2">Nuevo Registro</h4>
                <div id="msg_register" class="alert alert-danger" role="alert" style="display: none;">
                                    <p id="msg_register_p"></p>
                                </div>
                                <div class="form-group">
                                    <label for="ruc">Tipo de Documento:</label>
                                    <div class="document-selector-container">
                                        <select class="form-select select-ruc-dni" id="selectDocument">
                                            <option value="dni" selected>DNI (8 dígitos)</option>
                                            <option value="ruc">RUC (11 dígitos)</option>
                                        </select>
                                    </div>
                                    <div class="document-input-container">
                                        <input type="number" oninput="inputDocument()" required autocomplete="off" maxlength="11" class="form-control" id="ruc_reg"
                                            placeholder="Ingrese su número de documento" name="ruc">
                                        <span id="counter" class="text-center">0/8</span>
                                    </div>                                    
                                </div>
                                <div class="form-group">
                                    <label for="email">Nombres:</label>
                                    <input type="text" required autocomplete="off" class="form-control" id="name_reg"
                                        placeholder="Enter name" name="name">
                                </div>
                                <div class="form-group">
                                    <label for="email">Correo Electronico:</label>
                                    <input type="email" required autocomplete="off" class="form-control" id="email_reg"
                                        placeholder="Enter email" name="email">
                                </div>
                                <div class="form-group">
                                    <label for="pwd">Contraseña:</label>
                                    <input type="password" required autocomplete="off" class="form-control" id="pwd_reg"
                                        placeholder="Ingrese contraseña" name="pswd">
                                </div>
                                <div class="form-group">
                                    <label for="pwd">Repita la Contraseña:</label>
                                    <input type="password" required autocomplete="off" class="form-control"
                                        id="pwd_repeat_reg" placeholder="Repita contraseña" name="pswd_rpt">
                                </div>
                                <button type="submit" class="btn btn-primary">Registrarse</button>
                            </form>
                        </div>
                        <!-- </div> -->
                        <!-- contenedor overlay -->
                        <div class="terceary-column">
                            <div class="contenedor-iniciar-sesion">
                                <h3>Hola!</h3>
                                <p>Por favor ingrese sus datos para registrarse</p>
                                <button id="iniciar-sesion" class="btn-iniciar-sesion">Iniciar Sesión</button>
                            </div>
                            <div class="contenedor-registro">
                                <h3>Bienvenido!</h3>
                                <p>Por favor ingrese sus credenciales para iniciar sesión</p>
                                <button id="registrarse" class="btn-registrarse">Registrarse!</button>
                            </div>
                        </div>
                    </div>
            </div>
        </div>
    </div>

</div>
<script>
function setDocumentsCounter() {
    let select = document.getElementById('selectDocument');
    let counter = document.getElementById('counter');
    let ruc_reg = document.getElementById('ruc_reg');
    if (select && counter && ruc_reg) {
        // Limpiar el input y remover clases del contador
        ruc_reg.value = '';
        counter.classList.remove('warning', 'success', 'error');
        if (select.value === 'dni') {
            ruc_reg.setAttribute('maxlength', '8');
            ruc_reg.setAttribute('placeholder', 'Ingrese su DNI (8 dígitos)');
            counter.textContent = '0/8';
        } else if (select.value === 'ruc') {
            ruc_reg.setAttribute('maxlength', '11');
            ruc_reg.setAttribute('placeholder', 'Ingrese su RUC (11 dígitos)');
            counter.textContent = '0/11';
        }
    }
}
    
document.addEventListener("DOMContentLoaded", () => {
    const firstColumn = document.getElementById("contenedor-form");

    const btnIniciarSesion = document.getElementById("iniciar-sesion");

    const btnRegistrarse = document.getElementById("registrarse");

    btnIniciarSesion.addEventListener("click", () => {
        firstColumn.classList.remove("active");

    });
    btnRegistrarse.addEventListener("click", () => {
        firstColumn.classList.add("active");

    });
    setDocumentsCounter();
});

function hexToHSL(hex) {
  let r = 0, g = 0, b = 0;

  if (hex.length === 4) {
    r = "0x" + hex[1] + hex[1];
    g = "0x" + hex[2] + hex[2];
    b = "0x" + hex[3] + hex[3];
  } else if (hex.length === 7) {
    r = "0x" + hex[1] + hex[2];
    g = "0x" + hex[3] + hex[4];
    b = "0x" + hex[5] + hex[6];
  }

  r /= 255;
  g /= 255;
  b /= 255;

  const max = Math.max(r, g, b),
        min = Math.min(r, g, b);
  let h, s, l = (max + min) / 2;

  if (max === min) {
    h = s = 0; // gris
  } else {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

    switch (max) {
      case r: h = (g - b) / d + (g < b ? 6 : 0); break;
      case g: h = (b - r) / d + 2; break;
      case b: h = (r - g) / d + 4; break;
    }
    h /= 6;
  }

  return [
    Math.round(h * 360),
    Math.round(s * 100) + "%",
    Math.round(l * 100) + "%"
  ];
}

// Fetch a Laravel
fetch('/ecommerce/color-ecommerce')
  .then(response => response.json())
  .then(data => {
    console.log('Color ecommerce:', data.color);

    // Convertir el HEX recibido a HSL
    const hsl = hexToHSL(data.color);

    // Guardar en variables CSS globales
    document.documentElement.style.setProperty("--primary-h", hsl[0]);
    document.documentElement.style.setProperty("--primary-s", hsl[1]);
    document.documentElement.style.setProperty("--primary-l", hsl[2]);

  })
  .catch(error => console.error('Error obteniendo el color:', error));

</script>



@push('scripts')
<!-- <script type="text/javascript" src="{{ asset('porto-ecommerce/assets/js/cart.js') }}"></script> -->
<script type="text/javascript">
    matchPassword();
    submitLogin();
    submitRegister();
    changeDocument();

    function matchPassword() {
        var password = document.getElementById("pwd_reg"),
            confirm_password = document.getElementById("pwd_repeat_reg");

        function validatePassword() {
            if (password.value != confirm_password.value) {
                confirm_password.setCustomValidity("El Password no coincide.");
            } else {
                confirm_password.setCustomValidity('');
            }
        }

        password.onchange = validatePassword;
        confirm_password.onkeyup = validatePassword;
    }

    function submitLogin() {
        $('#msg_login').hide();

        $('#form_login').submit(function (e) {
            e.preventDefault()
            $.ajax({
                type: "POST",
                dataType: 'JSON',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{route('tenant_ecommerce_login')}}",
                data: $(this).serialize(),
                success: function (data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        $('#msg_login').show();
                    }
                },
                error: function (error_data) {
                    console.log(error_data)
                }
            });
        })

    }

    function submitRegister() {
        $('#msg_register').hide();

        $('#form_register').submit(function (e) {
            e.preventDefault()
            $.ajax({
                type: "POST",
                dataType: 'JSON',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{route('tenant_ecommerce_store_user')}}",
                data: $(this).serialize(),
                success: function (data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        $('#msg_register').show();
                        $('#msg_register_p').text(data.message)
                    }
                },
                error: function (error_data) {
                    console.log(error_data)
                }
            });
        })
    }
    function changeDocument(){
        let select = document.getElementById('selectDocument');

        if (select) {
            select.addEventListener('change', function() {
                setDocumentsCounter();
            });
        }
    }
    function inputDocument(){
        let ruc_reg = document.getElementById('ruc_reg');
        let counter = document.getElementById('counter');

        if (ruc_reg) {
            ruc_reg.addEventListener('input', function() {
                const currentLength = ruc_reg.value.length;
                const maxLength = parseInt(ruc_reg.getAttribute('maxlength'));
                
                // Actualizar el texto del contador
                counter.textContent = `${currentLength}/${maxLength}`;
                
                // Remover clases previas
                counter.classList.remove('warning', 'success', 'error');
                
                // Agregar clase según el estado
                if (currentLength === 0) {
                    // Sin clase adicional para estado inicial
                } else if (currentLength < maxLength * 0.5) {
                    // Menos del 50% - sin clase especial
                } else if (currentLength < maxLength) {
                    counter.classList.add('warning');
                } else if (currentLength === maxLength) {
                    counter.classList.add('success');
                } else {
                    counter.classList.add('error');
                }

                // Limitar la longitud si excede el máximo
                if (currentLength > maxLength) {
                    ruc_reg.value = ruc_reg.value.slice(0, maxLength);
                    counter.textContent = `${maxLength}/${maxLength}`;
                    counter.classList.remove('error');
                    counter.classList.add('success');
                }
            });
        }
    }

</script>
@endpush
