<?php
// app/helpers/auth.php

function ensure_session_started(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function current_user(): ?array {
    ensure_session_started();
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!current_user()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'No autenticado']);
        exit;
    }
}

function require_role(array $roles): void {
    $u = current_user();
    if (!$u || !in_array($u['rol'] ?? '', $roles, true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Sin permisos']);
        exit;
    }
}
