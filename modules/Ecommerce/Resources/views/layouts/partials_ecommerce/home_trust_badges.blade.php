{{-- Sección del home. La incluye el renderizador de secciones:
     ver App\Services\EcommerceHomeSections y ecommerce::index. --}}
        {{-- ═══ TRUST BADGES ═══ --}}
        <section class="ec-trust-badges" aria-label="Garantías">
            <div class="ec-trust-grid">
                <div class="ec-trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                    <strong>Compra segura</strong>
                    <span>Datos protegidos con SSL</span>
                </div>
                <div class="ec-trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="1.5"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    <strong>Envío a todo el Perú</strong>
                    <span>Despacho en 24-48h</span>
                </div>
                <div class="ec-trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="1.5"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                    <strong>Garantía de calidad</strong>
                    <span>Cambios y devoluciones</span>
                </div>
                <div class="ec-trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="1.5"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                    <strong>Atención personalizada</strong>
                    <span>WhatsApp y correo</span>
                </div>
            </div>
        </section>

<style>
/* ═══ TRUST BADGES ═══ */
.ec-trust-badges { padding: 2rem 0; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; margin: 2rem 0; }
.ec-trust-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; max-width: 900px; margin: 0 auto; text-align: center; }
.ec-trust-item { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.ec-trust-item strong { font-size: 14px; color: #1e293b; }
.ec-trust-item span { font-size: 12px; color: #94a3b8; }
@media(max-width:768px) { .ec-trust-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; } }
</style>
