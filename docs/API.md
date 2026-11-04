# Manual de la API de ISA Track

Versión 1 · API REST en JSON para integrar ISA Track con la app del mensajero, tiendas en línea, ERP, Excel u otros sistemas.

> Todos los ejemplos de respuesta de este manual son **respuestas reales** del sistema con los datos de demostración
> (`php artisan migrate:fresh --seed`). Solo se recortaron listas largas (`…`).

## 1. Lo básico

| Tema | Regla |
|---|---|
| URL base | `http://TU-SERVIDOR/api` (ej.: `http://192.168.2.68/api`). Con HTTPS: `https://tu-dominio/api` |
| Formato | JSON en UTF-8. Envía siempre `Accept: application/json` |
| Cuerpo | `Content-Type: application/json`, salvo para subir fotos y registrar entregas (`multipart/form-data`) |
| Autenticación | Token Bearer de Laravel Sanctum: `Authorization: Bearer <token>` |
| Empresa | Cada usuario pertenece a una empresa; la API solo muestra y modifica datos de **su** empresa. No hay que enviar la empresa |
| Permisos | Cada endpoint exige un permiso (ver sección 4). Sin él responde `403` |
| Identificadores | UUID (ej.: `01a1a74f-e69e-72ae-a47f-c241144d3457`) |
| Dinero | Pesos colombianos como **entero**, sin decimales ni puntos: `95000` = $95.000 |
| Fechas | Fecha y hora en ISO 8601 con zona horaria de Colombia: `2026-11-05T18:00:00-05:00`. Días: `AAAA-MM-DD` |
| Coordenadas | Grados decimales: `latitude` entre -90 y 90, `longitude` entre -180 y 180 (Medellín ≈ `6.24`, `-75.58`) |
| Límites | Login: 10 por minuto. Rastreo público: 60 por minuto. GPS del mensajero: 30 por minuto. Al pasarse responde `429` |

### 1.1 Primer llamado en 2 pasos

```bash
TOKEN=$(curl -s -X POST http://192.168.2.68/api/auth/login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"admin@demo.test","password":"password","device_name":"mi-integracion"}' | jq -r .token)

curl -s http://192.168.2.68/api/orders -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

En JavaScript (`fetch`):

```js
const base = 'http://192.168.2.68/api';
const { token } = await (await fetch(`${base}/auth/login`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  body: JSON.stringify({ email: 'admin@demo.test', password: 'password', device_name: 'app' }),
})).json();

const res = await fetch(`${base}/orders?status=assigned`, {
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
});
const { data, meta } = await res.json();
```

En PHP (Guzzle o cURL):

```php
$http = new \GuzzleHttp\Client(['base_uri' => 'http://192.168.2.68/api/', 'http_errors' => false]);
$login = json_decode($http->post('auth/login', ['json' => [
    'email' => 'admin@demo.test', 'password' => 'password', 'device_name' => 'erp',
]])->getBody(), true);

