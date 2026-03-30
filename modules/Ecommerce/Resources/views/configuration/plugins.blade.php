@extends('tenant.layouts.app')

@section('content')
<div class="page-header pr-0">
    <h2><i class="fas fa-puzzle-piece"></i></h2>
    <ol class="breadcrumbs">
        <li><a href="/ecommerce/configuration">Tienda Virtual</a></li>
        <li class="active"><span>Plugins</span></li>
    </ol>
</div>

@php
    $pluginConfig = config('theme-plugins', []);
    $activeTheme = config('app.active_theme', 'default');
    $rubroFeatures = $pluginConfig['rubro'][$activeTheme] ?? $pluginConfig['rubro'][app(\App\Services\ThemePluginService::class)->getRubro() ?? ''] ?? [];
@endphp

<div class="row">
    {{-- PLUGINS CORE --}}
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">
                    <i class="fas fa-bolt mr-2 text-warning"></i> Plugins Core
                    <small class="text-muted ml-2">Cargan en todas las páginas de la tienda</small>
                </h4>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:40px"></th>
                            <th>Plugin</th>
                            <th>Descripción</th>
                            <th>CDN</th>
                            <th style="width:100px">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $corePlugins = [
                                'gsap' => ['name' => 'GSAP', 'icon' => '🎬', 'desc' => 'Motor de animaciones profesionales. Animaciones fluidas al scroll, transiciones de elementos, efectos hover.'],
                                'gsap_scroll' => ['name' => 'GSAP ScrollTrigger', 'icon' => '📜', 'desc' => 'Extensión de GSAP para animaciones activadas al hacer scroll. Fade-in de productos, parallax.'],
                                'instant_page' => ['name' => 'instant.page', 'icon' => '⚡', 'desc' => 'Precarga páginas al pasar el mouse sobre links. Mejora velocidad percibida un 30%.'],
                            ];
                        @endphp
                        @foreach($corePlugins as $key => $info)
                        @php $plugin = $pluginConfig['core'][$key] ?? []; $enabled = $plugin['enabled'] ?? false; @endphp
                        <tr>
                            <td class="text-center" style="font-size:20px">{{ $info['icon'] }}</td>
                            <td>
                                <strong>{{ $info['name'] }}</strong>
                                @if(isset($plugin['type']))<br><code class="small">type: {{ $plugin['type'] }}</code>@endif
                            </td>
                            <td><span class="text-muted" style="font-size:13px">{{ $info['desc'] }}</span></td>
                            <td>
                                @if(isset($plugin['js']))<code class="small d-block text-truncate" style="max-width:250px" title="{{ $plugin['js'] }}">{{ basename($plugin['js']) }}</code>@endif
                                @if(isset($plugin['css']))<code class="small d-block text-truncate" style="max-width:250px" title="{{ $plugin['css'] }}">{{ basename($plugin['css']) }}</code>@endif
                            </td>
                            <td>
                                @if($enabled)
                                <span class="badge badge-success"><i class="fas fa-check"></i> Activo</span>
                                @else
                                <span class="badge badge-secondary">Inactivo</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- PLUGINS ECOMMERCE --}}
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">
                    <i class="fas fa-shopping-bag mr-2 text-primary"></i> Plugins Ecommerce
                    <small class="text-muted ml-2">Cargan según la página de la tienda</small>
                </h4>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:40px"></th>
                            <th>Plugin</th>
                            <th>Descripción</th>
                            <th>Páginas</th>
                            <th>CDN</th>
                            <th style="width:100px">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $ecomPlugins = [
                                'swiper' => ['name' => 'Swiper', 'icon' => '🎠', 'desc' => 'Carruseles táctiles profesionales. Para sliders de productos, banners, y galerías.'],
                                'drift_zoom' => ['name' => 'Drift Zoom', 'icon' => '🔍', 'desc' => 'Zoom de imagen al pasar el mouse. Muestra detalle del producto como Amazon/Falabella.'],
                                'glightbox' => ['name' => 'GLightbox', 'icon' => '🖼️', 'desc' => 'Galería lightbox con gestos táctiles. Abre imágenes en pantalla completa con navegación.'],
                            ];
                        @endphp
                        @foreach($ecomPlugins as $key => $info)
                        @php $plugin = $pluginConfig['ecommerce'][$key] ?? []; $enabled = $plugin['enabled'] ?? false; @endphp
                        <tr>
                            <td class="text-center" style="font-size:20px">{{ $info['icon'] }}</td>
                            <td><strong>{{ $info['name'] }}</strong></td>
                            <td><span class="text-muted" style="font-size:13px">{{ $info['desc'] }}</span></td>
                            <td>
                                @foreach($plugin['pages'] ?? [] as $page)
                                <span class="badge badge-light mr-1">{{ $page }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if(isset($plugin['js']))<code class="small d-block text-truncate" style="max-width:200px">{{ basename($plugin['js']) }}</code>@endif
                                @if(isset($plugin['css']))<code class="small d-block text-truncate" style="max-width:200px">{{ basename($plugin['css']) }}</code>@endif
                            </td>
                            <td>
                                @if($enabled)
                                <span class="badge badge-success"><i class="fas fa-check"></i> Activo</span>
                                @else
                                <span class="badge badge-secondary">Inactivo</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- FEATURES POR RUBRO --}}
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">
                    <i class="fas fa-layer-group mr-2 text-info"></i> Features por Rubro
                    <small class="text-muted ml-2">Componentes específicos según el tipo de negocio</small>
                </h4>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3" style="font-size:13px">
                    Tu tema activo: <strong>{{ $activeTheme }}</strong>.
                    Las features se activan automáticamente según el rubro del tema seleccionado.
                </p>
                <div class="row">
                    @php
                        $featureDescriptions = [
                            'size_selector' => ['name' => 'Selector de Tallas', 'icon' => '📐', 'desc' => 'Botones visuales para elegir talla (S, M, L, XL)'],
                            'color_swatches' => ['name' => 'Muestras de Color', 'icon' => '🎨', 'desc' => 'Círculos de color para elegir variante visual'],
                            'size_guide' => ['name' => 'Guía de Tallas', 'icon' => '📏', 'desc' => 'Modal con tabla de medidas por talla'],
                            'outfit_builder' => ['name' => 'Constructor de Outfits', 'icon' => '👗', 'desc' => 'Armar looks combinando prendas'],
                            'spec_compare' => ['name' => 'Comparador de Specs', 'icon' => '⚖️', 'desc' => 'Comparar especificaciones lado a lado'],
                            'spec_table' => ['name' => 'Tabla de Specs', 'icon' => '📋', 'desc' => 'Tabla de características técnicas del producto'],
                            'nutrition_info' => ['name' => 'Info Nutricional', 'icon' => '🥗', 'desc' => 'Calorías, proteínas, carbos, grasas'],
                            'delivery_scheduler' => ['name' => 'Horarios de Delivery', 'icon' => '🕐', 'desc' => 'Elegir horario de entrega'],
                            'allergen_filter' => ['name' => 'Filtro Alérgenos', 'icon' => '⚠️', 'desc' => 'Filtrar productos por alérgenos'],
                            'activity_filter' => ['name' => 'Filtro por Actividad', 'icon' => '🏃', 'desc' => 'Filtrar por deporte o actividad'],
                            'prescription_upload' => ['name' => 'Subir Receta', 'icon' => '💊', 'desc' => 'Adjuntar receta médica al pedido'],
                            'dosage_calculator' => ['name' => 'Calculadora de Dosis', 'icon' => '🧮', 'desc' => 'Calcular dosis según peso/edad'],
                            'unit_calculator' => ['name' => 'Calculadora de Unidades', 'icon' => '📐', 'desc' => 'Calcular m², litros, kg necesarios'],
                            'project_estimator' => ['name' => 'Estimador de Proyecto', 'icon' => '🏗️', 'desc' => 'Estimar materiales para un proyecto'],
                            'appointment_booking' => ['name' => 'Agendar Cita', 'icon' => '📅', 'desc' => 'Reservar cita en la boutique'],
                            'authenticity_verify' => ['name' => 'Verificar Autenticidad', 'icon' => '✅', 'desc' => 'Verificar producto original con código'],
                        ];
                    @endphp

                    @foreach($pluginConfig['rubro'] ?? [] as $rubroKey => $features)
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100 {{ $rubroKey === $activeTheme ? 'border-primary' : '' }}" style="border-width:{{ $rubroKey === $activeTheme ? '2px' : '1px' }}">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <strong style="text-transform:capitalize">{{ str_replace('-', ' ', $rubroKey) }}</strong>
                                @if($rubroKey === $activeTheme)
                                <span class="badge badge-primary">Tu tema</span>
                                @endif
                            </div>
                            <div class="card-body py-2">
                                @foreach($features as $featureKey => $enabled)
                                @php $fd = $featureDescriptions[$featureKey] ?? ['name' => $featureKey, 'icon' => '🔧', 'desc' => '']; @endphp
                                <div class="d-flex align-items-center justify-content-between py-1" style="font-size:13px">
                                    <span>
                                        <span style="font-size:14px">{{ $fd['icon'] }}</span>
                                        {{ $fd['name'] }}
                                    </span>
                                    @if($enabled)
                                    <span class="badge badge-success" style="font-size:10px"><i class="fas fa-check"></i></span>
                                    @else
                                    <span class="badge badge-light" style="font-size:10px">Premium</span>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card { border-radius: 10px; }
.card-header { background: transparent; border-bottom: 1px solid #f3f4f6; }
.table th { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: #6b7280; border-top: none; }
.table td { vertical-align: middle; }
code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
</style>
@endsection
