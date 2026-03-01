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

    // ⚠️ CONFIGURACIÓN DE CORREO (AJUSTA ESTOS DATOS A TU GMAIL / SMTP)
    'mail' => [
        'host'       => 'smtp.gmail.com',
        'port'       => 587,
        'username'   => 'tanyr09@gmail.com',      // <-- cambia esto
        'password'   => 'uzxxejifiwpknegm',         // <-- usa contraseña de aplicación de Gmail
        'from_email' => 'tanyr09@gmail.com',     // <-- mismo correo remitente
        'from_name'  => 'New Hope School',         // nombre que aparecerá en el correo
        'secure'     => 'tls',                     // tls o ssl según tu proveedor
    ],

    'microsoft' => [
        'tenant' => 'common',
        'client_id' => 'e12252f0-52cb-4635-b31c-2cf81175c9c2',
        'client_secret' => 'yt~8Q~6Hew5FCRYtL6XUGPX-.kCMTodUk~g1Acjj',
        'redirect_uri' => 'http://localhost/new_hope_platform/onedrive_callback.php',
        'scopes' => 'offline_access User.Read Files.ReadWrite.All',
        'onedrive_root' => 'Apps/NewHopePlatform'
    ],
];