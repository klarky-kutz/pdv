<?php
ob_start();
session_start();
include realpath(__DIR__.'/../').'/_init.php';

if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

$document->setTitle('Comprovantes Pix · Moda IA');
$document->setBodyClass('concierge_pix');

include ("header.php");
include ("left_sidebar.php");
?>
<div class="content-wrapper">
  <section class="content-header">
    <h1><i class="fa fa-qrcode" style="color:#6d28d9;margin-right:8px"></i>Validação de Comprovantes Pix</h1>
    <ol class="breadcrumb">
      <li><a href="dashboard.php"><i class="fa fa-home"></i> Home</a></li>
      <li>Moda IA</li>
      <li class="active">Comprovantes Pix</li>
    </ol>
  </section>
  <section class="content">
<style>
/* ── Moda IA – Estilos compartilhados ── */
@keyframes mia-blink{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.45;transform:scale(.7)}}
@keyframes mia-fadeIn{from{opacity:0}to{opacity:1}}
@keyframes mia-slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
#mia-root{display:flex;flex-direction:column;gap:14px}
.mia-actions{display:flex;justify-content:flex-end;gap:8px}
/* buttons */
#mia-root .btn,.mia-overlay .btn{display:inline-flex!important;align-items:center;gap:6px;padding:8px 14px;border-radius:2px!important;font-size:13px;font-weight:600;transition:all .18s}
#mia-root .btn-primary,.mia-overlay .btn-primary{background:linear-gradient(135deg,#6d28d9,#7c3aed)!important;color:#fff!important;box-shadow:0 2px 8px rgba(109,40,217,.35)!important;border:none!important}
#mia-root .btn-primary:hover,.mia-overlay .btn-primary:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(109,40,217,.45)!important;background:linear-gradient(135deg,#6d28d9,#7c3aed)!important;color:#fff!important}
#mia-root .btn-secondary,.mia-overlay .btn-secondary{background:#fff!important;border:1px solid #d1d5db!important;color:#374151!important}
#mia-root .btn-secondary:hover,.mia-overlay .btn-secondary:hover{border-color:#a78bfa!important;color:#6d28d9!important}
#mia-root .btn-danger,.mia-overlay .btn-danger{background:#fff!important;border:1px solid #fca5a5!important;color:#dc2626!important}
#mia-root .btn-danger:hover,.mia-overlay .btn-danger:hover{background:#fee2e2!important}
#mia-root .btn-success,.mia-overlay .btn-success{background:linear-gradient(135deg,#059669,#10b981)!important;color:#fff!important;border:none!important}
#mia-root .btn-success:hover,.mia-overlay .btn-success:hover{transform:translateY(-1px);background:linear-gradient(135deg,#059669,#10b981)!important;color:#fff!important}
#mia-root .btn-stats,.mia-overlay .btn-stats{background:#fff!important;border:1px solid #c4b5fd!important;color:#6d28d9!important}
#mia-root .btn-stats:hover,.mia-overlay .btn-stats:hover{background:#ede9fe!important;color:#6d28d9!important}
#mia-root .btn-sm,.mia-overlay .btn-sm{padding:5px 10px!important;font-size:12px!important}
/* info boxes */
.ib-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.ib{background:#fff;border:1px solid #e5e7eb;border-radius:2px;padding:14px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);position:relative;overflow:hidden}
.ib-icon{width:62px;height:62px;border-radius:2px;display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;flex-shrink:0}
.ib-icon.violet{background:linear-gradient(135deg,#6d28d9,#7c3aed)}
.ib-icon.blue{background:linear-gradient(135deg,#2563eb,#3b82f6)}
.ib-icon.amber{background:linear-gradient(135deg,#d97706,#f59e0b)}
.ib-icon.green{background:linear-gradient(135deg,#059669,#10b981)}
.ib-content{flex:1;min-width:0}
.ib-num{font-size:26px;font-weight:900;color:#111827;line-height:1.1}
.ib-label{font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-top:2px}
.ib-sub{font-size:11px;color:#9ca3af;margin-top:3px}
.ib-badge{position:absolute;top:10px;right:10px;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px}
.ib-badge.up{background:#d1fae5;color:#065f46}
.ib-badge.warn{background:#fef3c7;color:#92400e}
/* filter bar */
.filter-bar{background:#fff;border:1px solid #e5e7eb;border-radius:2px;padding:10px 14px;display:flex;align-items:center;gap:10px;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.fb-label{font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;display:flex;align-items:center;gap:5px}
.fb-chips{display:flex;gap:6px;flex-wrap:wrap}
.fb-chip{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid #d1d5db;color:#6b7280;cursor:pointer;transition:all .15s;background:#fff;display:flex;align-items:center;gap:4px}
.fb-chip:hover{border-color:#a78bfa;color:#6d28d9;background:#f5f3ff}
.fb-chip.on{background:#ede9fe;border-color:#c4b5fd;color:#4c1d95}
.fb-spacer{flex:1}
.fb-search{display:flex;align-items:center;gap:7px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:2px;padding:5px 10px;transition:border-color .15s}
.fb-search:focus-within{border-color:#7c3aed}
.fb-search i{color:#9ca3af;font-size:13px}
.fb-search input{border:none;background:transparent;outline:none;font-size:13px;color:#374151;width:180px}
.fb-search input::placeholder{color:#9ca3af}
/* mia-box */
.mia-box{background:#fff;border:1px solid #e5e7eb;border-radius:2px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden}
.bh{border-top:3px solid #6d28d9;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f3f4f6}
.bt{font-size:14px;font-weight:700;color:#374151;display:flex;align-items:center;gap:8px}
.bt i{color:#6d28d9}
.bt .count{background:#ede9fe;color:#4c1d95;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px}
.bt .count-warn{background:#fef3c7;color:#92400e;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px}
.bh-actions{display:flex;gap:8px;align-items:center}
#mia-root table{width:100%;border-collapse:collapse}
#mia-root thead th{background:#f9fafb;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;padding:10px 14px;text-align:left;border-bottom:1px solid #e5e7eb;white-space:nowrap}
#mia-root tbody tr{border-bottom:1px solid #f3f4f6;transition:background .12s}
#mia-root tbody tr:hover{background:#faf5ff}
#mia-root tbody tr:last-child{border-bottom:none}
#mia-root td{padding:10px 14px;vertical-align:middle}
.bf{background:#f9fafb;border-top:1px solid #f3f4f6;padding:10px 16px;display:flex;align-items:center;justify-content:space-between}
.bf-info{font-size:12px;color:#9ca3af;font-weight:600}
/* badges */
#mia-root .badge,.mia-overlay .badge{font-size:10px!important;font-weight:700!important;padding:3px 8px!important;border-radius:20px!important;display:inline-flex!important;align-items:center;gap:4px;white-space:nowrap;line-height:normal!important;background-color:transparent!important}
#mia-root .badge-pend,.mia-overlay .badge-pend{background:#fef3c7!important;color:#92400e!important}
#mia-root .badge-auto,.mia-overlay .badge-auto{background:#d1fae5!important;color:#065f46!important}
#mia-root .badge-manual,.mia-overlay .badge-manual{background:#dbeafe!important;color:#1e40af!important}
#mia-root .badge-rej,.mia-overlay .badge-rej{background:#fee2e2!important;color:#991b1b!important}
/* pagination */
#mia-root .pagination{display:flex!important;gap:4px!important;padding:0;margin:0;list-style:none}
#mia-root .pg-btn{width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:2px;border:1px solid #d1d5db;background:#fff;color:#6b7280;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s}
#mia-root .pg-btn:hover{border-color:#a78bfa;color:#6d28d9}
#mia-root .pg-btn.act{background:linear-gradient(135deg,#6d28d9,#7c3aed);border-color:#6d28d9;color:#fff}
/* pix-specific */
.proof-thumb{width:54px;height:54px;background:linear-gradient(135deg,#f3f4f6,#e5e7eb);border-radius:2px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#9ca3af;border:1px solid #e5e7eb;flex-shrink:0;cursor:pointer;transition:all .15s;overflow:hidden}
.proof-thumb:hover{border-color:#a78bfa;box-shadow:0 2px 8px rgba(109,40,217,.15)}
.ord-id{font-weight:700;color:#111827;font-size:13px}
.ord-client{font-size:12px;color:#6b7280;margin-top:2px;display:flex;align-items:center;gap:4px}
.val-num{font-size:18px;font-weight:900;color:#111827;line-height:1}
.val-sub{font-size:10px;color:#9ca3af;font-weight:600;margin-top:2px}
/* modal overlay/modal */
.mia-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1060;display:flex;align-items:center;justify-content:center;animation:mia-fadeIn .2s}
.mia-overlay.hide{display:none}
.mia-modal{background:#fff;border-radius:2px;box-shadow:0 10px 40px rgba(0,0,0,.3);animation:mia-slideUp .2s;width:780px;max-width:calc(100vw - 32px);max-height:92vh;display:flex;flex-direction:column}
.mh{background:linear-gradient(135deg,#4c1d95,#7c3aed);padding:20px;display:flex;align-items:flex-start;justify-content:space-between;flex-shrink:0}
.mh-info .mt{font-size:16px;font-weight:700;color:#fff;display:flex;align-items:center;gap:8px}
.mh-info .ms{font-size:12px;color:#c4b5fd;margin-top:3px}
.mh-close{background:none;border:none;color:#c4b5fd;font-size:18px;cursor:pointer;padding:2px 6px;transition:color .15s;line-height:1}
.mh-close:hover{color:#fff}
.mb{padding:18px;flex:1;overflow-y:auto;display:flex;gap:16px}
.mf{background:#faf5ff;border-top:1px solid #ede9fe;padding:12px 18px;display:flex;justify-content:flex-end;gap:8px;flex-shrink:0}
.proof-col{width:280px;flex-shrink:0}
.proof-preview{background:#f9fafb;border:1px solid #e5e7eb;border-radius:2px;padding:16px;text-align:center;min-height:240px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px}
.proof-preview .proof-icon{font-size:48px;color:#c4b5fd}
.proof-preview .proof-label{font-size:13px;font-weight:700;color:#374151}
.proof-preview .proof-sub{font-size:11px;color:#9ca3af}
.proof-meta{margin-top:10px;background:#fff;border:1px solid #e5e7eb;border-radius:2px;padding:10px}
.pm-row{display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px}
.pm-row:last-child{margin-bottom:0}
.pm-lbl{color:#6b7280;font-weight:600}
.pm-val{color:#374151;font-weight:700}
.order-col{flex:1;display:flex;flex-direction:column;gap:12px}
.sec-title{font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px;display:flex;align-items:center;gap:5px}
.sec-box{background:#f9fafb;border:1px solid #e5e7eb;border-radius:2px;padding:12px}
.sec-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;font-size:12px}
.sec-row:last-child{margin-bottom:0}
.sec-lbl{color:#6b7280;font-weight:600}
.sec-val{color:#374151;font-weight:700}
.item-row{display:flex;gap:10px;padding:8px 0;border-bottom:1px solid #f3f4f6}
.item-row:last-child{border-bottom:none}
.item-thumb{width:42px;height:42px;border-radius:2px;background:linear-gradient(135deg,#fce7f3,#fdf2f8);display:flex;align-items:center;justify-content:center;font-size:16px;color:#f9a8d4;flex-shrink:0}
.item-name{font-weight:700;color:#111827;font-size:12px}
.item-var{font-size:11px;color:#9ca3af;margin-top:2px}
.item-price{font-size:12px;font-weight:700;color:#6d28d9;margin-top:3px}
.total-row{display:flex;justify-content:space-between;align-items:center;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:2px;padding:10px 12px;margin-top:4px}
.total-lbl{font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase}
.total-val{font-size:22px;font-weight:900;color:#111827}
.validation-note{background:#fef3c7;border:1px solid #fde68a;border-radius:2px;padding:10px 12px;display:flex;align-items:flex-start;gap:8px}
.validation-note i{color:#d97706;margin-top:1px;flex-shrink:0}
.validation-note span{font-size:12px;color:#92400e;font-weight:600;line-height:1.4}
/* toast */
.mia-toast{position:fixed;bottom:20px;right:20px;background:#222d32;color:#fff;border-left:3px solid #6d28d9;padding:10px 16px;border-radius:2px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;z-index:9999;transform:translateX(120%);transition:transform .3s cubic-bezier(.34,1.56,.64,1);min-width:260px;box-shadow:0 4px 20px rgba(0,0,0,.3)}
.mia-toast.show{transform:translateX(0)}
.mia-toast i{color:#a78bfa}
</style>

<div id="mia-root">

  <!-- Ações -->
  <div class="mia-actions">
    <button class="btn btn-danger btn-sm" onclick="abrirModal('confirm-delete-cancelados')"><i class="fa fa-trash"></i> Apagar Comprovantes Cancelados</button>
    <button class="btn btn-secondary btn-sm"><i class="fa fa-download"></i> Exportar</button>
    <button class="btn btn-primary" onclick="showToast('Verificando novos comprovantes...')"><i class="fa fa-refresh"></i> Verificar Agora</button>
  </div>

  <!-- INFO BOXES -->
  <div class="ib-grid">
    <div class="ib">
      <div class="ib-icon amber"><i class="fa fa-clock-o"></i></div>
      <div class="ib-content">
        <div class="ib-num" id="pix-pend-count">0</div>
        <div class="ib-label">Pendentes de Validação</div>
        <div class="ib-sub">pagamentos Pix aguardando confirmação</div>
      </div>
      <div class="ib-badge warn" id="pix-pend-badge">0 pend.</div>
    </div>
    <div class="ib">
      <div class="ib-icon green"><i class="fa fa-check-circle"></i></div>
      <div class="ib-content">
        <div class="ib-num" id="pix-ok-count">0</div>
        <div class="ib-label">Confirmados</div>
        <div class="ib-sub">pedidos Pix já pagos</div>
      </div>
    </div>
    <div class="ib">
      <div class="ib-icon violet"><i class="fa fa-money"></i></div>
      <div class="ib-content">
        <div class="ib-num" id="pix-pend-valor">R$ 0,00</div>
        <div class="ib-label">Valor em Aberto</div>
        <div class="ib-sub">somente pendentes</div>
      </div>
    </div>
    <div class="ib">
      <div class="ib-icon blue"><i class="fa fa-magic"></i></div>
      <div class="ib-content">
        <div class="ib-num" id="pix-cancel-count">0</div>
        <div class="ib-label">Cancelados</div>
        <div class="ib-sub">pagamentos rejeitados/cancelados</div>
      </div>
    </div>
  </div>

  <!-- FILTER BAR -->
  <div class="filter-bar">
    <span class="fb-label"><i class="fa fa-filter"></i> Filtrar</span>
    <div class="fb-chips">
      <div class="fb-chip on" onclick="filtrar(this,'pendentes')"><i class="fa fa-clock-o" style="color:#d97706;font-size:10px"></i> Pendentes</div>
      <div class="fb-chip" onclick="filtrar(this,'confirmados')"><i class="fa fa-check-circle" style="color:#059669;font-size:10px"></i> Confirmados</div>
      <div class="fb-chip" onclick="filtrar(this,'cancelados')"><i class="fa fa-times" style="color:#dc2626;font-size:10px"></i> Cancelados</div>
    </div>
    <div class="fb-spacer"></div>
    <div class="fb-search">
      <i class="fa fa-search"></i>
      <input type="text" id="pix-search" name="pix-search" placeholder="Buscar pedido ou cliente..." oninput="debouncePix()" autocomplete="off">
    </div>
  </div>

  <!-- TABLE -->
  <div class="mia-box">
    <div class="bh">
      <span class="bt"><i class="fa fa-qrcode"></i> Pagamentos Pix <span class="count" id="pix-total-count">0</span> <span class="count-warn" id="pix-total-pend">0 pendentes</span></span>
      <div class="bh-actions">
        <button class="btn btn-secondary btn-sm"><i class="fa fa-download"></i> Exportar CSV</button>
      </div>
    </div>
    <table>
      <thead>
        <tr>
          <th>Comprovante</th>
          <th>Pedido / Cliente</th>
          <th>Valor</th>
          <th>Recebido em</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody id="pix-body"></tbody>
    </table>
    <div class="bf">
      <span class="bf-info" id="pix-footer">—</span>
    </div>
  </div>

</div><!-- /#mia-root -->

<!-- Modal: Confirmação de Exclusão de Cancelados -->
<div class="mia-overlay hide" id="ov-confirm-delete-cancelados">
  <div class="mia-modal" style="width:450px">
    <div class="mh" style="background:linear-gradient(135deg,#991b1b,#ef4444)">
      <div class="mh-info">
        <div class="mt"><i class="fa fa-trash"></i> Confirmar Exclusão</div>
        <div class="ms">Esta ação é irreversível</div>
      </div>
      <button class="mh-close" onclick="fecharModal('confirm-delete-cancelados')"><i class="fa fa-times"></i></button>
    </div>
    <div class="mb" style="flex-direction:column;gap:15px;text-align:center;padding:30px 25px">
      <div style="width:80px;height:80px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto">
        <i class="fa fa-trash-o" style="font-size:40px;color:#dc2626"></i>
      </div>
      <div style="font-size:18px;font-weight:800;color:#111827">Limpeza Total de Comprovantes</div>
      <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0">Você está prestes a apagar <strong>TODOS</strong> os arquivos de imagem de pedidos que foram <strong>Cancelados</strong>.</p>
      <div style="background:#fff7ed;border:1px solid #ffedd5;padding:10px;border-radius:4px;font-size:12px;color:#9a3412;display:flex;align-items:center;gap:8px">
        <i class="fa fa-info-circle"></i> Esta ação liberará espaço no servidor removendo as mídias permanentemente.
      </div>
    </div>
    <div class="mf" style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:15px 20px">
      <button class="btn btn-secondary" onclick="fecharModal('confirm-delete-cancelados')" style="flex:1">Manter Arquivos</button>
      <button class="btn btn-danger" id="btn-delete-cancelados" onclick="apagarComprovantesCancelados()" style="flex:1;justify-content:center">Sim, Apagar Tudo</button>
    </div>
  </div>
</div>

<!-- Modal: Confirmação de Exclusão Individual -->
<div class="mia-overlay hide" id="ov-confirm-delete-single">
  <div class="mia-modal" style="width:400px">
    <div class="mh" style="background:#374151">
      <div class="mh-info">
        <div class="mt"><i class="fa fa-file-image-o"></i> Apagar Comprovante</div>
        <div class="ms" id="delete-single-id">Pedido #0</div>
      </div>
      <button class="mh-close" onclick="fecharModal('confirm-delete-single')"><i class="fa fa-times"></i></button>
    </div>
    <div class="mb" style="flex-direction:column;gap:12px;text-align:center;padding:25px 20px">
      <div style="font-size:15px;font-weight:700;color:#111827">Remover este arquivo de mídia?</div>
      <p style="font-size:13px;color:#6b7280;line-height:1.5">O registro do pedido continuará existindo, mas o arquivo do comprovante será deletado do servidor.</p>
    </div>
    <div class="mf">
      <button class="btn btn-secondary" onclick="fecharModal('confirm-delete-single')">Cancelar</button>
      <button class="btn btn-danger" id="btn-delete-single-confirm" onclick="confirmarDeleteSingle()">Confirmar Exclusão</button>
    </div>
  </div>
</div>

<!-- Modal: Validar Comprovante -->
<div class="mia-overlay hide" id="ov-validar">
  <div class="mia-modal">
    <div class="mh">
      <div class="mh-info">
        <div class="mt"><i class="fa fa-qrcode"></i> Validação de Comprovante — Pedido #1042</div>
        <div class="ms">Recebido hoje às 15h42 · Maria Souza via WhatsApp</div>
      </div>
      <button class="mh-close" onclick="fecharModal('validar')"><i class="fa fa-times"></i></button>
    </div>
    <div class="mb">
      <div class="proof-col">
        <div class="proof-preview">
          <i class="fa fa-file-image-o proof-icon"></i>
          <div class="proof-label">Comprovante Pix</div>
          <div class="proof-sub">Enviado pelo cliente via WhatsApp</div>
          <button class="btn btn-secondary btn-sm" style="margin-top:8px"><i class="fa fa-search-plus"></i> Ampliar</button>
        </div>
        <div class="proof-meta">
          <div class="pm-row"><span class="pm-lbl">Tipo</span><span class="pm-val">Pix Instantâneo</span></div>
          <div class="pm-row"><span class="pm-lbl">Banco</span><span class="pm-val">Nubank</span></div>
          <div class="pm-row"><span class="pm-lbl">Hora Trans.</span><span class="pm-val">15h41</span></div>
          <div class="pm-row"><span class="pm-lbl">E2E ID</span><span class="pm-val" style="font-size:10px;color:#9ca3af">E336534...8921</span></div>
        </div>
      </div>
      <div class="order-col">
        <div class="sec-box">
          <div class="sec-title"><i class="fa fa-user"></i> Cliente</div>
          <div class="sec-row"><span class="sec-lbl">Nome</span><span class="sec-val">Maria Souza</span></div>
          <div class="sec-row"><span class="sec-lbl">WhatsApp</span><span class="sec-val">11 98765-4321</span></div>
          <div class="sec-row"><span class="sec-lbl">Histórico</span><span class="sec-val">7 compras · sem ocorrências</span></div>
        </div>
        <div class="sec-box">
          <div class="sec-title"><i class="fa fa-shopping-cart"></i> Itens do Pedido</div>
          <div class="item-row">
            <div class="item-thumb"><i class="fa fa-camera"></i></div>
            <div>
              <div class="item-name">Vestido Midi Floral</div>
              <div class="item-var">Rosa · Tamanho M · Qtd: 1</div>
              <div class="item-price">R$ 89,90</div>
            </div>
          </div>
          <div class="total-row">
            <span class="total-lbl">Total do Pedido</span>
            <span class="total-val">R$ 89,90</span>
          </div>
        </div>
        <div class="validation-note">
          <i class="fa fa-exclamation-triangle"></i>
          <span>Valor do comprovante (R$ 89,90) corresponde ao total do pedido. Nenhuma divergência detectada pela IA.</span>
        </div>
      </div>
    </div>
    <div class="mf">
      <button class="btn btn-secondary" onclick="fecharModal('validar')"><i class="fa fa-times"></i> Fechar</button>
      <button class="btn btn-danger" onclick="rejeitar(null);fecharModal('validar')"><i class="fa fa-times-circle"></i> Rejeitar</button>
      <button class="btn btn-success" onclick="aprovar(null);fecharModal('validar')"><i class="fa fa-check-circle"></i> Aprovar Pagamento</button>
    </div>
  </div>
</div>

  </section>
</div><!-- /content-wrapper -->

<div class="mia-toast" id="toast"><i class="fa fa-magic"></i> <span id="toast-msg">Operação concluída</span></div>

<script>
function abrirModal(id){document.getElementById('ov-'+id).classList.remove('hide')}
function fecharModal(id){document.getElementById('ov-'+id).classList.add('hide')}
document.querySelectorAll('.mia-overlay').forEach(o=>o.addEventListener('click',function(e){if(e.target===this)this.classList.add('hide')}))
let _tt;
function showToast(msg){const t=document.getElementById('toast');document.getElementById('toast-msg').textContent=msg;t.classList.add('show');clearTimeout(_tt);_tt=setTimeout(()=>t.classList.remove('show'),2800)}
let _pixFilter='pendentes';
let _pixTimer=null;
function filtrar(el,filter){
  _pixFilter=filter||'pendentes';
  document.querySelectorAll('.fb-chip').forEach(c=>c.classList.remove('on'));
  el.classList.add('on');
  carregarPix();
}
function debouncePix(){clearTimeout(_pixTimer);_pixTimer=setTimeout(carregarPix,200);}
function carregarPix(){
  const q=(document.getElementById('pix-search')||{}).value||'';
  fetch('../_inc/ai_pix_actions.php?action=list&filter='+encodeURIComponent(_pixFilter)+'&search='+encodeURIComponent(q))
    .then(r=>r.json()).then(d=>{
      if(d.error){showToast('Erro: '+(d.message||''));return;}
      document.getElementById('pix-body').innerHTML=d.rows_html||'';
      document.getElementById('pix-total-count').textContent=d.count||0;
      const st=d.stats||{};
      document.getElementById('pix-pend-count').textContent=st.pendentes||0;
      document.getElementById('pix-pend-badge').textContent=(st.pendentes||0)+' pend.';
      document.getElementById('pix-ok-count').textContent=st.confirmados||0;
      document.getElementById('pix-cancel-count').textContent=st.cancelados||0;
      document.getElementById('pix-pend-valor').textContent=money(st.pendentes_valor||0);
      document.getElementById('pix-total-pend').textContent=(st.pendentes||0)+' pendentes';
      document.getElementById('pix-footer').textContent='Exibindo '+(d.count||0)+' registro(s)';
      
      // Sincronizar tooltips ou eventos se necessário
      rebindPixEvents();
    }).catch(()=>showToast('Erro ao carregar Pix.'));
}
function rebindPixEvents(){
  // Aqui podemos adicionar eventos específicos aos novos elementos se necessário
}
let _orderToDelete = 0;
function deleteSingleProof(orderId){
  _orderToDelete = orderId;
  document.getElementById('delete-single-id').textContent = 'Pedido #' + orderId;
  abrirModal('confirm-delete-single');
}
function confirmarDeleteSingle(){
  if(!_orderToDelete) return;
  const btn = document.getElementById('btn-delete-single-confirm');
  const oldText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Excluindo...';
  
  const fd = new FormData();
  fd.append('action', 'delete_single_proof');
  fd.append('order_id', _orderToDelete);
  
  fetch('../_inc/ai_pix_actions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      fecharModal('confirm-delete-single');
      if (d.error) showToast('Erro: ' + (d.message || 'Erro ao deletar.'));
      else {
        showToast('✓ Comprovante removido.');
        carregarPix();
      }
    })
    .catch(() => showToast('Erro de conexão.'))
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = oldText;
      _orderToDelete = 0;
    });
}
function confirmarPix(orderId){
  const fd=new FormData();fd.append('action','confirm');fd.append('order_id',orderId);
  fetch('../_inc/ai_pix_actions.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      if(d.error){showToast('Erro: '+(d.message||''));return;}
      showToast('✓ Pagamento confirmado!');
      carregarPix();
    }).catch(()=>showToast('Erro ao confirmar.'));
}
function cancelarPix(orderId){
  if(!confirm('Cancelar este pagamento/pedido?')) return;
  const fd=new FormData();fd.append('action','cancel');fd.append('order_id',orderId);
  fetch('../_inc/ai_pix_actions.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      if(d.error){showToast('Erro: '+(d.message||''));return;}
      showToast('Cancelado.');
      carregarPix();
    }).catch(()=>showToast('Erro ao cancelar.'));
}
function abrirPedido(orderId){
  window.location.href='concierge_pedidos.php';
}
function apagarComprovantesCancelados(){
  const btn = document.getElementById('btn-delete-cancelados');
  const oldText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Apagando...';
  
  const fd = new FormData();
  fd.append('action', 'delete_cancelled_proofs');
  
  fetch('../_inc/ai_pix_actions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      fecharModal('confirm-delete-cancelados');
      if (d.error) {
        showToast('Erro: ' + (d.message || 'Erro desconhecido.'));
      } else {
        showToast('✓ ' + (d.message || 'Comprovantes deletados com sucesso!'));
        carregarPix();
      }
    })
    .catch(() => {
      fecharModal('confirm-delete-cancelados');
      showToast('Erro de conexão ao deletar.');
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = oldText;
    });
}
function aprovar(){showToast('Use o botão Confirmar na lista.');}
function rejeitar(){showToast('Use o botão Cancelar na lista.');}
function money(v){v=parseFloat(v||0);return 'R$ '+v.toFixed(2).replace('.',',');}
document.addEventListener('DOMContentLoaded',carregarPix);
</script>

<?php include ("footer.php"); ?>
