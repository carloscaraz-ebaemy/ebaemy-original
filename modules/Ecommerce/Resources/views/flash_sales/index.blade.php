@extends('tenant.layouts.app')

@section('content')
<div class="page-header pr-0">
    <h2>
        <i class="fas fa-bolt" style="color:#f59e0b"></i>
        Flash Sales
    </h2>
    <ol class="breadcrumbs">
        <li><a href="/ecommerce/configuration">Ecommerce</a></li>
        <li class="active"><span>Flash Sales</span></li>
    </ol>
</div>

<tenant-ecommerce-flash-sales></tenant-ecommerce-flash-sales>
@endsection
