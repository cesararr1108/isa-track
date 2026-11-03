# ISA Track: logística y rastreo de pedidos (v0.4)

Núcleo del SaaS de logística para empresas de Medellín: **multiempresa, roles,
pedidos con varios paquetes, estados con historial, asignación de transportador,
prueba de entrega (foto, firma, quién recibe y GPS), recaudo contra entrega
(efectivo, Nequi, Daviplata, datáfono), cuadre de caja diario, rastreo público
con marca blanca y notificación al cliente** (por ahora en el log; WhatsApp va
en la siguiente fase).

Stack: **PHP 8.3 · Laravel 13 · PostgreSQL 16** · Sanctum (tokens) · PHPUnit.

---

> **Prototipo navegable:** `docs/prototipo/index.html` es una maqueta en el navegador que aplica las mismas reglas del backend. Tiene menú lateral con: resumen, pedidos, rutas, mapa en vivo, transportadores, zonas y tarifas, caja, reportes, usuarios y permisos, app del mensajero y vista del cliente. Usa Leaflet + OpenStreetMap. Ábrela directamente, sin instalar nada.

**Colores corporativos:** azul profundo `#0B1F3A` (fondos oscuros, encabezados, menú), verde menta `#00C896` (botones principales, confirmaciones, estado "en camino") y ámbar `#FFB800` (alertas suaves, tarifas, puntos de interés). Están en `config/isa.php` (`branding`) y en la página de rastreo.

## 0. Panel web (etapa 1)

La aplicación real ya tiene **panel web** en `http://localhost:8000` (o la IP de tu servidor):
inicio de sesión, menú según permisos, **Resumen**, **Transportadores** y **Zonas y tarifas**.
Las secciones marcadas "Pronto" se construyen en las siguientes etapas; mientras tanto se pueden ver en `/prototipo`.

**Stack (elegido por seguridad primero, luego escalabilidad):** Laravel 13 + Inertia.js + Vue 3 + TypeScript + Vite.

- Sesión de servidor con cookie `httpOnly` y protección CSRF (no hay tokens guardados en el navegador).
- El servidor decide qué página y qué datos ve cada usuario; el menú llega ya filtrado por permisos.
- Todo el JavaScript, las fuentes y el mapa se empaquetan en el propio servidor: la
  Content-Security-Policy solo permite scripts propios con *nonce*.
- Login con máximo 5 intentos por minuto por correo e IP, regeneración de sesión y usuarios inactivos bloqueados.
- Historial del navegador cifrado: después de cerrar sesión, "atrás" no muestra datos.
- Fotos validadas por tipo real (JPG/PNG/WebP) y tamaño antes de guardar nada.

```
resources/js/
├── app.ts                    Arranque de Inertia
├── tracking.ts               Mapa de la página pública de rastreo
├── modules/<módulo>/         Igual que los módulos del backend
│   ├── pages/                Una página por pantalla (Inertia::render('<módulo>/pages/<Página>'))
│   └── components/
├── shared/
│   ├── layouts/AppLayout.vue Menú lateral, barra superior, mensajes
│   ├── map/createMap.ts      Mapa urbano (OpenFreeMap) con respaldo OSM/Esri
│   ├── ui/  lib/  styles/    Componentes, utilidades y tokens de color corporativo
└── types/                    Tipos TypeScript de los datos del servidor
```

Para compilar el frontend (en tu equipo o en el servidor, cada vez que cambie):

```bash
npm ci
npm run build      # revisa tipos (vue-tsc) y genera public/build
```

En desarrollo: `npm run dev` junto a `php artisan serve`.

## 1. Arrancar en 5 minutos

```bash
composer install
cp .env.example .env
php artisan key:generate

docker compose up -d            # PostgreSQL (con PostGIS) + Redis. O usa tu Postgres local.
php artisan migrate --seed      # crea tablas + datos demo e imprime links de rastreo
php artisan serve               # http://localhost:8000
```

Usuarios demo (contraseña `password`):

