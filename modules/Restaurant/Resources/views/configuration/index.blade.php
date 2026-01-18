@extends('tenant.layouts.app')

@section('content')
    <tenant-restaurant-configuration></tenant-restaurant-configuration>
@endsection

{{-- @push('scripts')
  <!-- QZ -->
  <script src="{{ asset('js/sha-256.min.js') }}"></script>
  <script src="{{ asset('js/qz-tray.js') }}"></script>
  <script src="{{ asset('js/rsvp-3.1.0.min.js') }}"></script>
  <script src="{{ asset('js/jsrsasign-all-min.js') }}"></script>
  <script src="{{ asset('js/sign-message.js') }}"></script>
  <script src="{{ asset('js/function-qztray.js') }}"></script>
  <!-- END QZ -->
@endpush --}}
