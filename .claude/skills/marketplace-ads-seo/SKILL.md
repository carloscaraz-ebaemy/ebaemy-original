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
| `robots.txt` por host | `MarketplaceController@robots` (central) y `Modules\Ecommerce\...\RobotsController` (tenant) | ✅ |
| Feed Meta / Google Merchant con variantes (`item_group_id`) | `MarketplaceController@metaCatalog` → `/feeds/meta-catalog.xml` | ✅ |
| **Feed filtrado por tienda** | `/feeds/meta-catalog.xml?tienda={subdominio}` | ✅ y expuesto en el panel |
| **Feed filtrado por categoría** (incluye descendencia) | `/feeds/meta-catalog.xml?categoria={id}` | ✅ y expuesto en el panel |
| Panel de SEO administrable (og:title, og:description, og:image, keywords, redes `sameAs`) | `/admin/marketplace/seo` → `resources/views/system/marketplace/seo.blade.php` | ✅ |
| Panel "Conectar catálogo" con las URLs de feed e instrucciones por plataforma | `/admin/marketplace/feeds` → `system/marketplace/feeds.blade.php` | ✅ |
| Click-out con UTM propio hacia el tenant | `MarketplaceListing::tenant_item_url_with_utm` (`utm_source=ebaemy_marketplace`) | ✅ |
| Analítica interna: vistas y clics por producto y día | `marketplace_listing_stats_daily`, `MarketplaceController@recordDailyStat` | ✅ |
| Feeds TikTok y Meta **por tenant** (canal externo, otra cosa) | `app/Services/Marketplace/TikTokService.php`, `MetaFeedService.php` | ✅ |

**Los dos filtros del feed son la pieza clave** de lo que pide el negocio: anunciar
*por tienda* y *por categoría*. Funcionan por query string **y el panel ya los
expone**, con un selector por tienda y otro por categoría
(`system/marketplace/feeds.blade.php`, líneas 86-104). Leer sólo la cabecera de ese
archivo hace creer que no están: léelo entero antes de proponer construirlos.

## La medición — implementada el 2026-10-03

Hasta esa fecha no había nada: ni `fbq`, ni `ttq`, ni `gtag`, ni API de conversiones.
Ahora el circuito completo existe y está **apagado por defecto**. Se enciende en
`/admin/marketplace/seo` → sección *Medición de publicidad*, pegando los IDs de
píxel y los tokens. Sin eso, el código no emite ni una línea.

| Pieza | Archivo |
|---|---|
| Qué se mide y con qué identificadores (fuente única) | `app/Services/Marketplace/AdsTracking.php` |
| Envío server-side a Meta CAPI y TikTok Events API | `app/Services/Marketplace/AdsConversionApi.php` |
| El envío, fuera del camino de la petición | `app/Jobs/Marketplace/SendAdsConversion.php` |
| Píxeles + `window.mpTrack()` + banner de cookies | `resources/views/marketplace/partials/tracking.blade.php` |
| Origen del visitante en sesión (alias `mp.attribution`) | `app/Http/Middleware/CaptureMarketplaceAttribution.php` |
| Consentimiento (`POST /marketplace/ads/consent`) | `app/Http/Controllers/Marketplace/AdsTrackingController.php` |
| Diagnóstico y evento de prueba | `php artisan ads:check [--send]` |
| Config administrable | migración `2026_10_03_000001`, panel en `system/marketplace/seo.blade.php` |
| Atribución en pedidos y leads | migración `2026_10_03_000002` |
| Lo que no puede romperse sin que salte un test | `tests/Unit/Marketplace/AdsTrackingTest.php` |

Si el usuario pregunta por qué no basta con el feed, la respuesta es:

> Sin píxel, TikTok y Meta no saben qué anuncio generó la venta. No pueden optimizar
> hacia compradores, sólo hacia clics. Se paga tráfico, no ventas, y el informe de la
> plataforma nunca cuadra con los pedidos reales.

### Cómo funciona, en cuatro reglas

1. **Las vistas no hablan con `fbq` ni `ttq`.** Llaman a `window.mpTrack(payload)` con
   lo que devuelve `AdsTracking::payload()`, y el partial decide. El día que cambie
   una plataforma se cambia en un archivo, no en seis.
2. **El `content_id` es `mp_{listing_id}`**, el mismo `<g:id>` del feed, y lo
   construye `AdsTracking::contentId()` — un solo sitio en todo el sistema. Si
   divergen, la coincidencia de catálogo es 0 % y los anuncios dinámicos no arrancan.
