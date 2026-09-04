<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/db.php';
// se já logado, vai para dashboard
if (is_logged_in()) { header('Location: index.php'); exit; }
try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Erro de banco</h1><p>'.htmlspecialchars($e->getMessage()).'</p><p>No servidor: <code>sudo apt install -y php-sqlite3 && sudo systemctl restart apache2</code></p>';
    exit;
}
// verifica se existe usuário, se não, mostra aviso
$hasUser = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login • Painel Admin - D.E. Jales</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🔐</text></svg>">
<style>
  :root{--primary:#0f3b8c;--primary2:#143a7a;--bg:#f1f5f9;--card:#ffffff;--border:#e2e8f0;--text:#0f172a;--muted:#64748b}
  *{box-sizing:border-box} html,body{margin:0;padding:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,sans-serif;background:var(--bg);color:var(--text)}
  .wrap{min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(ellipse at 20% 10%, rgba(15,59,140,.12), transparent 50%),radial-gradient(ellipse at 90% 90%, rgba(15,59,140,.08), transparent 50%),linear-gradient(#f8fafc,#f1f5f9)}
  .card{width:100%;max-width:420px;background:var(--card);border:1px solid var(--border);border-radius:20px;box-shadow:0 20px 60px rgba(15,59,140,.12),0 4px 16px rgba(0,0,0,.06);overflow:hidden}
  .head{background:linear-gradient(135deg,#0f2a5a 0%,#143a7a 45%,#1d4ed8 100%);color:#fff;padding:28px 28px 24px;text-align:center}
  .head .icon{width:48px;height:48px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);border-radius:14px;display:grid;place-items:center;margin:0 auto 12px;font-size:22px;backdrop-filter:blur(8px)}
  .head h1{margin:0;font-size:20px;letter-spacing:.02em}
  .head p{margin:6px 0 0;font-size:13px;opacity:.9}
  .body{padding:24px 28px 28px}
  label{display:block;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#334155;margin:14px 0 6px}
  label:first-child{margin-top:0}
  input{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px;font-size:14px;outline:none;transition:.15s;background:#fff}
  input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
  .btn{width:100%;padding:12px 16px;border-radius:12px;border:none;background:var(--primary);color:#fff;font-weight:700;font-size:14px;cursor:pointer;transition:.15s;margin-top:18px;display:flex;align-items:center;justify-content:center;gap:8px}
  .btn:hover{background:#0d336e} .btn:active{transform:translateY(1px)} .btn:disabled{opacity:.6;cursor:not-allowed}
  .alert{padding:11px 13px;border-radius:12px;font-size:13px;line-height:1.5;display:none;margin-bottom:14px}
  .alert.err{background:#fef2f2;border:1px solid #fecaca;color:#7f1d1d;display:block}
  .alert.ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#14532d;display:block}
  .alert.info{background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a5f;display:block}
  .foot{padding:14px 28px;background:#f8fafc;border-top:1px solid var(--border);text-align:center;font-size:12px;color:var(--muted)}
  .foot a{color:var(--primary);text-decoration:none;font-weight:600}
  .foot a:hover{text-decoration:underline}
  .hint{font-size:11px;color:var(--muted);margin-top:10px;text-align:center;line-height:1.4}
  .demo{margin-top:14px;padding:10px 12px;background:#fffbeb;border:1px dashed #facc15;border-radius:10px;font-size:12px;color:#78350f;text-align:center}
  .demo b{color:#92400e}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="head">
      <div class="icon">🔐</div>
      <h1>Painel Admin</h1>
      <p>D.E. Jales — Termo de Retirada</p>
    </div>
    <div class="body">
      <?php if(!$hasUser): ?>
        <div class="alert info">Nenhum usuário encontrado. Será criado <b>admin / admin123</b> automaticamente. Troque a senha após entrar.</div>
      <?php endif; ?>
      <div id="alert" class="alert" style="display:none"></div>
      <form id="formLogin" autocomplete="on">
        <label for="user">Usuário</label>
        <input id="user" name="username" type="text" placeholder="ex: admin" required autocomplete="username" autofocus>
        <label for="pass">Senha</label>
        <input id="pass" name="password" type="password" placeholder="••••••••" required autocomplete="current-password">
        <button class="btn" type="submit" id="btnLogin">Entrar →</button>
      </form>
      <div class="demo">
        Padrão inicial: <b>admin</b> / <b>admin123</b><br>
        <span style="font-size:11px;opacity:.8">Altere a senha após o primeiro acesso em “Meu usuário”.</span>
      </div>
      <div class="hint">
        Acesso restrito a administradores autorizados.<br>
        <a href="../index.html" style="color:#0f3b8c;text-decoration:none">← Voltar ao gerador de termo</a>
      </div>
    </div>
    <div class="foot">
      Protegido por sessão segura • Fotos expiram em 60 dias
    </div>
  </div>
</div>
<script>
const form = document.getElementById('formLogin');
const alertBox = document.getElementById('alert');
const btn = document.getElementById('btnLogin');
function showAlert(msg, type='err'){
  alertBox.textContent = msg;
  alertBox.className = 'alert ' + type;
  alertBox.style.display = 'block';
}
form.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const u = document.getElementById('user').value.trim();
  const p = document.getElementById('pass').value;
  if(!u || !p){ showAlert('Preencha usuário e senha.'); return; }
  btn.disabled = true; btn.textContent = 'Verificando...';
  alertBox.style.display='none';
  try{
    const r = await fetch('../api/auth.php?action=login', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({username:u, password:p})
    });
    const j = await r.json();
    if(j.ok){
      showAlert('Login OK! Redirecionando...','ok');
      setTimeout(()=> location.href='index.php', 600);
    } else {
      showAlert(j.error || 'Falha no login.', 'err');
      btn.disabled=false; btn.textContent='Entrar →';
    }
  }catch(err){
    showAlert('Erro de conexão: '+err.message, 'err');
    btn.disabled=false; btn.textContent='Entrar →';
  }
});
</script>
</body>
</html>