| Usuario | Rol |
|---|---|
| super@isa.test | superadmin (envía header `X-Company-Id` para operar una empresa) |
| admin@demo.test | administrador de empresa |
| despacho@demo.test | despachador |
| conductor@demo.test | conductor (vinculado al transportador "Juan Pérez") |
| gerencia@demo.test | solo lectura |

Pruebas: `php artisan test` (usa SQLite en memoria, no necesita Postgres).
Contra Postgres: `DB_CONNECTION=pgsql DB_DATABASE=isa_track_test php artisan test`.
Formato de código: `./vendor/bin/pint`.

---

## 2. La arquitectura en una imagen

```
            HTTP / Consola / Importador Excel / Cola        <- quien "entra"
                              │
        ┌─────────────── Infrastructure ────────────────┐  (Laravel vive SOLO aquí)
        │ Controllers, FormRequests, Eloquent, Providers│
        │      ┌──────────── Application ────────────┐  │
        │      │ Casos de uso (Handlers) + Puertos   │  │
        │      │     ┌─────────── Domain ────────┐   │  │
        │      │     │ Order, Carrier, Company,  │   │  │  PHP puro: reglas del negocio
        │      │     │ Value Objects, Eventos    │   │  │
        │      │     └───────────────────────────┘   │  │
        │      └─────────────────────────────────────┘  │
        └───────────────────────────────────────────────┘
                              │
              PostgreSQL / Log / WhatsApp / Fleet          <- adaptadores de salida
```

**Regla de oro:** las flechas solo apuntan hacia adentro. `Domain` y `Application`
no importan nada de Laravel. Lo garantiza `tests/Unit/ArchitectureTest.php`: si
alguien hace `use Illuminate\...` en el dominio, la prueba falla.

### Estructura de carpetas

```
src/                                  <- TU código (namespace Isa\)
├── Shared/                           Kernel compartido
│   ├── Domain/                       AggregateRoot, DomainException, Uuid, GeoPoint, Clock
│   ├── Application/                  Puertos: EventBus, TransactionManager, TenantContext
│   └── Infrastructure/               Implementaciones Laravel, multiempresa, roles, middlewares
├── Companies/                        Empresas (tenant) + marca blanca
├── Fleet/                            Transportadores: conductores propios o transportadoras
├── Cash/                             Caja: resumen del recaudo diario y cuadre por conductor
└── Shipping/                         Pedidos ← el corazón
    ├── Domain/                       Order (agregado), Package, StatusChange, OrderStatus,
    │                                 TrackingCode, Recipient, Address, eventos, excepciones
    ├── Application/                  Un caso de uso por carpeta:
    │   ├── CreateOrder/              CreateOrderCommand (datos) + CreateOrderHandler (lógica)
    │   ├── AssignCarrier/
    │   ├── ChangeOrderStatus/
    │   ├── DeliverOrder/             Entrega con prueba + pagos contra entrega
    │   ├── GetOrder/  ListOrders/  TrackOrder/
    │   ├── NotifyCustomer/           Reacciona al evento OrderStatusChanged
    │   └── Port/                     Interfaces que el caso de uso necesita del exterior
    └── Infrastructure/
        ├── Persistence/              Modelos Eloquent + EloquentOrderRepository
        ├── Adapter/                  FleetCarrierDirectory, RandomTrackingCodeGenerator...
        ├── Notification/             LogCustomerNotifier (luego WhatsAppCustomerNotifier)
        ├── Http/                     Controladores y validación
        └── ShippingServiceProvider   Une cada puerto con su adaptador

app/            Lo mínimo que Laravel espera: User y AuthController
config/isa.php  ★ Configuración del negocio (roles, estados, transiciones, mensajes)
routes/api.php  Todas las rutas de la API con su permiso
database/       Migraciones (esquema) y seeder demo
tests/          Unit (dominio puro, sin BD) y Feature (HTTP + BD)
```

### SOLID, en concreto

