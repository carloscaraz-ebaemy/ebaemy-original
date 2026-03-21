{{-- ── NEWSLETTER BANNER ───────────────────────────────── --}}
<div class="ec-newsletter-bar">
    <div class="container">
        <div class="ec-newsletter-bar__inner">
            <div class="ec-newsletter-bar__text">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                <div>
                    <strong>¡Suscríbete a nuestras novedades!</strong>
                    <span>Recibe ofertas exclusivas y nuevos productos en tu email.</span>
                </div>
            </div>
            <form class="ec-newsletter-bar__form" onsubmit="ecNewsletterSubmit(event, this)">
                @csrf
                <input type="email"
                       placeholder="Tu correo electrónico"
                       required
                       class="ec-newsletter-bar__input"
                       aria-label="Email para newsletter">
                <button type="submit" class="ec-newsletter-bar__btn">Suscribirme</button>
            </form>
        </div>
    </div>
</div>

<div class="footer-middle ec-footer-middle">
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
                    <h4 class="widget-title">Navegación rápida</h4>
                    <ul class="links ec-footer-links">
                        <li><a href="{{ route('tenant.ecommerce.index') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            Inicio
                        </a></li>
                        <li><a href="{{ route('tenant_detail_cart') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            Mi carrito
                        </a></li>
                        <li><a href="{{ route('tenant.ecommerce.wishlist') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                            Mis favoritos
                        </a></li>
                        <li><a href="{{ route('ecommerce.tracking') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            Rastrear pedido
                        </a></li>
                        @guest
                        <li><a href="{{ route('tenant_ecommerce_login') }}" class="login-link">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                            Iniciar sesión
                        </a></li>
                        @else
                        <li>
                            <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                Cerrar sesión
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">@csrf</form>
                        </li>
                        @endguest
                    </ul>
                </div>
            </div>
            <div class="col-md-3">
                <div class="widget">
                    <h4 class="widget-title">Términos y Condiciones</h4>
                    <div class="row">
                        <div class="col-12">
                            <ul class="ec-footer-links">
                                <li><a href="{{ route("tenant.politica_privacidad") }}">Políticas de privacidad</a></li>
                                <li><a href="{{ route("tenant.terminos_condiciones") }}">Términos y condiciones del uso del sitio</a></li>
                                <li><a href="{{ route("tenant.cambios_devolucion") }}">Cambios y Devoluciones</a></li>
                                <li><a href="{{ route("tenant.politica_envio") }}">Políticas de envío</a></li>
                                @if (Route::has('tenant.libro_reclamaciones'))
                                <li>
                                    <a href="{{ route('tenant.libro_reclamaciones') }}" class="ec-footer-links__reclamaciones">
                                        <i class="fas fa-book-open" aria-hidden="true"></i>
                                        <span>Libro de Reclamaciones</span>
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

{{-- Toast container para notificaciones "agregado al carrito" (gestionado por cart.js) --}}
<div id="ec-cart-toast-container"></div>

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
                {{ csrf_field() }}
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

                                <div class="ec-social-divider">
                                    <span>o continúa con</span>
                                </div>
                                <a href="{{ route('ecommerce.google.redirect') }}" class="ec-btn-google">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                    </svg>
                                    Continuar con Google
                                </a>

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
                {{ csrf_field() }}
                                <h4 class="title mb-2">Crear cuenta</h4>
                                <p class="ec-register-subtitle">Solo necesitas tu correo y una contraseña.</p>
                <div id="msg_register" class="alert alert-danger" role="alert" style="display: none;">
                                    <p id="msg_register_p"></p>
                                </div>
                                <div class="form-group">
                                    <label for="email_reg">Correo electrónico <span class="text-danger">*</span></label>
                                    <input type="email" required autocomplete="off" class="form-control" id="email_reg"
                                        placeholder="tucorreo@gmail.com" name="email">
                                </div>
                                <div class="form-group">
                                    <label for="pwd_reg">Contraseña <span class="text-danger">*</span></label>
                                    <input type="password" required autocomplete="off" class="form-control" id="pwd_reg"
                                        placeholder="Mínimo 6 caracteres" name="pswd">
                                </div>
                                <div class="form-group">
                                    <label for="pwd_repeat_reg">Repite la contraseña <span class="text-danger">*</span></label>
                                    <input type="password" required autocomplete="off" class="form-control"
                                        id="pwd_repeat_reg" placeholder="Repite la contraseña" name="pswd_rpt">
                                </div>
                                {{-- Nombre opcional --}}
                                <div class="form-group">
                                    <label for="name_reg">Nombre <span class="ec-optional-label">(opcional)</span></label>
                                    <input type="text" autocomplete="off" class="form-control" id="name_reg"
                                        placeholder="¿Cómo te llamamos?" name="name">
                                </div>
                                <button type="submit" class="btn btn-primary">Registrarse</button>

                                <div class="ec-social-divider">
                                    <span>o regístrate con</span>
                                </div>
                                <a href="{{ route('ecommerce.google.redirect') }}" class="ec-btn-google">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                    </svg>
                                    Continuar con Google
                                </a>
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

// Color primario ya inyectado server-side en <head> (master.blade.php) — sin flash

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
            var csrfToken = $('meta[name="csrf-token"]').attr('content');
            $.ajax({
                type: "POST",
                dataType: 'JSON',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
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
                error: function (xhr) {
                    if (xhr.status === 419) {
                        setTimeout(function () { location.reload(); }, 500);
                    } else {
                        $('#msg_login').show();
                    }
                }
            });
        })

    }

    function submitRegister() {
        $('#msg_register').hide();

        $('#form_register').submit(function (e) {
            e.preventDefault()
            // Refresh token from meta tag before each request
            var csrfToken = $('meta[name="csrf-token"]').attr('content');
            $.ajax({
                type: "POST",
                dataType: 'JSON',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
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
                error: function (xhr) {
                    $('#msg_register').show();
                    if (xhr.status === 419) {
                        $('#msg_register_p').text('Sesión expirada. Recarga la página e intenta de nuevo.');
                        // Reload after 2s so the CSRF token is refreshed
                        setTimeout(function () { location.reload(); }, 2000);
                    } else {
                        $('#msg_register_p').text('Error al registrar. Intenta de nuevo.');
                    }
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

<script>
function ecNewsletterSubmit(e, form) {
    e.preventDefault();
    var input = form.querySelector('input[type="email"]');
    var btn   = form.querySelector('button');
    var email = input.value.trim();
    if (!email) return;
    btn.textContent = '...';
    btn.disabled = true;
    // Simple localStorage opt-in (sin backend necesario para MVP)
    var list = JSON.parse(localStorage.getItem('ec_newsletter') || '[]');
    if (list.indexOf(email) === -1) { list.push(email); localStorage.setItem('ec_newsletter', JSON.stringify(list)); }
    setTimeout(function () {
        input.value = '';
        btn.textContent = '¡Listo!';
        btn.style.background = '#22c55e';
        setTimeout(function () { btn.textContent = 'Suscribirme'; btn.style.background = ''; btn.disabled = false; }, 3000);
    }, 600);
}
</script>
@endpush
