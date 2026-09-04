<?php
// api/prints.php - Histórico de impressões: logar, listar, deletar, cleanup
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    http_response_code(500);
    // Mensagem já vem amigável de get_pdo()
    echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'hint'=>'No servidor: sudo apt install -y php-sqlite3 && sudo systemctl restart apache2'], JSON_UNESCAPED_UNICODE);
    exit;
}
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
if (empty($action)) {
    $rawTmp = file_get_contents('php://input');
    $jTmp = json_decode($rawTmp, true);
    if (is_array($jTmp) && !empty($jTmp['action'])) $action = $jTmp['action'];
}

// ---------- LOGAR IMPRESSÃO (público, sem login) ----------
if ($action === 'log' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    // aceita tanto JSON quanto form
    if (!is_array($j)) $j = $_POST;

    $escola = trim($j['escola'] ?? $_POST['escola'] ?? '');
    $equipamentos = $j['equipamentos'] ?? $j['equips'] ?? $_POST['equipamentos'] ?? '';
    if (is_array($equipamentos)) $equipamentos = json_encode($equipamentos, JSON_UNESCAPED_UNICODE);
    if (empty($equipamentos) && !empty($j['equips_json'])) $equipamentos = $j['equips_json'];

    $html = $j['html'] ?? $j['html_snapshot'] ?? null;
    // limita tamanho do html para não estourar DB (máx 800KB)
    if ($html && strlen($html) > 800000) $html = substr($html, 0, 800000);

    $origem = $j['origem'] ?? 'impressao';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // foto base64 (do html2canvas ou similar)
    $fotoBase64 = $j['foto_base64'] ?? $j['image_base64'] ?? $j['foto'] ?? null;
    $fotoPath = null;

    if ($fotoBase64) {
        // remove prefix data:image/png;base64,
        if (strpos($fotoBase64, 'base64,') !== false) {
            $fotoBase64 = substr($fotoBase64, strpos($fotoBase64, 'base64,') + 7);
        }
        $fotoBase64 = str_replace(' ', '+', $fotoBase64);
        $bin = base64_decode($fotoBase64, true);
        if ($bin && strlen($bin) > 100 && strlen($bin) < 8*1024*1024) {
            $ext = 'png';
            // detecta por magic bytes
            if (substr($bin,0,2) === "\xFF\xD8") $ext = 'jpg';
            $fname = 'print_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $full = STORAGE_PATH . '/' . $fname;
            if (@file_put_contents($full, $bin)) {
                $fotoPath = 'storage/prints/' . $fname;
            }
        }
    }

    $expires = date('Y-m-d H:i:s', strtotime('+'.RETENTION_DAYS.' days'));

    // tenta inserir com colunas novas, fallback para antigas
    try {
        $stmt = $pdo->prepare("INSERT INTO prints (escola, equipamentos, html_snapshot, image_path, foto_path, ip, user_agent, expires_at, origem) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$escola, $equipamentos, $html, $fotoPath, $fotoPath, $ip, $ua, $expires, $origem]);
    } catch (Exception $e) {
        // fallback sem foto_path/origem
        try {
            $stmt = $pdo->prepare("INSERT INTO prints (escola, equipamentos, html_snapshot, image_path, ip, user_agent, expires_at) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$escola, $equipamentos, $html, $fotoPath, $ip, $ua, $expires]);
        } catch (Exception $e2) {
            json_response(['ok'=>false,'error'=>'Falha ao salvar histórico: '.$e2->getMessage()],500);
        }
    }

    $id = $pdo->lastInsertId();

    // limpeza oportunista
    if (rand(1,20)===1) try { cleanup_expired($pdo); } catch(Exception $e){}

    json_response(['ok'=>true,'id'=>$id,'expires_at'=>$expires,'foto'=>$fotoPath]);
}

// ---------- A PARTIR DAQUI, REQUER LOGIN ----------
require_login();

// LISTAR histórico com paginação e filtros
if ($method === 'GET' && ($action === 'list' || $action === '')) {
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = min(50, max(5, intval($_GET['per_page'] ?? 20)));
    $offset = ($page-1)*$perPage;

    $where = [];
    $params = [];

    $q = trim($_GET['q'] ?? '');
    if ($q !== '') {
        $where[] = "(escola LIKE ? OR equipamentos LIKE ? OR ip LIKE ?)";
        $like = "%{$q}%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $escolaFilter = trim($_GET['escola'] ?? '');
    if ($escolaFilter !== '') {
        $where[] = "escola LIKE ?";
        $params[] = "%{$escolaFilter}%";
    }
    $de = trim($_GET['de'] ?? '');
    $ate = trim($_GET['ate'] ?? '');
    if ($de !== '') { $where[] = "date(created_at) >= date(?)"; $params[] = $de; }
    if ($ate !== '') { $where[] = "date(created_at) <= date(?)"; $params[] = $ate; }

    $whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

    // total
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM prints {$whereSql}");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();
    $pages = max(1, ceil($total / $perPage));

    // dados
    $sql = "SELECT id, escola, equipamentos, image_path, foto_path, ip, user_agent, origem, created_at, expires_at,
                   julianday(expires_at) - julianday('now') as dias_restantes
            FROM prints {$whereSql}
            ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // normaliza foto_path vs image_path
    foreach ($rows as &$r) {
        $r['foto'] = $r['foto_path'] ?? $r['image_path'] ?? null;
        // calcula dias restantes arredondado
        $r['dias_restantes'] = $r['dias_restantes'] !== null ? intval(ceil($r['dias_restantes'])) : null;
        // decodifica equipamentos se JSON
        $eq = $r['equipamentos'];
        $decoded = json_decode($eq, true);
        if (is_array($decoded)) $r['equipamentos_array'] = $decoded;
        else {
            // tenta split por linha
            $r['equipamentos_array'] = $eq ? array_filter(array_map('trim', explode("\n", str_replace([';',','], "\n", $eq)))) : [];
            if (empty($r['equipamentos_array']) && $eq) $r['equipamentos_array'] = [$eq];
        }
        // não retorna html_snapshot completo na listagem (pesado), só preview
        unset($r['foto_path'], $r['image_path']);
    }

    // stats rápidos
    $stats = [];
    $stats['total'] = $total;
    $stats['hoje'] = $pdo->query("SELECT COUNT(*) FROM prints WHERE date(created_at)=date('now')")->fetchColumn();
    $stats['semana'] = $pdo->query("SELECT COUNT(*) FROM prints WHERE created_at >= datetime('now','-7 days')")->fetchColumn();
    $stats['com_foto'] = $pdo->query("SELECT COUNT(*) FROM prints WHERE (foto_path IS NOT NULL AND foto_path!='') OR (image_path IS NOT NULL AND image_path!='')")->fetchColumn();
    $stats['expira_em_breve'] = $pdo->query("SELECT COUNT(*) FROM prints WHERE expires_at <= datetime('now','+7 days')")->fetchColumn();

    json_response(['ok'=>true,'data'=>$rows,'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>$pages],'stats'=>$stats]);
}

// GET single com html
if ($method === 'GET' && $action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) json_response(['ok'=>false,'error'=>'ID obrigatório'],400);
    $stmt = $pdo->prepare("SELECT * FROM prints WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_response(['ok'=>false,'error'=>'Registro não encontrado'],404);
    $row['foto'] = $row['foto_path'] ?? $row['image_path'] ?? null;
    json_response(['ok'=>true,'data'=>$row]);
}

// DELETE registro
if (($method === 'DELETE' || $method === 'POST') && ($action === 'delete' || isset($_GET['id']))) {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        $raw = file_get_contents('php://input');
        $j = json_decode($raw, true) ?: [];
        $id = intval($j['id'] ?? 0);
    }
    if (!$id) json_response(['ok'=>false,'error'=>'ID obrigatório'],400);
    $stmt = $pdo->prepare("SELECT image_path, foto_path FROM prints WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_response(['ok'=>false,'error'=>'Registro não encontrado'],404);
    foreach (['image_path','foto_path'] as $col) {
        $p = $row[$col] ?? null;
        if ($p) {
            $full = BASE_PATH . '/' . ltrim($p,'/');
            if (file_exists($full)) @unlink($full);
        }
    }
    $pdo->prepare("DELETE FROM prints WHERE id=?")->execute([$id]);
    json_response(['ok'=>true,'deleted'=>$id]);
}

// CLEANUP manual
if ($action === 'cleanup') {
    $res = cleanup_expired($pdo);
    json_response(['ok'=>true,'cleanup'=>$res,'msg'=>"Removidos {$res['rows']} registros e {$res['files']} fotos expiradas (>".RETENTION_DAYS." dias)."]);
}

// STATS geral
if ($action === 'stats') {
    $stats = [];
    $stats['total'] = $pdo->query("SELECT COUNT(*) FROM prints")->fetchColumn();
    $stats['hoje'] = $pdo->query("SELECT COUNT(*) FROM prints WHERE date(created_at)=date('now')")->fetchColumn();
    $stats['semana'] = $pdo->query("SELECT COUNT(*) FROM prints WHERE created_at >= datetime('now','-7 days')")->fetchColumn();
    $stats['mes'] = $pdo->query("SELECT COUNT(*) FROM prints WHERE created_at >= datetime('now','-30 days')")->fetchColumn();
    $stats['expira_7d'] = $pdo->query("SELECT COUNT(*) FROM prints WHERE expires_at <= datetime('now','+7 days')")->fetchColumn();
    json_response(['ok'=>true,'stats'=>$stats]);
}

json_response(['ok'=>false,'error'=>'Ação inválida. Use ?action=list|get|cleanup|delete|log'],400);
