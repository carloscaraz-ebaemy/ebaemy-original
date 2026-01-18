@extends('tenant.layouts.app')

@section('content')
    <tenant-item-affectations :type-user="{{json_encode(Auth::user()->type)}}"></tenant-item-affectations>
@endsection