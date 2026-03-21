@extends('tenant.layouts.app')

@section('content')
<div class="page-header pr-0">
    <h2>
        <i class="fas fa-tag" style="color:#6366f1"></i>
        Cupones de Descuento
    </h2>
    <ol class="breadcrumbs">
        <li><a href="/ecommerce/configuration">Ecommerce</a></li>
        <li class="active"><span>Cupones</span></li>
    </ol>
</div>

<tenant-ecommerce-coupons></tenant-ecommerce-coupons>
@endsection
