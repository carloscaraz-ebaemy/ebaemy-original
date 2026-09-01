{{-- Cuerpo de la ficha de envio. Lo comparten el formulario publico
     (envio/nuevo) y el modal "Registrar envio" del panel, para que el
     operador cargue exactamente los mismos datos que el cliente: direccion
     con Google Maps, monto a cobrar, modalidad y datos de quien recoge.

     Parametros:
       $p        prefijo de los ids ("pub_" / "adm_"). Hace falta porque los
                 dos formularios pueden convivir y los ids son unicos.
       $context  'public' | 'admin'. El panel muestra campos internos
                 (contenido, peso, notas) que el cliente nunca ve.
--}}
@php
    $p = $p ?? 'pub_';
    $context = $context ?? 'public';
    // El cascader identifica cada formulario por grupo: compartirlo haria que
    // el del panel escribiera en los campos ocultos del publico.
    $ubGroup = $ubGroup ?? rtrim($p, '_');
    $esPublico = $context === 'public';
@endphp
                @csrf
                <input type="hidden" name="delivery_type" id="delivery_type" value="{{ old('delivery_type') }}">

                {{-- ══════════ PASO 0: Tipo de entrega ══════════ --}}
                <div class="step fade-in" data-step="0">
                    <div class="dtype">
                        <button type="button" class="dcard moto" data-type="domicilio">
                            <div class="ic">🏍️</div>
                            <div class="tx"><b>Entrega a domicilio &mdash; LIMA</b><span>Un motorizado lleva tu pedido hasta tu dirección. Ubicación por mapa.</span></div>
                            <div class="go">Elegir</div>
                        </button>
                        <button type="button" class="dcard ag" data-type="agencia">
                            <div class="ic">📦</div>
                            <div class="tx"><b>Envío por agencia &mdash; PROVINCIA</b><span>Enviamos tu pedido por agencia de transporte a tu provincia.</span></div>
                            <div class="go">Elegir</div>
                        </button>
                        <button type="button" class="dcard tienda" data-type="tienda">
                            <div class="ic">🏬</div>
                            <div class="tx"><b>Recojo en tienda</b><span>Preparamos tu pedido y te avisamos cuando esté listo para recogerlo.</span></div>
                            <div class="go">Elegir</div>
                        </button>
                    </div>
                </div>

                {{-- ══════════ PASO 1: Datos ══════════ --}}
                <div class="step" data-step="1" hidden>
                    <span class="tag moto" id="tag-moto" hidden>🏍️ Entrega a domicilio · LIMA</span>
                    <span class="tag ag" id="tag-ag" hidden>📦 Envío por agencia · PROVINCIA</span>
                    <span class="tag tienda" id="tag-tienda" hidden>🏬 Recojo en tienda</span>

                    <label>Documento</label>
                    <div class="doc-types">
                        @foreach(\App\Models\Tenant\ShippingRequest::DOC_TYPES as $dv => $dl)
                            <label class="doc-opt">
                                <input type="radio" name="document_type" value="{{ $dv }}" {{ old('document_type', 'dni') === $dv ? 'checked' : '' }}>
                                <span>{{ $dl }}</span>
                            </label>
                        @endforeach
                    </div>
                    <input type="text" name="dni" id="{{ $p }}dni" value="{{ old('dni') }}"
                           maxlength="11" inputmode="numeric" autocomplete="off" placeholder="8 dígitos">
                    <small class="js-doc-status hint"></small>
                    <div id="clientFound" class="found" hidden>
                        <div class="t">Cliente encontrado</div>
                        <div class="n" id="cf_name">—</div>
                        <div style="font-size:13px;color:var(--muted);margin-bottom:8px;">¿Deseas utilizar esta información?</div>
                        <div class="acts">
                            <button type="button" class="use" id="cf_use">Usar datos</button>
                            <button type="button" class="new" id="cf_new">Ingresar nuevos</button>
                        </div>
                    </div>

                    <label class="req">Nombre completo</label>
                    <input type="text" name="full_name" id="{{ $p }}full_name" value="{{ old('full_name') }}" required maxlength="160">
                    <small class="hint" id="{{ $p }}name_hint">🔒 Se completa automáticamente al ingresar tu documento.</small>

                    <label class="req">Celular (WhatsApp)</label>
                    <input type="tel" name="phone" id="{{ $p }}phone" value="{{ old('phone') }}" required maxlength="9" inputmode="numeric" placeholder="999 999 999" class="js-phone-pe">
                    <small class="js-phone-err" style="color:#dc2626;display:block;font-size:12px;margin-top:2px;"></small>

                    {{-- Cliente EMPRESA (RUC): la agencia no le entrega el paquete a
                         una razón social, pide el DNI y el nombre de una persona.
                         Este bloque aparece solo cuando el documento tiene 11
                         dígitos, que es como se distingue el RUC en este formulario. --}}
                    <div id="{{ $p }}pickup_box" hidden
                         style="margin-top:14px;padding:12px 14px;border:1px solid #bfdbfe;background:#f8fbff;border-radius:12px;">
                        <div style="font-weight:700;font-size:14px;color:#1e3a8a;margin-bottom:2px;">
                            🧾 ¿Quién recoge el paquete?
                        </div>
                        <div style="font-size:12.5px;color:#475569;margin-bottom:10px;">
                            Registraste un <b>RUC</b>. La agencia de transporte entrega solo a una
                            <b>persona con DNI</b>, así que necesitamos sus datos.
                        </div>

                        <label class="req">Nombre de quien recoge</label>
                        <input type="text" name="pickup_person_name" id="{{ $p }}pickup_name"
                               value="{{ old('pickup_person_name') }}" maxlength="160"
                               placeholder="Nombre y apellidos de la persona">

                        <label class="req">DNI de quien recoge</label>
                        <input type="text" name="pickup_person_dni" id="{{ $p }}pickup_dni"
                               value="{{ old('pickup_person_dni') }}" maxlength="20"
                               inputmode="numeric" placeholder="8 dígitos">

                        <label>Celular de quien recoge <span style="color:#94a3b8;font-weight:400;">(opcional)</span></label>
                        <input type="tel" name="pickup_person_phone" id="{{ $p }}pickup_phone"
                               value="{{ old('pickup_person_phone') }}" maxlength="9"
                               inputmode="numeric" placeholder="999 999 999">

                        <small class="hint" id="{{ $p }}pickup_err" hidden style="color:#dc2626;"></small>
                    </div>

                    {{-- ─────── Rama DOMICILIO (Google Maps) ─────── --}}
                    <div class="branch-domicilio" hidden>
                        {{-- Un solo campo de dirección: es el buscador de Google Y el
                             dato que se guarda. El cliente puede corregirlo a mano
                             (número de puerta, dpto) sin perder el pin del mapa. --}}
                        <label class="req">Dirección de entrega</label>
                        <div class="map-search">
                            <input type="text" name="shipping_destination" id="{{ $p }}addr_domicilio" value="{{ old('shipping_destination') }}"
                                   maxlength="500" autocomplete="off" placeholder="Ej. Av. Arequipa 1234, Miraflores">
                        </div>
                        <small class="hint">Escribe y elige tu dirección de la lista; luego ajusta el marcador si hace falta.</small>
                        @if(!empty($mapsKey))
                            <div id="shipMap"></div>
                            <div class="map-note">Arrastra el marcador para ajustar la ubicación exacta.</div>
                            <div class="map-picked" id="mapPicked">
                                <div class="c" id="mp_city">—</div>
                            </div>
                        @else
                            <div class="map-off">⚠️ El mapa no está disponible por ahora. Escribe tu dirección completa y una referencia clara.</div>
                        @endif

                        {{-- Campos ocultos que llena Google Maps --}}
                        <input type="hidden" name="latitude" id="{{ $p }}lat" value="{{ old('latitude') }}">
                        <input type="hidden" name="longitude" id="{{ $p }}lng" value="{{ old('longitude') }}">
                        <input type="hidden" name="google_place_id" id="{{ $p }}place_id" value="{{ old('google_place_id') }}">
                        <input type="hidden" name="formatted_address" id="{{ $p }}formatted" value="{{ old('formatted_address') }}">
                        <input type="hidden" name="google_maps_url" id="{{ $p }}maps_url" value="{{ old('google_maps_url') }}">
                        <input type="hidden" name="destination_city" id="{{ $p }}city_domicilio" value="{{ old('destination_city') }}">
                        <input type="hidden" name="distance_km" id="{{ $p }}dist_km" value="{{ old('distance_km') }}">
                        <input type="hidden" name="distance_text" id="{{ $p }}dist_text" value="{{ old('distance_text') }}">
                        <input type="hidden" name="duration_text" id="{{ $p }}dur_text" value="{{ old('duration_text') }}">
                        <input type="hidden" name="delivery_price" id="{{ $p }}delivery_price" value="{{ old('delivery_price') }}">
                        <div id="priceBox" style="display:none;margin-top:10px;padding:12px 14px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;font-size:14px;color:#065f46;">
                            💵 Costo <b>aproximado</b> del servicio de envío: <b style="font-size:16px;">S/ <span id="priceText">—</span></b>
                            <div style="font-size:11.5px;color:#059669;margin-top:2px;">Es un <b>precio referencial</b> según la distancia. El costo final puede variar y se confirma al coordinar la entrega.</div>
                        </div>

                        <label>Referencia e indicaciones</label>
                        <input type="text" name="reference" id="{{ $p }}reference_dom" value="{{ old('reference') }}" maxlength="255" placeholder="Dpto 302, portón negro, frente al parque…">
                    </div>

                    {{-- ─────── Rama AGENCIA (ubigeo) ─────── --}}
                    <div class="branch-agencia" hidden>
                        <label class="req">Destino (Departamento / Provincia / Distrito)</label>
                        <div class="ubigeo-field" data-ubigeo-group="{{ $ubGroup }}">
                            <div class="ubigeo-display" tabindex="0">Seleccionar departamento / provincia / distrito…</div>
                            <input type="hidden" name="department_id" data-ub="department">
                            <input type="hidden" name="province_id"   data-ub="province">
                            <input type="hidden" name="district_id"   data-ub="district">
                            <div class="ubigeo-pop" hidden>
                                <div class="ubigeo-col" data-col="dep"></div>
                                <div class="ubigeo-col" data-col="prov"></div>
                                <div class="ubigeo-col" data-col="dist"></div>
                            </div>
                        </div>

                        <label>Agencia de transporte <span style="color:#dc2626;">*</span></label>
                        <div class="agency-field">
                            <select class="agency-select">
                                <option value="">— Selecciona —</option>
                                @foreach(\App\Models\Tenant\ShippingRequest::AGENCIES as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
                                <option value="__otra__">Otra…</option>
                            </select>
                            <input type="text" class="agency-input" name="shipping_agency" id="{{ $p }}shipping_agency" value="{{ old('shipping_agency') }}" maxlength="120" placeholder="Nombre de la agencia" style="display:none;margin-top:8px;">
                        </div>
                        <small class="hint" id="{{ $p }}agency_err" hidden style="color:#dc2626;">
                            Elige la agencia de transporte: es obligatoria para los envíos a provincia.
                        </small>

                        {{-- Las agencias tienen varias oficinas por ciudad (Shalom
                             tiene nombre por local): saber en cuál recoge el cliente
                             es más útil que una referencia genérica. Reutiliza la
                             columna `reference`, que en agencia significa "oficina". --}}
                        <label id="{{ $p }}office_label">Oficina donde recogerás <span style="color:#94a3b8;font-weight:400;">(opcional)</span></label>
                        <input type="text" name="reference" id="{{ $p }}reference_ag" value="{{ old('reference') }}" maxlength="255" placeholder="Ej. Terminal Terrestre, Av. Aviación 123…">
                        <small class="hint" id="{{ $p }}office_hint">Si ya sabes en qué local vas a recoger, escríbelo. Si no, déjalo en blanco: la agencia te lo indicará.</small>
                        <small class="hint" id="{{ $p }}dest_err" hidden style="color:#dc2626;"></small>

                        {{-- El paquete normalmente solo viaja hasta la agencia: el
                             cliente lo recoge ahí y su dirección no la usa nadie.
                             Solo pedimos dirección si la agencia hace reparto. --}}
                        <label class="chk chk-inline">
                            <input type="checkbox" id="{{ $p }}ag_home" {{ old('delivery_type') === 'agencia' && old('shipping_destination') ? 'checked' : '' }}>
                            <span>La agencia lleva el paquete hasta mi domicilio</span>
                        </label>
                        <div id="agHomeWrap" hidden>
                            <label>Dirección de reparto</label>
                            <input type="text" name="shipping_destination" id="{{ $p }}addr_agencia" value="{{ old('shipping_destination') }}" maxlength="255" placeholder="Av./Jr./Calle y número">
                        </div>

                        {{-- ── Resumen de costos ────────────────────────────────
                             Antes eran DOS recuadros sueltos: uno advertia del
                             recargo a domicilio y otro informaba la tarifa
                             tienda→agencia. Decian cosas que se pisaban ("el
                             flete se paga aparte" en los dos) y el cliente tenia
                             que armar el cuadro solo.

                             Ahora es UNA tabla con el recorrido del paquete en
                             orden, y en cada tramo QUIEN cobra. Esa es la
                             confusion real: el cliente no distingue lo que nos
                             paga a nosotros de lo que paga en la agencia. --}}
                        @php
                            $tramoTienda = !empty($agencyFree)
                                ? 'GRATIS'
                                : ((!empty($agencyShow) && !empty($agencyFee) && $agencyFee > 0)
                                    ? 'S/ ' . number_format($agencyFee, 2)
                                    : null);
                        @endphp
                        <div class="cost-box">
                            <div class="cost-box__t">💰 Cómo se cobra tu envío</div>

                            @if($tramoTienda)
                                <div class="cost-row">
                                    <div class="cost-row__l">
                                        <b>1. De nuestra tienda a la agencia</b>
                                        <small>Lo cobramos nosotros, al registrar el envío.</small>
                                    </div>
                                    <div class="cost-row__v {{ !empty($agencyFree) ? 'is-free' : '' }}">{{ $tramoTienda }}</div>
                                </div>
                            @endif

                            <div class="cost-row">
                                <div class="cost-row__l">
                                    <b>{{ $tramoTienda ? '2.' : '1.' }} De la agencia a tu ciudad</b>
                                    <small>Lo cobra la agencia. Depende del destino y del peso.</small>
                                </div>
                                <div class="cost-row__v is-soft">Lo pagas allá</div>
                            </div>

                            {{-- Solo aparece si pidio reparto: si no, es ruido. --}}
                            <div class="cost-row cost-row--extra" id="costHome" hidden>
                                <div class="cost-row__l">
                                    <b>{{ $tramoTienda ? '3.' : '2.' }} De la agencia hasta tu puerta</b>
                                    <small>Cobro <b>adicional</b> de la agencia, bastante mayor que
                                    recoger en su oficina. Si prefieres pagar menos, desmarca la opción
                                    de reparto a domicilio.</small>
                                </div>
                                <div class="cost-row__v is-warn">Costo extra</div>
                            </div>
                        </div>
                    </div>

                    {{-- ─────── Rama RECOJO EN TIENDA ───────
                         No viaja: sin dirección, sin agencia, sin ubigeo y sin
                         cobro de envío. Solo hace falta saber a quién se le
                         entrega y, si acaso, cuándo piensa pasar. --}}
                    <div class="branch-tienda" hidden>
                        <div class="pickup-box">
                            <div class="pickup-box__t">🏬 Recogerás tu pedido en la tienda</div>
                            @if(!empty($storeAddress))
                                <div class="pickup-box__addr">{{ $storeAddress }}</div>
                            @endif
                            <div class="pickup-box__s">
                                Te avisamos por WhatsApp apenas esté listo. Acércate con tu documento de identidad.
                            </div>
                        </div>

                        <label>¿Cuándo piensas pasar? (opcional)</label>
                        <input type="text" name="reference" id="{{ $p }}reference_tienda" value="{{ old('reference') }}"
                               maxlength="255" placeholder="Ej. mañana por la tarde, el sábado…">
                        <small class="hint">Nos ayuda a tenerlo listo a tiempo. No es un compromiso.</small>
                    </div>

                    <div class="row-btns">
                        <button type="button" class="btn btn-ghost" id="backStep0">← Volver</button>
                        <button type="button" class="btn" id="toStep2">Continuar →</button>
                    </div>
                </div>

                {{-- ══════════ PASO 2: Confirmación ══════════ --}}
                <div class="step" data-step="2" hidden>
                    <div class="conf">
                        <div class="h" id="c_type_h">Resumen</div>
                        <div class="rows">
                            <div class="r"><span class="k">Tipo de entrega</span><span class="v" id="c_type">—</span></div>
                            <div class="r"><span class="k">Nombre</span><span class="v" id="c_name">—</span></div>
                            <div class="r"><span class="k">Documento</span><span class="v" id="c_doc">—</span></div>
                            <div class="r"><span class="k">Celular</span><span class="v" id="c_phone">—</span></div>
                            <div class="r" id="r_pickup"><span class="k">Recoge</span><span class="v" id="c_pickup">—</span></div>
                            <div class="r" id="r_ubigeo"><span class="k">Ubigeo</span><span class="v" id="c_ubigeo">—</span></div>
                            <div class="r"><span class="k">Dirección</span><span class="v" id="c_dir">—</span></div>
                            <div class="r"><span class="k" id="k_ref">Referencia</span><span class="v" id="c_ref">—</span></div>
                            <div class="r" id="r_ag"><span class="k">Agencia</span><span class="v" id="c_ag">—</span></div>
                            <div class="r" id="r_coords"><span class="k">Ubicación GPS</span><span class="v" id="c_coords">—</span></div>
                            <div class="r" id="r_price"><span class="k">Costo aprox. de envío</span><span class="v" id="c_price" style="color:#059669;">—</span></div>
                        </div>
                        {{-- Se repite aca a proposito: el paso 1 se llena rapido
                             y este es el momento en que el cliente confirma. --}}
                        <div class="conf-warn" id="r_home_extra" hidden>
                            <span>🏠</span>
                            <div>Pediste <b>reparto a domicilio</b>: la agencia te cobrará un
                                <b>adicional</b> al entregarte, además del flete a tu ciudad.</div>
                        </div>
                    </div>

                    {{-- ── Tiempos de despacho: recuadro visible, uno por
                         modalidad. Antes iba en letra chica dentro de las
                         condiciones y la gente no lo leía. El JS muestra el
                         que corresponde a la modalidad elegida. --}}
                    <div class="eta eta--prov" id="eta-agencia" hidden>
                        <div class="eta__t">⚠️ Importante</div>
                        <div class="eta__b">
                            Los pedidos con destino a <strong>provincia</strong> se preparan y despachan
                            <strong>entre 2 y {{ max(2, (int) ($maxDays ?? 4)) }} días hábiles</strong>, según la
                            disponibilidad de materiales de embalaje y el proceso logístico.
                            <div class="eta__s">Agradecemos su comprensión.</div>
                        </div>
                    </div>

                    <div class="eta eta--lima" id="eta-domicilio" hidden>
                        <div class="eta__t">🚚 Entregas en Lima</div>
                        <div class="eta__b">
                            Los pedidos para Lima tienen <strong>prioridad logística</strong> y normalmente se
                            preparan para despacho <strong>el mismo día o al siguiente día hábil</strong>.
                        </div>
                    </div>

                    <div class="eta eta--tienda" id="eta-tienda" hidden>
                        <div class="eta__t">🏬 Recojo en tienda</div>
                        <div class="eta__b">
                            Preparamos tu pedido cuanto antes y te avisamos por WhatsApp
                            <strong>apenas esté listo para recoger</strong>.
                        </div>
                    </div>

                    {{-- Términos y sorteo los declara el CLIENTE. En el panel
                         los carga un operador: no firma términos en nombre de
                         nadie ni inscribe a un sorteo sin su consentimiento. --}}
                    @if($esPublico)
                    <div class="terms-box">
                        <label class="chk">
                            <input type="checkbox" name="accepted_terms" id="{{ $p }}terms" value="1" required>
                            <span>Confirmo que todos los datos ingresados son correctos.</span>
                        </label>
                        <div class="cond">
                            Autorizo el uso de mis datos únicamente para gestionar el envío de mi pedido.<br><br>
                            El tiempo de despacho puede variar dependiendo de:
                            <ul>
                                <li>Disponibilidad del producto.</li>
                                <li>Disponibilidad de materiales para embalaje.</li>
                                <li>Horarios de despacho.</li>
                                <li>Agencia de transporte o disponibilidad del motorizado.</li>
                                <li>Eventos externos que puedan retrasar la entrega.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- ── Participación en el sorteo, dentro del mismo flujo ──
                         Solo aparece si hay una campaña vigente. Al marcar la
                         casilla el cliente queda inscrito al confirmar, sin
                         necesidad de recibir ningún enlace. --}}
                    @if(!empty($raffle))
                        <div class="rfz">
                            <div class="rfz__head">
                                @if($raffle->prizeImageUrl('small'))
                                    <img class="rfz__img" src="{{ $raffle->prizeImageUrl('small') }}" alt="{{ $raffle->prize_name }}">
                                @endif
                                <div class="rfz__tx">
                                    <div class="rfz__tag">🎁 Sorteo vigente</div>
                                    <div class="rfz__name">{{ $raffle->name }}</div>
                                    @if($raffle->prize_name)
                                        <div class="rfz__prize">Premio: <strong>{{ $raffle->prize_name }}</strong></div>
                                    @endif
                                </div>
                            </div>

                            @if($raffle->description)
                                <div class="rfz__desc">{{ \Illuminate\Support\Str::limit($raffle->description, 180) }}</div>
                            @endif

                            <div class="rfz__dates">
                                @if($raffle->registration_closes_at)
                                    Participa hasta el <strong>{{ $raffle->registration_closes_at->format('d/m/Y') }}</strong>
                                @endif
                                @if($raffle->draw_at)
                                    · Sorteo el <strong>{{ $raffle->draw_at->format('d/m/Y') }}</strong>
                                @endif
                            </div>

                            @if($raffle->terms)
                                <details class="rfz__terms">
                                    <summary>Ver bases y condiciones</summary>
                                    <div>{{ $raffle->terms }}</div>
                                </details>
                            @endif

                            <label class="chk rfz__chk">
                                <input type="checkbox" name="join_raffle" id="{{ $p }}join_raffle" value="1">
                                <span>
                                    Deseo participar en el sorteo y autorizo el uso de los datos de este pedido
                                    <strong>exclusivamente para esta campaña</strong>, de acuerdo con las bases y condiciones.
                                </span>
                            </label>
                            <div class="rfz__cond">
                                Tu participación queda confirmada cuando validemos el pago de este pedido.
                            </div>
                        </div>
                    @endif
                    @endif

                    <div class="row-btns">
                        <button type="button" class="btn btn-ghost" id="backStep1">← Volver</button>
                        <button type="submit" class="btn" id="confirmBtn">Confirmar registro</button>
                    </div>
                </div>
