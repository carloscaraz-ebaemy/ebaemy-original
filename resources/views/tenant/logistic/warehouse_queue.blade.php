@extends('tenant.layouts.app')

@section('title', 'Cola de Almacén — Logística')

@section('content')
<div id="app-logistic">
    <warehouse-queue
        tenant-uuid="{{ $tenant_uuid }}"
        :warehouse-id="{{ $warehouse_id ?? 'null' }}"
    ></warehouse-queue>
</div>
@endsection

@push('scripts')
<script>
    // Configuración de Echo para Broadcasting en tiempo real
    window.__LOGISTIC_CONFIG__ = {
        tenantUuid: '{{ $tenant_uuid }}',
        warehouseId: {{ $warehouse_id ?? 'null' }},
        pusherKey: '{{ config('broadcasting.connections.pusher.key') }}',
        pusherCluster: '{{ config('broadcasting.connections.pusher.options.cluster', 'mt1') }}',
        pusherHost: '{{ config('broadcasting.connections.pusher.options.host', '') }}',
        pusherPort: {{ config('broadcasting.connections.pusher.options.port', 6001) }},
        pusherScheme: '{{ config('broadcasting.connections.pusher.options.scheme', 'https') }}',
        wsRoute: '{{ url('/broadcasting/auth') }}',
    };
</script>
@endpush