3. **`event_id` sólo cuando el evento también va por servidor** (`purchase` y `lead`,
   sembrados con el número de pedido y el id del lead). Ponerlo en un evento que
   sólo vive en el navegador es contraproducente: el payload se renderiza una vez, y
   un segundo `AddToCart` legítimo llegaría con el id del primero y se descartaría.
4. **`Purchase` se manda una sola vez por pedido**, con la guarda
   `marketplace_orders.ads_purchase_sent_at`. Hay tres caminos a «pedido pagado»
   (contra entrega, retorno de MercadoPago y webhook) y un `Purchase` duplicado infla
   el ROAS de la cuenta sin posibilidad de corregirlo después.

### Los 5 eventos y dónde se emiten

| Evento | Navegador | Servidor |
|---|---|---|
| `view_content` | `marketplace/show.blade.php` | — |
| `add_to_cart` | `show.blade.php` y `partials/listing-card-script.blade.php` (los **dos** botones) | — |
| `initiate_checkout` | `marketplace/checkout.blade.php` | — |
| `purchase` | `order_confirmation.blade.php` | `MarketplaceCheckoutController::sendPurchaseConversion()` |
| `lead` | `marketplace/thanks.blade.php` (sólo si viene del POST, por flash) | `MarketplaceController@lead` |

El `add_to_cart` se emite **tras** la respuesta del servidor, nunca al pulsar: medirlo
antes contaría carritos que no existieron.

### Atribución

`mp.attribution` va sobre las **5 rutas que son destino de anuncios** (`marketplace`,
`c/{fullSlug}`, `categoria/{slug}`, `tienda/{subdomain}`, `item/{slug}`), no en el
grupo `web` — ahí lo pagarían también los 17 tenants. Guarda `utm_*` más `fbclid`,
`ttclid` y `gclid` en la sesión, **en el primer aterrizaje y sin sobrescribir**: si
alguien llega por un anuncio, se va y vuelve a mano, la venta sigue siendo del
anuncio. Un clic de campaña nueva sí reemplaza.

De ahi salen las columnas de `marketplace_orders` y `marketplace_leads`, y de ahi el
**informe por campaña** del dashboard del SuperAdmin
(`system/marketplace/partials/campaign-report.blade.php`, datos en
`MarketplaceAdminController@dashboard`). Tres cosas que hay que saber antes de
tocarlo:

- **Lo primero que muestra es la cobertura**, no las campañas: cuántos pedidos del
  rango traen origen conocido. Con cobertura 0 % el resto de la tabla no dice nada, y
  la causa casi siempre es que las URLs de destino de los anuncios no llevan UTM — el
  panel lo explica en pantalla en vez de mostrar una tabla vacía.
- **Lee `marketplace_orders` (el pedido padre), no `tenant_marketplace_orders`** como
  el KPI de pedidos de arriba. Los dos números **no coinciden y no deben**: un pedido
  con productos de tres tiendas es 1 aquí y 3 allá. La nota al pie del panel lo dice,
  y un test lo protege, porque si no se reporta como bug.
- **No aplica el filtro de tienda**, a propósito: una campaña es del marketplace y su
  pedido padre puede repartirse entre varias tiendas, así que recortarlo por una sola
  daría un ingreso inflado.

Y dos trampas que ya mordieron:

- **`MarketplaceLead::create()` descarta en silencio** lo que no esté en `$fillable`.
  Las ocho columnas de atribución están añadidas; si se añade otra, va también ahi.
- **Meta no acepta el `fbclid` pelado**: exige `fb.1.{timestamp_ms}.{fbclid}` y
  descarta el evento sin avisar. Lo construye `AdsTracking::metaFbc()`.

### Lo que queda pendiente de la medición

- **El `Purchase` del webhook de MercadoPago.** Hoy se cubre cuando el comprador ve
  la confirmación con `payment_status = 'paid'`. Si paga y nunca vuelve, el evento no
  sale. Enganchar el webhook es la red que falta — es idempotente, se puede llamar
  desde ahi sin riesgo de doblar.
- **Revisión de A17** sobre el banner de consentimiento y el endpoint público.

## Fase 3 · Campañas por tienda y por categoría

El feed filtrado y su UI ya existen (ver la primera tabla). Lo que queda es cómo se
usan:

- **Una URL de destino canónica por campaña.** Para tienda,
  `/marketplace/tienda/{subdominio}`; para categoría, `/marketplace/c/{full_slug}` —
  nunca la URL legacy `/marketplace/categoria/{slug}`, que redirige 301 y quema parte
  del presupuesto en el salto.
