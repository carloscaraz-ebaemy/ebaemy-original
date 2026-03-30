{{-- Tabla de especificaciones técnicas — Theme Tecnología --}}
@if(isset($specs) && is_array($specs) && count($specs))
<div class="ec-spec-table" style="margin:1.5rem 0">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:8px">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6m-6 4h6"/></svg>
        Especificaciones Técnicas
    </h3>
    <table style="width:100%;border-collapse:collapse;font-size:13px;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
        @foreach($specs as $key => $value)
        <tr style="border-bottom:1px solid #f3f4f6;{{ $loop->even ? 'background:#f9fafb' : '' }}">
            <td style="padding:10px 14px;font-weight:600;color:#374151;width:40%">{{ $key }}</td>
            <td style="padding:10px 14px;color:#6b7280">{{ $value }}</td>
        </tr>
        @endforeach
    </table>
</div>
@endif
