@extends('tenant.layouts.app')

@section('content')
<div class="page-header pr-0">
    <h2>
        <i class="fas fa-bell" style="color:#22c55e"></i>
        Avisos de Stock
    </h2>
    <ol class="breadcrumbs">
        <li><a href="/ecommerce/configuration">Ecommerce</a></li>
        <li class="active"><span>Avisos de Stock</span></li>
    </ol>
</div>

<tenant-ecommerce-stock-notifications></tenant-ecommerce-stock-notifications>
@endsection
