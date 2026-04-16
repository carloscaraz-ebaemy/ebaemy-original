@extends('tenant.layouts.app')

@section('content')
<div class="page-header pr-0">
    <h2>
        <i class="fa fa-whatsapp" style="color:#16a34a"></i>
        Campañas WhatsApp
    </h2>
    <ol class="breadcrumbs">
        <li><a href="/ecommerce/configuration">Ecommerce</a></li>
        <li class="active"><span>Campañas WhatsApp</span></li>
    </ol>
</div>

<tenant-ecommerce-whatsapp-campaigns></tenant-ecommerce-whatsapp-campaigns>
@endsection
