@extends('tenant.layouts.app')

@section('content')
<div class="page-header pr-0">
    <h2><a href="/ecommerce/configuration"><i class="fas fa-cogs"></i></a></h2>
    <ol class="breadcrumbs">
        <li class="active"><span> Configuración </span></li>
    </ol>
</div>
<div class="row tab-content-default row-new bg-transparent mt-1" style="background: transparent !important;">

    {{-- 🏪 TIENDA (Info + Tags + Logo + Color) --}}
    <tenant-ecommerce-configuration-info></tenant-ecommerce-configuration-info>
    <tenant-ecommerce-configuration-tag></tenant-ecommerce-configuration-tag>
    <tenant-ecommerce-configuration-logo></tenant-ecommerce-configuration-logo>
    <tenant-ecommerce-configuration-color></tenant-ecommerce-configuration-color>

    {{-- 💳 PAGOS (Culqi + MercadoPago + PayPal) --}}
    <tenant-ecommerce-configuration-culqi></tenant-ecommerce-configuration-culqi>
    <tenant-ecommerce-configuration-mercadopago></tenant-ecommerce-configuration-mercadopago>
    <tenant-ecommerce-configuration-paypal></tenant-ecommerce-configuration-paypal>

    {{-- 🏬 MARKETPLACES --}}
    <tenant-ecommerce-configuration-marketplaces></tenant-ecommerce-configuration-marketplaces>
    @include('ecommerce::configuration.partials.feeds')

    {{-- 📊 SEO & MARKETING --}}
    <tenant-ecommerce-configuration-seo></tenant-ecommerce-configuration-seo>
    <tenant-ecommerce-configuration-social></tenant-ecommerce-configuration-social>
    <tenant-ecommerce-configuration-pixels></tenant-ecommerce-configuration-pixels>
    <tenant-ecommerce-configuration-newsletter></tenant-ecommerce-configuration-newsletter>

    {{-- 🔧 AVANZADO (Scripts + Términos + Links) --}}
    <tenant-ecommerce-configuration-script></tenant-ecommerce-configuration-script>
    <tenant-ecommerce-configuration-terms></tenant-ecommerce-configuration-terms>
    <tenant-ecommerce-configuration-links></tenant-ecommerce-configuration-links>

</div>
@endsection
