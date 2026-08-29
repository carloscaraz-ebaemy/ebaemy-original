{{-- Sección del home. La incluye el renderizador de secciones:
     ver App\Services\EcommerceHomeSections y ecommerce::index. --}}
        {{-- ═══ TESTIMONIOS / RESEÑAS REALES ═══ --}}
        @php
            $latestReviews = \App\Models\Tenant\ProductReview::where('status', 'approved')
                ->where('rating', '>=', 4)
                ->with('item:id,description,slug,image')
                ->latest()
                ->limit(6)
                ->get();
        @endphp
        @if($latestReviews->count() >= 3)
        <section class="ec-home-section ec-testimonials-section" aria-label="Opiniones de clientes">
            <div class="ec-section-header">
                <h2 class="ec-section-title">Lo que dicen nuestros clientes</h2>
            </div>
            <div class="ec-testimonials-grid">
                @foreach($latestReviews as $review)
                <div class="ec-testimonial-card">
                    <div class="ec-testimonial-stars">
                        @for($i = 1; $i <= 5; $i++)
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                 fill="{{ $i <= $review->rating ? '#F59E0B' : '#e5e7eb' }}" stroke="none">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        @endfor
                    </div>
                    <p class="ec-testimonial-text">"{{ \Illuminate\Support\Str::limit($review->comment, 150) }}"</p>
                    <div class="ec-testimonial-author">
                        <strong>{{ $review->reviewer_name ?? 'Cliente verificado' }}</strong>
                        @if($review->item)
                        <a href="/ecommerce/item/{{ $review->item->slug ?? $review->item->id }}" class="ec-testimonial-product">
                            {{ \Illuminate\Support\Str::limit($review->item->description, 40) }}
                        </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </section>
        @endif

<style>
/* ═══ TESTIMONIOS ═══ */
.ec-testimonials-section { margin-top: 2rem; }
.ec-testimonials-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.2rem; }
.ec-testimonial-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; transition: box-shadow .2s; }
.ec-testimonial-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.ec-testimonial-stars { display: flex; gap: 2px; margin-bottom: 10px; }
.ec-testimonial-text { font-size: 14px; color: #374151; line-height: 1.5; margin: 0 0 12px; font-style: italic; }
.ec-testimonial-author strong { display: block; font-size: 13px; color: #1e293b; }
.ec-testimonial-product { display: block; font-size: 11px; color: #4F46E5; text-decoration: none; margin-top: 2px; }
@media(max-width:768px) { .ec-testimonials-grid { grid-template-columns: 1fr; } }
</style>
