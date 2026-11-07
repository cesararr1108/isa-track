# CLAUDE.md — ISA Track

Guía para trabajar en este proyecto sin tener que leerlo todo: qué es, dónde está cada cosa, las reglas del negocio y cómo agregar cambios.
**Idioma:** todo en español (código de dominio en inglés; textos, mensajes, comentarios, commits y documentación en español).

## 1. Qué es

SaaS de logística y rastreo de domicilios para empresas de Medellín (multiempresa). Pedidos con paquetes, mensajeros, zonas con tarifa, rutas, prueba de entrega, recaudo contra entrega, cuadre de caja, reportes, permisos por usuario y rastreo público para el cliente.

- **Backend:** PHP 8.3+ · Laravel 13 · PostgreSQL 16 (pruebas en SQLite en memoria) · Sanctum (tokens de la API).
- **Panel web:** Inertia.js + Vue 3 + TypeScript + Vite. Mapas: Leaflet + MapLibre (empaquetados, sin CDN).
- **Prototipo de demostración:** `docs/prototipo/index.html` (HTML suelto, datos en memoria; ya no es la app real).
- **Colores corporativos:** azul profundo `#0B1F3A` (menú, encabezados), verde menta `#00C896` (botones, confirmaciones, "en camino"), ámbar `#FFB800` (alertas, tarifas, puntos de interés). Tokens en `resources/js/shared/styles/tokens.css`.
- **Documentación:** `README.md` (instalación y recetas), `docs/mapa-funciones.pdf` (mapa visual de funciones con capturas, para explicar el sistema), `docs/API.md` (manual de la API con ejemplos reales; PDF en `docs/manual-api.pdf`), `docs/guia-panel-frontend.pdf` (cómo funciona Vue/Vite/Inertia).

## 2. Comandos

```bash
php artisan test                 # 240+ pruebas (SQLite en memoria; no necesita Postgres)
./vendor/bin/pint                # formato PHP (el CI lo exige: pint --test)
npm run build                    # vue-tsc (tipos) + Vite → public/build. Obligatorio antes de subir cambios de frontend
npm run typecheck                # solo tipos
php artisan serve  +  npm run dev    # desarrollo
php artisan migrate:fresh --seed     # datos demo: admin@demo.test / despacho@ / gerencia@ / conductor@demo.test, clave "password"
```

CI (`.github/workflows/tests.yml`): pint, pruebas en Postgres, `composer audit`, `npm audit --audit-level=high`, `npm run build`.

## 3. Arquitectura (hexagonal por módulos)

```
src/<Módulo>/
  Domain/          PHP puro: entidades/agregados, value objects, excepciones de negocio, interfaces de repositorio
  Application/     Casos de uso: <Accion>/<Accion>Handler (+ Command). Puertos (interfaces) en Application/Port
  Infrastructure/  Laravel: Http/ (API), Http/Web/ (panel Inertia), Persistence/ (Eloquent), Adapter/ (otros módulos), <Módulo>ServiceProvider
```