| Principio | Dónde se ve |
|---|---|
| **S**: una responsabilidad | Un Handler = un caso de uso. El controlador solo traduce HTTP ↔ comando. |
| **O**: abierto/cerrado | Para enviar por WhatsApp se *agrega* `WhatsAppCustomerNotifier`; no se toca el caso de uso. |
| **L**: sustitución | Cualquier `OrderRepository` (Eloquent, en memoria para pruebas) sirve igual. |
| **I**: interfaces pequeñas | `CarrierDirectory` solo expone lo que Pedidos necesita de Flota (2 métodos). |
| **D**: inversión de dependencias | Los handlers piden interfaces; los `ServiceProvider` deciden la implementación. |

---

## 3. Laravel para quien ya sabe PHP

Lo único de Laravel que necesitas para este proyecto:

| Concepto Laravel | Qué es en PHP "normal" | Dónde está |
|---|---|---|
| **Service Container** | Un contenedor de inyección de dependencias. Si un constructor pide `OrderRepository`, Laravel busca qué clase registraste y la crea. | `*ServiceProvider.php` → `$this->app->bind(Interfaz::class, Implementacion::class)` |
| **Service Provider** | El "bootstrap" de cada módulo: registra bindings y listeners. | `bootstrap/providers.php` lista los providers |
| **Rutas** | Un router: URL + método HTTP → `[Controlador::class, 'metodo']`. | `routes/api.php` (prefijo `/api`), `routes/web.php` |
| **Middleware** | Filtros antes del controlador (auth, empresa, permisos). | `auth:sanctum`, `tenant`, `permission:orders.create` |
| **FormRequest / `validate()`** | Validación del formato de entrada; si falla responde 422 solo. | `CreateOrderRequest`, `$request->validate([...])` |
| **Eloquent** | Un ORM tipo Active Record. Aquí **solo** se usa dentro de los repositorios. | `*/Infrastructure/Persistence` |
| **Migraciones** | Scripts versionados del esquema de BD. | `database/migrations` → `php artisan migrate` |
| **config() / .env** | `config('isa.roles')` lee `config/isa.php`; `env('X')` lee el `.env`. | `config/isa.php` |
| **Eventos** | `Event::listen(Clase::class, ...)`: el EventBus publica y los listeners reaccionan. | `ShippingServiceProvider::boot()` |
| **artisan** | La consola del framework. | `php artisan route:list`, `migrate`, `tinker`, `test` |

**Recorrido de una petición** `POST /api/orders`:

1. `routes/api.php` → middleware `auth:sanctum` (valida el token), `tenant` (fija la empresa), `permission:orders.create`.
2. `OrderController::store()` recibe `CreateOrderRequest` ya validado.
3. `$request->toCommand()` → `CreateOrderCommand` (DTO sin nada de HTTP).
4. `CreateOrderHandler` → `Order::create()` aplica las reglas → `OrderRepository::save()` → publica eventos.
5. `NotifyCustomerOnStatusChange` escucha `OrderStatusChanged` y avisa al cliente.
6. El controlador devuelve JSON con `OrderPresenter`.

---

## 4. Qué se configura sin tocar código (`config/isa.php` y `.env`)

- **Roles y permisos**: qué puede hacer cada rol (con comodines `orders.*`).
- **Transiciones de estado**: p. ej. permitir `incident → in_route` (reintento).
- **Etiquetas y colores de estado**: verde entregado, azul en ruta, ámbar novedad, rojo devuelto.
- **Motivos de novedad**: dirección errada, cliente ausente, rechazado, reprogramado.
- **Código de rastreo**: prefijo y longitud (`ISA_TRACKING_PREFIX`, `ISA_TRACKING_LENGTH`).
- **Notificaciones**: canal (`ISA_NOTIFIER=log`) y plantilla del mensaje por estado.
- **Ciudad por defecto**: `ISA_DEFAULT_CITY`.
- **Prueba de entrega obligatoria**: nombre de quien recibe, foto, firma y GPS se activan o desactivan uno por uno (`ISA_PROOF_PHOTO=false`...).
- **Dónde se guardan fotos y firmas**: `ISA_EVIDENCE_DISK=local` (en `storage/app/private`) o `s3`. Tamaño máximo: `ISA_EVIDENCE_MAX_KB`.
- **Medios de pago** y sus etiquetas.
- **Caja**: exigir nota cuando el efectivo entregado no cuadra (`cash.require_note_on_difference`).

