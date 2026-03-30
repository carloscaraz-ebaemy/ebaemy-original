<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Plugins Core — cargan en TODOS los themes
    |--------------------------------------------------------------------------
    */
    'core' => [
        'gsap' => [
            'js'       => 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js',
            'enabled'  => true,
            'priority' => 3,
        ],
        'gsap_scroll' => [
            'js'       => 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js',
            'enabled'  => true,
            'priority' => 4,
        ],
        'instant_page' => [
            'js'       => 'https://instant.page/5.2.0',
            'type'     => 'module',
            'enabled'  => true,
            'priority' => 99,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins Ecommerce — cargan en páginas de tienda
    |--------------------------------------------------------------------------
    */
    'ecommerce' => [
        'swiper' => [
            'css'     => 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
            'js'      => 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
            'enabled' => true,
            'pages'   => ['home', 'product', 'category'],
        ],
        'drift_zoom' => [
            'css'     => 'https://cdn.jsdelivr.net/npm/drift-zoom@1/dist/drift-basic.min.css',
            'js'      => 'https://cdn.jsdelivr.net/npm/drift-zoom@1/dist/Drift.min.js',
            'enabled' => true,
            'pages'   => ['product'],
        ],
        'glightbox' => [
            'css'     => 'https://cdn.jsdelivr.net/npm/glightbox@3/dist/css/glightbox.min.css',
            'js'      => 'https://cdn.jsdelivr.net/npm/glightbox@3/dist/js/glightbox.min.js',
            'enabled' => true,
            'pages'   => ['product'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features por Rubro — habilitan componentes específicos
    |--------------------------------------------------------------------------
    */
    'rubro' => [
        'ropa' => [
            'size_selector'  => true,
            'color_swatches' => true,
            'size_guide'     => true,
            'outfit_builder' => false,
        ],
        'ropa-urbana' => [
            'size_selector'  => true,
            'color_swatches' => true,
            'size_guide'     => true,
        ],
        'ropa-elegante' => [
            'size_selector'  => true,
            'color_swatches' => true,
            'size_guide'     => true,
        ],
        'tecnologia' => [
            'spec_compare' => true,
            'spec_table'   => true,
        ],
        'alimentos' => [
            'nutrition_info'     => true,
            'delivery_scheduler' => true,
            'allergen_filter'    => true,
        ],
        'deportes' => [
            'size_selector'  => true,
            'activity_filter' => true,
        ],
        'farmacia' => [
            'prescription_upload' => true,
            'dosage_calculator'   => true,
        ],
        'ferreteria' => [
            'unit_calculator'    => true,
            'project_estimator'  => true,
        ],
        'lujo' => [
            'appointment_booking' => true,
            'authenticity_verify' => true,
        ],
    ],
];
