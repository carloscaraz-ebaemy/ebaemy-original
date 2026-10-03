<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ConfigurationEcommerce;
use Hyn\Tenancy\Environment;

/**
 * Dueño único de /robots.txt para TODOS los hosts.
 *
 * Antes existía `public/robots.txt` (archivo físico). nginx sirve los estáticos
 * antes de llegar a Laravel, en cualquier host, así que ese archivo se servía
 * también en los dominios de tenant: cada tienda publicaba las reglas del
 * marketplace central, declaraba el sitemap de ebaemy.com en lugar del propio y
 * bloqueaba sus fotos de producto con `Disallow: /storage/uploads/`. Este
 * controlador nunca se ejecutaba. Se retiró el archivo y aquí se ramifica.
 *
 * La ruta (modules/Ecommerce/Routes/web.php) no lleva restricción de dominio, y
 * los providers de los módulos se registran antes que los de la app, así que
 * esta acción responde también en ebaemy.com. De ahí el branch por tenant: en el
 * dominio central no hay tenant resuelto — mismo criterio que SitemapController.
 */
class RobotsController extends Controller
{
    public function index()
    {
        // Mismo criterio que SitemapController y que el propio agrupado de
        // routes/web.php: en el dominio central no hay tenant resuelto.
        if (app(Environment::class)->tenant()) {
            try {
                $content = $this->tenantRules();
            } catch (\Throwable $e) {
                // tenantRules() consulta la conexion del tenant. Si falla, un 500
                // en robots.txt es peor que unas reglas conservadoras: Google
                // reintenta y, mientras, trata el sitio como no rastreable.
                $content = $this->tenantFallbackRules();
            }
        } else {
            $content = $this->centralRules();
        }

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * Reglas minimas de tenant cuando no se pudo leer su configuracion.
     * Permiten rastrear la tienda y sus fotos, y declaran su sitemap.
     */
    private function tenantFallbackRules(): string
    {
        $domain = request()->getScheme() . '://' . request()->getHost();

        return <<<TXT
User-agent: *
Allow: /
Allow: /ecommerce/
Disallow: /admin/
Disallow: /api/
Disallow: /dashboard
Disallow: /login
Disallow: /pedido/

Sitemap: {$domain}/ecommerce/sitemap.xml
TXT;
    }

    /**
     * Reglas del marketplace central (ebaemy.com). Copia literal del
     * `public/robots.txt` que estaba en producción: es el que Google ya conoce,
     * así que no se cambia nada al moverlo aquí.
     */
    private function centralRules(): string
    {
        $domain = request()->getScheme() . '://' . request()->getHost();

        return <<<TXT
User-agent: *
Allow: /
Allow: /marketplace
Allow: /marketplace/c/
Allow: /marketplace/categoria/
Allow: /marketplace/item/
Allow: /seller
Disallow: /admin
Disallow: /admin/
Disallow: /login
Disallow: /dashboard
Disallow: /system
Disallow: /api/
Disallow: /tenancy/
Disallow: /storage/uploads/
Disallow: /seller/application/
Disallow: /marketplace/gracias/

# Páginas de cuenta/compra: no aportan a búsqueda y NO deben rankear como
# resultado de marca (antes salía /marketplace/login como resultado #1).
Disallow: /marketplace/login
Disallow: /marketplace/register
Disallow: /marketplace/auth/
Disallow: /marketplace/account
Disallow: /marketplace/cart
Disallow: /marketplace/checkout
Disallow: /marketplace/favoritos
Disallow: /marketplace/favorites

User-agent: GPTBot
Disallow: /

User-agent: ClaudeBot
Allow: /marketplace
Disallow: /admin
Disallow: /login

Crawl-delay: 1

Sitemap: {$domain}/sitemap-marketplace.xml

# Feeds para integración con plataformas externas
# Meta Commerce Manager (Catálogo): {$domain}/feeds/meta-catalog.xml
# Google Merchant Center:           {$domain}/feeds/google-merchant.xml
TXT;
    }

    /**
     * Reglas de la tienda de UN tenant.
     *
     * A diferencia del central, aquí SÍ se permiten las fotos de producto: sin
     * ellas Google Imágenes no indexa el catálogo y las fichas pierden la
     * miniatura en resultados.
     */
    private function tenantRules(): string
    {
        $seo = ConfigurationEcommerce::first();
        // Por defecto permitimos indexación si no existe configuración SEO explícita.
        $indexable = $seo ? (bool) ($seo->indexable ?? true) : true;
        $domain = request()->getScheme() . '://' . request()->getHost();

        if (!$indexable) {
            return "User-agent: *\nDisallow: /\n";
        }

        return <<<TXT
User-agent: *
Allow: /
Allow: /ecommerce/
Allow: /ecommerce/item/

Disallow: /admin/
Disallow: /api/
Disallow: /dashboard
Disallow: /login
Disallow: /register
Disallow: /ecommerce/detail_cart
Disallow: /ecommerce/pay_cart
Disallow: /ecommerce/checkout
Disallow: /ecommerce/cart/
Disallow: /ecommerce/login
Disallow: /ecommerce/stock-check
Disallow: /ecommerce/order/
Disallow: /ecommerce/configuration
Disallow: /ecommerce/feed/

# Enlace que se manda al cliente con sus datos de envío: URL pública por
# external_id, no debe acabar en un índice.
Disallow: /pedido/

Disallow: /storage/
Allow: /storage/uploads/items/
Allow: /storage/uploads/logos/
Allow: /storage/uploads/promotions/
Allow: /storage/uploads/favicons/

Sitemap: {$domain}/ecommerce/sitemap.xml
Sitemap: {$domain}/sitemap.xml
TXT;
    }
}
