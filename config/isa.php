<?php

/*
|--------------------------------------------------------------------------
| Configuración del negocio (ISA Track)
|--------------------------------------------------------------------------
| Todo lo que cambia según la operación vive aquí, no en el código.
| Después de editar en producción ejecuta: php artisan config:cache
*/

return [

    /*
    | Roles y permisos. Comodines: '*' (todo) y 'orders.*' (todo de pedidos).
    | A cada usuario se le pueden dar permisos extra o quitar permisos de su rol
    | desde /api/users (columnas users.permissions y users.revoked_permissions).
    */
    'roles' => [
        'superadmin' => ['*'],
        'company_admin' => ['orders.*', 'carriers.*', 'cash.*', 'zones.*', 'routes.*', 'reports.view', 'users.manage', 'stats.view'],
        'dispatcher' => ['orders.*', 'carriers.view', 'cash.*', 'zones.view', 'routes.*', 'reports.view'],
        'driver' => ['orders.view', 'orders.change_status', 'cash.view', 'routes.view', 'routes.drive'],
        'viewer' => ['orders.view', 'carriers.view', 'cash.view', 'zones.view', 'routes.view', 'reports.view', 'stats.view'],
    ],

    'role_labels' => [
        'superadmin' => 'Superadministrador',
        'company_admin' => 'Administrador',
        'dispatcher' => 'Despachador',
        'driver' => 'Mensajero',
        'viewer' => 'Solo lectura (gerencia)',
    ],

    /* Catálogo de permisos que se pueden asignar a un usuario (y su nombre en pantalla). */
    'permissions' => [
        'orders.view' => 'Ver pedidos',
        'orders.create' => 'Crear pedidos',
        'orders.assign' => 'Asignar pedidos a mensajeros',
        'orders.change_status' => 'Cambiar estado y registrar entregas',
        'carriers.view' => 'Ver transportadores',
        'carriers.create' => 'Registrar transportadores',
        'carriers.update' => 'Editar transportadores y su foto',
        'zones.view' => 'Ver zonas y tarifas',
        'zones.manage' => 'Crear y editar zonas y tarifas',
        'routes.view' => 'Ver rutas',
        'routes.manage' => 'Programar y editar rutas',
        'routes.drive' => 'Iniciar y terminar rutas (mensajero)',
        'cash.view' => 'Ver caja',
        'cash.close' => 'Cerrar caja',
        'reports.view' => 'Ver y descargar reportes',
        'users.manage' => 'Gestionar usuarios y permisos',
        'stats.view' => 'Ver estadísticas',
    ],

    /* Flota: dónde se guardan las fotos de los mensajeros. */
    'fleet' => [
        'photo_disk' => env('ISA_CARRIER_PHOTO_DISK', 'local'),
        'photo_max_kb' => (int) env('ISA_CARRIER_PHOTO_MAX_KB', 4096),
    ],

    /* Rutas: punto de partida por defecto (bodega). Medellín, sector Guayabal. */
    'routing' => [
        'depot' => [
            'latitude' => (float) env('ISA_DEPOT_LAT', 6.2120),
            'longitude' => (float) env('ISA_DEPOT_LNG', -75.5850),
        ],
    ],

    'shipping' => [
        'default_city' => env('ISA_DEFAULT_CITY', 'Medellín'),

        'tracking_code' => [
            'prefix' => env('ISA_TRACKING_PREFIX', 'ISA'),
            'length' => (int) env('ISA_TRACKING_LENGTH', 8),
        ],

        /*
        | Cambios de estado permitidos: 'desde' => ['hacia', ...].
        | La asignación de transportador lleva a 'assigned'.
        */
        'transitions' => [
            'created' => ['prepared', 'assigned', 'returned'],
            'prepared' => ['assigned', 'returned'],
            'assigned' => ['in_route', 'prepared'],
            'in_route' => ['delivered', 'incident'],
            'incident' => ['in_route', 'assigned', 'returned'],
            'delivered' => [],
            'returned' => [],
        ],
    ],

    /* Etiquetas y colores de estado (panel y página de rastreo). */
    'statuses' => [
        'created' => ['label' => 'Creado', 'color' => '#64748b'],
        'prepared' => ['label' => 'Alistado', 'color' => '#6366f1'],
        'assigned' => ['label' => 'Asignado', 'color' => '#0ea5e9'],
        'in_route' => ['label' => 'En camino', 'color' => '#00C896'],
        'delivered' => ['label' => 'Entregado', 'color' => '#16a34a'],
        'incident' => ['label' => 'Novedad', 'color' => '#FFB800'],
        'returned' => ['label' => 'Devuelto', 'color' => '#dc2626'],
    ],

    'incident_reasons' => [
        'wrong_address' => 'Dirección errada',
        'customer_absent' => 'Cliente ausente',
        'rejected' => 'Rechazado',
        'rescheduled' => 'Reprogramado',
        'other' => 'Otro motivo',
    ],

    /*
    | Prueba de entrega: qué es obligatorio para marcar un pedido como entregado
    | y en qué disco se guardan fotos y firmas ('local' = storage/app/private, o 's3').
    */
    'delivery_proof' => [
        'require' => [
            'receiver_name' => (bool) env('ISA_PROOF_RECEIVER_NAME', true),
            'photo' => (bool) env('ISA_PROOF_PHOTO', true),
            'signature' => (bool) env('ISA_PROOF_SIGNATURE', true),
            'location' => (bool) env('ISA_PROOF_LOCATION', false),
        ],
        'disk' => env('ISA_EVIDENCE_DISK', 'local'),
        'max_file_kb' => (int) env('ISA_EVIDENCE_MAX_KB', 8192),
    ],

    /* Medios de recaudo contra entrega. */
    'payment_methods' => [
        'cash' => 'Efectivo',
        'nequi' => 'Nequi',
        'daviplata' => 'Daviplata',
        'card_terminal' => 'Datáfono',
        'bank_transfer' => 'Transferencia',
    ],

    'cash' => [
        // Si el efectivo entregado no cuadra, exigir una nota explicando la diferencia.
        'require_note_on_difference' => true,
    ],

    /*
    | Marca por defecto si la empresa no define la suya (colores corporativos ISA):
    | azul profundo #0B1F3A (fondos, encabezados), verde menta #00C896 (botones,
    | confirmaciones, "en camino") y ámbar #FFB800 (alertas suaves, tarifas).
    */
    'branding' => [
        'primary_color' => '#0B1F3A',
        'secondary_color' => '#00C896',
        'highlight_color' => '#FFB800',
    ],

    'notifications' => [
        // log = escribe en storage/logs (desarrollo). Próximo: whatsapp.
        'driver' => env('ISA_NOTIFIER', 'log'),

        // Estados que avisan al cliente final. Sin plantilla = no se notifica.
        // Variables: {nombre}, {codigo}, {link}
        'templates' => [
            'created' => 'Hola {nombre}, recibimos tu pedido {codigo}. Síguelo aquí: {link}',
            'in_route' => 'Hola {nombre}, tu pedido {codigo} va en camino. Míralo en vivo: {link}',
            'delivered' => '¡Listo {nombre}! Tu pedido {codigo} fue entregado. Detalle: {link}',
            'incident' => 'Hola {nombre}, tuvimos una novedad con tu pedido {codigo}. Detalles: {link}',
        ],
    ],
];
