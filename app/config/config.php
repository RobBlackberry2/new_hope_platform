<?php
// Deben ajustar los valores segun su entorno local
return [
    'db' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => 'Riolu21.',
        'name' => 'new_hope_platform',
        'port' => 3306,
        'charset' => 'utf8mb4',
    ],

    // El proyecto DEBE vivir en htdocs/new_hope_platform para acceder como http://localhost/new_hope_platform
    'base_url' => '/new_hope_platform',

    // Para desarrollo: activa/desactiva errores
    'debug' => true,

    'microsoft' => [
        'tenant' => 'common',
        'client_id' => 'e12252f0-52cb-4635-b31c-2cf81175c9c2',
        'client_secret' => 'yt~8Q~6Hew5FCRYtL6XUGPX-.kCMTodUk~g1Acjj',
        'redirect_uri' => 'http://localhost/new_hope_platform/onedrive_callback.php',
        'scopes' => 'offline_access User.Read Files.ReadWrite.All',
        'onedrive_root' => 'Apps/NewHopePlatform'
    ],
];



