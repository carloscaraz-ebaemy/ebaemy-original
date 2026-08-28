{{-- Descripcion del producto para la ficha del ecommerce.
     Unico lugar donde se decide QUE texto se muestra y COMO, para que la
     ficha completa y la vista rapida no se contradigan.
     Prioridad: items.mp_notes (HTML del editor) -> items.name (el campo que el
     formulario del ERP rotula "Descripcion") -> nada. --}}
@php
    // Los comentarios aqui son de PHP: dentro de @php un {{-- --}} no se procesa.
    $descHtml  = trim((string) (data_get($record, 'mp_notes') ?? ''));
    $descPlain = trim((string) (data_get($record, 'name') ?? ''));

    // Si el "nombre secundario"/description del ERP repite el titulo no aporta nada.
    $titleText = trim((string) (data_get($record, 'description') ?? ''));
    if ($descPlain !== '' && mb_strtolower($descPlain) === mb_strtolower($titleText)) {
        $descPlain = '';
    }

    $renderDesc = '';
    if ($descHtml !== '') {
        $allowedTags = '<p><br><strong><em><b><i><u><ul><ol><li><a><h2><h3><h4><h5><blockquote><span><div><table><thead><tbody><tr><th><td><hr>';
        // strip_tags borra la etiqueta pero DEJA su contenido: un <script> se
        // convertia en el texto suelto "alert(1)" dentro de la ficha. Los
        // bloques con contenido no visible se quitan enteros antes.
        $clean = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1\s*>#is', '', $descHtml);
        $clean = preg_replace('#</?(script|style|iframe|object|embed)[^>]*>#i', '', $clean);
        $clean = strip_tags($clean, $allowedTags);
        // Handlers y protocolos peligrosos dentro de las etiquetas permitidas.
        $clean = preg_replace('/on[a-z]+\s*=\s*"[^"]*"/i', '', $clean);
        $clean = preg_replace("/on[a-z]+\s*=\s*'[^']*'/i", '', $clean);
        $clean = preg_replace('/(javascript|vbscript|data)\s*:/i', '', $clean);
        $hasHtml = $clean !== strip_tags($clean);
        // Sin etiquetas, el texto plano igual debe respetar los saltos de linea.
        $renderDesc = $hasHtml ? $clean : nl2br(e($clean));
    } elseif ($descPlain !== '') {
        $renderDesc = nl2br(e($descPlain));
    }
@endphp

@if($renderDesc !== '')
    <div class="ec-product-desc">{!! $renderDesc !!}</div>
@else
    <p class="ec-product-desc ec-product-desc--empty">
        Este producto aun no tiene una descripcion detallada.
    </p>
@endif

@once
    <style>
        .ec-product-desc {
            color: #374151;
            font-size: 14px;
            line-height: 1.7;
            word-break: break-word;
        }
        .ec-product-desc > *:first-child { margin-top: 0; }
        .ec-product-desc > *:last-child { margin-bottom: 0; }
        .ec-product-desc p { margin: 0 0 10px; }
        .ec-product-desc ul,
        .ec-product-desc ol { margin: 0 0 12px; padding-left: 20px; }
        .ec-product-desc li { margin-bottom: 4px; }
        .ec-product-desc h2,
        .ec-product-desc h3,
        .ec-product-desc h4,
        .ec-product-desc h5 {
            margin: 16px 0 8px;
            font-size: 15px;
            font-weight: 600;
            color: #111827;
        }
        .ec-product-desc a { color: #2563eb; text-decoration: underline; }
        .ec-product-desc img { max-width: 100%; height: auto; }
        .ec-product-desc table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 12px;
            font-size: 13px;
        }
        .ec-product-desc th,
        .ec-product-desc td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        .ec-product-desc--empty { color: #9ca3af; font-style: italic; }
        @media (max-width: 575px) {
            .ec-product-desc { font-size: 13.5px; line-height: 1.65; }
        }
    </style>
@endonce
