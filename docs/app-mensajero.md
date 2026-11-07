# App del mensajero (PWA)

La app del mensajero vive dentro del mismo proyecto, en **`/mensajero`**. Es una página web hecha para el celular
que se instala como app (ícono en la pantalla de inicio, pantalla completa). Usa la misma sesión, los mismos
permisos y los mismos casos de uso que el panel y la API.

## Qué hace

| Pantalla | Qué puede hacer el mensajero |
|---|---|
| **Mi ruta de hoy** (`/mensajero`) | Ver sus entregas pendientes en el orden de la ruta (las que van en camino o con novedad, arriba), cuánto debe cobrar, iniciar y terminar la ruta, ver lo entregado hoy y su caja (efectivo que debe entregar y lo cobrado por Nequi, Daviplata, etc.). Se actualiza sola cada minuto. |
| **Pedido** (`/mensajero/pedidos/{id}`) | Llamar o escribir por WhatsApp al cliente, abrir la dirección en Google Maps o Waze, ver paquetes e historial. **Salir a entregar** (en camino), **Reportar novedad** (con motivo) y **Entregar**. |
| **Entregar** | Nombre de quien recibe, foto con la cámara (se reduce a ~300 KB antes de subirla), firma con el dedo y cobro contra entrega con uno o varios medios de pago (avisa si no cuadra). Adjunta el GPS. |

- Al iniciar sesión, un usuario con rol **Mensajero** llega directo a `/mensajero` (y `/` lo manda ahí).
- Mientras tenga una ruta en curso o un pedido en camino, la app **comparte la ubicación** cada 30 s
  (`POST /mensajero/ubicacion`) y pide que no se apague la pantalla (Wake Lock). El mapa en vivo la muestra.
- Cada cambio de estado y la entrega guardan la ubicación en el historial.
- Sin señal, al abrir la app se ve una página de "Estás sin señal" en vez del error del navegador.

## Instalarla en el celular

**Requisito: HTTPS.** Sin HTTPS el celular no deja usar la cámara, el GPS ni instalar la app
(en `http://192.168.x.x` solo funciona como página web normal, sin GPS).

- **Android (Chrome):** entrar a `https://<dominio>/mensajero` → aparece el aviso **"Instalar"** (o menú ⋮ → "Instalar app").
- **iPhone (Safari):** botón **Compartir** → **"Agregar a inicio"**. La app muestra estas instrucciones.

## Archivos

```
public/mensajero-app/        manifest.json, íconos, página sin conexión
public/sw-mensajero.js       service worker (alcance /mensajero). Nunca guarda páginas ni datos de pedidos.
app/Http/Controllers/Web/DriverAppController.php   pantallas y acciones (rutas driver.* en routes/web.php)
src/Shipping/Infrastructure/Http/DeliverOrderRequest.php   validación de la entrega (compartida con la API)
resources/js/modules/driver/
  pages/DriverHome.vue, pages/DriverOrder.vue
  components/DeliveryForm.vue, SignaturePad.vue, InstallBanner.vue
  layouts/DriverLayout.vue
  device/   ← todo lo que toca el celular: location.ts (GPS), camera.ts (foto), http.ts (sesión), pwa.ts (instalar)
tests/Feature/DriverAppTest.php
```

Si cambias el service worker, sube `VERSION` en `public/sw-mensajero.js` para que los celulares tomen el nuevo.

## Limitación de la PWA y siguiente paso (Capacitor)

El navegador **deja de enviar el GPS si el mensajero bloquea la pantalla o cambia de app** (sobre todo en iPhone).
Para GPS en segundo plano se envuelve esta misma app con **Capacitor** (app Android real):

1. `npm i @capacitor/core @capacitor/cli @capacitor/android` → `npx cap init "ISA Mensajero" com.isatrack.mensajero` → `npx cap add android`.
2. En `capacitor.config.ts` usar `server.url: 'https://<dominio>/mensajero'`: la app carga el sitio, la sesión
   funciona igual y los cambios de pantallas llegan sin publicar una versión nueva.
3. En `resources/js/modules/driver/device/location.ts`, si `Capacitor.isNativePlatform()`, usar un plugin de GPS en segundo
   plano (p. ej. `@capacitor-community/background-geolocation`), que muestra la notificación fija que exige Android.
4. `npx cap open android` → generar el APK/AAB en Android Studio. Se puede repartir el APK directo o publicarlo en Play Store
   (declarando el uso de ubicación).

Pendiente para la etapa siguiente: guardar entregas hechas sin señal y enviarlas cuando vuelva la conexión.
