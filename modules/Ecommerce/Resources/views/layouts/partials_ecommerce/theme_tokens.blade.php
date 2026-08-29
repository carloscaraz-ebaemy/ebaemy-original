{{--
    Tokens de color del ecommerce del tenant.

    Emite --primary-h/s/l (legacy, lo que ya consumía todo el CSS) más la
    paleta --theme-* nueva. Va en el <head> de cada layout, antes de los CSS
    de componentes, para que no haya flash de color al cargar.

    Reemplaza el bloque de conversión HEX→HSL que estaba copiado en 5 layouts.
    Ver App\Services\EcommerceThemeTokens.

    Parámetro opcional:
      $config  ConfigurationEcommerce ya cargada, para no repetir la query
               en layouts que ya la tienen a mano.
--}}
<style>{!! \App\Services\EcommerceThemeTokens::cssVariables($config ?? null) !!}</style>
