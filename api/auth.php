<?php
// api/auth.php - Login / Logout / Status
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (empty($action)) {
    // tenta ler JSON
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    if (is_array($j) && !empty($j['action'])) $action = $j['action'];
}

try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'hint'=>'No servidor: sudo apt install -y php-sqlite3 && sudo systemctl restart apache2'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'login') {
    // aceita JSON ou form
    $input = [];
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    if (is_array($j)) $input = $j;
    $username = trim($_POST['username'] ?? $input['username'] ?? '');
    $password = $_POST['password'] ?? $input['password'] ?? '';

    if ($username === '' || $password === '') {
        json_response(['ok'=>false,'error'=>'Preencha usuário e senha.'], 400);
    }

    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_response(['ok'=>false,'error'=>'Usuário ou senha inválidos.'], 401);
    }

    // login ok
    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_user'] = $user['username'];
    // regenera id para segurança
    if (function_exists('session_regenerate_id')) @session_regenerate_id(true);

    json_response(['ok'=>true,'user'=>['id'=>$user['id'],'username'=>$user['username']]]);
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    @session_destroy();
    json_response(['ok'=>true]);
}

if ($action === 'status') {
    if (is_logged_in()) {
        json_response(['ok'=>true,'logged'=>true,'user'=>['id'=>current_admin_id(),'username'=>current_admin_user()]]);
    } else {
        json_response(['ok'=>true,'logged'=>false]);
    }
}

if ($action === 'change_password') {
    require_login();
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true) ?: [];
    $current = $_POST['current'] ?? $j['current'] ?? '';
    $new = $_POST['new'] ?? $j['new'] ?? $j['password'] ?? '';
    if (strlen($new) < 4) json_response(['ok'=>false,'error'=>'Nova senha deve ter ao menos 4 caracteres.'],400);
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id=?");
    $stmt->execute([current_admin_id()]);
    $row = $stmt->fetch();
    if ($current !== '' && !password_verify($current, $row['password_hash'])) {
        json_response(['ok'=>false,'error'=>'Senha atual incorreta.'],401);
    }
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, current_admin_id()]);
    json_response(['ok'=>true,'msg'=>'Senha alterada.']);
}

json_response(['ok'=>false,'error'=>'Ação inválida. Use ?action=login|logout|status'],400);
