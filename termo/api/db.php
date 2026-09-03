<?php
// api/db.php - PDO SQLite + inicialização + limpeza automática
require_once __DIR__ . '/config.php';

function get_pdo() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $needInit = !file_exists(DB_FILE);
    try {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // timeout para concorrência
        $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000; PRAGMA foreign_keys=ON;');
    } catch (Exception $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'Falha ao abrir banco: '.$e->getMessage()]);
        exit;
    }

    if ($needInit) {
        init_db($pdo);
    } else {
        // garante que tabelas existam mesmo se arquivo já existia mas incompleto
        ensure_tables($pdo);
        // migra colunas que faltam (compatibilidade com instalações antigas)
        migrate_db($pdo);
    }

    // limpeza automática leve: 1 a cada 10 requisições ou se ?cleanup=1
    if (rand(1,10) === 1) {
        try { cleanup_expired($pdo); } catch (Exception $e) {}
    }

    return $pdo;
}

function init_db(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by INTEGER
        );
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS prints (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            escola TEXT,
            equipamentos TEXT,
            html_snapshot TEXT,
            image_path TEXT,
            ip TEXT,
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME
        );
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_prints_created ON prints(created_at);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_prints_expires ON prints(expires_at);");

    // cria admin padrão se não existir nenhum usuário
    $cnt = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($cnt == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?,?)");
        $stmt->execute(['admin', $hash]);
    }
}

function ensure_tables(PDO $pdo) {
    // cria tabelas se não existirem
    init_db($pdo);
}

function migrate_db(PDO $pdo) {
    // Adiciona columnas faltantes de versões antigas sem recriar tabela
    try {
        $cols = $pdo->query("PRAGMA table_info(prints)")->fetchAll();
        $names = array_column($cols, 'name');
        if (!in_array('origem', $names)) {
            $pdo->exec("ALTER TABLE prints ADD COLUMN origem TEXT DEFAULT 'impressao'");
        }
        if (!in_array('foto_path', $names)) {
            // foto_path é alias novo; se image_path já existe, apenas cria foto_path
            $pdo->exec("ALTER TABLE prints ADD COLUMN foto_path TEXT");
            // migra dados antigos image_path -> foto_path
            if (in_array('image_path', $names)) {
                $pdo->exec("UPDATE prints SET foto_path = image_path WHERE foto_path IS NULL AND image_path IS NOT NULL");
            }
        }
        if (!in_array('image_path', $names) && in_array('foto_path', $names)) {
            $pdo->exec("ALTER TABLE prints ADD COLUMN image_path TEXT");
            $pdo->exec("UPDATE prints SET image_path = foto_path WHERE image_path IS NULL AND foto_path IS NOT NULL");
        }
    } catch (Exception $e) {
        // ignora erro de migração
    }
}

/**
 * Apaga automaticamente registros e fotos com mais de RETENTION_DAYS
 * Retorna quantidade removida
 */
function cleanup_expired(PDO $pdo = null) {
    if ($pdo === null) $pdo = get_pdo();
    $days = RETENTION_DAYS;
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

    // busca arquivos para apagar
    $stmt = $pdo->prepare("SELECT id, image_path, foto_path FROM prints WHERE created_at < ? OR expires_at < datetime('now')");
    // compat: tenta expires_at, senão created_at
    try {
        $stmt->execute([$cutoff]);
    } catch (Exception $e) {
        $stmt = $pdo->prepare("SELECT id, image_path FROM prints WHERE created_at < ?");
        $stmt->execute([$cutoff]);
    }
    $rows = $stmt->fetchAll();
    $deletedFiles = 0;
    foreach ($rows as $r) {
        foreach (['image_path','foto_path'] as $col) {
            $path = $r[$col] ?? null;
            if ($path) {
                $full = BASE_PATH . '/' . ltrim($path, '/');
                // também tenta STORAGE_PATH relativo
                if (!file_exists($full) && file_exists(STORAGE_PATH . '/' . basename($path))) {
                    $full = STORAGE_PATH . '/' . basename($path);
                }
                if (file_exists($full) && is_file($full)) {
                    @unlink($full);
                    $deletedFiles++;
                } else if (file_exists($path) && is_file($path)) {
                    @unlink($path);
                    $deletedFiles++;
                }
            }
        }
    }

    // apaga registros
    $del = $pdo->prepare("DELETE FROM prints WHERE created_at < ? OR expires_at < datetime('now')");
    try {
        $del->execute([$cutoff]);
    } catch (Exception $e) {
        $del = $pdo->prepare("DELETE FROM prints WHERE created_at < ?");
        $del->execute([$cutoff]);
    }
    $deletedRows = $del->rowCount();

    // limpeza extra: arquivos órfãos no storage com mais de 60 dias sem registro
    if (is_dir(STORAGE_PATH)) {
        $files = glob(STORAGE_PATH . '/*.{png,jpg,jpeg,webp,pdf}', GLOB_BRACE);
        foreach ($files as $f) {
            if (is_file($f) && filemtime($f) < strtotime("-{$days} days")) {
                // verifica se ainda está referenciado
                $base = basename($f);
                $chk = $pdo->prepare("SELECT COUNT(*) FROM prints WHERE image_path LIKE ? OR foto_path LIKE ?");
                $chk->execute(["%{$base}%","%{$base}%"]);
                if ($chk->fetchColumn() == 0) {
                    @unlink($f);
                    $deletedFiles++;
                }
            }
        }
    }

    return ['rows'=>$deletedRows, 'files'=>$deletedFiles, 'cutoff'=>$cutoff];
}
