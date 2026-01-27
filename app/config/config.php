<?php
// Deben ajustar los valores segun su entorno local
return [
    'db' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'name' => 'new_hope_platform',
        'port' => 3306,
        'charset' => 'utf8mb4',
    ],

    // El proyecto DEBE vivir en htdocs/new_hope_platform para acceder como http://localhost/new_hope_platform
    'base_url' => '/new_hope_platform',

    // Para desarrollo: activa/desactiva errores
    'debug' => true,
];
