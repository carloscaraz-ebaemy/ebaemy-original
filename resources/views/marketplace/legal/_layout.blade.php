{{-- Wrapper compartido para páginas legales / informativas del marketplace.
     Tipografía generosa, max-width estrecho para lectura cómoda en móvil
     y desktop. Tap targets 44px en links inline.  --}}
@push('styles')
<style>
.mp-legal {
    max-width: 760px;
    margin: 0 auto;
    padding: 24px clamp(16px, 4vw, 24px) 60px;
    color: var(--mp-ink, #111827);
}
.mp-legal__breadcrumb {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 14px;
}
.mp-legal__breadcrumb a {
    color: var(--mp-primary, #0f8a82);
    text-decoration: none;
}
.mp-legal__breadcrumb a:hover { text-decoration: underline; }
.mp-legal h1 {
    font-size: clamp(24px, 5vw, 32px);
    font-weight: 800;
    margin: 0 0 8px;
    line-height: 1.2;
}
.mp-legal__lead {
    font-size: 15px;
    color: #4b5563;
    margin: 0 0 28px;
    line-height: 1.55;
}
.mp-legal__updated {
    display: inline-block;
    font-size: 12px;
    color: #6b7280;
    background: #f3f4f6;
    padding: 4px 10px;
    border-radius: 999px;
    margin-bottom: 24px;
}
.mp-legal h2 {
    font-size: clamp(17px, 3.5vw, 20px);
    font-weight: 700;
    margin: 32px 0 12px;
    color: #111827;
}
.mp-legal h3 {
    font-size: 15px;
    font-weight: 700;
    margin: 20px 0 8px;
    color: #1f2937;
}
.mp-legal p {
    font-size: 15px;
    line-height: 1.65;
    color: #374151;
    margin: 0 0 14px;
}
.mp-legal ul, .mp-legal ol {
    padding-left: 22px;
    margin: 0 0 16px;
}
.mp-legal li {
    font-size: 15px;
    line-height: 1.65;
    color: #374151;
    margin-bottom: 6px;
}
.mp-legal a {
    color: var(--mp-primary, #0f8a82);
    text-decoration: underline;
}
.mp-legal a:hover { color: var(--mp-primary-dark, #0c6b65); }
.mp-legal__contact {
    margin-top: 36px;
    padding: 18px 20px;
    background: linear-gradient(135deg, #f0fdfa 0%, #ecfdf5 100%);
    border-left: 3px solid var(--mp-primary, #0f8a82);
    border-radius: 8px;
}
.mp-legal__contact strong { color: var(--mp-primary-dark, #0c6b65); }
@media (max-width: 600px) {
    .mp-legal { padding-top: 18px; padding-bottom: 40px; }
    .mp-legal h2 { margin-top: 24px; }
}
</style>
@endpush
