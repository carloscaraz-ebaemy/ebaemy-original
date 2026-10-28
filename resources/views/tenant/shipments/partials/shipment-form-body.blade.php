{{-- Cuerpo de la ficha de envio. Lo comparten el formulario publico
     (/R3GISTRO) y el modal "Registrar envio" del panel, para que el
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
                @php
                    // Lo que el cliente necesita para ELEGIR: cuándo llega y
                    // cuánto le cuesta, en la misma tarjeta. Antes había que
                    // elegir a ciegas y enterarse en el último paso.
                    $chev = '<svg class="chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6l-6 6"/></svg>';
                    $clock = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 7v5l3 3"/></svg>';
                    $agCost = !empty($agencyFree) ? 'Gratis hasta la agencia'
                        : ((!empty($agencyShow) && !empty($agencyFee) && $agencyFee > 0) ? 'S/ ' . number_format($agencyFee, 2) . ' + flete en destino' : 'Flete se paga en destino');
                @endphp
                <div class="step fade-in" data-step="0">
                    <div class="step-head">
                        <h2 class="step-h">¿Cómo quieres recibir tu pedido?</h2>
                        <p class="step-sub">Elige una opción. Después te pedimos tus datos.</p>
                    </div>
                    <div class="dtype">
                        <button type="button" class="dcard moto" data-type="domicilio">
                            <div class="ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 16m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M19 16m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M7.5 14h5l4 -4h-10.5m1.5 4l4 -4"/><path d="M13 6h2l1.5 3l2 4"/></svg></div>
                            <div class="tx"><b>A domicilio en Lima</b><span>Un motorizado lo lleva hasta tu puerta.</span></div>
                            {!! $chev !!}
                            <div class="meta">
                                @if(!empty($eta['domicilio']))<span class="chip">{!! $clock !!}{{ $eta['domicilio']['short'] }}</span>@endif
                                <span class="chip">{{ !empty($pricePerKm) ? 'Costo según distancia' : 'Costo al coordinar' }}</span>
                            </div>
                            <div class="go">Elegir</div>
                        </button>
                        <button type="button" class="dcard ag" data-type="agencia">
                            <div class="ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5"/><path d="M12 12l8 -4.5"/><path d="M12 12l0 9"/><path d="M12 12l-8 -4.5"/><path d="M16 5.25l-8 4.5"/></svg></div>
                            <div class="tx"><b>Por agencia a provincia</b><span>Lo recoges en la oficina de tu ciudad.</span></div>
                            {!! $chev !!}
                            <div class="meta">
                                @if(!empty($eta['agencia']))<span class="chip">{!! $clock !!}{{ $eta['agencia']['short'] }}</span>@endif
                                <span class="chip {{ !empty($agencyFree) ? 'is-ok' : '' }}">{{ $agCost }}</span>
                            </div>
                            <div class="go">Elegir</div>
                        </button>
                        <button type="button" class="dcard tienda" data-type="tienda">
                            <div class="ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21l18 0"/><path d="M3 7v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1h-18l2 -4h14l2 4"/><path d="M5 21l0 -10.15"/><path d="M19 21l0 -10.15"/><path d="M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4"/></svg></div>
                            <div class="tx"><b>Recojo en tienda</b><span>{{ !empty($storeAddress) ? \Illuminate\Support\Str::limit($storeAddress, 60) : 'Pasas a recogerlo cuando esté listo.' }}</span></div>
                            {!! $chev !!}
                            <div class="meta">
                                @if(!empty($eta['tienda']))<span class="chip">{!! $clock !!}{{ $eta['tienda']['short'] }}</span>@endif
                                <span class="chip is-ok">Sin costo de envío</span>
                            </div>
                            <div class="go">Elegir</div>
                        </button>
                    </div>
                </div>

                {{-- ══════════ PASO 1: Datos ══════════ --}}
                <div class="step" data-step="1" hidden>
                    <span class="tag moto" id="tag-moto" hidden>A domicilio en Lima</span>
                    <span class="tag ag" id="tag-ag" hidden>Por agencia a provincia</span>
                    <span class="tag tienda" id="tag-tienda" hidden>Recojo en tienda</span>

                    <div class="sec-h" style="margin-top:6px"><i>1</i>¿Quién recibe el pedido?</div>
                    <p class="step-sub" style="margin:2px 0 14px 36px">Con tu documento completamos tu nombre.</p>

                    <div class="err-sum" id="{{ $p }}errsum_datos" hidden>
                        <div class="err-sum__t">Revisa tu información</div>
                        <ul class="err-sum__l"></ul>
                    </div>

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
                    {{-- La salida del callejon sin salida.

                         Con DNI/RUC el nombre llega de RENIEC/SUNAT y el campo va
                         bloqueado, que esta bien mientras la consulta responda.
                         Cuando NO responde --documento que no figura, servicio
                         caido, sin saldo-- el campo se quedaba vacio, bloqueado y
                         required: el cliente no podia escribir su nombre ni
                         continuar, y se quedaba clavado en el paso 2 de 5 sin que
                         nada le dijera como salir.

                         Ahora siempre puede escribirlo a mano. El enlace esta
                         desde el principio, no solo tras el fallo: quien ya sabe
                         que su documento no figura no tiene que provocar el error
                         para poder avanzar. --}}
                    <small class="hint" id="{{ $p }}name_hint">
                        🔒 Se completa automáticamente al ingresar tu documento.
                        <button type="button" class="js-name-manual" id="{{ $p }}name_manual">Escribirlo a mano</button>
                    </small>

                    <label class="req">Celular (WhatsApp)</label>
                    <input type="tel" name="phone" id="{{ $p }}phone" value="{{ old('phone') }}" required maxlength="9" inputmode="numeric" placeholder="999 999 999" class="js-phone-pe">
                    <small class="js-phone-err" style="color:#dc2626;display:block;font-size:12px;margin-top:2px;"></small>

                    {{-- Segundo contacto DE ESTA ENTREGA: el vecino, el familiar,
                         el numero al que llamar cuando el primero no contesta.
                         No es un dato del cliente —cambia con cada envio—, por eso
                         vive en el envio y no en su ficha. --}}
                    <label>Teléfono adicional <span style="font-weight:400;color:#64748b;">(opcional)</span></label>
                    <input type="tel" name="alternate_phone" id="{{ $p }}alternate_phone"
                           value="{{ old('alternate_phone') }}" maxlength="9" inputmode="numeric"
                           placeholder="999 999 999">
                    <small class="hint">A quién llamar si el primero no contesta.</small>

                    {{-- Cliente EMPRESA (RUC): la agencia no le entrega el paquete a
                         una razón social, pide el DNI y el nombre de una persona.
                         Este bloque aparece solo cuando el documento tiene 11
                         dígitos, que es como se distingue el RUC en este formulario. --}}
                    <div id="{{ $p }}pickup_box" hidden
                         style="margin-top:16px;padding:14px 16px;background:var(--accent-soft);border-radius:var(--r);">
                        <div style="font-weight:750;font-size:15px;margin-bottom:2px;">
                            ¿Quién recoge el paquete?
                        </div>
                        <div style="font-size:13.5px;color:var(--ink-2);margin-bottom:6px;">
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

                    <div class="row-btns">
                        {{-- Sin botones: este bloque ya no termina el paso. La entrega
                             va debajo, en la misma pantalla, y las dos acciones
                             --volver y continuar-- viven al final de todo. Dejar
                             aqui un «Volver» partia la pantalla por la mitad. --}}
                    </div>
                </div>

                {{-- ══════════ PASO 2: A donde llega ══════════
                     Antes vivia dentro del paso de datos, asi que el cliente
                     recibia de golpe documento, nombre, celular, direccion,
                     mapa, agencia y costos en una sola pantalla. Partido en
                     dos, cada paso cabe en un movil sin scroll infinito. --}}
                <div class="step" data-step="2" hidden>
                    {{-- Mismo paso que «Tus datos» (se ven juntos): es la segunda
                         sección, no una pantalla nueva. --}}
                    <div class="sec-h"><i>2</i><span class="js-dest-h">¿A dónde llega?</span></div>
                    <p class="step-sub" id="{{ $p }}dest_sub" style="margin:2px 0 14px 36px">Completa los datos de la entrega.</p>

                    {{-- Resumen de lo que falta. Se llena desde el JS al pulsar
                         Continuar: cada linea lleva al campo que la origina. --}}
                    <div class="err-sum" id="{{ $p }}errsum_dest" hidden>
                        <div class="err-sum__t">Revisa tu información</div>
                        <ul class="err-sum__l"></ul>
                    </div>

                    {{-- ─────── Rama DOMICILIO (Google Maps) ─────── --}}
                    <div class="branch-domicilio" hidden>
                        {{-- Un solo campo de dirección: es el buscador de Google Y el
                             dato que se guarda. El cliente puede corregirlo a mano
                             (número de puerta, dpto) sin perder el pin del mapa. --}}
                        <label class="req">Dirección de entrega</label>
                        @if(!empty($mapsKey))
                            {{-- En el celular lo más rápido y exacto es el GPS: un toque
                                 y el pin, la dirección y el costo salen solos. --}}
                            <button type="button" class="geo-btn" id="geoBtn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="7"/></svg>
                                <span>Usar mi ubicación actual</span>
                            </button>
                            <div class="geo-msg" id="geoMsg" hidden></div>
                            <div class="geo-or">o escribe tu dirección</div>
                        @endif
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
                        <small class="hint">Ayuda al motorizado a encontrarte: frente al parque principal, al costado de la farmacia, portón verde…</small>
                    </div>

                    {{-- ─────── Rama AGENCIA (ubigeo) ─────── --}}
                    <div class="branch-agencia" hidden>
                        <label class="req">¿A dónde enviamos el paquete?</label>
                        {{-- Modo «en línea»: el campo ES el buscador y los resultados
                             empujan el contenido en vez de taparlo. El panel usa
                             el mismo widget en modo popup (sin el atributo). --}}
                        <div class="ubigeo-field" data-ubigeo-group="{{ $ubGroup }}" @if($esPublico) data-ub-mode="inline" data-ub-ag="shalom" @endif>
                            <div class="ubigeo-display" tabindex="0">Busca ciudad, provincia o distrito…</div>
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
                        {{-- Con Shalom la oficina se elige de su catálogo (copia
                             guardada en el sistema, ver ShalomAgencyCatalog): el
                             almacén recibe la oficina exacta y no un texto libre.
                             Para las demás agencias, o si el cliente no encuentra
                             la suya, sigue el texto libre de siempre. --}}
                        <style>
                            .sh-box{margin-top:4px}
                            .sh-tools{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin:2px 0 8px}
                            .sh-near{border:1.5px solid var(--line-2);background:var(--surface);border-radius:999px;padding:6px 12px;font-size:13px;font-weight:650;color:var(--ink-2);cursor:pointer;min-height:36px}
                            .sh-list{display:grid;gap:8px;max-height:360px;overflow-y:auto;padding:2px}
                            .sh-list.is-bad{outline:2px solid var(--bad);outline-offset:2px;border-radius:12px}
                            .sh-opt{display:block;width:100%;text-align:left;border:1.5px solid var(--line);background:var(--surface);border-radius:var(--r);padding:12px 14px;cursor:pointer;font:inherit;color:var(--ink)}
                            .sh-opt:hover{border-color:var(--accent-line)}
                            .sh-opt[aria-checked="true"]{border-color:var(--accent);background:var(--accent-soft);box-shadow:0 0 0 1px var(--accent)}
                            .sh-opt b{display:block;font-size:14.5px;line-height:1.3}
                            .sh-opt span{display:block;font-size:13px;color:var(--ink-2);margin-top:3px;line-height:1.4}
                            .sh-opt i{font-style:normal;display:inline-block;font-size:12px;font-weight:650;border-radius:999px;padding:2px 9px;margin-top:7px;margin-right:4px;background:#f3efe9;color:var(--ink-2)}
                            .sh-mode{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:12px 0 2px}
                            .sh-mode__opt{text-align:left;border:1.5px solid var(--line);background:var(--surface);border-radius:var(--r);padding:10px 12px;cursor:pointer;font:inherit;color:var(--ink);min-height:56px}
                            .sh-mode__opt b{display:block;font-size:14.5px}
                            .sh-mode__opt span{display:block;font-size:12.5px;color:var(--ink-2);margin-top:2px}
                            .sh-mode__opt[aria-checked="true"]{border-color:var(--accent);background:var(--accent-soft);box-shadow:0 0 0 1px var(--accent)}
                            .sh-quote{display:flex;gap:8px;align-items:flex-start;margin:10px 0 2px;padding:10px 12px;border-radius:var(--r-sm);background:var(--ok-soft);color:var(--ok);font-size:13.5px;font-weight:600}
                            .sh-empty{font-size:14px;color:var(--ink-2);margin:4px 0;padding:12px 14px;background:#f3efe9;border-radius:var(--r)}
                        </style>
                        <div id="{{ $p }}sh_box" class="sh-box" hidden>
                            <label class="req">¿En qué oficina de Shalom recogerás?</label>
                            <div class="sh-tools">
                                <small class="hint" id="{{ $p }}sh_scope"></small>
                                <button type="button" class="sh-near" id="{{ $p }}sh_near" hidden>Ordenar por cercanía</button>
                            </div>
                            <div class="sh-list" id="{{ $p }}sh_list" role="radiogroup" aria-label="Oficinas de Shalom"></div>
                            <input type="hidden" name="agency_office_id" id="{{ $p }}agency_office_id" value="{{ old('agency_office_id') }}">
                            {{-- Vía de envío: solo si la tienda ofrece aéreo y las dos
                                 oficinas lo atienden (ver ShipmentController::shalomAirOffered). --}}
                            <div class="sh-mode" id="{{ $p }}sh_mode" hidden role="radiogroup" aria-label="Vía de envío">
                                <button type="button" class="sh-mode__opt" data-air="0" aria-checked="true">
                                    <b>🚚 Terrestre</b><span class="js-t">—</span>
                                </button>
                                <button type="button" class="sh-mode__opt" data-air="1" aria-checked="false">
                                    <b>✈️ Aéreo</b><span class="js-a">—</span>
                                </button>
                            </div>
                            <input type="hidden" name="shalom_air" id="{{ $p }}shalom_air" value="{{ old('shalom_air') ? 1 : 0 }}">
                            <div class="sh-quote" id="{{ $p }}sh_quote" hidden></div>
                            <label class="chk chk-inline" id="{{ $p }}sh_unknown_wrap">
                                <input type="checkbox" name="agency_office_unknown" value="1" id="{{ $p }}sh_unknown" {{ old('agency_office_unknown') ? 'checked' : '' }}>
                                <span>No encuentro mi oficina en la lista</span>
                            </label>
                            <small class="hint" id="{{ $p }}sh_err" hidden style="color:#dc2626;">Toca la oficina donde recogerás tu paquete, o marca «No encuentro mi oficina».</small>
                        </div>

                        <div id="{{ $p }}office_manual">
                            <label id="{{ $p }}office_label">Oficina donde recogerás <span style="color:#94a3b8;font-weight:400;">(opcional)</span></label>
                            <input type="text" name="reference" id="{{ $p }}reference_ag" value="{{ old('reference') }}" maxlength="255" placeholder="Ej. Terminal Terrestre, Av. Aviación 123…">
                            <small class="hint" id="{{ $p }}office_hint">Si ya sabes en qué local vas a recoger, escríbelo. Si no, déjalo en blanco: la agencia te lo indicará.</small>
                        </div>
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
                            <div class="cost-box__t">Cómo se paga tu envío</div>

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
                                    <small id="{{ $p }}flete_sub">Lo cobra la agencia. Depende del destino y del tamaño: Shalom mide y pesa el paquete al recibirlo.</small>
                                </div>
                                <div class="cost-row__v is-soft" id="{{ $p }}flete_v">Lo pagas allá</div>
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
                            <div class="pickup-box__t">Recoges tu pedido en la tienda</div>
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
                        <button type="button" class="btn btn-ghost" id="backStep0">Volver</button>
                        <button type="button" class="btn" id="toStep2">Continuar</button>
                    </div>
                </div>

                {{-- ══════════ PASO 3: Confirmación ══════════ --}}
                <div class="step" data-step="3" hidden>
                    <div class="step-head">
                        <h2 class="step-h">Revisa y confirma</h2>
                        <p class="step-sub">Si algo no está bien, toca «Editar».</p>
                    </div>
                    <div class="conf">
                        <div class="h" id="c_type_h">Resumen</div>

                        {{-- Dos secciones y no una lista corrida: cada una vuelve
                             al paso que la llena, para corregir sin rehacer. --}}
                        <div class="conf-sec">
                            <div class="conf-sec__h">
                                <span>Tus datos</span>
                                <button type="button" class="conf-edit" data-edit-step="1">Editar</button>
                            </div>
                            <div class="rows">
                                <div class="r"><span class="k">Tipo de entrega</span><span class="v" id="c_type">—</span></div>
                                <div class="r"><span class="k">Nombre</span><span class="v" id="c_name">—</span></div>
                                <div class="r"><span class="k">Documento</span><span class="v" id="c_doc">—</span></div>
                                <div class="r"><span class="k">Celular</span><span class="v" id="c_phone">—</span></div>
                                <div class="r" id="r_pickup"><span class="k">Recoge</span><span class="v" id="c_pickup">—</span></div>
                            </div>
                        </div>

                        <div class="conf-sec">
                            <div class="conf-sec__h">
                                <span id="conf_sec_entrega">Entrega</span>
                                <button type="button" class="conf-edit" data-edit-step="2">Editar</button>
                            </div>
                            <div class="rows">
                                <div class="r" id="r_ubigeo"><span class="k">Destino</span><span class="v" id="c_ubigeo">—</span></div>
                                <div class="r"><span class="k">Dirección</span><span class="v" id="c_dir">—</span></div>
                                <div class="r"><span class="k" id="k_ref">Referencia</span><span class="v" id="c_ref">—</span></div>
                                <div class="r" id="r_ag"><span class="k">Agencia</span><span class="v" id="c_ag">—</span></div>
                                <div class="r" id="r_via" hidden><span class="k">Vía</span><span class="v" id="c_via">—</span></div>
                                <div class="r" id="r_coords"><span class="k">Ubicación GPS</span><span class="v" id="c_coords">—</span></div>
                                <div class="r" id="r_price"><span class="k">Costo aprox. de envío</span><span class="v" id="c_price" style="color:#059669;">—</span></div>
                            </div>
                        </div>
                        {{-- Se repite aca a proposito: el paso 1 se llena rapido
                             y este es el momento en que el cliente confirma. --}}
                        <div class="conf-warn" id="r_home_extra" hidden>
                            <span>🏠</span>
                            <div>Pediste <b>reparto a domicilio</b>: la agencia te cobrará un
                                <b>adicional</b> al entregarte, además del flete a tu ciudad.</div>
                        </div>
                    </div>

                    {{-- Fechas estimadas de la modalidad elegida: el JS muestra
                         la que corresponde (ids eta-*). --}}
                    @include('tenant.shipments.partials.eta-box', ['etaType' => 'agencia', 'etaId' => 'eta-agencia'])
                    @include('tenant.shipments.partials.eta-box', ['etaType' => 'domicilio', 'etaId' => 'eta-domicilio'])
                    @include('tenant.shipments.partials.eta-box', ['etaType' => 'tienda', 'etaId' => 'eta-tienda'])

                    {{-- Términos y sorteo los declara el CLIENTE. En el panel
                         los carga un operador: no firma términos en nombre de
                         nadie ni inscribe a un sorteo sin su consentimiento. --}}
                    @if($esPublico)
                    <div class="terms-box">
                        <label class="chk">
                            <input type="checkbox" name="accepted_terms" id="{{ $p }}terms" value="1" required>
                            <span>Mis datos son correctos y autorizo usarlos solo para el envío de este pedido.</span>
                        </label>
                        <details class="cond">
                            <summary>¿Qué puede cambiar la fecha?</summary>
                            <ul>
                                <li>Disponibilidad del producto.</li>
                                <li>Disponibilidad de materiales para embalaje.</li>
                                <li>Horarios de despacho.</li>
                                <li>Agencia de transporte o disponibilidad del motorizado.</li>
                                <li>Eventos externos que puedan retrasar la entrega.</li>
                            </ul>
                        </details>
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
                        <button type="button" class="btn btn-ghost" id="backStep1">Volver</button>
                        <button type="submit" class="btn" id="confirmBtn">Confirmar mis datos</button>
                    </div>
                </div>
