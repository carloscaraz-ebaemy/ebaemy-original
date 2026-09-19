@extends('tenant.layouts.app')

@section('content')
<div class="page-header pr-0">
    <h2>
        <i class="fas fa-book-open" style="color:#1f5eff"></i>
        Libro de Reclamaciones
    </h2>
    <ol class="breadcrumbs">
        <li><a href="/ecommerce/configuration">Ecommerce</a></li>
        <li class="active"><span>Libro de Reclamaciones</span></li>
    </ol>
</div>

<tenant-ecommerce-claims></tenant-ecommerce-claims>
@endsection
