{{--
    Preconnect + preload de las primeras imagenes que se ven al abrir la pagina.

    Por que existe:
      Las fotos NO salen de ebaemy.com, salen del subdominio de cada tienda
      (carolayimport.ebaemy.com, lia.ebaemy.com, ...). Una home mezcla cinco
      origenes distintos, asi que la imagen mas grande del primer pliegue
      —la que Google mide como LCP— esperaba a que el navegador descubriera
      el <img>, abriera conexion y negociara TLS con ese subdominio: ~600 ms
      de regalo en movil antes del primer byte.

      Desde el <head> se adelantan dos cosas:
        - preconnect a los origenes de las primeras tarjetas (el handshake va
          en paralelo con la descarga del HTML). Limitado a 4: cada conexion
          abierta de mas tambien cuesta.
        - preload de la primera imagen, la candidata a LCP.

    El preload SOLO sirve si lo que se declara aqui coincide EXACTO con el
    <img> real; si no, la foto se descarga dos veces. De ahi $withSrcset: las
    tarjetas de la rejilla llevan srcset (partials/listing-card) y las del
    carrusel de ofertas no (partials/daily-offers).

    Vars esperadas:
      $items        paginador o coleccion de MarketplaceListing
      $withSrcset   bool — true solo si esos <img> llevan srcset/sizes
--}}
@php
    $plWithSrcset = $withSrcset ?? false;

    // Pagina 2 en adelante y las tandas del scroll infinito no pintan el
    // primer pliegue: adelantar ahi solo roba ancho de banda a lo que si se ve.
    $plPage1 = ! (is_object($items ?? null)
                  && method_exists($items, 'currentPage')
                  && $items->currentPage() > 1);

    // OJO: `collect($paginator)` NO da los productos. El paginador es
    // Arrayable, asi que Collection le llama a toArray() y lo que llega es
    // {current_page, data, total, ...} — tomar los 6 primeros devuelve
    // enteros, y la primera iteracion revienta con «Attempt to read property
    // image_url on int». Se escapo a produccion el 2026-10-08 y dejo en 500
    // la busqueda, las categorias y las paginas de tienda: la home no lo
    // destapaba porque ahi este partial recibe la coleccion de ofertas.
    // `all()` existe tanto en el paginador como en la coleccion y en los dos
    // casos devuelve los elementos.
    $plSource = is_object($items ?? null) && method_exists($items, 'all')
        ? $items->all()
        : ($items ?? []);

    $plOrigins = [];
    $plLcp     = null;

    foreach ($plPage1 ? collect($plSource)->take(6) : collect() as $plItem) {
        if (! is_object($plItem)) {
            continue;
        }

        $plPrimary = $plItem->primary_image_url ?? $plItem->image_url;
        if (! $plPrimary) {
            continue;
        }

        // Mismo criterio que la tarjeta: la miniatura de 512 solo vale cuando
        // se esta pintando la foto del padre (ver partials/listing-card).
        $plThumb = (! empty($plItem->thumb_image_url) && $plPrimary === $plItem->image_url)
            ? $plItem->thumb_image_url
            : null;

        $plSrc = $plThumb ?: $plPrimary;

        if ($plLcp === null) {
            $plLcp = ['src' => $plSrc, 'thumb' => $plThumb, 'primary' => $plPrimary];
        }

        $plScheme = parse_url($plSrc, PHP_URL_SCHEME);
        $plHost   = parse_url($plSrc, PHP_URL_HOST);
        if ($plScheme && $plHost && ! in_array($plScheme . '://' . $plHost, $plOrigins, true)) {
            $plOrigins[] = $plScheme . '://' . $plHost;
        }
    }
@endphp

@push('preload')
    @foreach(array_slice($plOrigins, 0, 4) as $plOrigin)
        <link rel="preconnect" href="{{ $plOrigin }}" crossorigin>
    @endforeach
    @if($plLcp)
        <link rel="preload" as="image" fetchpriority="high"
              href="{{ $plLcp['src'] }}"
              @if($plWithSrcset && $plLcp['thumb'])
                  imagesrcset="{{ $plLcp['thumb'] }} 512w, {{ $plLcp['primary'] }} 1080w"
                  imagesizes="(max-width: 640px) 48vw, 260px"
              @endif>
    @endif
@endpush
