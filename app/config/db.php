<?php
// app/config/db.php
class Database {
    public static function connect() {
        $config = require __DIR__ . '/config.php';
        $db = $config['db'];

        $mysqli = new mysqli($db['host'], $db['user'], $db['pass'], $db['name'], (int)$db['port']);
        if ($mysqli->connect_errno) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error de conexión a la base de datos']);
            exit;
        }

        $mysqli->set_charset($db['charset'] ?? 'utf8mb4');
        return $mysqli;
    }
}
