<?php
// api/cleanup.php - Endpoint público para cron / chamada manual de limpeza
// Pode ser chamado via: curl https://seusite/termo/api/cleanup.php
// Ou agendado no crontab: 0 3 * * * curl -s https://seusite/termo/api/cleanup.php > /dev/null
// Também pode ser via cron do sistema: php /var/www/html/termo/api/cleanup.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

// permite também execução via CLI
$isCli = php_sapi_name() === 'cli';

try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if ($isCli) { fwrite(STDERR, "ERRO: $msg\n"); exit(1); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$msg,'hint'=>'No servidor: sudo apt install -y php-sqlite3 && sudo systemctl restart apache2'], JSON_UNESCAPED_UNICODE);
    exit;
}
$res = cleanup_expired($pdo);

$out = ['ok'=>true,'retention_days'=>RETENTION_DAYS,'removed_rows'=>$res['rows'],'removed_files'=>$res['files'],'cutoff'=>$res['cutoff'],'at'=>date('Y-m-d H:i:s')];

if ($isCli) {
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} else {
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
}
