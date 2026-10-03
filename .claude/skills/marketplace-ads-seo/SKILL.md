---
name: marketplace-ads-seo
description: SEO y publicidad pagada del marketplace ebaemy.com — campañas por tienda y por categoría en TikTok, Meta (Facebook/Instagram) y Google. Invocar cuando el usuario pida lanzar publicidad, anunciar productos, medir conversiones, instalar píxeles, generar feeds de catálogo por tienda o categoría, mejorar el posicionamiento del marketplace, o cuando se reporte que "la publicidad no convierte" o "no sé si la publicidad funciona".
---

# SEO y publicidad del marketplace ebaemy

## Lee esto primero: el SEO orgánico YA está construido

El error más caro que puedes cometer aquí es reimplementar lo que existe. Antes de
escribir una línea, abre estos archivos. Todo lo de esta tabla **está en producción**:

| Qué | Dónde | Estado |
|---|---|---|
| Meta tags, keywords, robots, theme-color | `resources/views/marketplace/layout.blade.php` (cabecera `@php`) | ✅ |
| JSON-LD `Organization` + `WebSite` con `SearchAction` (sitelinks searchbox) | mismo layout, `$mpJsonLd` | ✅ |
| JSON-LD + canonical por página | `show`, `category`, `category_official`, `tenant` (`.blade.php`) | ✅ |
| `sitemap-marketplace.xml` con productos, categorías oficiales, legacy y **una URL por tienda** | `MarketplaceController@sitemap` | ✅ |
| `robots.txt` (el real es el estático) | `public/robots.txt` | ✅ |
| Feed Meta / Google Merchant con variantes (`item_group_id`) | `MarketplaceController@metaCatalog` → `/feeds/meta-catalog.xml` | ✅ |
| **Feed filtrado por tienda** | `/feeds/meta-catalog.xml?tienda={subdominio}` | ✅ pero **invisible en la UI** |
| **Feed filtrado por categoría** (incluye descendencia) | `/feeds/meta-catalog.xml?categoria={id}` | ✅ pero **invisible en la UI** |
| Panel de SEO administrable (og:title, og:description, og:image, keywords, redes `sameAs`) | `/admin/marketplace/seo` → `resources/views/system/marketplace/seo.blade.php` | ✅ |
| Panel "Conectar catálogo" con las URLs de feed e instrucciones por plataforma | `/admin/marketplace/feeds` → `system/marketplace/feeds.blade.php` | ✅ |
| Click-out con UTM propio hacia el tenant | `MarketplaceListing::tenant_item_url_with_utm` (`utm_source=ebaemy_marketplace`) | ✅ |
| Analítica interna: vistas y clics por producto y día | `marketplace_listing_stats_daily`, `MarketplaceController@recordDailyStat` | ✅ |
| Feeds TikTok y Meta **por tenant** (canal externo, otra cosa) | `app/Services/Marketplace/TikTokService.php`, `MetaFeedService.php` | ✅ |

**Los dos filtros del feed son la pieza clave** de lo que pide el negocio: anunciar
*por tienda* y *por categoría*. Ya funcionan por query string. Nadie puede usarlos
porque el panel sólo muestra la URL global. Eso es trabajo de UI, no de backend.

## Lo que de verdad falta: medir

Grep el repo entero: no hay `fbq`, no hay `ttq`, no hay `gtag`, no hay Events API de
TikTok, no hay Conversions API de Meta. **Cero.**

Consecuencia concreta — dila así si el usuario pregunta por qué no basta con el feed:

> Sin píxel, TikTok y Meta no saben qué anuncio generó la venta. No pueden optimizar
> hacia compradores, sólo hacia clics. Se paga tráfico, no ventas, y el informe de la
> plataforma nunca cuadra con los pedidos reales.

## Las fases, en el orden en que importan

Nunca empieces por la fase 3. Sin la 1, la 3 no se puede evaluar.

### Fase 1 · Medición (desbloquea todo lo demás)

