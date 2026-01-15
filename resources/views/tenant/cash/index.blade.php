@extends('tenant.layouts.app')

@section('content')

    <cash-index :type-user="{{json_encode(Auth::user()->type)}}"  
        :configuration="{{\App\Models\Tenant\Configuration::AvailableReportSeller()}}"></cash-index>

@endsection
