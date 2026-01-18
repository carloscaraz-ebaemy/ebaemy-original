@extends('system.layouts.app')

@section('content')
    <div class="page-header pr-0">
        <h2><a href="/ecommerce/configuration">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user" style="margin-top: -5px"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"></path><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"></path></svg>
        </a></h2>
        <ol class="breadcrumbs">
            <li class="active"><span> Perfil </span></li>
        </ol>
    </div>

    <div class="row">
        <div class="col-lg-6 col-md-12">
            <system-users-form></system-users-form>
        </div>
        <div class="col-lg-6 col-md-12">
            <system-users-token-user></system-users-token-user>
        </div>
    </div>

@endsection