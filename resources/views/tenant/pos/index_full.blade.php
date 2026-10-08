@extends('tenant.layouts.app_pos')

@push('styles')
    <link rel="stylesheet" href="{{ asset_v('css/pos.css') }}"/>
@endpush

@section('content')
    <tenant-pos-index  ></tenant-pos-index>
@endsection

@push('scripts')
    <script></script>
@endpush
