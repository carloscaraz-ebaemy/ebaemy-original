<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="sidebar-light sidebar-left-big-icons">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
{{--    <title>{{ config('app.name', 'Facturación Electrónica') }}</title>--}}
    <title>Editor de etiquetas</title>
    <!-- Scripts -->

    <!-- Fonts -->
    {{--<link rel="dns-prefetch" href="https://fonts.gstatic.com">--}}
    {{--<link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet" type="text/css">--}}

    @vite(['resources/js/app.js'])

    <!-- Styles -->
    {{--<link rel="stylesheet" href="https://cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css" />--}}
    {{--<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/7.26.29/sweetalert2.min.css" />--}}
{{--    <link rel="stylesheet" href="{{asset('porto-light/vendor/bootstrap-datepicker/css/bootstrap-datepicker3.css')}}" />--}}

    <!-- Specific Page Vendor CSS -->
    {{--<link rel="stylesheet" href="{{asset('porto-light/vendor/jquery-ui/jquery-ui.css')}}" />--}}
    {{--<link rel="stylesheet" href="{{asset('porto-light/vendor/jquery-ui/jquery-ui.theme.css')}}" />--}}
    {{--<link rel="stylesheet" href="{{asset('porto-light/vendor/select2/css/select2.css')}}" />--}}
{{--    <link rel="stylesheet" href="{{asset('porto-light/vendor/select2-bootstrap-theme/select2-bootstrap.min.css')}}" />--}}

    <!-- Daterange picker plugins css -->
    {{--<link href="{{ asset('porto-light/vendor/bootstrap-timepicker/css/bootstrap-timepicker.css') }}" rel="stylesheet">--}}
    {{--<link href="{{ asset('porto-light/vendor/bootstrap-daterangepicker/daterangepicker.css') }}" rel="stylesheet">--}}

{{--    <link rel="stylesheet" href="{{asset('porto-light/vendor/bootstrap-timepicker/css/bootstrap-timepicker.css')}}" />--}}

    <link rel="stylesheet" href="{{asset('porto-light/vendor/jquery-loading/dist/jquery.loading.css')}}" />

    @if (file_exists(public_path('theme/custom_styles.css')))
        <link rel="stylesheet" href="{{ asset('theme/custom_styles.css') }}" />
    @endif
    {{--@stack('styles')--}}


    {{--<script src="{{ asset('porto-light/vendor/modernizr/modernizr.js') }}"></script>--}}

</head>
  <style>
  /* Copia directa de tus estilos base, solo añadí alguna clase pequeña para unidades */
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: hsl(0 0% 98%);
    min-height: 100vh;
  }
  .container {
    max-width: 100%;
    height: 100vh;
    display: flex;
    flex-direction: column;
  }
  .header {
    background: hsl(0 0% 100%);
    border-bottom: 1px solid hsl(0 0% 89.8%);
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
  }
  .header h1 {
    font-size: 1.5rem;
    font-weight: 600;
    color: hsl(0 0% 3.9%);
  }
  .header-center {
    display: flex;
    align-items: center;
    gap: 1rem;
  }
  .dimensions-controls {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    background: hsl(0 0% 98%);
    border: 1px solid hsl(0 0% 89.8%);
    border-radius: 0.5rem;
  }
  .dimensions-controls label {
    font-size: 0.875rem;
    font-weight: 500;
    color: hsl(0 0% 45%);
    margin-right: 0.25rem;
  }
  .dimensions-controls input {
    width: 60px;
    padding: 0.25rem 0.5rem;
    border: 1px solid hsl(0 0% 89.8%);
    border-radius: 0.25rem;
    font-size: 0.875rem;
    text-align: center;
  }
  .unit-label {
    font-size: 0.75rem;
    color: hsl(0 0% 45%);
  }
  .zoom-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    background: hsl(0 0% 98%);
    border: 1px solid hsl(0 0% 89.8%);
    border-radius: 0.5rem;
  }
  .zoom-btn {
    width: 28px;
    height: 28px;
    border: 1px solid hsl(0 0% 89.8%);
    background: hsl(0 0% 100%);
    border-radius: 0.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.875rem;
    font-weight: 600;
  }
  .zoom-btn:hover {
    background: hsl(221.2 83.2% 53.3%);
    color: white;
    border-color: hsl(221.2 83.2% 53.3%);
  }
  .zoom-level {
    font-size: 0.75rem;
    font-weight: 500;
    color: hsl(0 0% 45%);
    min-width: 40px;
    text-align: center;
  }
  .header-actions {
    display: flex;
    gap: 0.5rem;
  }
  .main-content {
    display: flex;
    flex: 1;
    overflow: hidden;
  }
  .sidebar,
  .right-sidebar {
    width: 380px;
    background: hsl(0 0% 100%);
    border-right: 1px solid hsl(0 0% 89.8%);
    padding: 1.5rem;
    overflow-y: auto;
  }
  .right-sidebar {
    border-right: none;
    border-left: 1px solid hsl(0 0% 89.8%);
  }
  .sidebar h3,
  .field-properties-section h3 {
    font-size: 0.875rem;
    font-weight: 600;
    color: hsl(0 0% 3.9%);
    margin-bottom: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }
  .tool-section {
    margin-bottom: 2rem;
  }
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
    padding: 0.5rem 1rem;
    gap: 0.5rem;
    position: relative;
    overflow: hidden;
  }
  .btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
  }
  .btn:active::before {
    width: 300px;
    height: 300px;
  }
  .btn-primary {
    background: hsl(222.2 47.4% 11.2%);
    color: hsl(210 40% 98%);
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
  }
  .btn-secondary {
    background: hsl(0 0% 100%);
    color: hsl(222.2 47.4% 11.2%);
    border: 1px solid hsl(0 0% 89.8%);
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
  }
  .btn-outline {
    background: transparent;
    color: hsl(222.2 47.4% 11.2%);
    border: 1px solid hsl(0 0% 89.8%);
  }
  .btn-destructive {
    background: hsl(0 84.2% 60.2%);
    color: hsl(0 0% 98%);
  }
  .btn-full {
    width: 100%;
    margin-bottom: 0.5rem;
  }
  .input-group {
    margin-bottom: 1rem;
  }
  .input-group label {
    display: block;
    margin-bottom: 0.375rem;
    color: hsl(0 0% 3.9%);
    font-size: 0.875rem;
    font-weight: 500;
  }
  .input-group input[type="text"],
  .input-group input[type="number"],
  .input-group select {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid hsl(0 0% 89.8%);
    border-radius: 0.375rem;
    font-size: 0.875rem;
    background: hsl(0 0% 100%);
  }
  .canvas-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 3rem;
    background: linear-gradient(135deg, hsl(0 0% 97%) 0%, hsl(0 0% 95%) 100%);
    position: relative;
    overflow: auto;
  }
  .canvas-container::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
      linear-gradient(hsl(0 0% 90% / 0.3) 1px, transparent 1px),
      linear-gradient(90deg, hsl(0 0% 90% / 0.3) 1px, transparent 1px);
    background-size: 20px 20px;
    pointer-events: none;
  }
  #labelCanvas {
    background: hsl(0 0% 100%);
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
    position: relative;
    border: 1px solid hsl(0 0% 89.8%);
    margin: auto;
  }
  .field {
    position: absolute;
    border: 1px solid transparent;
    cursor: move;
    padding: 0.25rem;
    background: transparent;
    user-select: none;
    transition: all 0.2s;
    outline: none;
  }
  .field:hover {
    background: hsl(210 40% 96.1% / 0.5);
  }
  .field.selected {
    border: 2px solid hsl(221.2 83.2% 53.3%);
    background: hsl(221.2 83.2% 53.3% / 0.08);
    box-shadow: 0 0 0 1px hsl(221.2 83.2% 53.3% / 0.2),
      0 4px 6px -1px rgb(0 0 0 / 0.1);
  }
  .field .resize-handle {
    position: absolute;
    width: 10px;
    height: 10px;
    background: hsl(0 0% 100%);
    border: 2px solid hsl(221.2 83.2% 53.3%);
    border-radius: 50%;
    right: -5px;
    bottom: -5px;
    cursor: nwse-resize;
    opacity: 0;
    transform: scale(0.8);
    transition: all 0.2s;
  }
  .field.selected .resize-handle {
    opacity: 1;
    transform: scale(1);
  }
  .field .delete-btn {
    position: absolute;
    top: -10px;
    right: -10px;
    width: 22px;
    height: 22px;
    background: hsl(0 84.2% 60.2%);
    color: hsl(0 0% 100%);
    border: 2px solid hsl(0 0% 100%);
    border-radius: 50%;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
    display: none;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px 0 rgb(0 0 0 / 0.15);
    opacity: 0;
    transform: scale(0.8);
    transition: all 0.2s;
  }
  .field .delete-btn:hover {
    background: hsl(0 84.2% 50.2%);
    transform: scale(1.1);
  }
  .field.selected .delete-btn {
    display: flex;
    opacity: 1;
    transform: scale(1);
  }
  .field .field-badge {
    position: absolute;
    top: -8px;
    left: -8px;
    background: hsl(221.2 83.2% 53.3%);
    color: hsl(0 0% 100%);
    font-size: 10px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    opacity: 0;
    transform: translateY(-2px);
    transition: all 0.2s;
    pointer-events: none;
    box-shadow: 0 2px 4px 0 rgb(0 0 0 / 0.15);
  }
  .field.selected .field-badge {
    opacity: 1;
    transform: translateY(0);
  }
  .field-content {
    pointer-events: none;
    overflow: hidden;
    width: 100%;
    height: 100%;
  }
  .field-content img {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }
  .field-content svg {
    width: 100%;
    height: 100%;
  }
  .file-upload { display: none; }
  
  /* Tags */
  .tag-selector {
    border: 1px solid hsl(0 0% 89.8%);
    border-radius: 0.375rem;
    background: hsl(0 0% 100%);
    min-height: 40px;
    padding: 0.25rem;
    position: relative;
  }
  .selected-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    margin-bottom: 0.25rem;
  }
  .selected-tag {
    display: inline-flex;
    align-items: center;
    background: hsl(221.2 83.2% 53.3%);
    color: white;
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    gap: 0.25rem;
  }
  .remove-tag {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: bold;
    padding: 0;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .tag-input-container {
    position: relative;
  }
  .tag-input-container input {
    border: none;
    outline: none;
    background: transparent;
    width: 100%;
    padding: 0.25rem;
    font-size: 0.875rem;
  }
  .tag-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid hsl(0 0% 89.8%);
    border-radius: 0.375rem;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
  }
  .tag-dropdown.show {
    display: block;
  }
  .tag-option {
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    border-bottom: 1px solid hsl(0 0% 95%);
  }
  .tag-option:last-child {
    border-bottom: none;
  }
  .tag-option.selected {
    background: hsl(221.2 83.2% 53.3% / 0.1);
    color: hsl(221.2 83.2% 53.3%);
  }
  .tag-option-name {
    font-weight: 500;
  }
  .tag-option-value {
    font-size: 0.75rem;
    color: hsl(0 0% 45%);
    margin-top: 0.125rem;
  }
  
  /* Botones alineación / toggle */
  .btn-align,
  .btn-toggle {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem;
    border: 1px solid hsl(0 0% 89.8%);
    background: hsl(0 0% 100%);
    cursor: pointer;
    border-radius: 0.375rem;
    transition: all 0.2s;
  }
  .btn-align:hover,
  .btn-toggle:hover {
    background: hsl(0 0% 96.1%);
    border-color: hsl(221.2 83.2% 53.3%);
  }
  .btn-align.active,
  .btn-toggle.active {
    background: hsl(221.2 83.2% 53.3%);
    border-color: hsl(221.2 83.2% 53.3%);
    color: white;
  }
  
  /* Plantillas */
  .template-card {
    border: 1px solid hsl(0 0% 89.8%);
    border-radius: 0.375rem;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    background: hsl(0 0% 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    position: relative;
  }
  .template-card.default {
    border-color: hsl(142.1 76.2% 36.3%);
    background: hsl(142.1 76.2% 36.3% / 0.05);
  }
  .template-name {
    font-weight: 600;
    color: hsl(0 0% 3.9%);
    font-size: 0.875rem;
    flex: 1;
  }
  .template-actions {
    display: flex;
    gap: 0.25rem;
  }
  .btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 0.375rem;
    border: 1px solid hsl(0 0% 89.8%);
    background: hsl(0 0% 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 14px;
  }
  .btn-icon.default {
    color: hsl(142.1 76.2% 36.3%);
  }
  .btn-icon.default.default {
    background: hsl(142.1 76.2% 36.3%);
    color: white;
    border-color: hsl(142.1 76.2% 36.3%);
  }
  
  /* Propiedades campo */
  .field-properties-section .input-group {
    margin-bottom: 0.75rem;
  }
  .field-properties-section .input-group label {
    margin-bottom: 0.25rem;
    font-weight: 500;
    color: #555;
    font-size: 0.9rem;
  }
  
  /* Print */
  @media print {
    .sidebar,
    .right-sidebar,
    .header {
      display: none !important;
    }
    .canvas-container {
      padding: 0;
      background: white;
    }
    #labelCanvas {
      box-shadow: none;
      border: none;
    }
    .field {
      border: none !important;
      background: transparent !important;
    }
    .resize-handle,
    .delete-btn {
      display: none !important;
    }
  }
  </style>
  
<body >
<div id="main-wrapper">
    @yield('content')
</div>

    {{--@stack('scripts')--}}

    @yield('content-mercadopago')
    <!-- Theme Base, Components and Settings -->
    {{--<script src="{{asset('porto-light/js/theme.js')}}"></script>--}}
    <!-- Vendor -->
    <script src="{{ asset('porto-light/vendor/jquery/jquery.js')}}"></script>
    {{--
</body>
</html>