1. **Un partial único de tracking**, `marketplace/partials/tracking.blade.php`,
   incluido **en el layout**, no en cada vista. Esto no es opcional: hay 4 vistas con
   cards (`index`, `category`, `category_official`, `tenant`) más `show` y
   `favorites`, y el skill `marketplace-cards` prohíbe explícitamente duplicar un
   componente compartido entre ellas.
2. **IDs administrables, no hardcodeados**: columnas nuevas en `configurations`
   (base `system`) junto a las `marketplace_og_*` ya existentes —
   `marketplace_meta_pixel_id`, `marketplace_tiktok_pixel_id`, `marketplace_ga4_id`
   y los tokens de las APIs de conversión. Por migración, nunca SQL directo (R1). El
   panel que las edita es `/admin/marketplace/seo`, que ya existe: se le añade una
   pestaña. Si el campo está vacío, el partial **no emite nada** — ni un `<script>`
   vacío.
3. **Los 5 eventos, y su `content_id` tiene que coincidir con el del feed.**
   El feed emite `<g:id>mp_{listing_id}</g:id>`. Si el píxel manda el `item_id` del
   tenant, Meta y TikTok reportan 0 % de coincidencia de catálogo y los anuncios
   dinámicos (DPA / catálogo de vídeo) no arrancan nunca. **`mp_{listing_id}`
   siempre.**

   | Evento | Dónde | Meta | TikTok |
   |---|---|---|---|
   | Ver ficha | `marketplace/show.blade.php` | `ViewContent` | `ViewContent` |
   | Añadir al carrito | respuesta de `MarketplaceCartController` | `AddToCart` | `AddToCart` |
   | Iniciar checkout | `marketplace/checkout.blade.php` | `InitiateCheckout` | `InitiateCheckout` |
   | Compra | `marketplace/order_confirmation.blade.php` | `Purchase` | `CompletePayment` |
   | Lead / contacto | `MarketplaceController@lead` | `Lead` | `SubmitForm` |

4. **Server-side obligatorio para `Purchase`.** El navegador no es fiable: iOS/ATT y
   los bloqueadores se comen una parte de los eventos, y el carrito del marketplace
   se reparte entre varios tenants. El píxel del navegador manda el evento optimista;
   la verdad la manda el servidor desde donde se confirma el pedido, con un
   `event_id` compartido para que la plataforma **deduplique**. Sin `event_id` se
   cuenta doble y el ROAS sale inflado.
   Es trabajo de cola, no de request (R16 y `feedback_http_batch_heavy_work`): un job
   `SendAdsConversion`, y se verifica la **entrega** (`events_received` en la
   respuesta), nunca el encolado.
5. **Consentimiento.** El marketplace es público y de cara a consumidores peruanos
   (Ley 29733). El partial se monta detrás de la aceptación de cookies; si no hay
   banner, ese es el primer entregable de la fase, y lo revisa A17 (seguridad).

### Fase 2 · Atribución (saber de qué anuncio vino el pedido)

El UTM que existe va en dirección contraria: etiqueta el clic que **sale** del
marketplace hacia el tenant. No existe captura del UTM que **entra**.

- Middleware ligero que, en la primera visita, guarda en la sesión del comprador
  `utm_source/medium/campaign/content/term`, más `fbclid`, `ttclid` y `gclid`.
  `ttclid` y `fbclid` no son adorno: son lo que la API de conversiones necesita para
  casar el evento con el clic.
- Columnas de atribución en `marketplace_orders` y `marketplace_leads` (migración).
  Se escriben al crear el pedido, nunca después.
- Con eso, `/admin/marketplace/dashboard` puede contestar la única pregunta que
  importa: **cuánto se gastó y cuánto se vendió, por campaña**.

### Fase 3 · Campañas por tienda y por categoría

Aquí ya se puede gastar dinero con sentido.

- **Exponer los feeds filtrados en el panel.** Un selector de tienda y otro de
  categoría que generen la URL `?tienda=` / `?categoria=` lista para copiar. Es lo
  más barato con más impacto de todo este skill: el backend ya está hecho.
