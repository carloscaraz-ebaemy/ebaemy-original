{{-- Info nutricional — Theme Alimentos --}}
<div class="ec-nutrition" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin:1.5rem 0">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:8px">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Información Nutricional
    </h3>
    <p style="font-size:12px;color:#9ca3af;margin-bottom:12px">Por porción de 100g</p>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px">
        <div style="text-align:center;padding:12px;background:#f9fafb;border-radius:8px">
            <div style="font-size:22px;font-weight:700;color:hsl(var(--primary-h),var(--primary-s),var(--primary-l))">{{ $calories ?? '—' }}</div>
            <div style="font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.03em">Calorías</div>
        </div>
        <div style="text-align:center;padding:12px;background:#f9fafb;border-radius:8px">
            <div style="font-size:22px;font-weight:700;color:#2563eb">{{ $protein ?? '—' }}g</div>
            <div style="font-size:10px;color:#9ca3af;text-transform:uppercase">Proteínas</div>
        </div>
        <div style="text-align:center;padding:12px;background:#f9fafb;border-radius:8px">
            <div style="font-size:22px;font-weight:700;color:#f59e0b">{{ $carbs ?? '—' }}g</div>
            <div style="font-size:10px;color:#9ca3af;text-transform:uppercase">Carbos</div>
        </div>
        <div style="text-align:center;padding:12px;background:#f9fafb;border-radius:8px">
            <div style="font-size:22px;font-weight:700;color:#ef4444">{{ $fat ?? '—' }}g</div>
            <div style="font-size:10px;color:#9ca3af;text-transform:uppercase">Grasas</div>
        </div>
    </div>
    @if(isset($allergens) && count($allergens))
    <div style="padding:10px 14px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;font-size:12px;color:#92400e;font-weight:600">
        Alérgenos: {{ implode(', ', $allergens) }}
    </div>
    @endif
</div>