$orders = json_decode($http->get('orders', ['headers' => [
    'Authorization' => 'Bearer '.$login['token'], 'Accept' => 'application/json',
]])->getBody(), true);
```

## 2. Autenticación

### POST /auth/login

Público. Devuelve un token para usar en el resto de llamados. El token no vence solo: se invalida con `/auth/logout`, al desactivar el usuario o al cambiarle la contraseña.

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `email` | texto | sí | Correo del usuario |
| `password` | texto | sí | Contraseña |
| `device_name` | texto | no | Nombre del dispositivo o integración (ej.: `app-juan`, `tienda-shopify`) |

Respuesta `200`:

```json
{
  "token": "1|quBE7PiGnXFajBOt85SvzCXDHKq00OaVnsdQCrak91e31c59",
  "user": {
    "id": "01a1a753-d292-7255-b9d1-61fe1058b586",
    "name": "Ana Admin",
    "email": "admin@demo.test",
    "phone": null,
    "role": "company_admin",
    "active": true,
    "company_id": "01a1a753-d0e6-71fb-a578-d7ac68a86357",
    "carrier_id": null,
    "carrier": null,
    "extra_permissions": [],
    "revoked_permissions": [],
    "permissions": [
      "orders.view",
      …
    ]
  }
}
```

Credenciales erradas o usuario inactivo → `422`:

```json
{
  "message": "Credenciales incorrectas.",
  "errors": {
    "email": ["Credenciales incorrectas."]
  }
}
```

### GET /auth/me

Usuario dueño del token, con sus permisos efectivos y, si es mensajero, su transportador.

```json
{
  "user": {
    "id": "01a1a753-d292-7255-b9d1-61fe1058b586",
    "name": "Ana Admin",
    "email": "admin@demo.test",
    "phone": null,
    "role": "company_admin",
    "active": true,
    "company_id": "01a1a753-d0e6-71fb-a578-d7ac68a86357",
    "carrier_id": null,
    "carrier": null,
    "extra_permissions": [],
    "revoked_permissions": [],
    "permissions": [
      "orders.view",
      …
    ]
  }
}
```

### POST /auth/logout

Invalida el token usado en la petición. Respuesta `204` sin cuerpo.

## 3. Errores

| Código | Cuándo | Cuerpo |
|---|---|---|
| `401` | Falta el token o no es válido | `{"message": "Unauthenticated."}` |
| `403` | El usuario no tiene el permiso, o está desactivado | `{"message": "No tienes permiso para realizar esta acción."}` |
| `404` | El recurso no existe **o es de otra empresa** | `{"error": "order_not_found", "message": "…"}` |
| `422` (validación) | Un campo falta o tiene mal formato | `{"message": "…", "errors": {"campo": ["mensaje"]}}` |
| `422` (negocio) | Se rompe una regla del negocio | `{"error": "codigo_estable", "message": "…"}` |
| `429` | Demasiadas peticiones | Espera el tiempo de la cabecera `Retry-After` |

Error de validación (`errors` trae un arreglo de mensajes por campo, en español):

```json
{
  "message": "El correo ya está registrado. (y 3 errores más)",
  "errors": {
    "email": ["El correo ya está registrado."],
    "password": ["El campo contraseña debe tener al menos 8 caracteres.", "La contraseña debe contener al menos un número."],
    "role": ["El valor elegido para rol no es válido."]
  }
}
```

Error de negocio. El campo `error` es un **código estable**: úsalo en tu código para decidir qué hacer; `message` es para mostrar al usuario.

```json
{
  "error": "invalid_status_transition",
  "message": "No se puede pasar el pedido de \"assigned\" a \"returned\"."
}
```

### 3.1 Códigos de error de negocio

| Código | HTTP | Significado |
|---|---|---|
| `invalid_status_transition` | 422 | El pedido no puede pasar de su estado actual al pedido (ver tabla de estados) |
| `delivery_requires_proof` | 422 | Se intentó marcar `delivered` por `/status`; hay que usar `/deliver` |
| `delivery_proof_incomplete` | 422 | Falta nombre, foto, firma o GPS en la entrega (según configuración) |
| `collection_amount_mismatch` | 422 | Los pagos de la entrega no suman el valor contra entrega |
| `cash_register_already_closed` | 422 | La caja del mensajero ya se cerró ese día; no se aceptan más cobros |
| `carrier_required` | 422 | El pedido necesita un mensajero asignado para ese estado |
| `carrier_not_available` | 422 | El mensajero no existe o está inactivo |
| `incident_reason_required` | 422 | Una novedad necesita `incident_reason` |
| `order_requires_packages` | 422 | El pedido debe tener al menos un paquete |
| `order_not_assigned_to_actor` | 422 | Un mensajero intentó mover un pedido que no es suyo |
| `duplicate_carrier_document` | 422 | Ya existe un mensajero con esa cédula en la empresa |
| `duplicate_zone_name` | 422 | Ya existe una zona con ese nombre |
| `location_outside_coverage` | 422 | El punto no está dentro de ninguna zona activa |
| `route_requires_stops` | 422 | La ruta no tiene pedidos |
| `duplicate_route_stop` | 422 | Un pedido está repetido en la ruta |
| `stop_without_location` | 422 | Un pedido de la ruta no tiene coordenadas |
| `order_not_routable` | 422 | El pedido ya fue entregado, devuelto o va en camino |
| `order_already_in_route` | 422 | El pedido ya está en otra ruta planeada o en curso |
| `route_exceeds_carrier_capacity` | 422 | La carga supera los kg o paquetes del vehículo |
| `route_carrier_not_available` | 422 | El mensajero de la ruta no existe o está inactivo |
| `route_not_editable` | 422 | Solo se reordenan u optimizan rutas planeadas |
| `route_stops_mismatch` | 422 | El nuevo orden no tiene exactamente los mismos pedidos |
| `invalid_route_status_change` | 422 | Cambio de estado de ruta no permitido |
| `cash_already_closed` | 422 | Esa caja ya está cerrada |
| `difference_requires_note` | 422 | El efectivo no cuadra y falta la nota |
| `invalid_argument` | 422 | Un valor no cumple una regla de formato del dominio (placa, cédula, color…) |
| `order_not_found`, `carrier_not_found`, `zone_not_found`, `route_not_found` | 404 | No existe o es de otra empresa |

## 4. Roles y permisos

Cada usuario tiene un **rol**. Además se le pueden dar permisos **extra** o **quitar** permisos del rol (`/users`).

| Permiso | Para qué | Admin | Despacho | Mensajero | Solo lectura |
|---|---|---|---|---|---|
| `orders.view` | Ver pedidos | ✔ | ✔ | ✔ (solo los suyos) | ✔ |
| `orders.create` | Crear pedidos | ✔ | ✔ | | |
| `orders.assign` | Asignar mensajero | ✔ | ✔ | | |
| `orders.change_status` | Cambiar estado y entregar | ✔ | ✔ | ✔ (solo los suyos) | |
| `carriers.view` / `carriers.create` / `carriers.update` | Transportadores | ✔ | solo ver | | solo ver |
| `zones.view` / `zones.manage` | Zonas y tarifas | ✔ | solo ver | | solo ver |
| `routes.view` / `routes.manage` | Ver y programar rutas | ✔ | ✔ | solo ver las suyas | solo ver |
| `routes.drive` | Iniciar/terminar ruta y enviar GPS | ✔ | ✔ | ✔ | |
| `cash.view` / `cash.close` | Caja | ✔ | ✔ | solo ver la suya | solo ver |
| `reports.view` | Reportes | ✔ | ✔ | | ✔ |
| `users.manage` | Usuarios y permisos | ✔ | | | |
| `companies.create` | Crear empresas | solo superadministrador | | | |

## 5. Pedidos

### 5.1 Estados de un pedido

| Estado | Nombre | Puede pasar a |
|---|---|---|
| `created` | Creado | `prepared`, `assigned`, `returned` |
| `prepared` | Alistado | `assigned`, `returned` |
| `assigned` | Asignado | `in_route`, `prepared` |
| `in_route` | En camino | `delivered` (solo con `/deliver`), `incident` |
| `incident` | Novedad | `in_route`, `assigned`, `returned` |
| `delivered` | Entregado | — |
| `returned` | Devuelto | — |

Cada cambio queda en el historial del pedido con fecha, usuario, GPS, nota y motivo. Al crear el pedido y al pasar a `in_route`, `delivered` o `incident` se avisa al cliente final (por ahora el mensaje queda en el log; el envío por WhatsApp es la siguiente fase).

### GET /orders

Permiso `orders.view`. Lista paginada. Un mensajero solo ve sus pedidos.

| Parámetro | Descripción |
|---|---|
| `status` | Filtra por estado (`created`, `assigned`…) |
| `carrier_id` | Pedidos de un mensajero |
| `search` | Busca en código de rastreo, referencia de la tienda o nombre del destinatario |
| `page`, `per_page` | Página (desde 1) y tamaño (máx. 100; por defecto 20) |

`GET /orders?status=created&per_page=2`:

```json
{
  "data": [
    {
      "id": "01a1a753-e26a-70e7-8262-7fb3fd9edf1c",
      "tracking_code": "ISA-HCT4A8YH",
      "external_reference": "WEB-2001",
      "status": "created",
      "carrier_id": null,
      "recipient_name": "Pedro Ruiz",
      "city": "Medellín",
      "neighborhood": "Boston",
      "packages_count": 2,
      "latitude": 6.2477,
      "longitude": -75.5604,
      "delivery_fee": 6000,
      "cash_on_delivery": 95000,
      "zone_name": "Centro - Boston",
      "promised_at": "2026-11-05T18:00:00-05:00",
      "created_at": "2026-11-04T11:10:54-05:00"
    },
    {
      "id": "01a1a753-d521-72b7-a705-3386dc384e75",
      "tracking_code": "ISA-2FU5ZA5Y",
      "external_reference": "WEB-1002",
      "status": "created",
      "carrier_id": null,
      "recipient_name": "María Ochoa",
      "city": "Medellín",
      "neighborhood": "Estadio",
      "packages_count": 1,
      "latitude": 6.2531,
      "longitude": -75.5888,
      "delivery_fee": 7000,
      "cash_on_delivery": 120000,
      "zone_name": "Laureles - Estadio",
      "promised_at": "2026-11-05T17:00:00-05:00",
      "created_at": "2026-11-04T11:10:51-05:00"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 2,
    "total": 2
  }
}
```

### POST /orders

Permiso `orders.create`. Crea el pedido, genera el código de rastreo y la tarifa del domicilio según la zona donde caen las coordenadas.

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `recipient.name` | texto (150) | sí | Destinatario |
| `recipient.phone` | texto (20) | sí | Celular (WhatsApp) |
| `recipient.email` | correo | no | |
| `address.line` | texto (200) | sí | Dirección |
| `address.city` | texto | no | Por defecto la ciudad configurada (Medellín) |
| `address.neighborhood` | texto | no | Barrio |
| `address.reference` | texto | no | Indicaciones (portería, apto) |
| `address.latitude`, `address.longitude` | número | no (juntos) | Sin coordenadas no hay tarifa por zona ni se puede poner en una ruta |
| `packages` | lista (1 a 50) | sí | `[{ "description": "Caja", "weight_kg": 1.2 }]` |
| `cash_on_delivery` | entero | no | Valor a cobrar al entregar. Vacío = ya pagado |
| `delivery_fee` | entero | no | Tarifa manual; si no se envía, la calcula la zona |
| `external_reference` | texto (60) | no | Número del pedido en tu tienda |
| `promised_at` | fecha y hora | no | Hora prometida al cliente (para "entregas a tiempo") |

```json
{
  "external_reference": "WEB-2001",
  "cash_on_delivery": 95000,
  "promised_at": "2026-11-05T18:00:00-05:00",
  "recipient": { "name": "Pedro Ruiz", "phone": "+57 310 000 1122", "email": "pedro@correo.co" },
  "address": { "line": "Cl 50 # 40-10", "neighborhood": "Boston", "reference": "Apto 302",
               "latitude": 6.2477, "longitude": -75.5604 },
  "packages": [ { "description": "Caja", "weight_kg": 1.2 }, { "description": "Sobre" } ]
}
```

Respuesta `201` (el pedido completo):

```json
{
  "data": {
    "id": "01a1a753-e26a-70e7-8262-7fb3fd9edf1c",
    "tracking_code": "ISA-HCT4A8YH",
    "external_reference": "WEB-2001",
    "status": "created",
    "carrier_id": null,
    "promised_at": "2026-11-05T18:00:00-05:00",
    "created_at": "2026-11-04T11:10:54-05:00",
    "cash_on_delivery": 95000,
    "delivery_fee": 6000,
    "zone": {
      "id": "01a1a753-d0fe-733b-b31a-9920e83d1f13",
      "name": "Centro - Boston"
    },
    "delivery": null,
    "recipient": {
      "name": "Pedro Ruiz",
      "phone": "+57 310 000 1122",
      "email": "pedro@correo.co"
    },
    "address": {
      "city": "Medellín",
      "neighborhood": "Boston",
      "reference": "Apto 302",
      "latitude": 6.2477,
      "longitude": -75.5604
    },
    "packages": [
      {
        "id": "01a1a753-e26b-72b1-8559-aaab6fcc61be",
        "code": "ISA-HCT4A8YH-1",
        "description": "Caja",
        "weight_kg": 1.2
      },
      {
        "id": "01a1a753-e26b-72b1-8559-aaab6ffc59ff",
        "code": "ISA-HCT4A8YH-2",
        "description": "Sobre",
        "weight_kg": null
      }
    ],
    "history": [
      {
        "status": "created",
        "occurred_at": "2026-11-04T11:10:54-05:00",
        "changed_by": "01a1a753-d292-7255-b9d1-61fe1058b586",
        "latitude": null,
        "longitude": null,
        "note": null,
        "incident_reason": null
      }
    ]
  }
}
```

### GET /orders/{id}

Permiso `orders.view`. Pedido completo con paquetes, historial y, si ya se entregó, la prueba de entrega y los pagos. Mismo formato que la respuesta de crear.

### POST /orders/{id}/assign

Permiso `orders.assign`. Asigna o reasigna el mensajero.

```json
{ "carrier_id": "01a1a74f-d52a-712a-a26d-823eb134b706" }
```

### POST /orders/{id}/status

Permiso `orders.change_status`. Cambia el estado (no sirve para `delivered`).

| Campo | Obligatorio | Descripción |
|---|---|---|
| `status` | sí | `prepared`, `assigned`, `in_route`, `incident`, `returned` |
| `incident_reason` | si `status` = `incident` | `wrong_address`, `customer_absent`, `rejected`, `rescheduled`, `other` |
| `latitude`, `longitude` | no | Dónde está el mensajero. Al pasar a `in_route` se muestra aproximada al cliente |
| `note` | no | Texto libre (máx. 500) |

```json
{ "status": "in_route", "latitude": 6.2480, "longitude": -75.5650 }
```

### POST /orders/{id}/deliver

Permiso `orders.change_status`. Registra la entrega con su **prueba**. Se envía como `multipart/form-data` (lleva archivos). Por defecto son obligatorios el nombre de quien recibe, la foto y la firma (configurable en `config/isa.php`).

| Campo | Descripción |
|---|---|
| `receiver_name` | Quién recibe |
| `receiver_document` | Documento (opcional) |
| `photo` | Foto del paquete entregado (imagen, máx. 8 MB) |
| `signature` | Firma (imagen PNG del trazo) |
| `latitude`, `longitude` | GPS del lugar de entrega |
| `note` | Nota opcional |
| `payments[i][method]` | `cash`, `nequi`, `daviplata`, `card_terminal`, `bank_transfer` |
| `payments[i][amount]` | Entero en pesos |
| `payments[i][reference]` | Número de aprobación (Nequi, datáfono…) |

Los pagos deben sumar **exactamente** el `cash_on_delivery` del pedido; se puede dividir en varios medios.

```bash
curl -X POST http://192.168.2.68/api/orders/$ID/deliver \
  -H "Authorization: Bearer $TOKEN_MENSAJERO" -H 'Accept: application/json' \
  -F receiver_name="Pedro Ruiz" -F receiver_document=1020304050 \
  -F photo=@paquete.jpg -F signature=@firma.png \
  -F latitude=6.2477 -F longitude=-75.5604 \
  -F 'payments[0][method]=cash'  -F 'payments[0][amount]=50000' \
  -F 'payments[1][method]=nequi' -F 'payments[1][amount]=45000' -F 'payments[1][reference]=M998877'
```

Respuesta `200` (el pedido, ahora `delivered`, con la prueba):

```json
{
  "delivered_at": "2026-11-04T11:10:55-05:00",
  "receiver_name": "Pedro Ruiz",
  "receiver_document": "1020304050",
  "has_photo": true,
  "has_signature": true,
  "latitude": 6.2477,
  "longitude": -75.5604,
  "collected_total": 95000,
  "payments": [
    {
      "method": "cash",
      "amount": 50000,
      "reference": null
    },
    {
      "method": "nequi",
      "amount": 45000,
      "reference": "M998877"
    }
  ]
}
```

Errores frecuentes: `delivery_proof_incomplete` ("Falta información en la prueba de entrega: foto, firma."), `collection_amount_mismatch`:

```json
{
  "error": "collection_amount_mismatch",
  "message": "El pedido debe recaudar $95.000 y se registraron $90.000."
}
```

### GET /orders/{id}/evidence/{photo|signature}

Permiso `orders.view`. Descarga la foto o la firma de la entrega (imagen).

## 6. Transportadores (mensajeros)

### GET /carriers

Permiso `carriers.view`. Parámetro opcional `only_active=1`.

```json
{
  "id": "01a1a753-d0f1-70bb-8171-4eaf23758235",
  "name": "Camila Rendón",
  "type": "own_driver",
  "document_number": "43987123",
  "phone": "+57 310 222 4455",
  "email": null,
  "vehicle_plate": "JKL345",
  "vehicle_type": "van",
  "capacity_kg": 600,
  "capacity_packages": 80,
  "has_photo": false,
  "last_location": null,
  "active": true
}
```

### POST /carriers · PUT /carriers/{id}

Permisos `carriers.create` / `carriers.update`. La placa se normaliza (`xyz-12a` → `XYZ12A`) y la cédula queda solo con dígitos.

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `name` | texto | sí | Nombre completo o razón social |
| `type` | texto | sí | `own_driver` (mensajero propio) o `external_company` (transportadora) |
| `document_number` | texto | no | Cédula (5 a 15 dígitos, única en la empresa) |
| `phone` | texto | no | |
| `email` | correo | no | |
| `vehicle_type` | texto | no | `motorcycle`, `bicycle`, `car`, `van`, `truck`, `on_foot` |
| `vehicle_plate` | texto | no | 5 a 7 letras o números |
| `capacity_kg` | número | no | Peso máximo por ruta. Vacío = sin límite |
| `capacity_packages` | entero | no | Paquetes máximos por ruta. Vacío = sin límite |

```json
{ "name": "Luisa Gil", "type": "own_driver", "document_number": "43.111.222",
  "phone": "+57 310 555 1234", "vehicle_plate": "xyz-12a", "vehicle_type": "motorcycle",
  "capacity_kg": 35, "capacity_packages": 12 }
```

Respuesta `201`:

```json
{
  "id": "01a1a753-e1d7-709e-bbc1-248c3d316dd6",
  "data": {
    "id": "01a1a753-e1d7-709e-bbc1-248c3d316dd6",
    "name": "Luisa Gil",
    "type": "own_driver",
    "document_number": "43111222",
    "phone": "+57 310 555 1234",
    "email": "luisa@empresa.co",
    "vehicle_plate": "XYZ12A",
    "vehicle_type": "motorcycle",
    "capacity_kg": 35,
    "capacity_packages": 12,
    "has_photo": false,
    "last_location": null,
    "active": true
  }
}
```

### Otras operaciones

| Método y ruta | Permiso | Qué hace |
|---|---|---|
| `GET /carriers/{id}` | `carriers.view` | Un transportador |
| `POST /carriers/{id}/active` | `carriers.update` | `{"active": false}` lo desactiva (no se le pueden asignar pedidos) |
| `POST /carriers/{id}/photo` | `carriers.update` | Sube la foto: `multipart`, campo `photo` (JPG, PNG o WebP; mín. 64×64 px) |
| `GET /carriers/{id}/photo` | `carriers.view` | Descarga la foto |

## 7. App del mensajero

Estos endpoints los usa el propio mensajero con su token (usuario de rol `driver`).

| Método y ruta | Permiso | Qué hace |
|---|---|---|
| `POST /me/photo` | autenticado | Sube su propia foto (`multipart`, campo `photo`). El cliente la ve mientras su pedido va en camino |
| `POST /me/location` | `routes.drive` | Reporta su GPS: `{"latitude": 6.248, "longitude": -75.565}`. Respuesta `204`. Máx. 30 por minuto (recomendado: cada 20–30 s mientras está en ruta). Alimenta el mapa en vivo |
| `GET /orders` | `orders.view` | Solo devuelve sus pedidos |
| `GET /routes?date=AAAA-MM-DD` | `routes.view` | Solo devuelve sus rutas |
| `POST /routes/{id}/status` | `routes.drive` | Inicia (`in_progress`) o termina (`completed`) su ruta |
| `POST /orders/{id}/status` y `/deliver` | `orders.change_status` | Salir hacia la entrega, reportar novedad y entregar (solo sus pedidos) |
| `GET /cash/summary` | `cash.view` | Su recaudo del día |

## 8. Zonas y tarifas

### GET /zones

Permiso `zones.view`. Parámetro opcional `only_active=1`. `polygon` es la lista de vértices `[latitud, longitud]`; `center` es el punto central.

```json
{
  "id": "01a1a753-d0fc-722e-ba91-addc21108e69",
  "name": "Belén",
  "color": "#A855F7",
  "delivery_fee": 7500,
  "estimated_minutes": 50,
  "active": true,
  "polygon": [
    [6.235, -75.615],
    …
  ],
  "center": [6.2185, -75.5995]
}
```

### POST /zones · PUT /zones/{id} · DELETE /zones/{id}

Permiso `zones.manage`. Si dos zonas se cruzan, se usa la más pequeña.

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `name` | texto (80) | sí | Único en la empresa |
| `polygon` | lista | sí | 3 a 500 puntos `[lat, lng]` |
| `delivery_fee` | entero | sí | Precio del domicilio en pesos |
| `color` | `#RRGGBB` | no | Por defecto `#00C896` |
| `estimated_minutes` | entero | no | Tiempo estimado de entrega |
| `active` | booleano | no | `false` = pausada (no cotiza) |

```json
{ "name": "Sabaneta", "color": "#00C896", "delivery_fee": 11000, "estimated_minutes": 60,
  "polygon": [[6.160, -75.625], [6.160, -75.600], [6.140, -75.600], [6.140, -75.625]] }
```

`DELETE` responde `204`. Los pedidos ya creados conservan su tarifa.

### GET /zones/quote?latitude=…&longitude=…

Permiso `zones.view`. Cotiza el domicilio a un punto (útil en el checkout de una tienda).

```json
{
  "data": {
    "zone_id": "01a1a753-d0f7-72d0-8c16-f89c3f55de7f",
    "zone_name": "El Poblado",
    "color": "#00C896",
    "delivery_fee": 8000,
    "estimated_minutes": 45
  }
}
```

Fuera de cobertura → `422`:

```json
{
  "error": "location_outside_coverage",
  "message": "La dirección está fuera de las zonas de cobertura."
}
```

## 9. Rutas

### POST /routes

Permiso `routes.manage`. Programa una ruta: valida que el mensajero esté activo, que los pedidos tengan coordenadas y no estén en otra ruta abierta, y que la carga quepa en el vehículo. Con `optimize: true` ordena las paradas para recorrer menos desde la bodega. **Asigna** los pedidos al mensajero.

| Campo | Obligatorio | Descripción |
|---|---|---|
| `carrier_id` | sí | Mensajero |
| `order_ids` | sí | Lista de 1 a 200 pedidos (estados `created`, `prepared`, `assigned` o `incident`) |
| `date` | no | Día `AAAA-MM-DD` (por defecto hoy) |
| `name` | no | Nombre de la ruta |
| `optimize` | no | `true` por defecto |
| `start.latitude`, `start.longitude` | no | Punto de partida; por defecto la bodega (`ISA_DEPOT_LAT/LNG`) |

Respuesta `201`:

```json
{
  "data": {
    "id": "01a1a753-e355-7049-8f9f-db21f58711d8",
    "name": "Ruta norte",
    "date": "2026-11-04",
    "status": "planned",
    "carrier": {
      "id": "01a1a753-d0f1-70bb-8171-4eaf23758235",
      "name": "Camila Rendón",
      "vehicle_plate": "JKL345",
      "vehicle_type": "van",
      "capacity_kg": 600,
      "capacity_packages": 80
    },
    "start": {
      "latitude": 6.212,
      "longitude": -75.585
    },
    "stops_count": 1,
    "delivered_count": 0,
    "total_weight_kg": 2.5,
    "total_packages": 1,
    "distance_km": 4.59,
    "created_at": "2026-11-04T11:10:55-05:00",
    "started_at": null,
    "finished_at": null,
    "stops": [
      {
        "sequence": 1,
        "order_id": "01a1a753-d521-72b7-a705-3386dc384e75",
        "tracking_code": "ISA-2FU5ZA5Y",
        "status": "assigned",
        "recipient_name": "María Ochoa",
        "address": "Cra 70 # 44-20",
        "neighborhood": "Estadio",
        "cash_on_delivery": 120000,
        "latitude": 6.2531,
        "longitude": -75.5888,
        "weight_kg": 2.5,
        "packages": 1,
        "leg_km": 4.59
      }
    ]
  }
}
```

### Otras operaciones

| Método y ruta | Permiso | Qué hace |
|---|---|---|
| `GET /routes` | `routes.view` | Filtros: `date`, `from`, `to`, `carrier_id`, `status` (`planned`, `in_progress`, `completed`, `cancelled`) |
| `GET /routes/{id}` | `routes.view` | Una ruta con sus paradas (mismo formato que crear) |
| `GET /routes/{id}/geojson` | `routes.view` | La ruta en GeoJSON para pintarla en Leaflet, Mapbox o Google Maps |
| `PUT /routes/{id}/stops` | `routes.manage` | Reordena: `{"order_ids": [...]}` con los mismos pedidos en otro orden |
| `POST /routes/{id}/optimize` | `routes.manage` | Reordena automáticamente (solo rutas planeadas) |
| `POST /routes/{id}/status` | `routes.drive` | `{"status": "in_progress" \| "completed" \| "cancelled"}` |

`GET /routes/{id}/geojson`:

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "geometry": {
        "type": "LineString",
        "coordinates": [
          [-75.585, 6.212],
          [-75.5888, 6.2531]
        ]
      },
      "properties": {
        "kind": "path",
        "distance_km": 4.59
      }
    },
    …
  ]
}
```

## 10. Caja

| Método y ruta | Permiso | Qué hace |
|---|---|---|
| `GET /cash/summary?carrier_id=&date=` | `cash.view` | Recaudo del día de un mensajero por medio de pago y efectivo que debe entregar. El mensajero ve el suyo sin enviar `carrier_id` |
| `GET /cash/closings?date=` | `cash.view` | Cierres de caja del día |
| `POST /cash/closings` | `cash.close` | Cierra la caja: `{"carrier_id", "date"?, "cash_received", "notes"?}`. Si no cuadra exige `notes` |

`GET /cash/summary`:

```json
{
  "data": {
    "carrier_id": "01a1a753-d0ee-7136-9e00-e5f139a2ccc4",
    "date": "2026-11-04",
    "collected": {
      "orders_count": 2,
      "totals_by_method": {
        "cash": 90000,
        "nequi": 65000
      },
      "total": 155000,
      "expected_cash": 90000
    },
    "closing": null
  }
}
```

`POST /cash/closings` con faltante y nota → `201`:

```json
{
  "data": {
    "id": "01a1a753-e3be-73ea-a14a-c6eabadeaa9e",
    "carrier_id": "01a1a753-d0ee-7136-9e00-e5f139a2ccc4",
    "date": "2026-11-04",
    "expected": {
      "orders_count": 2,
      "totals_by_method": {
        "cash": 90000,
        "nequi": 65000
      },
      "total": 155000,
      "expected_cash": 90000
    },
    "cash_received": 85000,
    "difference": -5000,
    "result": "short",
    "notes": "Faltan $5.000, se descuentan de la liquidación",
    "closed_by": "01a1a753-d292-7255-b9d1-61fe1058b586",
    "closed_at": "2026-11-04T11:10:55-05:00"
  }
}
```

Después del cierre, ese mensajero no puede registrar más cobros ese día (`cash_register_already_closed`). Cada cierre queda en la bitácora de auditoría.

## 11. Reportes

| Método y ruta | Permiso | Qué hace |
|---|---|---|
| `GET /reports/summary?from=&to=&carrier_id=&zone_id=` | `reports.view` | Totales, por día, por mensajero, por zona, recaudo por medio y novedades por motivo. Rango máximo 366 días. Sin fechas: últimos 7 días |
| `GET /reports/orders.csv?from=&to=&carrier_id=&zone_id=` | `reports.view` | Pedidos del rango en CSV para Excel (separador `;`, UTF-8 con BOM) |

`GET /reports/summary?from=2026-11-04&to=2026-11-04` (recortado):

```json
{
  "orders": 7,
  "by_status": {
    "assigned": 4,
    "delivered": 2,
    "in_route": 1
  },
  "delivered": 2,
  "returned": 0,
  "in_progress": 5,
  "with_incident_now": 0,
  "incidents_reported": 0,
  "delivery_rate": 100,
  "on_time_rate": 100,
  "delivery_fees": 51500,
  "delivery_fees_delivered": 13500,
  "collected": 155000,
  "collected_cash": 90000
}
```

## 12. Usuarios y permisos

Permiso `users.manage`. Solo se gestionan usuarios de la propia empresa; no se pueden crear superadministradores.

| Método y ruta | Qué hace |
|---|---|
| `GET /permissions` | Catálogo de permisos y los que trae cada rol |
| `GET /users` | Usuarios de la empresa |
| `POST /users` | Crea un usuario |
| `PUT /users/{id}` | Modifica (solo los campos enviados) |

| Campo | Descripción |
|---|---|
| `name`, `email` | Obligatorios al crear. El correo es único |
| `password` | Obligatoria al crear; mín. 8 caracteres con letras y números. Al cambiarla se cierran sus sesiones |
| `role` | `company_admin`, `dispatcher`, `driver`, `viewer` |
| `carrier_id` | Obligatorio si `role` = `driver` (un usuario por transportador) |
| `extra_permissions` | Permisos que se le suman al rol |
| `revoked_permissions` | Permisos del rol que se le quitan (quitar gana sobre dar) |
| `active` | `false` lo bloquea y cierra sus sesiones. Nadie se puede desactivar a sí mismo |
| `phone` | Opcional |

```json
{ "name": "Laura Ruiz", "email": "laura@empresa.co", "password": "Clave2026", "role": "dispatcher",
  "extra_permissions": ["users.manage"], "revoked_permissions": ["cash.close"] }