- **Una URL de destino canónica por campaña.** Para tienda,
  `/marketplace/tienda/{subdominio}`; para categoría, `/marketplace/c/{full_slug}` —
  nunca la URL legacy `/marketplace/categoria/{slug}`, que redirige 301 y quema parte
  del presupuesto en el salto.
- **No crees landings nuevas por campaña.** Las páginas de tienda y de categoría ya
  tienen JSON-LD, canonical y están en el sitemap. Una landing paralela duplica
  contenido y compite contra la página orgánica.
- Catálogo de TikTok: TikTok acepta el mismo formato de Google Shopping, así que
  `/feeds/meta-catalog.xml` sirve tal cual — el panel ya lo dice. **No hace falta un
  `TikTokService` para el marketplace central**; el que existe es por tenant y es
  otra cosa (canal externo, dueño A08).

### Fase 4 · Creatividades y contenido

Trabajo de negocio, no de código, pero el sistema lo habilita o lo bloquea:

- La imagen del feed sale del estándar de imagen (`project_imagen_estandar`). Un
  producto que lo incumple se rechaza en el catálogo de Meta: audítalo con
  `php artisan images:audit` **antes** de culpar al feed.
- `items.description` es el nombre del producto; `items.name` está NULL (R3). Un
  título de anuncio construido desde `name` sale vacío y la plataforma rechaza el
  producto sin decir por qué.

## Gotchas que ya costaron tiempo en este repo

- **Blade compila `@push` y `@if` dentro de comentarios `//` de un `<script>`.** Es
  exactamente el tipo de archivo que vas a escribir aquí. Comenta con `{{-- --}}`
  fuera del script, o con `/* */` dentro. Ya rompió el modal del marketplace.
- **`@json()` se rompe con comas anidadas** — usa `{!! json_encode(...) !!}` para el
  payload de los eventos, que lleva arrays de items.
- **El precio puede venir `0`, no `null`** (Element UI). Un `Purchase` con `value: 0`
  envenena el ROAS de la cuenta publicitaria y no se puede borrar después.
- **Un `curl` a una ruta con `auth` devuelve el login con 200.** Para verificar los
  paneles `/admin/marketplace/*`, mira `laravel.log`, no el código HTTP.
- **El nombre de la tienda en el marketplace es una copia congelada** en
  `tenant_name`. Si un anuncio sale con el nombre viejo, el arreglo es
  `php artisan marketplace:refresh-branding`, no tocar la vista.
- **`public/robots.txt` gana**: existen el estático *y* `MarketplaceController@robots`.
  El estático se sirve antes y es el que ve Google. Si editas el método y no el
  archivo, en producción no cambia nada. Edita el archivo.

## Cómo verificar, de verdad

No cierres nada con "debería funcionar":

```bash
curl -s https://ebaemy.com/feeds/meta-catalog.xml | head -40
curl -s "https://ebaemy.com/feeds/meta-catalog.xml?tienda=<sub>" | grep -c "<item>"
curl -s "https://ebaemy.com/feeds/meta-catalog.xml?categoria=<id>" | grep -c "<item>"
curl -s https://ebaemy.com/sitemap-marketplace.xml | grep -c "<loc>"
curl -s https://ebaemy.com/robots.txt
```

- Píxel de navegador: Meta Pixel Helper / TikTok Pixel Helper, en la ficha, en el
  carrito y en la confirmación. **Las tres**, no sólo la home.
- Server-side: la respuesta de la API tiene que traer `events_received: 1`. Un `200`
  con `events_received: 0` es un fallo silencioso (R15).
- Coincidencia de catálogo: en Meta Commerce Manager, el porcentaje de eventos
  casados con el catálogo. Si es 0 %, el `content_id` no es `mp_{listing_id}`.

## Dueños

El código vive en la zona Z2 (base `system`), así que **A07 marketplace-central** es
quien edita `MarketplaceController` y las vistas del marketplace. **A21 crecimiento**
define qué se mide y cómo; **A16** escribe las migraciones; **A17** revisa el
consentimiento y cualquier endpoint público nuevo; **A18** valida; **A19** despliega.
