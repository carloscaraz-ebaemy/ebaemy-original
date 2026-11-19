<?php

namespace App\Support;

/**
 * HTML de vendedores (descripciones de producto) limpio para mostrarse en
 * ebaemy.com, que comparte origen con el panel SuperAdmin y la cuenta del
 * comprador. Lista BLANCA con HTMLPurifier: solo formato y enlaces http/https/
 * mailto. Reemplaza el filtro casero con strip_tags + regex, que se saltaba
 * con `<a href="javascript:…">`, `<span onmouseover=alert(1)>` o
 * `<a/onclick=…>` (auditoría de seguridad 2026-11-19).
 */
class SafeHtml
{
    private static ?\HTMLPurifier $purifier = null;

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return self::purifier()->purify($html);
    }

    private static function purifier(): \HTMLPurifier
    {
        if (self::$purifier) {
            return self::$purifier;
        }

        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,em,b,i,u,ul,ol,li,a[href|title],h2,h3,h4,blockquote,span');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.Nofollow', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        $cache = storage_path('framework/cache/htmlpurifier');
        if (!is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }
        $config->set('Cache.SerializerPath', is_writable($cache) ? $cache : null);

        return self::$purifier = new \HTMLPurifier($config);
    }
}
