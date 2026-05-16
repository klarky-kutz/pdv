<?php
ob_start();
session_start();
include realpath(__DIR__.'/../').'/_init.php';

if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

$document->setTitle('Conversas · Moda IA');
$document->setBodyClass('concierge_conversas');

include ("header.php");
include ("left_sidebar.php");
?>
<div class="content-wrapper" style="min-height:1px">
  <section class="content-header">
    <h1><i class="fa fa-comments" style="color:#6d28d9;margin-right:8px"></i>Conversas WhatsApp</h1>
    <ol class="breadcrumb">
      <li><a href="dashboard.php"><i class="fa fa-home"></i> Home</a></li>
      <li>Moda IA</li>
      <li class="active">Conversas</li>
    </ol>
  </section>
  <section class="content">
<style>
/* ── Moda IA – Estilos ── */
#mia-root{display:flex;flex-direction:column;gap:14px}
.mia-top{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}
#mia-root .btn{display:inline-flex!important;align-items:center;gap:6px;padding:8px 14px;border-radius:2px!important;font-size:13px;font-weight:600;transition:all .18s;cursor:pointer}
#mia-root .btn-primary{background:linear-gradient(135deg,#6d28d9,#7c3aed)!important;color:#fff!important;border:none!important}
#mia-root .btn-secondary{background:#fff!important;border:1px solid #d1d5db!important;color:#374151!important}
#mia-root .btn-stats{background:#fff!important;border:1px solid #c4b5fd!important;color:#6d28d9!important}
#mia-root .btn-sm{padding:5px 10px!important;font-size:12px!important}
.btn-pause{background:#fff!important;border:1px solid #fca5a5!important;color:#dc2626!important}
.btn-pause:hover{background:#fee2e2!important}
.btn-resume{background:linear-gradient(135deg,#6d28d9,#7c3aed)!important;color:#fff!important;border:none!important}

/* info boxes */
.ib-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:14px}
.ib{background:#fff;border:1px solid #e5e7eb;border-radius:2px;padding:14px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);position:relative;overflow:hidden}
.ib-icon{width:62px;height:62px;border-radius:2px;display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;flex-shrink:0}
.ib-icon.violet{background:linear-gradient(135deg,#6d28d9,#7c3aed)}
.ib-icon.green{background:linear-gradient(135deg,#059669,#10b981)}
.ib-icon.blue{background:linear-gradient(135deg,#2563eb,#3b82f6)}
.ib-icon.orange{background:linear-gradient(135deg,#d97706,#f59e0b)}
.ib-content{flex:1;min-width:0}
.ib-num{font-size:22px;font-weight:900;color:#111827;line-height:1.1}
.ib-label{font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-top:2px}
.icon-svg{display:inline-flex;align-items:center;justify-content:center}
.icon-svg svg{display:block}

/* filter bar */
.filter-bar{background:#fff;border:1px solid #e5e7eb;border-radius:2px;padding:10px 14px;display:flex;align-items:center;gap:10px;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.fb-label{font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;display:flex;align-items:center;gap:5px}
.fb-chips{display:flex;gap:6px;flex-wrap:wrap}
.fb-chip{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid #d1d5db;color:#6b7280;cursor:pointer;transition:all .15s;background:#fff;display:flex;align-items:center;gap:4px}
.fb-chip:hover{border-color:#a78bfa;color:#6d28d9;background:#f5f3ff}
.fb-chip.on{background:#ede9fe;border-color:#c4b5fd;color:#4c1d95}
.fb-spacer{flex:1}
.fb-search{display:flex;align-items:center;gap:7px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:2px;padding:5px 10px;transition:border-color .15s}
.fb-search i{color:#9ca3af;font-size:13px}
.fb-search input{border:none;background:transparent;outline:none;font-size:13px;color:#374151;width:150px}

/* table */
.mia-box{background:#fff;border:1px solid #e5e7eb;border-radius:2px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden}
.bh{border-top:3px solid #6d28d9;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f3f4f6}
.bt{font-size:14px;font-weight:700;color:#374151;display:flex;align-items:center;gap:8px}
.bt i{color:#6d28d9}
.bt .count{background:#ede9fe;color:#4c1d95;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px}
table{width:100%;border-collapse:collapse}
thead th{background:#f9fafb;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;padding:10px 14px;text-align:left;border-bottom:1px solid #e5e7eb;white-space:nowrap}
tbody tr{border-bottom:1px solid #f3f4f6;transition:background .12s}
tbody tr:hover{background:#faf5ff}
td{padding:10px 14px;vertical-align:middle}
.av{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0;background:linear-gradient(135deg,#f5f3ff,#ede9fe);color:#6d28d9}
.badge{font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;display:inline-flex;align-items:center;gap:4px;white-space:nowrap}
.badge-ativa{background:#d1fae5;color:#065f46}
.badge-manual{background:#f3f4f6;color:#6b7280}
.badge-auto{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
.inter-num{font-size:20px;font-weight:900;color:#4c1d95;line-height:1}
.inter-sub{font-size:10px;color:#9ca3af;font-weight:600;margin-top:2px}

/* Toast */
.mia-toast{position:fixed;bottom:20px;right:20px;background:#222d32;color:#fff;border-left:3px solid #6d28d9;padding:10px 16px;border-radius:2px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;z-index:9999;transform:translateX(120%);transition:transform .3s cubic-bezier(.34,1.56,.64,1);min-width:260px;box-shadow:0 4px 20px rgba(0,0,0,.3)}
.mia-toast.show{transform:translateX(0)}
.mia-toast i{color:#a78bfa}

/* Modal Memória */
.mia-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:10000;align-items:center;justify-content:center;padding:20px}
.mia-modal-content{background:#fff;width:100%;max-width:600px;border-radius:4px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,.2);display:flex;flex-direction:column;max-height:90vh}
.mia-modal-header{padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;background:#f9fafb}
.mia-modal-title{font-size:16px;font-weight:700;color:#111827;display:flex;align-items:center;gap:10px}
.mia-modal-close{background:none;border:none;font-size:20px;color:#9ca3af;cursor:pointer;padding:5px;line-height:1}
.mia-modal-close:hover{color:#ef4444}
.mia-modal-body{padding:20px;overflow-y:auto;flex:1}
.mia-modal-footer{padding:14px 20px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;background:#f9fafb}

.mem-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:20px}
.mem-item{background:#f9fafb;border:1px solid #e5e7eb;padding:12px;border-radius:4px}
.mem-label{font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.mem-value{font-size:13px;font-weight:600;color:#111827}

.mem-section-title{font-size:12px;font-weight:700;color:#6d28d9;margin:20px 0 10px;display:flex;align-items:center;gap:8px}
.mem-section-title::after{content:'';flex:1;height:1px;background:#ddd6fe}

.mem-pref-list{display:flex;flex-direction:column;gap:8px}
.mem-pref-row{display:flex;justify-content:space-between;padding:8px;background:#f5f3ff;border-radius:4px;border-left:3px solid #a78bfa}
.mem-pref-key{font-size:11px;font-weight:700;color:#4c1d95;text-transform:capitalize}
.mem-pref-val{font-size:11px;font-weight:600;color:#111827}

/* Confirm Modal */
.confirm-modal-content {max-width:400px;text-align:center;padding:30px 20px;border-radius:12px;border:none}
.confirm-icon {width:70px;height:70px;background:#fef2f2;color:#ef4444;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 20px;box-shadow:inset 0 0 0 1px rgba(239,68,68,0.1)}
.confirm-icon.warning {background:#fffbeb;color:#f59e0b;box-shadow:inset 0 0 0 1px rgba(245,158,11,0.1)}
.confirm-title {font-size:20px;font-weight:800;color:#111827;margin-bottom:12px;letter-spacing:-0.5px}
.confirm-text {font-size:14px;color:#4b5563;margin-bottom:30px;line-height:1.6;padding:0 10px}
.confirm-btns {display:flex;gap:12px;justify-content:center}
.confirm-btns .btn {padding:10px 20px;font-weight:700;border-radius:8px;transition:all 0.2s}
.btn-warning {background:#f59e0b;color:#fff;border:none;box-shadow:0 4px 12px rgba(245,158,11,0.2)}
.btn-warning:hover {background:#d97706;transform:translateY(-1px);box-shadow:0 6px 15px rgba(245,158,11,0.3)}
.btn-danger {background:#ef4444;color:#fff;border:none;box-shadow:0 4px 12px rgba(239,68,68,0.2)}
.btn-danger:hover {background:#dc2626;transform:translateY(-1px);box-shadow:0 6px 15px rgba(239,68,68,0.3)}
.confirm-btns .btn-secondary {background:#f3f4f6;color:#4b5563;border:1px solid #e5e7eb}
.confirm-btns .btn-secondary:hover {background:#e5e7eb;color:#1f2937}
</style>

<div id="mia-root">
  <div class="mia-top">
    <div class="fb-date-nav" style="margin-right:auto">
      <button class="fb-date-btn" onclick="mudarData(-1)" title="Dia Anterior"><i class="fa fa-chevron-left"></i></button>
      <button class="fb-date-btn" onclick="abrirCalendario()" style="font-weight:700;min-width:90px">
        <i class="fa fa-calendar" style="color:#6d28d9;margin-right:6px"></i> 
        <span id="top-date-label">Hoje</span>
      </button>
      <button class="fb-date-btn" onclick="mudarData(1)" title="Próximo Dia"><i class="fa fa-chevron-right"></i></button>
    </div>
    
    <input type="date" id="date-picker" style="display:none" onchange="selecionarData(this.value)">
    <button class="btn btn-secondary btn-sm" onclick="exportarCSV()"><i class="fa fa-download"></i> Exportar CSV</button>
    <button class="btn btn-primary" onclick="carregarConversas()"><i class="fa fa-refresh"></i> Atualizar</button>
  </div>
  <div class="ib-grid">
    <div class="ib">
      <div class="ib-icon violet"><i class="fa fa-comments-o"></i></div>
      <div class="ib-content">
        <div class="ib-num" id="stat-total">0</div>
        <div class="ib-label">Contatos no Dia</div>
      </div>
    </div>
    <div class="ib">
      <div class="ib-icon green">
        <span class="icon-svg" aria-hidden="true">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
            <path d="M15 14c2.67 0 8 1.34 8 4v2h-8.5" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="9" cy="8" r="4" stroke="#ffffff" stroke-width="2"/>
            <path d="M16 6v6M13 9h6" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
            <path d="M1 20c0-2.66 5.33-4 8-4" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </span>
      </div>
      <div class="ib-content">
        <div class="ib-num" id="stat-novas">0</div>
        <div class="ib-label">Novos Clientes</div>
      </div>
    </div>
    <div class="ib">
      <div class="ib-icon blue"><i class="fa fa-shopping-cart"></i></div>
      <div class="ib-content">
        <div class="ib-num" id="stat-pedidos">0</div>
        <div class="ib-label">Vendas Geradas</div>
      </div>
    </div>
    <div class="ib">
      <div class="ib-icon orange"><i class="fa fa-magic"></i></div>
      <div class="ib-content">
        <div class="ib-num" id="stat-ativas">0</div>
        <div class="ib-label">IA em Atendimento</div>
      </div>
    </div>
  </div>

  <div class="filter-bar">
    <span class="fb-label"><i class="fa fa-filter"></i> Filtrar</span>
    <div class="fb-chips">
      <div class="fb-chip on" onclick="filtrar(this,'todas')"><i class="fa fa-list"></i> Todas</div>
      <div class="fb-chip" onclick="filtrar(this,'ativas')"><i class="fa fa-circle" style="font-size:7px;color:#059669"></i> Ativas</div>
      <div class="fb-chip" onclick="filtrar(this,'manual')"><i class="fa fa-user"></i> Manual</div>
      <div class="fb-chip" onclick="selecionarData(new Date().toISOString().split('T')[0])"><i class="fa fa-calendar"></i> Hoje</div>
    </div>
    
    <div class="fb-spacer"></div>

    <div class="fb-search">
      <i class="fa fa-search"></i>
      <input type="text" id="conv-search" placeholder="Buscar cliente ou telefone..." oninput="debounceSearch()">
    </div>
    
  </div>

  <div class="mia-box">
    <div class="bh">
      <span class="bt"><i class="fa fa-whatsapp" style="color:#25d366"></i> Atendimentos do Dia <span class="count" id="conv-count">0</span></span>
    </div>
    <table>
      <thead>
        <tr>
          <th>Cliente</th>
          <th>Último Produto Solicitado</th>
          <th>Mensagens</th>
          <th>Último Contato</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody id="conv-body">
        <tr><td colspan="6" style="text-align:center;padding:30px;color:#9ca3af">Carregando conversas...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="mia-toast" id="toast"><i class="fa fa-magic"></i> <span id="toast-msg">Operação concluída</span></div>

<!-- Modal Memória -->
<div class="mia-modal" id="modal-memoria">
  <div class="mia-modal-content">
    <div class="mia-modal-header">
      <div class="mia-modal-title"><i class="fa fa-brain" style="color:#6d28d9"></i> Memória da IA</div>
      <button class="mia-modal-close" onclick="fecharMemoria()">&times;</button>
    </div>
    <div class="mia-modal-body" id="memoria-body">
      <div style="text-align:center;padding:30px;color:#9ca3af"><i class="fa fa-spinner fa-spin"></i> Carregando memória...</div>
    </div>
    <div class="mia-modal-footer">
      <button class="btn btn-secondary" onclick="fecharMemoria()">Fechar</button>
    </div>
  </div>
</div>

<!-- Modal Confirmação -->
<div class="mia-modal" id="modal-confirmacao">
  <div class="mia-modal-content confirm-modal-content">
    <div id="confirm-icon-box" class="confirm-icon">
      <i id="confirm-icon-i" class="fa fa-exclamation-triangle"></i>
    </div>
    <div class="confirm-title" id="confirm-title">Confirmar Ação</div>
    <div class="confirm-text" id="confirm-text">Deseja realmente prosseguir?</div>
    <div class="confirm-btns">
      <button class="btn btn-secondary" onclick="fecharConfirmacao()">Cancelar</button>
      <button class="btn" id="confirm-btn-exec">Confirmar</button>
    </div>
  </div>
</div>

<script>
let _convFilter = 'todas';
let _currentDate = new Date().toISOString().split('T')[0];
let _searchTimer;
let _tt;
let _lastRenderedHtml = '';
let _currentLoadController = null;

function showToast(msg){const t=document.getElementById('toast');document.getElementById('toast-msg').textContent=msg;t.classList.add('show');clearTimeout(_tt);_tt=setTimeout(()=>t.classList.remove('show'),2800)}

function mudarData(offset) {
  let d = new Date(_currentDate + 'T12:00:00');
  d.setDate(d.getDate() + offset);
  selecionarData(d.toISOString().split('T')[0]);
}

function selecionarData(date) {
  if (!date) return;
  _currentDate = date;
  const hoje = new Date().toISOString().split('T')[0];
  document.getElementById('date-picker').value = date;
  const topDateLabel = document.getElementById('top-date-label');
  
  if (date === hoje) {
    topDateLabel.textContent = 'Hoje';
  } else {
    const parts = date.split('-');
    topDateLabel.textContent = parts[2] + '/' + parts[1];
  }
  carregarConversas();
}

function filtrar(el, filter) {
  _convFilter = filter;
  document.querySelectorAll('.fb-chip').forEach(c => c.classList.remove('on'));
  el.classList.add('on');
  carregarConversas();
}

function debounceSearch() {
  clearTimeout(_searchTimer);
  _searchTimer = setTimeout(carregarConversas, 400);
}

function carregarConversas() {
  if (_currentLoadController) _currentLoadController.abort();
  _currentLoadController = new AbortController();
  const q = document.getElementById('conv-search').value;
  const url = '../_inc/ai_conversas_actions.php?action=list&filter=' + _convFilter + '&date=' + _currentDate + '&search=' + encodeURIComponent(q);
  
  fetch(url, { signal: _currentLoadController.signal })
    .then(async r => {
      const payload = await r.json();
      if (!r.ok || payload.error) {
        throw new Error(payload.message || ('Falha HTTP ' + r.status));
      }
      return payload;
    })
    .then(d => {
      if (d.html !== _lastRenderedHtml) {
        document.getElementById('conv-body').innerHTML = d.html;
        _lastRenderedHtml = d.html;
      }
      document.getElementById('conv-count').textContent = d.count;
      
      // Update stats
      if (d.stats) {
        document.getElementById('stat-total').textContent = d.stats.total;
        document.getElementById('stat-novas').textContent = d.stats.novas;
        document.getElementById('stat-pedidos').textContent = d.stats.pedidos;
        document.getElementById('stat-ativas').textContent = d.stats.ativas;
      }
    }).catch(err => {
      if (err && err.name === 'AbortError') return;
      console.error(err);
      showToast('Erro ao carregar dados: ' + err.message);
    });
}
function abrirCalendario() {
  const picker = document.getElementById('date-picker');
  if (typeof picker.showPicker === 'function') {
    picker.showPicker();
    return;
  }
  picker.click();
}

function toggleIA(remoteJid, status) {
  const fd = new FormData();
  fd.append('action', 'toggle_atendimento');
  fd.append('remote_jid', remoteJid);
  fd.append('status', status);
  
  fetch('../_inc/ai_conversas_actions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.error) return showToast('Erro: ' + d.message);
      showToast('Status da IA atualizado!');
      carregarConversas();
    }).catch(() => showToast('Erro ao atualizar status.'));
}

function verMemoria(phone) {
  const modal = document.getElementById('modal-memoria');
  const body = document.getElementById('memoria-body');
  modal.style.display = 'flex';
  body.innerHTML = '<div style="text-align:center;padding:30px;color:#9ca3af"><i class="fa fa-spinner fa-spin"></i> Carregando memória...</div>';

  fetch('../_inc/ai_conversas_actions.php?action=get_memory&phone=' + encodeURIComponent(phone))
    .then(r => r.json())
    .then(d => {
      if (d.error) {
        body.innerHTML = '<div style="text-align:center;padding:30px;color:#ef4444"><i class="fa fa-exclamation-triangle"></i> ' + d.message + '</div>';
        return;
      }

      let html = `
        <div class="mem-grid">
          <div class="mem-item">
            <div class="mem-label">Cliente</div>
            <div class="mem-value">${d.profile.name}</div>
          </div>
          <div class="mem-item">
            <div class="mem-label">WhatsApp</div>
            <div class="mem-value">${d.profile.phone}</div>
          </div>
          <div class="mem-item">
            <div class="mem-label">Total Mensagens</div>
            <div class="mem-value">${d.profile.total_interactions}</div>
          </div>
          <div class="mem-item">
            <div class="mem-label">Última Interação</div>
            <div class="mem-value">${d.profile.last_interaction}</div>
          </div>
        </div>

        <div class="mem-section-title">Memória de Curto Prazo (N8N / IA)</div>
        <div class="mem-grid">
          <div class="mem-item" style="grid-column: span 2">
            <div class="mem-label">Atualizar Usuário (interesse/dúvida)</div>
            <div class="mem-value" style="font-weight:400;color:#374151;white-space:pre-wrap">${d.profile.interesse_duvida || 'Nenhum registro recente.'}</div>
          </div>
          <div class="mem-item" style="grid-column: span 2">
            <div class="mem-label">Resumo da Conversa</div>
            <div class="mem-value" style="font-weight:400;color:#374151;white-space:pre-wrap">${d.profile.conversation_summary || 'Nenhum resumo gerado ainda.'}</div>
          </div>
        </div>

        <div class="mem-section-title">Preferências e Perfil Mapeado (Longo Prazo)</div>
        <div class="mem-pref-list">
      `;

      const keys = Object.keys(d.memory);
      const filteredKeys = keys.filter(k => k !== 'atualizar_usuario' && k !== 'resumo_conversa' && k !== 'tamanho_usual');
      
      if (d.profile.usual_size) {
        html += `
          <div class="mem-pref-row">
            <span class="mem-pref-key">Tamanho Usual</span>
            <span class="mem-pref-val">${d.profile.usual_size}</span>
          </div>
        `;
      }
      
      if (filteredKeys.length === 0 && !d.profile.usual_size) {
        html += '<div style="text-align:center;padding:10px;color:#9ca3af;font-size:12px">Nenhuma preferência mapeada ainda.</div>';
      } else {
        filteredKeys.forEach(key => {
          let val = d.memory[key];
          if (typeof val === 'object') val = JSON.stringify(val);
          html += `
            <div class="mem-pref-row">
              <span class="mem-pref-key">${key.replace(/_/g, ' ')}</span>
              <span class="mem-pref-val">${val}</span>
            </div>
          `;
        });
      }

      html += '</div>';
      body.innerHTML = html;
    }).catch(() => {
      body.innerHTML = '<div style="text-align:center;padding:30px;color:#ef4444"><i class="fa fa-exclamation-triangle"></i> Erro ao carregar memória.</div>';
    });
}

function fecharMemoria() {
  document.getElementById('modal-memoria').style.display = 'none';
}

let _confirmAction = null;

function abrirConfirmacao(config) {
  const modal = document.getElementById('modal-confirmacao');
  const iconBox = document.getElementById('confirm-icon-box');
  const iconI = document.getElementById('confirm-icon-i');
  const title = document.getElementById('confirm-title');
  const text = document.getElementById('confirm-text');
  const btnExec = document.getElementById('confirm-btn-exec');

  iconBox.className = 'confirm-icon ' + (config.type || '');
  iconI.className = 'fa ' + (config.icon || 'fa-exclamation-triangle');
  title.textContent = config.title || 'Confirmar';
  text.textContent = config.text || '';
  btnExec.className = 'btn ' + (config.btnClass || 'btn-primary');
  btnExec.textContent = config.btnText || 'Confirmar';
  
  _confirmAction = config.action;
  btnExec.onclick = () => {
    if (_confirmAction) _confirmAction();
    fecharConfirmacao();
  };
  
  modal.style.display = 'flex';
}

function fecharConfirmacao() {
  document.getElementById('modal-confirmacao').style.display = 'none';
  _confirmAction = null;
}

function limparConversa(phone) {
  abrirConfirmacao({
    type: 'warning',
    icon: 'fa-eraser',
    title: 'Limpar Memória',
    text: 'Deseja realmente limpar a memória da IA para este cliente? Isso removerá as preferências salvas e o histórico de interações (incluindo LIDs vinculados). O nome do cliente será mantido.',
    btnClass: 'btn-warning',
    btnText: 'Limpar Agora',
    action: () => {
      const fd = new FormData();
      fd.append('action', 'clear_memory');
      fd.append('phone', phone);
      
      fetch('../_inc/ai_conversas_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
          if (d.error) return showToast('Erro: ' + d.message);
          showToast('Memória da conversa resetada!');
          carregarConversas();
        }).catch(() => showToast('Erro ao limpar conversa.'));
    }
  });
}

function deletarConversa(phone) {
  abrirConfirmacao({
    type: 'danger',
    icon: 'fa-trash',
    title: 'Excluir Conversa',
    text: 'Deseja realmente apagar o registro desta conversa? Esta ação é irreversível e apagará todos os perfis vinculados.',
    btnClass: 'btn-danger',
    btnText: 'Excluir Registro',
    action: () => {
      const fd = new FormData();
      fd.append('action', 'delete');
      fd.append('phone', phone);
      
      fetch('../_inc/ai_conversas_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
          if (d.error) return showToast('Erro: ' + d.message);
          showToast('Registro de conversa apagado!');
          carregarConversas();
        }).catch(() => showToast('Erro ao apagar conversa.'));
    }
  });
}

function abrirWhatsApp(telefone) {
  const numero = telefone.replace(/\D/g, '');
  window.open('https://wa.me/55' + numero, '_blank');
}
function exportarCSV() {
  const rows = Array.from(document.querySelectorAll('#conv-body tr'));
  const dataRows = rows.filter(row => row.querySelectorAll('td').length === 6);
  if (!dataRows.length) {
    showToast('Sem dados para exportar.');
    return;
  }

  const header = ['Cliente', 'Telefone', 'Ultimo Produto Solicitado', 'Mensagens', 'Ultimo Contato', 'Status'];
  const csvLines = [header.join(';')];

  dataRows.forEach(row => {
    const cells = row.querySelectorAll('td');
    const infosCliente = cells[0].querySelectorAll('div[style*=\"font-size\"]');
    const cliente = infosCliente[0] ? infosCliente[0].textContent : '';
    const telefone = infosCliente[1] ? infosCliente[1].textContent : '';
    const produto = (cells[1].querySelector('div') || {}).textContent || '';
    const mensagens = (cells[2].querySelector('.inter-num') || {}).textContent || '0';
    const ultimoContato = cells[3].textContent || '';
    const status = cells[4].textContent || '';

    const clean = value => '"' + String(value).replace(/"/g, '""').replace(/\s+/g, ' ').trim() + '"';
    csvLines.push([
      clean(cliente),
      clean(telefone.replace('', '').trim()),
      clean(produto),
      clean(mensagens),
      clean(ultimoContato),
      clean(status),
    ].join(';'));
  });

  const blob = new Blob([csvLines.join('\n')], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'conversas_' + _currentDate + '.csv';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
  showToast('CSV exportado com sucesso!');
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('date-picker').value = _currentDate;
  selecionarData(_currentDate);
});
</script>

  </section>
</div>
<?php include ("footer.php"); ?>