---

## 5. API

Todas las privadas requieren `Authorization: Bearer <token>` y `Accept: application/json`.

| Método | Ruta | Permiso | Descripción |
|---|---|---|---|
| POST | `/api/auth/login` | público | `{email, password}` → `{token, user}` |
| GET | `/api/auth/me` | autenticado | Usuario actual |
| POST | `/api/companies` | `companies.create` | Crear empresa (superadmin) |
| GET/POST | `/api/carriers` | `carriers.view` / `carriers.create` | Transportadores |
| GET | `/api/orders?status=&carrier_id=&search=&page=` | `orders.view` | Listado (el conductor solo ve los suyos) |
| POST | `/api/orders` | `orders.create` | Crear pedido con paquetes |
| GET | `/api/orders/{id}` | `orders.view` | Detalle con historial |
| POST | `/api/orders/{id}/assign` | `orders.assign` | `{carrier_id}` |
| POST | `/api/orders/{id}/status` | `orders.change_status` | `{status, latitude?, longitude?, note?, incident_reason?}` (no sirve para `delivered`) |
| POST | `/api/orders/{id}/deliver` | `orders.change_status` | **multipart**: `receiver_name`, `receiver_document?`, `photo`, `signature`, `latitude?`, `longitude?`, `note?`, `payments[i][method\|amount\|reference]` |
| GET | `/api/orders/{id}/evidence/{photo\|signature}` | `orders.view` | Descarga la foto o la firma |
| GET | `/api/cash/summary?carrier_id=&date=` | `cash.view` | Recaudo del día por medio de pago (el conductor ve el suyo) |
| GET | `/api/cash/closings?date=` | `cash.view` | Cierres de caja del día |
| POST | `/api/cash/closings` | `cash.close` | `{carrier_id, date?, cash_received, notes?}` → cuadre del conductor |
| GET/PUT | `/api/carriers/{id}` | `carriers.view` / `carriers.update` | Datos del mensajero: `document_number` (cédula), `phone`, `email`, `vehicle_plate`, `vehicle_type` (bicycle, motorcycle, car, van, truck, on_foot), `capacity_kg`, `capacity_packages` |
| POST | `/api/carriers/{id}/active` | `carriers.update` | `{active}` activar / desactivar |
| POST/GET | `/api/carriers/{id}/photo` | `carriers.update` / `carriers.view` | Foto del mensajero (multipart `photo`) |
| POST | `/api/me/photo` | autenticado | El mensajero sube su propia foto |
| GET/POST | `/api/zones` | `zones.view` / `zones.manage` | Zonas: `{name, color, delivery_fee, estimated_minutes, polygon: [[lat,lng],...]}` |
| PUT/DELETE | `/api/zones/{id}` | `zones.manage` | Editar / borrar zona |
| GET | `/api/zones/quote?latitude=&longitude=` | `zones.view` | Tarifa del domicilio en ese punto |
| GET/POST | `/api/routes` | `routes.view` / `routes.manage` | Rutas por día: `{carrier_id, date, order_ids[], optimize?, name?}` |
| GET | `/api/routes/{id}` · `/api/routes/{id}/geojson` | `routes.view` | Ruta con paradas, km y la geometría para el mapa |
| PUT | `/api/routes/{id}/stops` | `routes.manage` | Reordenar `{order_ids}` |
| POST | `/api/routes/{id}/optimize` | `routes.manage` | Reordenar para recorrer menos |
| POST | `/api/routes/{id}/status` | `routes.drive` | `{status: in_progress\|completed\|cancelled}` |
| GET | `/api/reports/summary?from=&to=&carrier_id=&zone_id=` | `reports.view` | Reporte por rango de fechas |
| GET | `/api/reports/orders.csv?from=&to=` | `reports.view` | Pedidos del rango en CSV (Excel) |
| GET | `/api/permissions` | `users.manage` | Catálogo de permisos y lo que trae cada rol |
| GET/POST/PUT | `/api/users` · `/api/users/{id}` | `users.manage` | Usuarios: rol, `carrier_id`, `extra_permissions`, `revoked_permissions`, `active` |
| GET | `/api/public/tracking/{codigo}` | público | Rastreo en JSON (sin datos personales) |
| GET | `/api/public/tracking/{codigo}/carrier-photo` | público | Foto del mensajero, solo mientras el pedido va en camino |
| GET | `/rastreo/{codigo}` | público | Página de rastreo móvil con la marca de la empresa |

