<?php
// api/config.php - Configurações centrais do painel admin + histórico

// Caminho base do projeto: /var/www/html/termo  (ou raiz do repo)
define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
define('STORAGE_PATH', BASE_PATH . '/storage/prints');
define('DB_FILE', DATA_PATH . '/termo.db');

// Dias para manter histórico e fotos antes de apagar automaticamente
define('RETENTION_DAYS', 60);

// Garante que pastas existam
if (!is_dir(DATA_PATH)) @mkdir(DATA_PATH, 0755, true);
if (!is_dir(STORAGE_PATH)) @mkdir(STORAGE_PATH, 0755, true);

// Timezone Brasil
date_default_timezone_set('America/Sao_Paulo');

// Inicia sessão se ainda não iniciada
if (session_status() === PHP_SESSION_NONE) {
    // cookie seguro: httponly, samesite lax
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_login() {
    if (empty($_SESSION['admin_id'])) {
        json_response(['ok'=>false,'error'=>'Não autenticado. Faça login.'], 401);
    }
}

function require_login_redirect() {
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function is_logged_in() {
    return !empty($_SESSION['admin_id']);
}

function current_admin_id() { return $_SESSION['admin_id'] ?? null; }
function current_admin_user() { return $_SESSION['admin_user'] ?? null; }
