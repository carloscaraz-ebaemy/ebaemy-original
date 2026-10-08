@extends('tenant.layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset_v('css/pos.css') }}"/>
@endpush

@section('content')
    <tenant-pos-fast
      :configuration="{{ $configuration}}"
      :soap-company="{{ json_encode($soap_company) }}"
      :business-turns="{{ $business_turns }}"
      :type-user="{{json_encode(Auth::user()->type)}}"
      :is-print="{{json_encode($configuration->auto_print)}}">
    </tenant-pos-fast>
@endsection

@push('scripts')
  <!-- QZ -->
  <script src="{{ asset_v('js/sha-256.min.js') }}"></script>
  <script src="{{ asset_v('js/qz-tray.js') }}"></script>
  <script src="{{ asset_v('js/rsvp-3.1.0.min.js') }}"></script>
  <script src="{{ asset_v('js/jsrsasign-all-min.js') }}"></script>
  <script src="{{ asset_v('js/sign-message.js') }}"></script>
  <script src="{{ asset_v('js/function-qztray.js') }}"></script>
  <!-- END QZ -->
@endpush
