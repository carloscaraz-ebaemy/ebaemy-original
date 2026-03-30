@extends('tenant.layouts.app')

@section('content')
    @php $v2Establishments = \Modules\Dashboard\Helpers\DashboardView::getEstablishments(); @endphp
    <tenant-dash-v2 :establishments="{{ json_encode($v2Establishments) }}"></tenant-dash-v2>

@endsection
