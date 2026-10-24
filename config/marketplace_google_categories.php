<?php

/*
 * Categoría oficial del marketplace → categoría de producto de Google
 * (google_product_category), para el feed /feeds/meta-catalog.xml.
 *
 * La clave es el prefijo del `full_slug` de marketplace_categories; gana el
 * prefijo más largo que coincida, así una subcategoría sin entrada propia
 * hereda la de su madre y ninguna queda sin categoría. El valor es el ID
 * numérico de la taxonomía oficial (taxonomy-with-ids.es-ES.txt, versión
 * 2021-09-21): Google y Meta aceptan el número en cualquier idioma, y con él
 * aprueban antes el producto, lo clasifican mejor en Shopping y calculan bien
 * impuestos y políticas por categoría.
 *
 * Cada ID está verificado contra esa taxonomía. Al añadir una categoría al
 * árbol del marketplace, buscar su ruta ahí y añadir la fila; si no se añade,
 * hereda la de su categoría madre.
 *
 * `servicios` no lleva fila a propósito: Google Shopping no admite servicios.
 */
return [
    // Hogar — Casa y jardín
    'hogar'                                      => 536,
    'hogar/decoracion'                           => 696,
    'hogar/decoracion/plantas-artificiales'      => 6265, // Flora artificial
    'hogar/decoracion/arreglos-florales'         => 6265,
    'hogar/decoracion/alfombras-y-tapetes'       => 598,
    'hogar/decoracion/espejos'                   => 595,
    'hogar/decoracion/floreros-y-jarrones'       => 602,
    'hogar/muebles'                              => 436,  // Mobiliario
    'hogar/cocina-y-mesa'                        => 638,
    'hogar/cocina-y-mesa/vajilla'                => 673,
    'hogar/cocina-y-mesa/ollas-y-sartenes'       => 6070,
    'hogar/cocina-y-mesa/pequenos-electrodomesticos' => 730,
    'hogar/cocina-y-mesa/organizacion'           => 2948,
    'hogar/iluminacion'                          => 594,
    'hogar/bano'                                 => 574,
    'hogar/limpieza'                             => 623,
    'hogar/textil-hogar'                         => 4171,
    'hogar/textil-hogar/sabanas-y-edredones'     => 569,
    'hogar/textil-hogar/almohadones-y-cojines'   => 4454,
    'hogar/jardin-y-plantas'                     => 689,
    'hogar/jardin-y-plantas/macetas-y-jardineras' => 721,
    'hogar/jardin-y-exterior'                    => 689,

    // Moda — Ropa y accesorios
    'moda'                                       => 166,
    'moda/mujer'                                 => 1604,
    'moda/hombre'                                => 1604,
    'moda/ninos'                                 => 1604,
    'moda/calzado'                               => 187,
    'moda/accesorios'                            => 167,

    // Tecnología — Electrónica
    'tecnologia'                                 => 222,
    'tecnologia/celulares'                       => 267,
    'tecnologia/computacion'                     => 278,
    'tecnologia/audio'                           => 223,
    'tecnologia/video-y-tv'                      => 386,
    'tecnologia/gaming'                          => 3356,

    // Belleza — Salud y belleza
    'belleza'                                    => 469,
    'belleza/maquillaje'                         => 477,
    'belleza/cuidado-de-la-piel'                 => 567,
    'belleza/cuidado-del-cabello'                => 486,
    'belleza/perfumeria'                         => 479,
    'belleza/salud-y-bienestar'                  => 491,
    'belleza/manicure-y-pedicure'                => 473,
    'belleza/depilacion'                         => 4507,

    // Niños y bebés
    'ninos-y-bebes'                              => 537,
    'ninos-y-bebes/panales-y-cuidado'            => 548,
    'ninos-y-bebes/juguetes'                     => 1253,
    'ninos-y-bebes/juguetes/educativos'          => 1262,
    'ninos-y-bebes/juguetes/munecas-y-peluches'  => 1255,
    'ninos-y-bebes/coches-y-sillas'              => 2764,

    // Deportes — Equipamiento deportivo
    'deportes-y-aire-libre'                      => 988,
    'deportes-y-aire-libre/indumentaria-deportiva' => 5322,
    'deportes-y-aire-libre/fitness-y-gimnasio'   => 990,
    'deportes-y-aire-libre/pesas-y-crossfit'     => 990,
    'deportes-y-aire-libre/yoga-y-pilates'       => 999,
    'deportes-y-aire-libre/ciclismo'             => 1025,
    'deportes-y-aire-libre/camping-y-outdoor'    => 1011,

    'alimentos-y-bebidas'                        => 412,

    // Mascotas
    'mascotas'                                   => 2,
    'mascotas/perros'                            => 5,
    'mascotas/gatos'                             => 4,

    // Libros, música y arte
    'libros-musica-y-arte'                       => 8,
    'libros-musica-y-arte/libros'                => 784,
    'libros-musica-y-arte/peliculas-y-series'    => 783,
    'libros-musica-y-arte/arte-y-manualidades'   => 16,
    'libros-musica-y-arte/instrumentos-musicales' => 54,

    'automotor'                                  => 5613,

    // Industria y oficina
    'industria-y-oficina'                        => 922,
    'industria-y-oficina/muebles-de-oficina'     => 6362,
    'industria-y-oficina/herramientas-industriales' => 111,
    'industria-y-oficina/epp-y-seguridad'        => 2047,
    'industria-y-oficina/limpieza-industrial'    => 623,

    // Eventos y fiestas
    'eventos-y-fiestas'                          => 96,
    'eventos-y-fiestas/globos-y-bombas'          => 2587,

    // Coleccionables y hobbies
    'coleccionables-y-hobbies'                   => 8,
    'coleccionables-y-hobbies/juegos-de-mesa'    => 1246,

    // Salud y bienestar
    'salud-y-bienestar'                          => 491,
    'salud-y-bienestar/suplementos-y-vitaminas'  => 525,
    'salud-y-bienestar/movilidad-y-ortopedia'    => 519,
    'salud-y-bienestar/cuidado-personal'         => 2915,
    'salud-y-bienestar/cuidado-personal/cuidado-dental' => 526,

    // Navidad — Decoraciones festivas y estacionales
    'navidad'                                    => 596,
    'navidad/adornos-y-esferas'                  => 3144,
];
