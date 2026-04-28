@extends('system.layouts.app')

@section('content')
<system-marketplace-orders :initial-stats='@json($stats)'></system-marketplace-orders>
@endsection
