---
description: Audita el estándar de imagen del catálogo, explica lo que incumple y repara lo reparable
argument-hint: "[tenant-uuid] [--fix] — sin argumentos audita todos los tenants sin tocar nada"
---

Eres el agente **imagenes** de EBAEMY (`.claude/agents/imagenes.md`). Carga el skill
`ebaemy-imagen-estandar` antes de empezar y sigue estos pasos **en orden**, sin saltarte
ninguno y sin pedir permiso entre uno y otro.

Argumentos recibidos: `$ARGUMENTS`

## 1 · Mide, antes de tocar nada

Corre la auditoría en producción por SSH (`ebaemy@ebaemy.com`, el proyecto está en
`/home/ebaemy/ebaemy/laravel`):

```bash
php artisan images:audit --json
```

Si en `$ARGUMENTS` viene un uuid de tenant, añade `--tenant=<uuid>`.
Guarda ese resultado: es la línea base y la vas a necesitar al final.

## 2 · Interpreta, no vuelques el informe

Para cada motivo de incumplimiento, di **qué significa** y **si tiene arreglo**:

- *pesa más de 300 KB* o *mide más de 1200 px* → esa imagen se saltó el pipeline.
  Reparable con `--fix`.
- *falta la versión `_mp` / `_mobile` / `_medium` / `_small`* → anterior a la fase 6.
  Reparable con `--fix`.
- *la imagen principal no está en disco* → **no es reparable**: no hay de dónde
  regenerar. Comprueba si ese tenant sigue vivo antes de proponer nada
  (un tenant bloqueado responde 503 en su storefront, y eso no es una caída).

Compara con la línea base que el skill tiene documentada (977 con imagen, 804 cumplían,
173 no, a 2026-10-02). Si el número de incumplimientos **subió**, eso es lo primero que
tienes que contar: significa que algo está entrando por fuera de las dos puertas, y hay
que encontrar por dónde antes de reparar.

## 3 · Comprueba que las puertas siguen cerradas

Esto es lo que evita que el problema vuelva. Recorre el repo y verifica:

```bash
grep -rn "el-upload" resources/js/views/tenant --include=*.vue | grep -i "image\|foto"
```

Para cada `<el-upload>` de imagen que encuentres, confirma las tres cosas:

1. `:before-upload="prepararImagen"` — o un método propio que llame a `prepararImagen`.
2. El componente importa `imageCompressor` y lo declara en `mixins`.
3. El `accept` incluye `image/heic,image/heif`.

Y en el backend, que el endpoint que recibe la imagen pase por
`ImageProcessingService::processAndStore` y nunca escriba la ruta a mano.

**Cualquier upload que falle alguno de los tres puntos es un hallazgo**, aunque hoy no
dé error: es por donde entrarán las fotos pesadas mañana. Arréglalo en la puerta
(`prepararImagen`), no formulario por formulario.

## 4 · Repara, solo si te lo pidieron

Si `$ARGUMENTS` incluye `--fix`:

```bash
php artisan images:audit --fix
```

Avisa antes de que es trabajo pesado (reescribe miles de archivos) y **no lo lances si
hay un despliegue en curso**. Si no te lo pidieron, no repares: enseña el número y el
comando exacto, y deja la decisión al usuario.

## 5 · Cierra con el comando, no con una opinión

Vuelve a correr `php artisan images:audit` y enseña el antes y el después. Si quedan
incumplimientos, di cuáles y por qué no se pueden arreglar solos.

## Cómo informas

En español, directo, sin volcar la salida cruda del comando. Una tabla con el reparto
por tenant, los motivos agrupados, y al final **una sola recomendación**: qué harías
ahora. Si todo cumple, dilo en una línea y no inventes trabajo.

Si tocaste código: compila (`npm run build`), commitea, push a **los dos remotes** y
despliega siguiendo el skill `ebaemy-deploy`. Si solo auditaste, no commitees nada.
