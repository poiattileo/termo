<?php
// api/users.php - CRUD de usuários admins (protegido)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_login();
try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'hint'=>'No servidor: sudo apt install -y php-sqlite3 && sudo systemctl restart apache2'], JSON_UNESCAPED_UNICODE);
    exit;
}
$method = $_SERVER['REQUEST_METHOD'];

// GET = listar
if ($method === 'GET') {
    $users = $pdo->query("SELECT id, username, created_at FROM users ORDER BY id ASC")->fetchAll();
    // também retorna total de impressões para dashboard
    $totalPrints = $pdo->query("SELECT COUNT(*) FROM prints")->fetchColumn();
    json_response(['ok'=>true,'users'=>$users,'totalPrints'=>$totalPrints,'me'=>['id'=>current_admin_id(),'username'=>current_admin_user()]]);
}

// POST = criar usuário
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true) ?: [];
    $username = trim($_POST['username'] ?? $j['username'] ?? '');
    $password = $_POST['password'] ?? $j['password'] ?? '';

    if (strlen($username) < 3) json_response(['ok'=>false,'error'=>'Usuário deve ter ao menos 3 caracteres.'],400);
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) json_response(['ok'=>false,'error'=>'Usuário só pode conter letras, números, ponto, underline e hífen.'],400);
    if (strlen($password) < 4) json_response(['ok'=>false,'error'=>'Senha deve ter ao menos 4 caracteres.'],400);

    // verifica duplicado
    $chk = $pdo->prepare("SELECT id FROM users WHERE username=?");
    $chk->execute([$username]);
    if ($chk->fetch()) json_response(['ok'=>false,'error'=>'Usuário já existe.'],409);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, created_by) VALUES (?,?,?)");
    $stmt->execute([$username, $hash, current_admin_id()]);
    $id = $pdo->lastInsertId();
    json_response(['ok'=>true,'id'=>$id,'username'=>$username],201);
}

// DELETE = remover ?id=...
if ($method === 'DELETE' || ($method === 'POST' && ($_GET['action'] ?? '')==='delete')) {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        $raw = file_get_contents('php://input');
        $j = json_decode($raw, true) ?: [];
        $id = $j['id'] ?? null;
    }
    $id = intval($id);
    if (!$id) json_response(['ok'=>false,'error'=>'ID obrigatório.'],400);
    if ($id === intval(current_admin_id())) json_response(['ok'=>false,'error'=>'Você não pode remover seu próprio usuário.'],400);

    $cnt = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($cnt <= 1) json_response(['ok'=>false,'error'=>'Não é possível remover o último usuário.'],400);

    $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
    $stmt->execute([$id]);
    if ($stmt->rowCount()===0) json_response(['ok'=>false,'error'=>'Usuário não encontrado.'],404);
    json_response(['ok'=>true,'deleted'=>$id]);
}

json_response(['ok'=>false,'error'=>'Método não suportado.'],405);