```

Respuesta `201`:

```json
{
  "data": {
    "id": "01a1a753-e1ad-70f8-a8f8-a486856dfb43",
    "name": "Laura Ruiz",
    "email": "laura@empresa.co",
    "phone": null,
    "role": "dispatcher",
    "active": true,
    "company_id": "01a1a753-d0e6-71fb-a578-d7ac68a86357",
    "carrier_id": null,
    "carrier": null,
    "extra_permissions": ["users.manage"],
    "revoked_permissions": ["cash.close"],
    "permissions": [
      "orders.view",
      …
    ]
  }
}
```

## 13. Empresas (superadministrador)

### POST /companies

Permiso `companies.create` (solo `superadmin`). El superadministrador no pertenece a una empresa: para operar dentro de una empresa envía la cabecera `X-Company-Id: <uuid>` en cada petición.

| Campo | Descripción |
|---|---|
| `name` | Obligatorio |
| `tax_id` | NIT |
| `logo_url` | URL del logo (página de rastreo) |
| `primary_color`, `secondary_color` | `#RRGGBB` (marca blanca en el rastreo) |

Respuesta `201`: `{"id": "<uuid>"}`.

## 14. Rastreo público (sin token)

Para mostrar el estado del pedido al cliente final en tu propia página o app. No expone teléfonos, dirección exacta ni datos internos (Ley 1581 de 2012). La ubicación del mensajero se redondea a unos 110 m y el destino a nivel de barrio.

