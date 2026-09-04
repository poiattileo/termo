<?php
// install.php - Roda uma vez no servidor para inicializar banco e permissões
// Acesse: http://SEU-IP/termo/install.php
// Depois APAGUE ou bloqueie este arquivo.

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/db.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    http_response_code(500);
    $hint = htmlspecialchars($e->getMessage());
    $drivers = htmlspecialchars(implode(', ', PDO::getAvailableDrivers()));
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Erro DB</title><style>body{font-family:system-ui;padding:24px;background:#fef2f2} .card{max-width:780px;margin:auto;background:#fff;border:1px solid #fecaca;border-radius:12px;padding:20px}</style></head><body><div class='card'><h1 style='color:#991b1b'>❌ Erro de banco</h1><p><b>$hint</b></p><p>Drivers PDO disponíveis: <code>$drivers</code></p><p>Solução no servidor:</p><pre>sudo apt update && sudo apt install -y php-sqlite3 php8.1-sqlite3 php8.2-sqlite3\nsudo phpenmod pdo_sqlite\nsudo systemctl restart apache2  # ou nginx + php-fpm</pre><p>Depois recarregue esta página.</p><p><a href='install.php'>↻ Tentar novamente</a></p></div></body></html>";
    exit;
}
$msg = [];
$msg[] = "✔ Banco SQLite inicializado em: " . DB_FILE;
$msg[] = "   Tamanho: " . (file_exists(DB_FILE) ? round(filesize(DB_FILE)/1024,1)." KB" : "—");

// verifica usuários
$users = $pdo->query("SELECT id, username, created_at FROM users ORDER BY id")->fetchAll();
$msg[] = "✔ Usuários existentes: " . count($users);
foreach($users as $u) $msg[] = "   - #{$u['id']} {$u['username']} ({$u['created_at']})";

// verifica storage
$writableData = is_writable(DATA_PATH) ? "✔ gravável" : "❌ SEM permissão de escrita";
$writableStore = is_writable(STORAGE_PATH) ? "✔ gravável" : "❌ SEM permissão de escrita";
$msg[] = "Pasta data: " . DATA_PATH . " → $writableData";
$msg[] = "Pasta storage/prints: " . STORAGE_PATH . " → $writableStore";

// cria admin se não existir
if (count($users)==0) {
    $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?,?)")
        ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
    $msg[] = "✔ Usuário padrão criado: <b>admin / admin123</b> — TROQUE A SENHA após login!";
}

// teste de extensão
$ext = [];
$ext[] = "PHP " . PHP_VERSION;
$ext[] = "PDO SQLite: " . (extension_loaded('pdo_sqlite') ? "OK" : "FALTANDO (apt install php-sqlite3)");
$ext[] = "GD: " . (extension_loaded('gd') ? "OK" : "não instalada (opcional)");
$ext[] = "wkhtmltoimage: " . (trim(shell_exec('which wkhtmltoimage 2>/dev/null')) ?: "não encontrado (opcional, para gerar foto server-side)");
$ext[] = "wkhtmltopdf: " . (trim(shell_exec('which wkhtmltopdf 2>/dev/null')) ?: "não encontrado (opcional, para impressão)");

?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Instalação - Termo</title>
<style>body{font-family:system-ui, sans-serif;background:#f1f5f9;color:#0f172a;padding:24px} .card{max-width:780px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,.06)} h1{margin:0 0 8px} pre{background:#0f172a;color:#e2e8f0;padding:14px;border-radius:10px;overflow:auto;font-size:13px} a{color:#0f3b8c} .ok{color:#16a34a} .err{color:#dc2626}</style></head><body>
<div class="card">
<h1>✅ Instalação — Termo D.E. Jales</h1>
<p>Verifique abaixo se tudo está OK e depois <b>apague este arquivo</b> (<code>install.php</code>) por segurança.</p>
<pre><?=htmlspecialchars(implode("\n",$msg))?></pre>
<h3>Extensões / Dependências</h3>
<pre><?=htmlspecialchars(implode("\n",$ext))?></pre>
<h3>Próximos passos</h3>
<ol>
<li>Acesse <a href="admin/login.php">admin/login.php</a> — login <code>admin / admin123</code></li>
<li>Crie novos usuários em “Usuários admins” e troque a senha padrão</li>
<li>Gere um termo em <a href="index.html">index.html</a> e clique em <b>Imprimir</b> — deve aparecer no histórico</li>
<li>Configure limpeza automática via cron (opcional, já roda a cada acesso):<br><code>0 3 * * * curl -s http://localhost/termo/api/cleanup.php &gt; /dev/null</code><br>ou <code>0 3 * * * php /var/www/html/termo/api/cleanup.php</code></li>
<li>Garanta permissões:<br><code>sudo chown -R www-data:www-data /var/www/html/termo/data /var/www/html/termo/storage<br>sudo chmod -R 755 /var/www/html/termo</code></li>
<li>Se tudo OK, remova: <code>sudo rm /var/www/html/termo/install.php</code></li>
</ol>
<p><a href="admin/login.php" style="display:inline-block;background:#0f3b8c;color:#fff;padding:10px 18px;border-radius:10px;text-decoration:none;font-weight:700">→ Ir para login</a> <a href="index.html" style="margin-left:8px">Gerador</a></p>
</div>
</body></html>
