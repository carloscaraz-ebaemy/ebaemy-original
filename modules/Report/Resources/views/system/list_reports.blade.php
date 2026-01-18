@extends('system.layouts.app')

@section('content')

    <div class="page-header pr-0">
        <h2>
            <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-report-analytics" style="margin-top: -5px"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" /><path d="M9 17v-5" /><path d="M12 17v-1" /><path d="M15 17v-3" /></svg>
        </h2>
        <ol class="breadcrumbs">
            <li>
                <span class="text-muted">Reportes</span>
            </li>
        </ol>
    </div>

    <div class="row mt-5">
        <!-- General -->
        <div class="col-6 col-md-4 mb-4">
            <div class="card card-dashboard card-reports">
                <div class="card-body">
                    <h6 class="card-title">General</h6>
                    <ul class="card-report-links">
                        <li>
                            <a href="{{route('system.report_login_lockout.index')}}">
                                Cuentas bloquedas
                            </a>
                        </li>
                        <li>
                            <a href="{{route('system.user_not_change_password.index')}}">
                                Usuarios con contraseña desactualizada
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
