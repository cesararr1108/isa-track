<?php

/*
| Inertia: el servidor decide qué página y qué datos ve cada usuario.
| Las páginas viven en resources/js/modules/<módulo>/pages/*.vue
*/
return [
    'ssr' => [
        'enabled' => false,
    ],

    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [resource_path('js/modules')],
        'extensions' => ['vue'],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
    ],

    'expose_shared_prop_keys' => true,

    'store_previous_url' => false,

    'history' => [
        // Cifra los datos de las páginas en el historial del navegador: tras cerrar sesión,
        // el botón "atrás" no muestra información de la empresa.
        'encrypt' => (bool) env('INERTIA_ENCRYPT_HISTORY', true),
    ],

    'devtools' => [
        'enabled' => false,
    ],
];
