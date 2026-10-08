@extends('tenant.layouts.app')

@section('content')
    <tenant-restaurant-configuration></tenant-restaurant-configuration>
@endsection

{{-- @push('scripts')
  <!-- QZ -->
  <script src="{{ asset_v('js/sha-256.min.js') }}"></script>
  <script src="{{ asset_v('js/qz-tray.js') }}"></script>
  <script src="{{ asset_v('js/rsvp-3.1.0.min.js') }}"></script>
  <script src="{{ asset_v('js/jsrsasign-all-min.js') }}"></script>
  <script src="{{ asset_v('js/sign-message.js') }}"></script>
  <script src="{{ asset_v('js/function-qztray.js') }}"></script>
  <!-- END QZ -->
@endpush --}}