- **Añade los UTM a la URL del anuncio.** Es lo que hace que la atribución sirva:
  sin `utm_campaign`, el pedido se guarda sin campaña y el informe no puede separar
  nada.
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

- **Un `@section('title')` literal en una vista anula el campo del panel.** El layout
  hace `@yield('title', $mpOgTitle)`, así que el título configurado en
  `/admin/marketplace/seo` **sólo** se aplica a las páginas que no declaran la
  sección. La portada la declaraba a mano (`'Marketplace ebaemy'`), de modo que el
  campo «título» del panel no controlaba el titular azul de Google — sólo el og:title
  al compartir. Arreglado el 2026-10-03 dejando que la portada caiga al default.
  **Antes de dar por bueno un campo del panel, compruébalo contra el HTML servido:**
  `curl -s <url> | grep -o '<title>[^<]*</title>'`. El panel y lo que ve Google
  pueden no ser lo mismo.

- **Blade compila `@push` y `@if` dentro de comentarios `//` de un `<script>`.** Es
  exactamente el tipo de archivo que vas a escribir aquí. Comenta con `{{-- --}}`
  fuera del script, o con `/* */` dentro. Ya rompió el modal del marketplace.
- **`@json()` se rompe con comas anidadas** — usa `{!! json_encode(...) !!}` para el
  payload de los eventos, que lleva arrays de items.
- **Y Blade también compila esas dos formas dentro del comentario que las explica.**
  Un `/* ... @json() ... */` en el `<script>` se convierte en `<?php echo json_encode(,
  15, 512) ?>` y el archivo deja de parsear; con `{!! !!}` sale un `Unmatched '}'`.
  Pasó al escribir `partials/tracking.blade.php`: no nombres las directivas dentro de
  un `<script>`, descríbelas.
- **`GROUP BY` por alias revienta con `only_full_group_by`**, que es el `sql_mode` por
  defecto de este MySQL 8. Agrupar por el alias de un `COALESCE(...)` del `SELECT` da
  error 1055 y el **dashboard entero devuelve 500**. Hay que repetir la expresión
  completa en `groupByRaw`. Lo encontró ejecutar la query, no leerla.
- **El precio puede venir `0`, no `null`** (Element UI). Un `Purchase` con `value: 0`
  envenena el ROAS de la cuenta publicitaria y no se puede borrar después.
- **Un `curl` a una ruta con `auth` devuelve el login con 200.** Para verificar los
  paneles `/admin/marketplace/*`, mira `laravel.log`, no el código HTTP.
- **El nombre de la tienda en el marketplace es una copia congelada** en
  `tenant_name`. Si un anuncio sale con el nombre viejo, el arreglo es
  `php artisan marketplace:refresh-branding`, no tocar la vista.
- **Ya no hay `public/robots.txt`**, y no lo vuelvas a crear (borrado el 2026-10-03,
  commit `e91a537cb`). nginx servía ese archivo físico antes de llegar a Laravel **en
  cualquier host**, así que los 17 dominios de tenant publicaban las reglas del
  marketplace central: declaraban el sitemap de ebaemy.com en vez del propio y
  bloqueaban sus fotos con `Disallow: /storage/uploads/`. Ahora lo sirve Laravel por
  host. Un `robots.txt` físico es siempre un bug multi-tenant.

## Cómo verificar, de verdad

No cierres nada con "debería funcionar":

```bash
curl -s https://ebaemy.com/feeds/meta-catalog.xml | head -40
curl -s "https://ebaemy.com/feeds/meta-catalog.xml?tienda=<sub>" | grep -c "<item>"
curl -s "https://ebaemy.com/feeds/meta-catalog.xml?categoria=<id>" | grep -c "<item>"
curl -s https://ebaemy.com/sitemap-marketplace.xml | grep -c "<loc>"
curl -s https://ebaemy.com/robots.txt
```

- **Antes que nada, que los Blade compilen.** `view:cache` NO valida sintaxis: compila
  con `Blade::compileString()` y pasa `php -l` al resultado. Es lo único que detecta
  el gotcha de arriba, y lo detecta en segundos.
- `php artisan ads:check` — diagnóstico de configuración; con `--send`, manda un
  evento real y comprueba que la plataforma lo recibe.
- `./vendor/bin/phpunit --filter "AdsTrackingTest|MarketplaceCampaignReportTest"` —
  cubre el `content_id`, la deduplicación, las reglas de atribución y el panel del
  informe en sus casos borde. 21 tests. (El repo arrastra 7
  deprecaciones de PHPUnit en todos sus tests; no son de aquí.)
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
