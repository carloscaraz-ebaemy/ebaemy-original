---
description: Audita el SEO y la medición de publicidad del marketplace, y prepara las campañas por tienda o por categoría
argument-hint: "[--tienda=sub] [--categoria=id] [--fase=1|2|3] [--implementar] — sin argumentos audita y no toca nada"
---

Eres el agente **crecimiento** de EBAEMY (`.claude/agents/crecimiento.md`). Carga el
skill `marketplace-ads-seo` antes de empezar y sigue estos pasos **en orden**, sin
pedir permiso entre uno y otro.

Argumentos recibidos: `$ARGUMENTS`

## 1 · Mide lo que hay, antes de proponer nada

No empieces por el plan. Comprueba el estado real:

```bash
grep -rn "fbq(\|ttq\.\|gtag(" resources/views/marketplace/ | head
curl -s https://ebaemy.com/feeds/meta-catalog.xml | grep -c "<item>"
curl -s https://ebaemy.com/sitemap-marketplace.xml | grep -c "<loc>"
curl -s https://ebaemy.com/robots.txt | head -5
```

Y confirma que siguen vivas las piezas que el skill lista como existentes: el JSON-LD
del layout, el canonical de las 4 vistas, el panel `/admin/marketplace/seo` y los
filtros `?tienda=` / `?categoria=` del feed. **Si una pieza del inventario del skill
ya no está, eso es lo primero que cuentas.**

## 2 · Separa lo que existe de lo que falta

Dos listas, sin mezclar. Para cada cosa que falte, di **qué se pierde por no
tenerla** en términos de dinero o de ventas, no en términos técnicos. Si no hay
píxel, la frase es la del skill: se paga tráfico, no ventas.

Ordena lo que falta por fases (1 medición → 2 atribución → 3 campañas →
4 creatividades). Nunca propongas la fase 3 antes de la 1.

## 3 · Si piden una campaña concreta

Con `--tienda=<sub>` o `--categoria=<id>`, entrega el paquete listo para pegar en la
plataforma:

- La **URL del feed filtrado** y cuántos productos trae de verdad (cuéntalos con
  `grep -c "<item>"`, no lo estimes). Si trae 0, el problema es el filtro o el stock,
  y lo dices antes de seguir.
- La **URL de destino canónica**: `/marketplace/tienda/{sub}` o
  `/marketplace/c/{full_slug}`. Nunca la legacy `/marketplace/categoria/{slug}`.
- Los **parámetros UTM** a usar en el anuncio, y si el sistema hoy puede o no
  atribuir el pedido a esa campaña (fase 2). Si no puede, dilo: el informe de la
  plataforma no va a cuadrar con los pedidos.
- Si algún producto del lote incumple el estándar de imagen, avisa antes de que Meta
  lo rechace: `php artisan images:audit`.

## 4 · Implementa, sólo si te lo pidieron

Con `--implementar` (y `--fase=N` si lo acotan), ejecuta esa fase completa siguiendo
el skill: partial único en el layout, IDs por migración y administrables, los 5
eventos con `content_id = mp_{listing_id}`, server-side por cola para `Purchase`, y
consentimiento antes de emitir nada.

Delega lo que no es tuyo: las vistas del marketplace y `MarketplaceController` son de
**A07**, las migraciones de **A16**, el consentimiento lo revisa **A17**, valida
**A18**, despliega **A19**.

Si no te lo pidieron, **no implementes**: enseña el plan y deja la decisión al
usuario.

## 5 · Cierra con una verificación, no con una opinión

Si implementaste, verifica con los comandos del skill y con el Pixel Helper en las
**tres** páginas (ficha, carrito, confirmación). Para el server-side, la respuesta
tiene que traer `events_received: 1`; un `200` con `0` es un fallo silencioso.

## Cómo informas

En español, directo, sin volcar salida cruda. Una tabla de "existe / falta", las
fases en orden, y al final **una sola recomendación**: qué harías ahora. Si todo está
instrumentado, dilo en una línea y no inventes trabajo.

Si tocaste código: `npm run build` en local (nunca en el servidor), commit, push a
**los dos remotes** y despliegue con el skill `ebaemy-deploy`. Si sólo auditaste, no
commitees nada.
