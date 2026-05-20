# App Android del Marketplace (TWA) — Guía de build y publicación

Esta carpeta contiene la configuración para empaquetar la PWA del marketplace
(`ebaemy.com/marketplace`) como **app Android** publicable en Google Play,
usando **TWA (Trusted Web Activity)** vía Bubblewrap.

La app es un contenedor delgado: corre la misma PWA, mismo push, mismo
checkout. No hay código duplicado. Cuando actualizás la web, la app se
actualiza sola (salvo cambios de versión/ícono que requieren re-subir el AAB).

---

## Requisitos (una vez)

- **Node.js 18+** (ya lo tenés)
- **JDK 17** — Bubblewrap lo puede instalar solo la primera vez
- **Android SDK** — Bubblewrap lo puede instalar solo la primera vez
- **Cuenta Google Play Console** ($25 único) — https://play.google.com/console
- Instalar Bubblewrap CLI:
  ```bash
  npm install -g @bubblewrap/cli
  ```

---

## Paso 1 — Inicializar el proyecto TWA

Desde una carpeta de trabajo (NO el repo Laravel, ej. `~/ebaemy-android/`):

```bash
bubblewrap init --manifest https://ebaemy.com/manifest-marketplace.json
```

Cuando pregunte, usá los valores de `twa-manifest.json` de esta carpeta:
- Package ID: `com.ebaemy.marketplace`
- App name: `ebaemy Marketplace`
- Launcher name: `ebaemy`
- Display mode: `standalone`
- Status bar color: `#0f8a82`

En el primer init, Bubblewrap **genera el keystore** (`android-keystore.jks`)
y te pide una contraseña. **GUARDÁ ese keystore y la contraseña en un lugar
seguro** — si lo perdés, no podés volver a actualizar la app en Play Store.

---

## Paso 2 — Obtener el SHA-256 fingerprint

```bash
keytool -list -v -keystore android-keystore.jks -alias ebaemy
```

Buscá la línea `SHA256:` y copiá el valor (formato `AB:CD:12:...`).

---

## Paso 3 — Configurar Digital Asset Links en producción

Esto es lo que hace que la app NO muestre la barra del navegador (se ve como
app nativa). El servidor ya sirve `/.well-known/assetlinks.json` dinámicamente
(ruta `twa.assetlinks`), solo falta el fingerprint en el `.env`:

```bash
# En el server, /home/ebaemy/ebaemy/laravel/.env
TWA_PACKAGE_NAME="com.ebaemy.marketplace"
TWA_SHA256_FINGERPRINT="AB:CD:12:...:EF"
```

Luego:
```bash
php artisan config:cache
sudo systemctl reload nginx
```

Verificá que responde bien:
```bash
curl https://ebaemy.com/.well-known/assetlinks.json
```

Debe devolver el JSON con tu package_name y fingerprint.

> IMPORTANTE: cuando subas a Play Store y actives **Play App Signing** (lo
> recomienda Google), Play genera OTRO certificado de firma. Vas a tener que
> AGREGAR ese segundo fingerprint al `.env` (separado por coma):
> `TWA_SHA256_FINGERPRINT="<upload-fp>,<play-signing-fp>"`.
> El fingerprint de Play App Signing está en Play Console → Configuración →
> Integridad de la app → Certificado de firma de apps.

---

## Paso 4 — Build del APK/AAB

```bash
bubblewrap build
```

Genera:
- `app-release-signed.apk` — para probar en un dispositivo (instalación directa)
- `app-release-bundle.aab` — para subir a Google Play

Probar en tu celular antes de publicar:
```bash
bubblewrap install   # con el cel conectado por USB y depuración activada
```

Verificá que:
1. La app abre el marketplace SIN barra de navegador (si aparece la barra,
   el assetlinks.json no está bien configurado — repetí paso 3).
2. Las notificaciones push funcionan (activá con el botón / `ebaemyEnablePush()`).

---

## Paso 5 — Publicar en Google Play

1. Entrá a https://play.google.com/console → Crear app
2. Subí el `app-release-bundle.aab`
3. Completá la ficha de Play Store:
   - Ícono 512×512 (usá `/images/icon-512.png`)
   - Screenshots del teléfono (mínimo 2 — capturá el marketplace)
   - Descripción corta + larga
   - Categoría: Compras
   - **Política de privacidad**: usá `https://ebaemy.com/marketplace/legal/...`
4. Activá **Play App Signing** (recomendado) → agregá ese fingerprint al
   `.env` (ver nota del paso 3)
5. Enviá a revisión (suele tardar 1-3 días)

---

## Actualizaciones futuras

- **Cambios en la web** (productos, precios, UI): se reflejan solos, NO hay que
  re-subir nada a Play Store.
- **Cambios de ícono, nombre, o features de la app**: incrementá
  `appVersionCode` y `appVersionName` en `twa-manifest.json`, `bubblewrap build`,
  subí el nuevo AAB.

---

## Checklist de requisitos PWA (ya cumplidos)

- [x] Servido por HTTPS
- [x] `manifest-marketplace.json` con name, icons 192+512, display standalone, start_url
- [x] Service worker registrado (`sw-marketplace.js`)
- [x] Íconos maskable
- [x] `/.well-known/assetlinks.json` (falta solo el fingerprint en .env)

---

## iOS (futuro)

TWA es solo Android. Para iPhone se necesita Capacitor + cuenta Apple Developer
($99/año) + APNs. Queda para una segunda etapa según lo conversado.