**Regla dura:** `Domain` y `Application` no importan `Illuminate\`, `Laravel\`, `App\` ni `\Infrastructure\` (lo verifica `tests/Unit/ArchitectureTest.php`). Un módulo habla con otro **solo** mediante un puerto en su `Application/Port` y un adaptador en su `Infrastructure/Adapter` (ej.: Shipping pide la tarifa a Zones con `DeliveryFeeQuoter` → `ZonesDeliveryFeeQuoter`).

| Módulo | Responsabilidad | Agregado / piezas clave |
|---|---|---|
| `Shared` | Kernel: `TenantContext` (empresa actual), `PermissionChecker`, `Role`, middlewares `tenant`/`permission`, `AuditLog`, `Clock`, `Money`, `GeoPoint`, `Uuid` | — |
| `Companies` | Empresas (tenant) y marca blanca | `Company`, `Branding` |
| `Fleet` | Transportadores/mensajeros: cédula, teléfono, placa, vehículo, capacidad, foto, última ubicación GPS | `Carrier`, `CarrierProfile`, `VehicleCapacity` |
| `Zones` | Zonas (polígonos) con tarifa de domicilio | `Zone`, `Polygon`, `ZoneResolver` |
| `Shipping` | Pedidos: paquetes, estados, historial, entrega con prueba, pagos, rastreo, notificación | `Order` (agregado central), `TransitionMap`, `DeliveryProof` |
| `Routing` | Rutas por mensajero y día, orden de paradas, optimización | `DeliveryRoute`, `RouteOptimizer`, `LoadLimit` |
| `Cash` | Recaudo del día por mensajero y cierre de caja | `CashClosing` |
| `Reports` | Reportes por rango de fechas (lectura) | `GenerateReportHandler`, `DateRange` |

Fuera de `src/`: `app/Models/User.php` (usuario con rol, permisos extra/quitados, `carrier_id`), `app/Services/UserManagement.php` (alta/edición de usuarios, compartido API/panel), `app/Http/Controllers/` (Auth, Users, `Web/` Login/Dashboard/LiveMap/UserPage), `app/Http/Middleware/` (`HandleInertiaRequests`, `SecurityHeaders`, `EnsureCompanyUser`).

**Dos puertas, mismos casos de uso:**
- `routes/web.php` → panel Inertia (sesión + CSRF). Controladores en `src/*/Infrastructure/Http/Web/*PageController.php`, responden `Inertia::render('<modulo>/pages/<Pagina>', props)` o `back()->with('success', ...)`.
- `routes/api.php` → API JSON `/api/*` (token Sanctum) para la futura app del mensajero e integraciones.
- Validación de formato compartida en FormRequests: `CreateOrderRequest`, `CarrierRequest`, `CarrierPhotoRequest`, `ZoneRequest`.

**Frontend** (`resources/js/`): `app.ts` resuelve `modules/<modulo>/pages/<Pagina>.vue`. `modules/{auth,dashboard,orders,routes,live,fleet,zones,cash,reports,users}`. Compartido en `shared/` (`layouts/AppLayout.vue`, `map/createMap.ts` + `map/useMap.ts`, `ui/`, `lib/format.ts`, `lib/permissions.ts`). Tipos de props en `types/index.ts`. `tracking.ts` = mapa de la página pública `/rastreo/{codigo}` (Blade `resources/views/tracking/show.blade.php`).

**Configuración del negocio:** `config/isa.php` (roles y permisos, catálogo de permisos, menú `navigation`, transiciones, estados y colores, motivos de novedad, prueba de entrega, medios de pago, caja, marca, mapas, bodega, flota).

## 4. Reglas de negocio (dónde vive cada una)

**Multiempresa:** todo lleva `company_id`. Doble barrera: filtro explícito en repositorios + global scope `BelongsToCompany`. Un recurso de otra empresa responde **404** (nunca 403). El superadmin no tiene empresa: usa la API con `X-Company-Id`; el panel lo bloquea (`EnsureCompanyUser`).

**Pedidos (`Shipping/Domain/Order.php`, transiciones en `config/isa.php`):**
- Estados: `created → prepared → assigned → in_route → delivered`; `in_route → incident → in_route|assigned|returned`; `created|prepared → returned`; `assigned → prepared`. `delivered` y `returned` son finales.
- `assigned`, `in_route`, `delivered` exigen mensajero. Reasignar en `assigned` no cambia el estado (queda en historial "Reasignado").
- `incident` exige motivo (`wrong_address`, `customer_absent`, `rejected`, `rescheduled`, `other`).
- `delivered` **solo** con `deliver()` (prueba de entrega); por `/status` → `delivery_requires_proof`. El panel no permite marcar entregado.
- Prueba de entrega obligatoria configurable: nombre de quien recibe, foto, firma (sí); GPS (no). Si falta → `delivery_proof_incomplete`.
- Contra entrega: los pagos deben sumar **exactamente** `cash_on_delivery` (varios medios permitidos); pedido ya pagado no acepta cobros. Dinero siempre entero en pesos (`Money`), nunca float.
- Un mensajero (rol `driver`) solo ve y mueve sus pedidos (`DriverScope`); si no → `order_not_assigned_to_actor`.
- Historial de estados solo inserción (fecha, usuario, GPS, nota, motivo). Fotos/firma en `evidence/{empresa}/{pedido}/`, se borran si la entrega es rechazada.
- Al crear: código de rastreo único `ISA-XXXXXXXX` y tarifa por zona (ver Zonas). `delivery_fee` manual tiene prioridad.
- Notificación al cliente al crear y en `in_route`, `delivered`, `incident` (plantillas en config; hoy `LogCustomerNotifier`, WhatsApp pendiente).

**Zonas (`Zones/Domain`):** polígono de 3 a 500 vértices, área > 0, color `#RRGGBB`, nombre único por empresa (sin importar mayúsculas). Un punto toma la zona activa que lo contiene; si se cruzan, **gana la más pequeña**. Cambiar o borrar una zona no cambia la tarifa de pedidos ya creados.

**Transportadores (`Fleet/Domain`):** cédula solo dígitos (5–15), única por empresa; placa normalizada `ABC12D` (5–7 alfanuméricos); capacidad kg y/o paquetes (null = sin límite); inactivo = no se le asignan pedidos ni rutas. Ubicación GPS: se ignora un reporte más viejo que el último; el mapa en vivo muestra solo las de las últimas 3 h. Fotos: JPG/PNG/WebP, mín. 64×64, se borra la anterior. Un usuario `driver` por transportador.

**Rutas (`Routing/Domain`):** mensajero activo; pedidos en `created|prepared|assigned|incident`, con coordenadas, sin repetir y que no estén en otra ruta abierta (`planned|in_progress`); la carga total no supera la capacidad del vehículo (`LoadLimit`). Orden: vecino más cercano + 2-opt desde la bodega (`ISA_DEPOT_LAT/LNG`). Al programar se **asignan** los pedidos al mensajero. Solo rutas `planned` se reordenan/optimizan. Estados: `planned → in_progress|cancelled`, `in_progress → completed|cancelled`. No se programan días pasados (panel).

**Caja (`Cash/Domain`):** efectivo esperado = solo pagos `cash` del mensajero ese día (Nequi, Daviplata, datáfono se reportan aparte). Un cierre por mensajero y día; guarda la foto de lo esperado. Diferencia ≠ 0 exige nota (`config isa.cash.require_note_on_difference`). Resultado `balanced|short|over`. Tras el cierre, ese mensajero no puede registrar más cobros ese día (`cash_register_already_closed`).

**Reportes (`Reports`):** rango máx. 366 días. Estados, tarifas y zonas = pedidos **creados** en el rango; recaudo = pagos **cobrados** en el rango; novedades = registradas en el rango. Efectividad = entregados / (entregados + devueltos). CSV con `;`, BOM y escape de fórmulas (`=`, `+`, `-`, `@`).

**Usuarios y permisos (`PermissionChecker`, `UserManagement`):** permiso efectivo = permisos del rol + extra − quitados (**quitar gana**; al superadmin no se le quita nada). Comodines `*` y `orders.*`. Roles: `company_admin`, `dispatcher`, `driver`, `viewer` (+ `superadmin`, no asignable). Contraseña mín. 8 con letras y números. Nadie se desactiva a sí mismo. Desactivar o cambiar contraseña cierra sesiones y tokens. Inactivo no entra (login ni API).

**Rastreo público:** sin teléfono, sin dirección exacta, sin usuarios internos (Ley 1581). Primer nombre del destinatario y de quien recibió; mensajero (nombre, vehículo, placa, foto) solo mientras `in_route`; ubicación redondeada a 3 decimales (~110 m) y destino a 2 (~1 km).

**Bitácora (`audit_logs`, `AuditLog`):** login correcto/fallido, alta/edición de usuarios (qué cambió), cierres de caja. Nunca contraseñas.

## 5. Cómo agregar o cambiar cosas (receta)

**Una regla de negocio nueva** (ej.: "no asignar pedidos de más de 30 kg a bicicletas"):
1. Escríbela en el **dominio** del módulo dueño (método del agregado o un servicio de dominio), lanzando una excepción propia en `Domain/Exception/` que extienda `Isa\Shared\Domain\DomainException` (o `NotFoundException` para 404) con un `errorCode()` estable en snake_case y mensaje en español.
2. Si necesita datos de otro módulo: puerto en `Application/Port` + adaptador en `Infrastructure/Adapter` + `bind` en el ServiceProvider. Nunca importar el dominio de otro módulo desde Domain/Application.
3. Si es configurable (un límite, un sí/no), va en `config/isa.php` con `env()` y se inyecta desde el ServiceProvider; no leer `config()` dentro del dominio.
4. Prueba unitaria en `tests/Unit/<Módulo>/` (dominio puro) y de punta a punta en `tests/Feature/` (API y/o panel). Agrega el código de error a la tabla de `docs/API.md` (sección 3.1).
5. No hace falta tocar el renderizado de errores: `bootstrap/app.php` ya traduce `DomainException` a `422 {"error","message"}` en la API y a `errors.general` (mensaje arriba de la página) en el panel.

**Un caso de uso / endpoint nuevo:**
1. `src/<Módulo>/Application/<Accion>/<Accion>Handler.php` (+ `Command` si hay varios datos). Recibe interfaces, usa `TenantContext` para la empresa y `Clock` para la hora.
2. Validación de formato en un FormRequest reutilizable o `$request->validate()` (mensajes salen solos en español; nombres de campos en `lang/es/validation.php` → `attributes`).
3. API: método en `Infrastructure/Http/<X>Controller.php` + ruta en `routes/api.php` con `->middleware('permission:<permiso>')`.
4. Panel: método en `Infrastructure/Http/Web/<X>PageController.php` + ruta en `routes/web.php` dentro del grupo `auth, company.user, tenant` con su permiso; acciones responden `back()->with('success', '…')`.
5. Permiso nuevo: agrégalo al catálogo `permissions` y a los roles en `config/isa.php`.
6. Si es una acción sensible (dinero, permisos, borrar), regístrala con `AuditLog::record()`.

**Una pantalla nueva del panel:**
1. `resources/js/modules/<modulo>/pages/<Pagina>.vue` con `<AppLayout title="…">`, props con `defineProps<…>()` y tipos en `types/index.ts`.
2. Formularios con `useForm` de `@inertiajs/vue3`; errores en `form.errors.<campo>` con `<FieldError>`; mostrar/ocultar botones con `usePermissions().can()` (solo visual: la seguridad está en la ruta).
3. Entrada en `config/isa.php` → `navigation` (si `route` es null sale como "Pronto").
4. Mapas: `useMap(elRef, ({ map, layers, lib }) => …)`; escapar texto en popups con `lib.escapeHtml`.
5. Prueba en `tests/Feature/Panel*Test.php` con `assertInertia(fn (Assert $p) => $p->component('<modulo>/pages/<Pagina>')…)`. Luego `npm run build` (debe pasar `vue-tsc`).

**Un campo nuevo en una entidad:** migración nueva (nunca editar una ya publicada) → Domain (propiedad + validación) → Command/Data y Presenter → repositorio Eloquent (guardar y leer) → FormRequest → tipos TS → formulario Vue → prueba. Ejemplo completo en `docs/guia-panel-frontend.pdf` §7.

**Cambiar una regla configurable** (transiciones, documentos obligatorios, plantillas, roles): solo `config/isa.php` / `.env`; luego `php artisan config:cache` en el servidor.

## 6. Convenciones y seguridad (no romper)

- Dinero entero en pesos; IDs UUID v7 generados en el dominio; fechas con `Clock` (zona `America/Bogota`).
- Controladores delgados: validan, llaman al handler, responden. Nada de reglas de negocio en controladores, Vue ni migraciones.
- Toda ruta privada lleva `permission:` en el servidor; nunca confiar en ocultar botones. Repositorios siempre filtran por `company_id`.
- Panel: sesión + CSRF; sin tokens en el navegador. CSP estricta con nonce (`SecurityHeaders`): **no cargar scripts ni estilos de CDNs** en el panel; instalar con npm y empaquetar. Hosts de mapas permitidos en `SecurityHeaders::MAP_IMG/MAP_CONNECT`.
- Archivos subidos: validar tipo real y tamaño antes de guardar nada; servirlos solo tras verificar permiso y empresa.
- `composer audit` y `npm audit` sin vulnerabilidades altas antes de subir dependencias.
- Mensajes al usuario en español claro: qué pasó y cómo arreglarlo.

## 7. Lecciones aprendidas (problemas que ya pasaron)

- **Sin HTTPS** (`http://192.168.x.x`): el cifrado del historial de Inertia no funciona y el login se quedaba cargando → se cifra solo en contexto seguro (`HandleInertiaRequests::isSecureContext`). COOP/HSTS solo con HTTPS.
- **`composer install --no-dev` no trae Faker** → el seeder no usa factories (crea usuarios con `User::query()->create`).
- **Laravel no trae español** → `lang/es/*.php` y `lang/es.json`. Mensajes de excepción del dominio: escribirlos ya en español.
- **"Vite manifest not found"** → falta `npm ci && npm run build` en el servidor. Si existe `public/hot` en producción, borrarlo.
- **MapLibre ≤ 6.4 tiene XSS** → usar ≥ 6.5 (hoy 6.16, solo ESM, empaquetado con Vite). El prototipo HTML solo usa mapas en imágenes (Esri/OSM).
- **CARTO pide llave** y OSM rechaza `file://` → mapa por defecto: OpenFreeMap "Liberty" (vectorial, sin llave) con respaldo OSM → Esri (`shared/map/createMap.ts`). MapTiler con `MAPTILER_KEY`.
- En Blade, `@json()` con arreglos complejos rompe el parser → armar la variable en `@php` y luego `@json($var)`.

## 8. Despliegue (servidor Ubuntu, `/var/www/isa-track`, Nginx + PHP-FPM, Node 22)

```bash
git pull origin main && composer install --no-dev --optimize-autoloader && npm ci && npm run build \
 && php artisan migrate --force && php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Producción: `APP_ENV=production`, `APP_DEBUG=false`. Con HTTPS: `SESSION_SECURE_COOKIE=true` y `APP_URL=https://…`. Permisos: `chown -R www-data:www-data storage bootstrap/cache`. Las ramas de trabajo se suben también a `main` cuando el usuario lo pide.

## 9. Marketing

`marketing/reel-promo/`: reel de Instagram (video, portada, animación fuente, música original y mezcla). Para la **voz colombiana con IA** (Azure `es-CO-SalomeNeural` / `es-CO-GonzaloNeural`): requiere en el entorno `AZURE_SPEECH_KEY`, `AZURE_SPEECH_REGION` y el dominio `<region>.tts.speech.microsoft.com` permitido; luego `python3 marketing/reel-promo/hacer_reel_con_voz.py --voz salome`. Ver su README. Si el usuario pide "el reel con Salomé", es esto.

## 10. Pendiente

App del mensajero en el celular (PWA o nativa: entrega con foto/firma y envío de GPS a `/api/me/location`), WhatsApp real (`WhatsAppCustomerNotifier` + colas Redis), tiempo real con Reverb, 2FA para administradores, PostGIS para consultas por zona, importación desde Excel/Shopify reutilizando `CreateOrderHandler`, etiquetas QR por paquete.