| Método y ruta | Qué hace |
|---|---|
| `GET /public/tracking/{codigo}` | Estado del pedido por código de rastreo (ej.: `ISA-WGF3ZKYY`) |
| `GET /public/tracking/{codigo}/carrier-photo` | Foto del mensajero, solo mientras el pedido va en camino |
| `GET /rastreo/{codigo}` (sin `/api`) | Página web lista para enviar por WhatsApp |

```json
{
  "data": {
    "tracking_code": "ISA-HCT4A8YH",
    "status": "in_route",
    "company": {
      "name": "Distribuidora El Poblado",
      "logo_url": null,
      "primary_color": "#0B1F3A",
      "secondary_color": "#00C896"
    },
    "recipient_first_name": "Pedro",
    "destination": ["Boston", "Medellín"],
    "packages_count": 2,
    "promised_at": "2026-11-05T18:00:00-05:00",
    "carrier_name": "Juan",
    "carrier": {
      "first_name": "Juan",
      "vehicle_type": "motorcycle",
      "vehicle_plate": "ABC12D",
      "has_photo": false
    },
    "delivery_fee": 6000,
    "zone_name": "Centro - Boston",
    "destination_point": {
      "latitude": 6.25,
      "longitude": -75.56
    },
    "received_by": null,
    "approximate_location": {
      "latitude": 6.248,
      "longitude": -75.565
    },
    "timeline": [
      {
        "status": "created",
        "occurred_at": "2026-11-04T11:10:54-05:00",
        "incident_reason": null
      },
      …
    ]
  }
}
```