Ejemplo:

```bash
TOKEN=$(curl -s -X POST localhost:8000/api/auth/login -H 'Accept: application/json' \
  -d email=despacho@demo.test -d password=password | jq -r .token)

curl -s -X POST localhost:8000/api/orders \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"external_reference":"WEB-2001",
       "recipient":{"name":"Pedro Ruiz","phone":"+57 310 000 1122"},
       "address":{"line":"Cl 50 # 40-10","neighborhood":"Boston","reference":"Apto 302"},
       "packages":[{"description":"Caja","weight_kg":1.2},{"description":"Sobre"}]}'
```

Registrar una entrega contra entrega (parte en efectivo, parte por Nequi):

```bash
curl -s -X POST localhost:8000/api/orders/$ID/deliver \
  -H "Authorization: Bearer $TOKEN_CONDUCTOR" -H 'Accept: application/json' \
  -F receiver_name="Laura Gómez" -F photo=@paquete.jpg -F signature=@firma.png \
  -F latitude=6.2088 -F longitude=-75.5679 \
  -F 'payments[0][method]=cash'  -F 'payments[0][amount]=80000' \
  -F 'payments[1][method]=nequi' -F 'payments[1][amount]=40000' -F 'payments[1][reference]=M998877'
```

Los errores de negocio responden `422` con un código estable, por ejemplo
`{"error":"invalid_status_transition","message":"No se puede pasar el pedido de \"created\" a \"in_route\"."}`.

---

## 6. Cómo agregar cosas (recetas)

**Un nuevo caso de uso** (p. ej. "Reprogramar entrega"):
1. Si hay una regla nueva, agrégala como método en `Shipping/Domain/Order.php` y pruébala en `tests/Unit/Shipping/OrderTest.php`.
2. Crea `Shipping/Application/RescheduleOrder/RescheduleOrderCommand.php` + `RescheduleOrderHandler.php` (copia `ChangeOrderStatus` como plantilla).
3. Agrega el método en `OrderController` y la ruta con su permiso en `routes/api.php`.
4. Agrega el permiso al rol que corresponda en `config/isa.php`.

**WhatsApp real:**
1. Crea `Shipping/Infrastructure/Notification/WhatsAppCustomerNotifier.php` que implemente `CustomerNotifier` (API de Meta o Twilio; idealmente despachando un Job a la cola Redis).
2. Regístralo en `ShippingServiceProvider::NOTIFIERS` y pon `ISA_NOTIFIER=whatsapp` en el `.env`.
Ningún caso de uso cambia.

**Un nuevo listener de eventos** (tiempo real con Reverb, estadísticas): escucha
`OrderStatusChanged` en el `boot()` del provider.

---

## 7. Entrega, recaudo y caja: cómo funciona

**Entrega (`DeliverOrderHandler` + `Order::deliver()`)**
1. Solo se puede marcar *entregado* registrando la entrega; `POST /status` con `delivered` se rechaza.
2. La prueba debe cumplir los requisitos configurados; si falta algo responde `delivery_proof_incomplete` con la lista de lo que falta.
3. Fotos y firma se guardan en `evidence/{empresa}/{pedido}/`. Si la entrega es rechazada se borran, para no dejar archivos huérfanos.
4. El rastreo público muestra solo el primer nombre de quien recibió; la foto y la firma solo se ven con login.

**Recaudo contra entrega**
- Al crear el pedido se indica `cash_on_delivery` en pesos (entero; el dinero nunca va en float). Sin ese campo, el pedido ya está pago.
- Los pagos de la entrega deben sumar **exactamente** ese valor; se permiten varios medios en una sola entrega. A un pedido ya pago no se le pueden registrar cobros.

