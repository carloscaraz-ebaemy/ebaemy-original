@extends('system.layouts.app')

@section('content')
<section role="main" class="content-body">
    <header class="page-header">
        <h2>Notificaciones</h2>
    </header>

    <div class="card">
        <div class="card-body p-0">
            @if($items->isEmpty())
                <div class="text-center text-muted p-5">
                    <div style="font-size:48px">🔕</div>
                    <p class="mt-3">No hay notificaciones aún.</p>
                </div>
            @else
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px"></th>
                            <th>Notificación</th>
                            <th style="width:150px">Tipo</th>
                            <th style="width:140px">Fecha</th>
                            <th style="width:90px">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $n)
                            <tr style="{{ $n->is_read ? '' : 'background:#eff6ff' }}">
                                <td class="text-center" style="font-size:20px">{{ $n->icon ?: '🔔' }}</td>
                                <td>
                                    @if($n->link)
                                        <a href="{{ $n->link }}" style="color:#111827;text-decoration:none">
                                            <strong>{{ $n->title }}</strong>
                                        </a>
                                    @else
                                        <strong>{{ $n->title }}</strong>
                                    @endif
                                    @if($n->body)
                                        <div style="font-size:12px;color:#6b7280;margin-top:2px">{{ $n->body }}</div>
                                    @endif
                                </td>
                                <td><code style="font-size:11px">{{ $n->type }}</code></td>
                                <td style="font-size:12px;color:#6b7280">{{ $n->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    @if($n->is_read)
                                        <span class="badge bg-secondary">Leída</span>
                                    @else
                                        <span class="badge bg-primary">Nueva</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    @if($items->hasPages())
        <div class="mt-3">{{ $items->links() }}</div>
    @endif
</section>
@endsection