## 15. Flujo completo de ejemplo

| Paso | Quién | Llamado |
|---|---|---|
| 1 | Tienda / ERP | `POST /orders` con coordenadas → recibe `id` y `tracking_code`; el cliente recibe el enlace de rastreo |
| 2 | Despacho | `POST /routes` con los pedidos del día y el mensajero → quedan asignados y ordenados |
| 3 | Mensajero | `GET /routes?date=hoy` → `POST /routes/{id}/status` `in_progress` |
| 4 | Mensajero | Cada 20–30 s: `POST /me/location` |
| 5 | Mensajero | Por cada parada: `POST /orders/{id}/status` `in_route` → `POST /orders/{id}/deliver` (o `incident` con motivo) |
| 6 | Mensajero | `POST /routes/{id}/status` `completed` |
| 7 | Caja | `GET /cash/summary?carrier_id=` → `POST /cash/closings` |
| 8 | Gerencia | `GET /reports/summary?from=&to=` o `GET /reports/orders.csv` |

## 16. Valores de referencia

| Lista | Valores |
|---|---|
| Estados de pedido | `created`, `prepared`, `assigned`, `in_route`, `delivered`, `incident`, `returned` |
| Motivos de novedad | `wrong_address` (dirección errada), `customer_absent` (cliente ausente), `rejected` (rechazado), `rescheduled` (reprogramado), `other` |
| Medios de pago | `cash` (efectivo), `nequi`, `daviplata`, `card_terminal` (datáfono), `bank_transfer` (transferencia) |
| Tipos de transportador | `own_driver` (propio), `external_company` (transportadora) |
| Vehículos | `motorcycle`, `bicycle`, `car`, `van`, `truck`, `on_foot` |
| Estados de ruta | `planned`, `in_progress`, `completed`, `cancelled` |
| Roles | `company_admin`, `dispatcher`, `driver`, `viewer` (y `superadmin`) |
| Resultado de caja | `balanced` (cuadra), `short` (faltante), `over` (sobrante) |

## 17. Buenas prácticas para integrar

- Guarda el token en un lugar seguro del servidor o del dispositivo (nunca en el código fuente ni en GitHub). Crea un usuario propio para cada integración, con solo los permisos que necesita.
- Usa HTTPS en producción: sin él, el token y las contraseñas viajan en texto plano.
- Decide con el campo `error` (código estable), no con el texto de `message`, que puede cambiar.
- Ante un `429`, espera los segundos de la cabecera `Retry-After` antes de reintentar.
- Las coordenadas permiten tarifa por zona y rutas: envíalas siempre que las tengas (la tienda puede pedirlas con el mapa del checkout).
- Guarda el `tracking_code` en tu sistema para enlazar con `/rastreo/{codigo}`.