**Cuadre de caja (módulo `Cash`)**
- `GET /cash/summary` suma lo recaudado por conductor y día, agrupado por medio de pago. El *efectivo esperado* es solo lo cobrado en `cash`; Nequi, Daviplata y datáfono se reportan aparte porque no se entregan en mano.
- `POST /cash/closings` registra cuánto efectivo entregó el conductor y calcula la diferencia (`balanced`, `short` o `over`). Si no cuadra, exige una nota.
- Hay un solo cierre por conductor y día, y queda guardada la foto de lo esperado en ese momento. Después del cierre ese conductor no puede registrar más cobros ese día (`cash_register_already_closed`).
- Los módulos se hablan por puertos: Pedidos pregunta a Caja con `CashRegisterStatus`, y Caja lee los pagos de Pedidos con `CollectedPayments`. Ninguno llama directo a las clases del otro.

## 8. Transportadores, zonas, rutas, reportes y permisos (v0.3)

- **Transportadores (`Fleet`)**: cédula (solo dígitos, única por empresa), teléfono, correo, placa normalizada (`abc-12d` → `ABC12D`), tipo de vehículo, capacidad en kg y en paquetes, y foto. Un usuario mensajero se vincula a su transportador y de ahí salen su foto y placa.
- **Zonas (`Zones`)**: polígonos dibujados en el mapa con su tarifa de domicilio. Al crear un pedido con coordenadas se calcula la zona y la tarifa (`DeliveryFeeQuoter`); si dos zonas se cruzan gana la más pequeña. Se puede enviar `delivery_fee` para fijarla a mano.
- **Rutas (`Routing`)**: mensajero + día + pedidos. Valida que el mensajero esté activo, que los pedidos tengan coordenadas y no estén en otra ruta abierta, y que la carga quepa en el vehículo. Ordena las paradas (vecino más cercano + 2-opt desde la bodega `ISA_DEPOT_LAT/LNG`) y asigna los pedidos. `/geojson` entrega la ruta lista para Leaflet.
- **Reportes (`Reports`)**: por rango de fechas (máx. 366 días): totales, efectividad, entregas a tiempo, por día, por mensajero, por zona, recaudo por medio de pago y novedades por motivo. Exporta CSV con `;` y BOM para Excel.
- **Permisos por usuario**: además de su rol, a cada usuario se le dan permisos extra o se le quitan (quitar gana). Un usuario inactivo no entra.
- **Vista del cliente** (`/rastreo/{codigo}`): colores corporativos, barra de progreso, foto, nombre y placa del mensajero mientras va en camino, mapa con su ubicación aproximada y tu barrio, zona y tarifa.

## 9. Decisiones del prototipo y siguientes pasos

- **Multiempresa** con columna `company_id`: doble barrera, con un filtro explícito en los repositorios y un *global scope* (`BelongsToCompany`). Más simple que `stancl/tenancy` para arrancar.
- **IDs UUID v7** generados en el dominio (ordenables por tiempo; no exponen cuántos pedidos hay).
- **Historial de estados de solo inserción**: cada cambio guarda fecha, usuario, ubicación, nota y motivo.
- **Rastreo público** que no expone teléfono, dirección exacta ni usuarios, y redondea la ubicación del conductor a unos 110 m (Ley 1581).
- **Coordenadas en columnas decimales**; migrar a `geography(Point)` de PostGIS cuando lleguen zonas y tarifas.

Pendiente para completar la "primera versión" del documento:
1. ~~Prueba de entrega~~ ✅ y ~~recaudo contra entrega con cuadre de caja~~ ✅ (v0.2).
2. Novedades con foto y reporte de frecuencia por motivo.
3. WhatsApp Business API + colas Redis.
4. App del conductor (PWA) y panel del despachador (Filament o Inertia + Vue).
5. Etiquetas con QR / código de barras por paquete.
6. Importación desde Excel / Shopify / WooCommerce, que reutiliza `CreateOrderHandler`.
7. Estadísticas: a tiempo vs. tarde (`promised_at`), novedades y entregas por conductor.
8. Tiempo real con Laravel Reverb.
