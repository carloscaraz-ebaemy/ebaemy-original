@extends('system.layouts.app')

@section('content')
    <div class="page-header pr-0">
        <h2><a href="/configurations">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-settings" style="margin-top: -3px"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z"></path><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"></path></svg>
        </a></h2>
        <ol class="breadcrumbs">
            <li class="active"><span> Configuraciones </span></li>
        </ol>
    </div>

    <div class="row">
        <!--<div class="col-lg-6 col-md-12 pt-2 pt-md-0">
            <system-companies-form></system-companies-form>
        </div> -->
        <div class="col-lg-6 col-md-12">
            <!-- <system-certificate-index></system-certificate-index> -->
            <system-login-settings :configuration='@json($configuration)'></system-login-settings>

            <system-login-other-configuration></system-login-other-configuration>
            <system-qrapi-configuration :configuration='@json($configuration)'></system-qrapi-configuration>
            <system-cron-order-configuration :configuration='@json($configuration)'></system-cron-order-configuration>
        </div>
        <div class="col-lg-6 col-md-12">
            <system-configuration-culqi></system-configuration-culqi>
            <system-configuration-token></system-configuration-token>
            <system-configuration-apk-url></system-configuration-apk-url>
            <system-support-configuration></system-support-configuration>
            <system-email-configuration :configuration='@json($configuration)'></system-email-configuration>
        </div>
    </div>

@endsection
