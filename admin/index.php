<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/db.php';
require_login_redirect();
$pdo = get_pdo();
// pega stats iniciais server-side para primeiro paint
try { $cleanupPreview = cleanup_expired($pdo); } catch(Exception $e) {$cleanupPreview=['rows'=>0,'files'=>0];}
$user = current_admin_user();
$uid = current_admin_id();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalPrints = $pdo->query("SELECT COUNT(*) FROM prints")->fetchColumn();
$hoje = $pdo->query("SELECT COUNT(*) FROM prints WHERE date(created_at)=date('now')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Painel Admin • D.E. Jales</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📋</text></svg>">
<style>
  :root{--primary:#0f3b8c;--primary2:#143a7a;--bg:#f1f5f9;--card:#fff;--border:#e2e8f0;--text:#0f172a;--muted:#64748b;--ok:#16a34a;--warn:#f59e0b;--danger:#dc2626}
  *{box-sizing:border-box} html,body{margin:0;padding:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--text)}
  .topbar{background:linear-gradient(90deg,#0f2a5a 0%,#143a7a 50%,#1a4a9a 100%);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;position:sticky;top:0;z-index:30;box-shadow:0 4px 20px rgba(0,0,0,.15)}
  .brand{display:flex;align-items:center;gap:12px}
  .brand .logo{width:40px;height:40px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);border-radius:12px;display:grid;place-items:center;font-size:18px;backdrop-filter:blur(6px)}
  .brand h1{margin:0;font-size:16px;letter-spacing:.02em}
  .brand small{display:block;font-size:11px;opacity:.85;letter-spacing:.04em;text-transform:uppercase}
  .top-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
  .btn{appearance:none;border:1px solid transparent;padding:8px 14px;border-radius:10px;font-weight:600;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.15s;text-decoration:none}
  .btn:active{transform:translateY(1px)}
  .btn-primary{background:var(--primary);color:#fff;border-color:#0f3b8c}
  .btn-primary:hover{background:#0d336e}
  .btn-ghost{background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.2)}
  .btn-ghost:hover{background:rgba(255,255,255,.18)}
  .btn-outline{background:#fff;color:#334155;border-color:var(--border)}
  .btn-outline:hover{background:#f8fafc}
  .btn-danger{background:var(--danger);color:#fff}
  .btn-danger:hover{background:#b91c1c}
  .btn-sm{padding:6px 10px;font-size:12px;border-radius:8px}
  .pill{font-size:11px;padding:4px 10px;border-radius:999px;font-weight:700;border:1px solid transparent}
  .pill-ok{background:#dcfce7;color:#14532d;border-color:#bbf7d0}
  .pill-warn{background:#fef3c7;color:#92400e;border-color:#fde68a}
  .pill-muted{background:#f1f5f9;color:#475569;border-color:#e2e8f0}
  .wrap{max-width:1280px;margin:0 auto;padding:20px}
  .grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px}
  @media(max-width:900px){.grid4{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:520px){.grid4{grid-template-columns:1fr}}
  .stat{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px;box-shadow:0 2px 10px rgba(0,0,0,.04)}
  .stat .k{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
  .stat .v{font-size:26px;font-weight:800;margin:6px 0 2px}
  .stat .d{font-size:12px;color:var(--muted)}
  .tabs{display:flex;gap:8px;margin:10px 0 14px;flex-wrap:wrap}
  .tab{padding:10px 16px;border-radius:12px;border:1px solid var(--border);background:#fff;cursor:pointer;font-weight:700;font-size:13px;color:#475569}
  .tab.active{background:var(--primary);color:#fff;border-color:var(--primary)}
  .panel{background:var(--card);border:1px solid var(--border);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.04);overflow:hidden}
  .panel-head{padding:16px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:#f8fafc}
  .panel-head h2{margin:0;font-size:14px;letter-spacing:.04em;text-transform:uppercase;color:#334155;display:flex;align-items:center;gap:8px}
  .filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
  .filters input,.filters select{padding:9px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:13px;outline:none;background:#fff;min-width:140px}
  .filters input:focus,.filters select:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
  .table-wrap{overflow:auto}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th{white-space:nowrap;text-align:left;padding:11px 12px;background:#f8fafc;border-bottom:1px solid var(--border);font-size:11px;letter-spacing:.05em;text-transform:uppercase;color:#64748b;position:sticky;top:0}
  td{padding:11px 12px;border-bottom:1px solid #f1f5f9;vertical-align:top}
  tr:hover td{background:#f8fafc}
  .equip-cell{max-width:320px}
  .equip-tag{display:inline-block;background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;padding:2px 7px;border-radius:999px;font-size:11px;margin:2px 3px 2px 0}
  .thumb{width:64px;height:44px;object-fit:cover;border-radius:8px;border:1px solid var(--border);background:#f1f5f9;cursor:pointer}
  .thumb-empty{width:64px;height:44px;display:grid;place-items:center;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;color:#94a3b8;font-size:11px}
  .actions{display:flex;gap:6px;flex-wrap:wrap}
  .pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-top:1px solid var(--border);background:#f8fafc;flex-wrap:wrap}
  .pagination .info{font-size:12px;color:var(--muted)}
  .card-form{padding:16px 18px;display:grid;gap:12px}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media(max-width:600px){.grid2{grid-template-columns:1fr}}
  label{font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#475569}
  input[type=text],input[type=password]{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none}
  input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
  .alert{padding:10px 12px;border-radius:10px;font-size:13px;display:none}
  .alert.show{display:block}
  .alert.ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#14532d}
  .alert.err{background:#fef2f2;border:1px solid #fecaca;color:#7f1d1d}
  .user-list{display:grid;gap:8px}
  .user-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:#fff}
  .user-row .meta{font-size:12px;color:var(--muted)}
  .modal{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);display:none;place-items:center;z-index:50;padding:18px}
  .modal.open{display:grid}
  .modal-card{width:min(920px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.25);border:1px solid var(--border)}
  .modal-head{position:sticky;top:0;background:#fff;border-bottom:1px solid var(--border);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px}
  .modal-body{padding:18px}
  .preview-frame{width:100%;height:620px;border:1px solid var(--border);border-radius:12px;background:#fff}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px}
  .divider{height:1px;background:var(--border);margin:12px 0}
  .kpi{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
  .kpi span{font-size:11px;padding:5px 9px;border-radius:999px;background:#f1f5f9;border:1px solid var(--border)}
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">
    <div class="logo">📋</div>
    <div>
      <h1>Painel Admin</h1>
      <small>D.E. JALES • Termo de Retirada • 60 dias</small>
    </div>
  </div>
  <div class="top-actions">
    <span class="pill pill-ok" title="Logado como admin">👤 <?=htmlspecialchars($user)?></span>
    <a class="btn btn-ghost" href="../index.html" target="_blank">📄 Gerador</a>
    <a class="btn btn-ghost" href="logout.php">Sair</a>
  </div>
</div>

<div class="wrap">

  <div class="grid4" id="stats">
    <div class="stat"><div class="k">Total histórico (60d)</div><div class="v" id="sTotal"><?=$totalPrints?></div><div class="d">Impressões salvas • expiram em 60 dias</div></div>
    <div class="stat"><div class="k">Hoje</div><div class="v" id="sHoje"><?=$hoje?></div><div class="d">Impressões de hoje</div></div>
    <div class="stat"><div class="k">Usuários admins</div><div class="v" id="sUsers"><?=$totalUsers?></div><div class="d">Contas com acesso ao painel</div></div>
    <div class="stat"><div class="k">Limpeza automática</div><div class="v" style="font-size:15px;color:var(--ok)">✔ Ativa</div><div class="d">Fotos e registros apagados após 60 dias • último check: <?=date('d/m H:i')?></div></div>
  </div>

  <div class="tabs">
    <button class="tab active" data-tab="historico" onclick="switchTab('historico')">📜 Histórico de impressões</button>
    <button class="tab" data-tab="usuarios" onclick="switchTab('usuarios')">👥 Usuários admins</button>
    <button class="tab" data-tab="conta" onclick="switchTab('conta')">⚙️ Minha conta</button>
  </div>

  <!-- HISTÓRICO -->
  <div id="tab-historico" class="panel">
    <div class="panel-head">
      <h2>📜 Histórico — todas as impressões (auto-apaga em 60 dias)</h2>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-outline btn-sm" onclick="loadPrints(1)">🔄 Atualizar</button>
        <button class="btn btn-outline btn-sm" onclick="runCleanup()">🧹 Limpar expirados agora</button>
      </div>
    </div>
    <div style="padding:12px 16px;background:#fff;border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:10px">
      <div class="filters">
        <input id="fQ" type="text" placeholder="🔍 Buscar escola, equipamento ou IP..." style="flex:1;min-width:220px" oninput="debounceLoad()">
        <input id="fEscola" type="text" placeholder="Filtrar escola" oninput="debounceLoad()">
        <input id="fDe" type="date" onchange="loadPrints(1)">
        <input id="fAte" type="date" onchange="loadPrints(1)">
        <select id="fPerPage" onchange="loadPrints(1)"><option value="10">10 / pág</option><option value="20" selected>20 / pág</option><option value="50">50 / pág</option></select>
        <button class="btn btn-outline btn-sm" onclick="clearFilters()">Limpar filtros</button>
      </div>
      <div class="kpi" id="kpiBar"></div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Data / Expira</th>
            <th>Escola</th>
            <th>Equipamentos</th>
            <th>Foto</th>
            <th>IP</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody id="tbodyPrints">
          <tr><td colspan="7" style="text-align:center;padding:28px;color:var(--muted)">Carregando...</td></tr>
        </tbody>
      </table>
    </div>
    <div class="pagination">
      <div class="info" id="pageInfo">—</div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-outline btn-sm" id="btnPrev" onclick="changePage(-1)">← Anterior</button>
        <button class="btn btn-outline btn-sm" id="btnNext" onclick="changePage(1)">Próxima →</button>
      </div>
    </div>
  </div>

  <!-- USUÁRIOS -->
  <div id="tab-usuarios" class="panel" style="display:none">
    <div class="panel-head">
      <h2>👥 Usuários administradores</h2>
      <span class="pill pill-muted" id="usersCount">—</span>
    </div>
    <div style="padding:16px;display:grid;gap:16px">
      <div style="background:#f8fafc;border:1px solid var(--border);border-radius:12px;padding:16px">
        <h3 style="margin:0 0 10px;font-size:13px;letter-spacing:.04em;text-transform:uppercase;color:#334155">＋ Criar novo admin</h3>
        <div id="userAlert" class="alert"></div>
        <form id="formUser" style="display:grid;gap:10px;max-width:520px">
          <div class="grid2">
            <div><label>Usuário</label><input id="newUser" type="text" placeholder="ex: maria.silva" required pattern="[a-zA-Z0-9._-]+" minlength="3"></div>
            <div><label>Senha</label><input id="newPass" type="password" placeholder="mín. 4 caracteres" required minlength="4"></div>
          </div>
          <button class="btn btn-primary" type="submit" style="justify-content:center">Criar usuário</button>
          <div style="font-size:11px;color:var(--muted)">Use apenas letras, números, ponto, underline ou hífen. A senha é criptografada (bcrypt).</div>
        </form>
      </div>

      <div>
        <h3 style="margin:0 0 10px;font-size:13px;letter-spacing:.04em;text-transform:uppercase;color:#334155">Lista de usuários</h3>
        <div id="usersList" class="user-list">
          <div style="text-align:center;padding:18px;color:var(--muted)">Carregando...</div>
        </div>
      </div>
    </div>
  </div>

  <!-- MINHA CONTA -->
  <div id="tab-conta" class="panel" style="display:none">
    <div class="panel-head"><h2>⚙️ Minha conta — <?=htmlspecialchars($user)?></h2></div>
    <div style="padding:18px;max-width:520px;display:grid;gap:14px">
      <div id="passAlert" class="alert"></div>
      <form id="formPass" style="display:grid;gap:10px">
        <div><label>Senha atual (opcional se esquecer, mas recomendado)</label><input id="curPass" type="password" placeholder="opcional"></div>
        <div><label>Nova senha</label><input id="newPass2" type="password" placeholder="mín. 4 caracteres" required minlength="4"></div>
        <div><label>Repetir nova senha</label><input id="newPass3" type="password" placeholder="repita" required minlength="4"></div>
        <button class="btn btn-primary" type="submit">Alterar minha senha</button>
      </form>
      <div class="divider"></div>
      <div style="font-size:12px;color:var(--muted);line-height:1.5">
        <b>Segurança:</b> senhas são armazenadas com <code>password_hash</code> (bcrypt). Ninguém vê sua senha em texto puro.<br>
        <b>Histórico:</b> impressões e fotos são apagadas automaticamente após <b>60 dias</b>. O sistema roda limpeza diária via cron ou a cada acesso.<br>
        <b>Storage:</b> <code>storage/prints/</code> e <code>data/termo.db</code> precisam ter permissão de escrita para <code>www-data</code>.
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-outline btn-sm" href="../api/cleanup.php" target="_blank">Ver JSON de limpeza</a>
        <a class="btn btn-outline btn-sm" href="../api/prints.php?action=stats" target="_blank">Ver stats JSON</a>
      </div>
    </div>
  </div>

  <div style="text-align:center;padding:18px;color:var(--muted);font-size:12px">
    D.E. Jales • Painel Admin • Fotos expiram automaticamente em 60 dias • <a href="../index.html" style="color:var(--primary);text-decoration:none">Gerador de termo</a>
  </div>

</div>

<!-- MODAL DETALHE -->
<div id="modal" class="modal" onclick="if(event.target===this) closeModal()">
  <div class="modal-card">
    <div class="modal-head">
      <div><b id="mTitle">Detalhe da impressão</b><div id="mSub" style="font-size:12px;color:var(--muted)"></div></div>
      <button class="btn btn-outline btn-sm" onclick="closeModal()">✕ Fechar</button>
    </div>
    <div class="modal-body" id="mBody">
      Carregando...
    </div>
  </div>
</div>

<script>
let curPage = 1;
let totalPages = 1;
let debounceTimer = null;
function switchTab(name){
  document.querySelectorAll('.tab').forEach(t=> t.classList.toggle('active', t.dataset.tab===name));
  document.getElementById('tab-historico').style.display = name==='historico' ? '' : 'none';
  document.getElementById('tab-usuarios').style.display = name==='usuarios' ? '' : 'none';
  document.getElementById('tab-conta').style.display = name==='conta' ? '' : 'none';
  if(name==='usuarios') loadUsers();
  if(name==='historico') loadPrints(1);
}

function debounceLoad(){ clearTimeout(debounceTimer); debounceTimer=setTimeout(()=>loadPrints(1), 380); }
function clearFilters(){
  document.getElementById('fQ').value='';
  document.getElementById('fEscola').value='';
  document.getElementById('fDe').value='';
  document.getElementById('fAte').value='';
  loadPrints(1);
}
function changePage(delta){
  const np = curPage + delta;
  if(np<1 || np>totalPages) return;
  loadPrints(np);
}

async function loadPrints(page=1){
  curPage = page;
  const q = document.getElementById('fQ').value.trim();
  const escola = document.getElementById('fEscola').value.trim();
  const de = document.getElementById('fDe').value;
  const ate = document.getElementById('fAte').value;
  const per_page = document.getElementById('fPerPage').value;
  const tbody = document.getElementById('tbodyPrints');
  tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:22px;color:var(--muted)">Carregando...</td></tr>';
  try{
    const params = new URLSearchParams({action:'list', page, per_page});
    if(q) params.set('q', q);
    if(escola) params.set('escola', escola);
    if(de) params.set('de', de);
    if(ate) params.set('ate', ate);
    const r = await fetch('../api/prints.php?' + params.toString());
    const j = await r.json();
    if(!r.ok || !j.ok) throw new Error(j.error || 'Falha ao carregar');
    renderPrints(j);
  }catch(err){
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:22px;color:#dc2626">Erro: ${err.message}</td></tr>`;
  }
}

function renderPrints(j){
  const rows = j.data || [];
  const tbody = document.getElementById('tbodyPrints');
  const pag = j.pagination || {};
  const stats = j.stats || {};
  curPage = pag.page || 1;
  totalPages = pag.pages || 1;
  document.getElementById('pageInfo').textContent = `${pag.total||0} registros • pág ${curPage}/${totalPages}`;
  document.getElementById('btnPrev').disabled = curPage<=1;
  document.getElementById('btnNext').disabled = curPage>=totalPages;
  // kpi
  const kpi = document.getElementById('kpiBar');
  kpi.innerHTML = `
    <span>📊 Total: <b>${stats.total||0}</b></span>
    <span>📅 Hoje: <b>${stats.hoje||0}</b></span>
    <span>🗓️ 7 dias: <b>${stats.semana||0}</b></span>
    <span>🖼️ Com foto: <b>${stats.com_foto||0}</b></span>
    <span>⏳ Expiram em 7d: <b>${stats.expira_em_breve||0}</b></span>
    <span>🧹 Auto-delete 60d</span>
  `;
  // atualiza stats topo
  if(document.getElementById('sTotal')) document.getElementById('sTotal').textContent = stats.total ?? pag.total ?? '-';
  if(document.getElementById('sHoje')) document.getElementById('sHoje').textContent = stats.hoje ?? '-';

  if(rows.length===0){
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:28px;color:var(--muted)">Nenhuma impressão encontrada. <br><small>Gere um termo no <a href="../index.html" target="_blank">gerador</a> e clique em Imprimir para logar automaticamente.</small></td></tr>';
    return;
  }
  tbody.innerHTML = rows.map(r=>{
    const d = new Date(r.created_at.replace(' ','T'));
    const exp = r.expires_at ? new Date(r.expires_at.replace(' ','T')) : null;
    const dias = r.dias_restantes;
    let badge = '';
    if(dias!==null){
      if(dias<=3) badge = `<span class="pill pill-warn" title="Expira em ${dias} dias">⏳ ${dias}d</span>`;
      else if(dias<=7) badge = `<span class="pill pill-warn">${dias}d</span>`;
      else badge = `<span class="pill pill-muted">${dias}d</span>`;
    }
    const escola = escapeHtml(r.escola || '—');
    const equips = (r.equipamentos_array||[]).slice(0,4);
    const equipsHtml = equips.length ? equips.map(e=>`<span class="equip-tag">${escapeHtml(e)}</span>`).join('') + (r.equipamentos_array.length>4 ? ` <small style="color:#64748b">+${r.equipamentos_array.length-4}</small>` : '') : '<span style="color:#94a3b8">—</span>';
    const foto = r.foto ? `<img class="thumb" src="../${r.foto}" alt="foto" loading="lazy" onclick="openModal(${r.id})" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid'"><div class="thumb-empty" style="display:none">sem preview</div>` : `<div class="thumb-empty">sem foto</div>`;
    const dataStr = d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
    const expStr = exp ? exp.toLocaleDateString('pt-BR') : '—';
    return `<tr>
      <td class="mono">#${r.id}</td>
      <td><div style="font-weight:600">${dataStr}</div><div style="font-size:11px;color:var(--muted)">expira ${expStr} ${badge}</div></td>
      <td style="max-width:220px"><div style="font-weight:700;text-transform:uppercase">${escola}</div><div style="font-size:11px;color:var(--muted)">${escapeHtml(r.origem||'impressao')}</div></td>
      <td class="equip-cell">${equipsHtml}</td>
      <td>${foto}</td>
      <td class="mono" style="font-size:11px">${escapeHtml(r.ip||'—')}</td>
      <td><div class="actions">
        <button class="btn btn-outline btn-sm" onclick="openModal(${r.id})">👁️ Ver</button>
        <button class="btn btn-danger btn-sm" onclick="deletePrint(${r.id})">🗑️</button>
      </div></td>
    </tr>`;
  }).join('');
}

async function deletePrint(id){
  if(!confirm('Apagar registro #'+id+' e sua foto? Essa ação não pode ser desfeita.')) return;
  try{
    const r = await fetch('../api/prints.php?action=delete&id='+id, {method:'DELETE'});
    const j = await r.json();
    if(!j.ok) throw new Error(j.error);
    loadPrints(curPage);
  }catch(err){ alert('Erro ao apagar: '+err.message); }
}

async function runCleanup(){
  if(!confirm('Rodar limpeza agora? Isso apaga fotos e registros com mais de 60 dias.')) return;
  try{
    const r = await fetch('../api/prints.php?action=cleanup');
    const j = await r.json();
    alert(j.msg || `Limpeza: ${j.cleanup.rows} registros, ${j.cleanup.files} fotos`);
    loadPrints(1);
  }catch(err){ alert('Erro: '+err.message); }
}

function escapeHtml(s){
  return String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

// MODAL
async function openModal(id){
  const modal = document.getElementById('modal');
  const body = document.getElementById('mBody');
  const title = document.getElementById('mTitle');
  const sub = document.getElementById('mSub');
  modal.classList.add('open');
  body.innerHTML = 'Carregando...';
  title.textContent = 'Impressão #' + id;
  sub.textContent = '';
  try{
    const r = await fetch('../api/prints.php?action=get&id='+id);
    const j = await r.json();
    if(!j.ok) throw new Error(j.error);
    const d = j.data;
    title.textContent = (d.escola || 'Sem escola') + ' — #' + d.id;
    sub.textContent = new Date(d.created_at.replace(' ','T')).toLocaleString('pt-BR') + ' • IP ' + (d.ip||'—') + ' • expira ' + (d.expires_at||'—');
    const equips = (()=>{ try{ const a=JSON.parse(d.equipamentos); if(Array.isArray(a)) return a; }catch(e){} return d.equipamentos ? [d.equipamentos] : []; })();
    if(typeof d.equipamentos === 'string' && d.equipamentos.startsWith('[')){
      try{ const arr=JSON.parse(d.equipamentos); if(Array.isArray(arr) && arr.length) equips.splice(0,equips.length,...arr); }catch(e){}
    }
    let equipsList = equips;
    if(equipsList.length===1 && typeof equipsList[0]==='string' && equipsList[0].includes('","')){
      try{ equipsList = JSON.parse(equipsList[0]); }catch(e){}
    }
    // se ainda for string única, tenta quebrar
    if(equipsList.length===1 && typeof equipsList[0]==='string' && equipsList[0].length>80){
      // mantém
    }
    const fotoHtml = d.foto ? `<img src="../${d.foto}" style="max-width:100%;border-radius:12px;border:1px solid var(--border)" onerror="this.style.display='none'"><div style="margin-top:6px"><a class="btn btn-outline btn-sm" href="../${d.foto}" target="_blank">Abrir foto em nova aba</a> <span style="font-size:12px;color:var(--muted)">Apagada automaticamente após 60 dias</span></div>` : '<div style="padding:12px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;color:#64748b">Sem foto salva (impressão antiga ou sem snapshot)</div>';
    const htmlPreview = d.html_snapshot ? `<iframe class="preview-frame" srcdoc="${escapeAttr(d.html_snapshot)}"></iframe>` : '<div style="color:#64748b">Sem HTML snapshot.</div>';
    body.innerHTML = `
      <div style="display:grid;gap:14px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div><label>Escola</label><div style="font-weight:800;text-transform:uppercase">${escapeHtml(d.escola||'—')}</div></div>
          <div><label>Data</label><div>${escapeHtml(d.created_at)} • expira ${escapeHtml(d.expires_at||'—')}</div></div>
        </div>
        <div><label>Equipamentos</label><div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">${equipsList.map(e=>`<span class="equip-tag">${escapeHtml(e)}</span>`).join('') || '<span style="color:#94a3b8">—</span>'}</div></div>
        <div><label>Foto (snapshot)</label><div style="margin-top:6px">${fotoHtml}</div></div>
        <div><label>HTML snapshot (prévia fiel do termo)</label><div style="margin-top:6px">${htmlPreview}</div></div>
        <div style="font-size:11px;color:var(--muted)">IP: ${escapeHtml(d.ip||'—')} • UA: ${escapeHtml((d.user_agent||'').slice(0,120))}</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-outline btn-sm" onclick="reprintHtml(${d.id})">🖨️ Reimprimir este HTML</button>
          <button class="btn btn-danger btn-sm" onclick="deletePrint(${d.id}); closeModal();">🗑️ Apagar registro</button>
        </div>
      </div>
    `;
  }catch(err){
    body.innerHTML = `<div style="color:#dc2626">Erro: ${escapeHtml(err.message)}</div>`;
  }
}
function escapeAttr(s){ return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }
function closeModal(){ document.getElementById('modal').classList.remove('open'); }
let _reprintCache = {};
async function reprintHtml(id){
  try{
    if(!_reprintCache[id]){
      const r = await fetch('../api/prints.php?action=get&id='+id);
      const j = await r.json();
      if(!j.ok) throw new Error(j.error);
      _reprintCache[id]=j.data.html_snapshot;
    }
    const html = _reprintCache[id];
    if(!html) throw new Error('Sem HTML');
    const w = window.open('','_blank');
    w.document.write(html);
    w.document.close();
    w.focus();
    setTimeout(()=> w.print(), 600);
  }catch(err){ alert('Falha ao reimprimir: '+err.message); }
}

// USUÁRIOS
async function loadUsers(){
  const list = document.getElementById('usersList');
  list.innerHTML = '<div style="text-align:center;padding:14px;color:var(--muted)">Carregando...</div>';
  try{
    const r = await fetch('../api/users.php');
    const j = await r.json();
    if(!j.ok) throw new Error(j.error);
    document.getElementById('usersCount').textContent = j.users.length + ' usuários';
    document.getElementById('sUsers').textContent = j.users.length;
    if(j.users.length===0) list.innerHTML='<div style="color:var(--muted)">Nenhum usuário.</div>';
    else list.innerHTML = j.users.map(u=>{
      const isMe = u.id == <?= (int)$uid ?>;
      return `<div class="user-row">
        <div><b>${escapeHtml(u.username)}</b> ${isMe?'<span class="pill pill-ok" style="margin-left:6px">você</span>':''}<div class="meta">ID #${u.id} • criado em ${new Date(u.created_at.replace(' ','T')).toLocaleDateString('pt-BR')}</div></div>
        <div>${isMe?'<span style="font-size:12px;color:var(--muted)">—</span>':`<button class="btn btn-danger btn-sm" onclick="deleteUser(${u.id}, '${escapeHtml(u.username)}')">Remover</button>`}</div>
      </div>`;
    }).join('');
  }catch(err){
    list.innerHTML = `<div style="color:#dc2626">Erro: ${escapeHtml(err.message)}</div>`;
  }
}
async function deleteUser(id, name){
  if(!confirm(`Remover usuário "${name}" (#${id})?`)) return;
  try{
    const r = await fetch('../api/users.php?id='+id, {method:'DELETE'});
    const j = await r.json();
    if(!j.ok) throw new Error(j.error);
    loadUsers();
  }catch(err){ alert('Erro: '+err.message); }
}
document.getElementById('formUser').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const u = document.getElementById('newUser').value.trim();
  const p = document.getElementById('newPass').value;
  const alertBox = document.getElementById('userAlert');
  alertBox.className='alert'; alertBox.style.display='none';
  try{
    const r = await fetch('../api/users.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({username:u,password:p})});
    const j = await r.json();
    if(!j.ok) throw new Error(j.error);
    alertBox.textContent = `Usuário "${j.username}" criado!`; alertBox.className='alert ok show';
    document.getElementById('formUser').reset();
    loadUsers();
  }catch(err){
    alertBox.textContent = err.message; alertBox.className='alert err show';
  }
});

// TROCA SENHA
document.getElementById('formPass').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const cur = document.getElementById('curPass').value;
  const n1 = document.getElementById('newPass2').value;
  const n2 = document.getElementById('newPass3').value;
  const box = document.getElementById('passAlert');
  box.className='alert'; box.style.display='none';
  if(n1!==n2){ box.textContent='As novas senhas não conferem.'; box.className='alert err show'; return; }
  if(n1.length<4){ box.textContent='Senha muito curta.'; box.className='alert err show'; return; }
  try{
    const r = await fetch('../api/auth.php?action=change_password', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({current:cur, new:n1})});
    const j = await r.json();
    if(!j.ok) throw new Error(j.error);
    box.textContent='Senha alterada com sucesso!'; box.className='alert ok show';
    e.target.reset();
  }catch(err){ box.textContent=err.message; box.className='alert err show'; }
});

// INIT
loadPrints(1);
</script>
</body>
</html>
