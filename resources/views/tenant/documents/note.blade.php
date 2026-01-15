@extends('tenant.layouts.app')

@section('content')

    <tenant-documents-note
        :user="{{ json_encode(auth()->user()) }}"
        :auth-user="{{json_encode(Auth::user()->getDataOnlyAuthUser())}}"
        :document_affected="{{ json_encode($document_affected) }}"
        :configuration="{{\App\Models\Tenant\Configuration::getPublicConfig()}}"
    ></tenant-documents-note>

@endsection
