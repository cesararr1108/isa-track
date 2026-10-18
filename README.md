# ISA Track: logística y rastreo de pedidos (prototipo v0.1)

Núcleo del SaaS de logística para empresas de Medellín: **multiempresa, roles,
pedidos con varios paquetes, estados con historial, asignación de transportador,
rastreo público con marca blanca y notificación al cliente** (por ahora en el log;
WhatsApp va en la siguiente fase).

Stack: **PHP 8.3 · Laravel 13 · PostgreSQL 16** · Sanctum (tokens) · PHPUnit.

---

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
└── Shipping/                         Pedidos ← el corazón
    ├── Domain/                       Order (agregado), Package, StatusChange, OrderStatus,
    │                                 TrackingCode, Recipient, Address, eventos, excepciones
    ├── Application/                  Un caso de uso por carpeta:
    │   ├── CreateOrder/              CreateOrderCommand (datos) + CreateOrderHandler (lógica)
    │   ├── AssignCarrier/
    │   ├── ChangeOrderStatus/
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
| POST | `/api/orders/{id}/status` | `orders.change_status` | `{status, latitude?, longitude?, note?, incident_reason?}` |
| GET | `/api/public/tracking/{codigo}` | público | Rastreo en JSON (sin datos personales) |
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

## 7. Decisiones del prototipo y siguientes pasos

- **Multiempresa** con columna `company_id`: doble barrera, con un filtro explícito en los repositorios y un *global scope* (`BelongsToCompany`). Más simple que `stancl/tenancy` para arrancar.
- **IDs UUID v7** generados en el dominio (ordenables por tiempo; no exponen cuántos pedidos hay).
- **Historial de estados de solo inserción**: cada cambio guarda fecha, usuario, ubicación, nota y motivo.
- **Rastreo público** que no expone teléfono, dirección exacta ni usuarios, y redondea la ubicación del conductor a unos 110 m (Ley 1581).
- **Coordenadas en columnas decimales**; migrar a `geography(Point)` de PostGIS cuando lleguen zonas y tarifas.

Pendiente para completar la "primera versión" del documento:
1. Prueba de entrega: foto, firma, nombre de quien recibe y GPS (subida a S3/disco).
2. Recaudo contra entrega (efectivo, Nequi, Daviplata, datáfono) y cuadre de caja.
3. WhatsApp Business API + colas Redis.
4. App del conductor (PWA) y panel del despachador (Filament o Inertia + Vue).
5. Etiquetas con QR / código de barras por paquete.
6. Importación desde Excel / Shopify / WooCommerce, que reutiliza `CreateOrderHandler`.
7. Estadísticas: a tiempo vs. tarde (`promised_at`), novedades y entregas por conductor.
8. Tiempo real con Laravel Reverb.
