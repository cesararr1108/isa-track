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
    | Permisos usados en routes/api.php: companies.create, carriers.view,
    | carriers.create, orders.view, orders.create, orders.assign, orders.change_status,
    | cash.view, cash.close
    */
    'roles' => [
        'superadmin' => ['*'],
        'company_admin' => ['orders.*', 'carriers.*', 'cash.*', 'stats.view'],
        'dispatcher' => ['orders.*', 'carriers.view', 'cash.*'],
        'driver' => ['orders.view', 'orders.change_status', 'cash.view'],
        'viewer' => ['orders.view', 'carriers.view', 'cash.view', 'stats.view'],
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
        'in_route' => ['label' => 'En ruta', 'color' => '#2563eb'],
        'delivered' => ['label' => 'Entregado', 'color' => '#16a34a'],
        'incident' => ['label' => 'Novedad', 'color' => '#d97706'],
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

    /* Marca por defecto si la empresa no define la suya. */
    'branding' => [
        'primary_color' => '#0f766e',
        'secondary_color' => '#f59e0b',
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
