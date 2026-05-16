<?php
ob_start();
session_start();
include realpath(__DIR__.'/../').'/_init.php';

if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

$document->setTitle('Pedidos WhatsApp · Moda IA');
$document->setBodyClass('concierge_pedidos');

include ("header.php");
include ("left_sidebar.php");
?>
<div class="content-wrapper" style="min-height:1px">
  <section class="content-header">
    <h1><i class="fa fa-trello" style="color:#6d28d9;margin-right:8px"></i>Pedidos WhatsApp</h1>
    <ol class="breadcrumb">
      <li><a href="dashboard.php"><i class="fa fa-home"></i> Home</a></li>
      <li>Moda IA</li>
      <li class="active">Pedidos</li>
    </ol>
  </section>
  <section class="content">
<style>
@keyframes blink{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.45;transform:scale(.7)}}
@keyframes ia-glow{0%,100%{box-shadow:0 0 8px rgba(109,40,217,.35)}50%{box-shadow:0 0 16px rgba(109,40,217,.6)}}
/* Actions */
.mia-actions{display:flex;justify-content:flex-end;gap:8px;margin-bottom:14px}
#mia-root .btn,.mia-drawer .btn{display:inline-flex!important;align-items:center;gap:6px;padding:8px 14px;border-radius:2px!important;font-size:13px;font-weight:600;transition:all .18s;cursor:pointer}
#mia-root .btn-primary,.mia-drawer .btn-primary{background:linear-gradient(135deg,#6d28d9,#7c3aed)!important;color:#fff!important;border:none!important}
#mia-root .btn-primary:hover,.mia-drawer .btn-primary:hover{transform:translateY(-1px)!important}
#mia-root .btn-secondary,.mia-drawer .btn-secondary{background:#fff!important;border:1px solid #d1d5db!important;color:#374151!important}
#mia-root .btn-secondary:hover,.mia-drawer .btn-secondary:hover{border-color:#a78bfa!important;color:#6d28d9!important}
#mia-root .btn-sm,.mia-drawer .btn-sm{padding:5px 10px!important;font-size:12px!important}
/* Status bar */
.status-bar{display:flex;gap:10px;margin-bottom:14px}
.mia-sb-item{background:#fff;border:1px solid #e5e7eb;border-radius:2px;padding:10px 16px;display:flex;align-items:center;gap:10px;flex:1;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.mia-sb-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.mia-sb-dot.pix-pend{background:#d97706}
.mia-sb-dot.sep{background:#2563eb}
.mia-sb-dot.rota{background:#7c3aed}
.mia-sb-dot.done{background:#059669}
.mia-sb-label{font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.3px}
.mia-sb-count{font-size:24px;font-weight:900;color:#111827}
.mia-sb-sub{font-size:11px;color:#9ca3af;margin-top:1px}
/* Kanban */
.kanban{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;align-items:start}
.kan-col{display:flex;flex-direction:column;border:1px solid #e5e7eb;border-radius:2px;overflow:hidden}
.kan-col.pedido{background:#fef9e7}
.kan-col.separacao{background:#eff6ff}
.kan-col.rota{background:#f5f3ff}
.kan-col.entregue{background:#f0fdf4}
.col-header{padding:10px 12px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;border-bottom:3px solid transparent}
.col-header.pedido{border-color:#d97706;background:linear-gradient(135deg,#fffbeb,#fef3c7)}
.col-header.separacao{border-color:#2563eb;background:linear-gradient(135deg,#eff6ff,#dbeafe)}
.col-header.rota{border-color:#7c3aed;background:linear-gradient(135deg,#f5f3ff,#ede9fe)}
.col-header.entregue{border-color:#059669;background:linear-gradient(135deg,#f0fdf4,#dcfce7)}
.col-title{font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.4px;display:flex;align-items:center;gap:7px}
.col-badge{font-size:10px;font-weight:900;padding:2px 8px;border-radius:20px}
.col-badge.pedido{background:#fef3c7;color:#92400e}
.col-badge.separacao{background:#dbeafe;color:#1e40af}
.col-badge.rota{background:#ede9fe;color:#4c1d95}
.col-badge.entregue{background:#d1fae5;color:#065f46}
.col-cards{padding:8px;display:flex;flex-direction:column;gap:8px}
/* Cards */
.card{display:block!important;background:#fff!important;border:1px solid #e5e7eb!important;border-radius:2px!important;padding:12px!important;cursor:pointer;transition:all .15s;box-shadow:0 1px 4px rgba(0,0,0,.06)!important;height:auto!important;min-height:0!important;flex:none!important}
.card:hover{border-color:#a78bfa;box-shadow:0 4px 12px rgba(109,40,217,.12);transform:translateY(-1px)}
.card-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.card-id{font-size:11px;font-weight:700;color:#6b7280}
.card-time{font-size:10px;color:#9ca3af;display:flex;align-items:center;gap:3px}
.card-client{font-weight:700;color:#111827;font-size:13px;margin-bottom:2px}
.card-phone{font-size:11px;color:#9ca3af;margin-bottom:8px;display:flex;align-items:center;gap:4px;transition:all .3s cubic-bezier(.34,1.56,.64,1)}
.card-phone:hover{color:#25d366;transform:translateX(4px);font-weight:600}
.card-items{border-top:1px solid #f3f4f6;padding-top:8px;margin-top:4px}
.card-item-row{display:flex;align-items:center;gap:8px;margin-bottom:4px}
.card-item-thumb{width:32px;height:32px;border-radius:2px;background:linear-gradient(135deg,#fce7f3,#fdf2f8);display:flex;align-items:center;justify-content:center;font-size:12px;color:#f9a8d4;flex-shrink:0}
.card-item-row>div:not(.card-item-thumb){flex:1;min-width:0}
.card-item-name{font-size:11px;font-weight:700;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.card-item-var{font-size:10px;color:#9ca3af}
.card-total{display:flex;align-items:center;justify-content:space-between;margin-top:8px;padding-top:6px;border-top:1px solid #f3f4f6}
.card-price{font-size:14px;font-weight:900;color:#111827}
.pix-badge{font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;display:flex;align-items:center;gap:3px}
.pix-badge.confirmed{background:#d1fae5;color:#065f46}
.pix-badge.pending{background:#fef3c7;color:#92400e}
.pix-badge.waiting{background:#ede9fe;color:#4c1d95}
.ia-status{font-size:11px;font-weight:700;padding:2px 7px;border-radius:20px;display:inline-flex;align-items:center;gap:3px}
.ia-status.active{background:linear-gradient(135deg,#ddd6fe,#c4b5fd);color:#4c1d95}
.ia-status.human{background:#f3f4f6;color:#6b7280}
/* Drawer */
.drawer-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1050;display:none}
.drawer-overlay.open{display:block}
.mia-drawer{position:fixed;top:0;right:-440px;width:440px;height:100vh;background:#fff;z-index:1055;display:flex;flex-direction:column;transition:right .3s cubic-bezier(.4,0,.2,1);box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.mia-drawer.open{right:0}
.dh{background:linear-gradient(135deg,#4c1d95,#7c3aed);padding:18px 20px;display:flex;align-items:flex-start;justify-content:space-between;flex-shrink:0}
.dh-info .dt{font-size:16px;font-weight:700;color:#fff;display:flex;align-items:center;gap:8px}
.dh-info .ds{font-size:12px;color:#c4b5fd;margin-top:3px}
.dh-close{background:none;border:none;color:#c4b5fd;font-size:18px;cursor:pointer;padding:2px 6px;border-radius:2px;line-height:1}
.dh-close:hover{color:#fff}
.db{flex:1;overflow-y:auto;padding:16px}
.db::-webkit-scrollbar{width:4px}
.db::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:2px}
.df{background:#faf5ff;border-top:1px solid #ede9fe;padding:12px 16px;display:flex;gap:8px;flex-shrink:0}
.d-section{margin-bottom:16px}
.d-section-title{font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px}
.d-client-block{background:#f9fafb;border:1px solid #e5e7eb;border-radius:2px;padding:12px;display:flex;align-items:center;gap:12px}
.d-client-av{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#fce7f3,#f9a8d4);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;color:#db2777;flex-shrink:0}
.d-client-name{font-weight:700;color:#111827;font-size:14px}
.d-client-info{font-size:12px;color:#6b7280;margin-top:2px}
.d-pref-chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
.d-pref{background:#ede9fe;color:#4c1d95;border:1px solid #c4b5fd;border-radius:20px;font-size:11px;font-weight:600;padding:2px 8px}
.d-item{display:flex;gap:10px;padding:10px 0;border-bottom:1px solid #f3f4f6}
.d-item:last-child{border-bottom:none}
.d-item-img{width:50px;height:50px;border-radius:2px;background:linear-gradient(135deg,#fce7f3,#fdf2f8);display:flex;align-items:center;justify-content:center;font-size:20px;color:#f9a8d4;flex-shrink:0}
.d-item-name{font-weight:700;color:#111827;font-size:13px}
.d-item-var{font-size:12px;color:#9ca3af;margin-top:2px}
.d-item-price{font-weight:700;color:#6d28d9;font-size:13px;margin-top:4px}
.d-total{background:#f9fafb;border:1px solid #e5e7eb;border-radius:2px;padding:12px;display:flex;justify-content:space-between;align-items:center}
.d-total-label{font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase}
.d-total-val{font-size:22px;font-weight:900;color:#111827}
/* IA Control */
.ia-ctrl{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border-radius:2px;margin-bottom:12px}
.ia-ctrl.on{background:linear-gradient(135deg,#f5f3ff,#ede9fe);border:1px solid #c4b5fd}
.ia-ctrl.off{background:#fff8f0;border:1px solid #fed7aa}
.ia-ctrl-left{display:flex;align-items:center;gap:8px}
.ia-robot{width:34px;height:34px;border-radius:2px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;transition:all .3s}
.ia-robot.on{background:linear-gradient(135deg,#6d28d9,#7c3aed);color:#fff;animation:ia-glow 2s infinite}
.ia-robot.off{background:#f3f4f6;color:#9ca3af}
.ia-ctrl-title{font-size:12px;font-weight:700}
.ia-ctrl.on .ia-ctrl-title{color:#4c1d95}
.ia-ctrl.off .ia-ctrl-title{color:#d97706}
.ia-ctrl-sub{font-size:10px;color:#9ca3af;margin-top:1px}
.btn-pause{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:2px;font-size:11px;font-weight:700;background:#fff;border:1px solid #fca5a5;color:#dc2626;cursor:pointer;flex-shrink:0;transition:background .15s}
.btn-pause:hover{background:#fee2e2}
.btn-resume{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:2px;font-size:11px;font-weight:700;background:linear-gradient(135deg,#6d28d9,#7c3aed);border:none;color:#fff;cursor:pointer;flex-shrink:0}
/* PIX premium */
.pix-proof-container{background:linear-gradient(135deg,#f5f3ff,#ede9fe);border:2px solid #ddd6fe;border-radius:4px;padding:12px;text-align:center;position:relative;overflow:hidden}
.proof-glow{position:absolute;top:-40%;right:-40%;width:120px;height:120px;background:radial-gradient(circle,rgba(109,40,217,.1),transparent);border-radius:50%;animation:float 6s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.proof-preview-premium{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100px;padding:8px 0}
.proof-text-main{font-size:12px;font-weight:700;color:#4c1d95;margin-bottom:2px}
.proof-text-sub{font-size:10px;color:#7c3aed;font-weight:600;letter-spacing:.5px;margin-bottom:6px}
.proof-status{font-size:11px;color:#059669;font-weight:700;background:#d1fae5;padding:4px 10px;border-radius:20px;display:inline-flex;align-items:center;gap:4px}
.btn-view-proof{display:flex;align-items:center;gap:6px;width:100%;padding:7px 10px;margin-top:8px;background:#fff;border:1.5px solid #c4b5fd;border-radius:2px;color:#6d28d9;font-weight:700;font-size:11px;cursor:pointer;transition:all .2s;justify-content:center}
.btn-view-proof:hover{background:#ede9fe;border-color:#a78bfa;transform:translateY(-1px)}
.pix-action-buttons{display:flex;gap:8px;margin-top:10px}
.btn-action{display:flex;align-items:center;justify-content:center;gap:4px;flex:1;padding:10px;border-radius:2px;font-weight:700;font-size:11px;border:none;cursor:pointer;transition:all .2s;text-transform:uppercase;letter-spacing:.2px}
.btn-action.reject{background:#fff;border:2px solid #fca5a5;color:#dc2626}
.btn-action.reject:hover{background:#fee2e2;border-color:#dc2626}
.btn-action.approve{background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;box-shadow:0 2px 8px rgba(5,150,105,.25)}
.btn-action.approve:hover{transform:translateY(-2px);box-shadow:0 4px 14px rgba(5,150,105,.4)}
.pix-confirm-compact{display:flex;flex-direction:column;gap:12px}
/* Conversation summary */
.conversation-summary{background:#f9fafb;border:1px solid #e5e7eb;border-radius:2px;padding:12px;font-size:12px;line-height:1.6;color:#374151}
/* Toast */
.mia-toast{position:fixed;bottom:20px;right:20px;background:#222d32;color:#fff;border-left:3px solid #6d28d9;padding:10px 16px;border-radius:2px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;z-index:9999;transform:translateX(120%);transition:transform .3s cubic-bezier(.34,1.56,.64,1);min-width:260px;box-shadow:0 4px 20px rgba(0,0,0,.3)}
.mia-toast.show{transform:translateX(0)}
.mia-toast i{color:#a78bfa}
/* ── Animations ── */
@keyframes mia-fadeIn{from{opacity:0}to{opacity:1}}
@keyframes mia-slideUp{from{transform:translateY(12px);opacity:0}to{transform:translateY(0);opacity:1}}
/* ── Date Dropdown ── */
.dd-wrap{position:relative;display:inline-flex}
.dd-panel{position:absolute;top:calc(100% + 6px);right:0;width:272px;background:#fff;border:1px solid #e5e7eb;border-radius:6px;box-shadow:0 10px 32px rgba(0,0,0,.15);z-index:1040;animation:mia-slideUp .15s;overflow:hidden}
.dd-panel.hide{display:none}
.dd-nav{display:flex;align-items:center;justify-content:space-between;padding:12px 14px 10px;background:linear-gradient(135deg,#f5f3ff,#ede9fe);border-bottom:1px solid #ddd6fe}
.dd-arr{background:#fff;border:1px solid #d1d5db;border-radius:4px;width:26px;height:26px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#6b7280;font-size:11px;transition:all .15s;line-height:1}
.dd-arr:hover{border-color:#a78bfa;color:#6d28d9}
.dd-center{text-align:center}
.dd-label{font-size:10px;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.4px}
.dd-val{font-size:12px;font-weight:700;color:#111827;margin-top:2px}
.dd-quick-row{padding:10px 12px;display:flex;gap:5px;flex-wrap:wrap;border-bottom:1px solid #f3f4f6;background:#fafafa}
.dd-qb{padding:3px 9px;border-radius:20px;border:1px solid #d1d5db;font-size:11px;font-weight:600;color:#6b7280;cursor:pointer;background:#fff;transition:all .15s}
.dd-qb:hover{border-color:#a78bfa;color:#6d28d9}
.dd-qb.on{background:#ede9fe;border-color:#c4b5fd;color:#4c1d95}
.dd-input-row{padding:10px 12px;border-bottom:1px solid #f3f4f6}
.dd-input-row label{font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:5px}
.dd-input{width:100%;border:1px solid #e5e7eb;border-radius:4px;padding:6px 10px;font-size:12px;color:#374151;outline:none;transition:border-color .15s}
.dd-input:focus{border-color:#7c3aed;box-shadow:0 0 0 2px rgba(124,58,237,.1)}
.dd-foot{padding:8px 12px;display:flex;justify-content:flex-end;gap:6px;background:#f9fafb}
.mia-filter-badge{display:inline-flex;align-items:center;gap:5px;background:#ede9fe;border:1px solid #c4b5fd;color:#4c1d95;font-size:11px;font-weight:700;padding:4px 9px;border-radius:20px;animation:mia-fadeIn .2s}
.mia-filter-badge button{background:none;border:none;color:#a78bfa;cursor:pointer;padding:0;font-size:14px;line-height:1;margin-left:1px}
.mia-filter-badge button:hover{color:#dc2626}
/* ── Search Modal ── */
.srch-ov{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1060;display:flex;align-items:flex-start;justify-content:center;padding-top:80px;animation:mia-fadeIn .15s}
.srch-ov.hide{display:none}
.srch-box{background:#fff;border-radius:6px;box-shadow:0 20px 60px rgba(0,0,0,.25);width:580px;max-width:calc(100vw - 32px);max-height:calc(100vh - 140px);display:flex;flex-direction:column;animation:mia-slideUp .2s;overflow:hidden}
.srch-hd{padding:14px 16px;display:flex;align-items:center;gap:10px;border-bottom:1px solid #f3f4f6}
.srch-iw{flex:1;display:flex;align-items:center;gap:8px;border:1.5px solid #e5e7eb;border-radius:4px;padding:8px 12px;transition:border-color .15s;background:#f9fafb}
.srch-iw:focus-within{border-color:#7c3aed;box-shadow:0 0 0 2px rgba(124,58,237,.1);background:#fff}
.srch-iw i{color:#9ca3af;font-size:14px}
.srch-iw input{border:none;outline:none;background:transparent;font-size:14px;color:#374151;flex:1;font-family:inherit}
.srch-iw input::placeholder{color:#b0b7c3}
.srch-x{width:32px;height:32px;border:none;background:#f3f4f6;border-radius:4px;color:#9ca3af;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;transition:all .15s;flex-shrink:0}
.srch-x:hover{background:#fee2e2;color:#dc2626}
.srch-chips{padding:10px 16px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:6px;flex-wrap:wrap;background:#fafafa}
.srch-chip-label{font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.3px;margin-right:2px}
.srch-chip{padding:3px 10px;border-radius:20px;border:1px solid #d1d5db;font-size:11px;font-weight:600;color:#6b7280;cursor:pointer;background:#fff;transition:all .15s;display:inline-flex;align-items:center;gap:4px}
.srch-chip:hover{border-color:#a78bfa;color:#6d28d9}
.srch-chip.on{background:#ede9fe;border-color:#c4b5fd;color:#4c1d95}
.srch-body{flex:1;overflow-y:auto;padding:12px 16px;min-height:180px}
.srch-body::-webkit-scrollbar{width:4px}
.srch-body::-webkit-scrollbar-thumb{background:#e5e7eb;border-radius:2px}
.srch-empty{text-align:center;padding:36px 0;color:#9ca3af}
.srch-empty i{font-size:38px;margin-bottom:10px;display:block;color:#d1d5db}
.srch-empty p{font-size:13px;font-weight:600;margin-bottom:4px}
.srch-empty small{font-size:11px;color:#c0c7d0}
.srch-rc{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:4px;cursor:pointer;transition:all .15s;border:1px solid transparent;margin-bottom:5px}
.srch-rc:last-child{margin-bottom:0}
.srch-rc:hover{background:#faf5ff;border-color:#e5e7eb;transform:translateX(2px)}
.srch-rc-av{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;color:#fff;flex-shrink:0}
.srch-rc-info{flex:1;min-width:0}
.srch-rc-name{font-weight:700;color:#111827;font-size:13px}
.srch-rc-det{font-size:11px;color:#6b7280;margin-top:2px;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.srch-rc-price{font-size:13px;font-weight:900;color:#111827;white-space:nowrap;text-align:right}
.srch-ft{padding:10px 16px;border-top:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;background:#fafafa}
.srch-count{font-size:12px;color:#9ca3af;font-weight:600}
/* ── Btn variants ── */
#mia-root .btn-danger,.mia-drawer .btn-danger{background:#dc2626!important;color:#fff!important;border:none!important}
#mia-root .btn-danger:hover,.mia-drawer .btn-danger:hover{background:#b91c1c!important;transform:translateY(-1px)!important}
#mia-root .btn-warning,.mia-drawer .btn-warning{background:#d97706!important;color:#fff!important;border:none!important}
#mia-root .btn-warning:hover,.mia-drawer .btn-warning:hover{background:#b45309!important;transform:translateY(-1px)!important}
/* ── Confirm modal ── */
.mia-confirm-ov{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1070;display:flex;align-items:center;justify-content:center}
.mia-confirm-ov.hide{display:none}
.mia-confirm-box{background:#fff;border-radius:8px;padding:28px 24px;width:380px;max-width:calc(100vw - 32px);box-shadow:0 20px 60px rgba(0,0,0,.3);animation:mia-slideUp .2s}
.mia-confirm-icon{font-size:40px;text-align:center;margin-bottom:10px}
.mia-confirm-title{font-size:16px;font-weight:700;color:#111827;text-align:center;margin-bottom:8px}
.mia-confirm-msg{font-size:13px;color:#6b7280;text-align:center;margin-bottom:22px;line-height:1.6}
.mia-confirm-btns{display:flex;gap:8px;justify-content:flex-end}
.mia-confirm-btns .btn-danger{background:#dc2626;color:#fff;border:none;display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:2px;font-size:13px;font-weight:600;cursor:pointer;transition:all .18s}
.mia-confirm-btns .btn-danger:hover{background:#b91c1c;transform:translateY(-1px)}
.mia-confirm-btns .btn-warning{background:#d97706;color:#fff;border:none;display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:2px;font-size:13px;font-weight:600;cursor:pointer;transition:all .18s}
.mia-confirm-btns .btn-warning:hover{background:#b45309;transform:translateY(-1px)}
.btn-view-profile{background:#f3f4f6;border:none;color:#6b7280;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:12px}
.btn-view-profile:hover{background:#e5e7eb;color:#111827;transform:scale(1.1)}
.profile-row{display:flex;flex-direction:column;gap:4px;padding:8px;background:#f9fafb;border-radius:6px;border:1px solid #f3f4f6}
.profile-label{font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.5px}
.profile-value{font-size:13px;color:#111827;line-height:1.4}
</style>

<div id="mia-root">

  <!-- Ações -->
  <div class="mia-actions" id="mia-actions-bar">
    <div class="dd-wrap" id="dd-wrap">
      <button class="btn btn-secondary btn-sm" onclick="abrirDateDropdown()"><i class="fa fa-calendar"></i> Hoje</button>
      <div class="dd-panel hide" id="dd-panel">
        <div class="dd-nav">
          <button class="dd-arr" onclick="navegarData(-1)"><i class="fa fa-chevron-left"></i></button>
          <div class="dd-center">
            <div class="dd-label" id="dd-date-label">Hoje</div>
            <div class="dd-val" id="dd-date-val"><?= date('d') ?> de <?= ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'][(int)date('n')-1] ?> de <?= date('Y') ?></div>
          </div>
          <button class="dd-arr" onclick="navegarData(1)"><i class="fa fa-chevron-right"></i></button>
        </div>
        <div class="dd-quick-row">
          <button class="dd-qb on" data-period="hoje" onclick="setPeriodo('hoje',this)">Hoje</button>
          <button class="dd-qb" data-period="ontem" onclick="setPeriodo('ontem',this)">Ontem</button>
          <button class="dd-qb" data-period="semana" onclick="setPeriodo('semana',this)">Esta semana</button>
          <button class="dd-qb" data-period="mes" onclick="setPeriodo('mes',this)">Este mês</button>
        </div>
        <div class="dd-input-row">
          <label>Data específica</label>
          <input type="date" class="dd-input" id="dd-custom" value="<?= date('Y-m-d') ?>" onchange="aplicarDataCustom(this.value)">
        </div>
        <div class="dd-foot">
          <button class="btn btn-secondary btn-sm" onclick="fecharDateDropdown()">Cancelar</button>
          <button class="btn btn-primary btn-sm" onclick="aplicarFiltroData()"><i class="fa fa-check"></i> Aplicar</button>
        </div>
      </div>
    </div>
    <button class="btn btn-secondary btn-sm" onclick="abrirBusca()"><i class="fa fa-search"></i> Buscar pedido</button>
    <button class="btn btn-primary btn-sm" onclick="showToast('Atualizando pedidos...')"><i class="fa fa-refresh"></i> Atualizar</button>
  </div>

  <!-- Status Bar -->
  <div class="status-bar">
    <div class="mia-sb-item"><span class="mia-sb-dot pix-pend"></span><div><div class="mia-sb-count" id="sb-pix-pend">0</div><div class="mia-sb-label">Aguardando Pix</div></div></div>
    <div class="mia-sb-item"><span class="mia-sb-dot sep"></span><div><div class="mia-sb-count" id="sb-sep">0</div><div class="mia-sb-label">Em Separação</div></div></div>
    <div class="mia-sb-item"><span class="mia-sb-dot rota"></span><div><div class="mia-sb-count" id="sb-rota">0</div><div class="mia-sb-label">Em Rota</div></div></div>
    <div class="mia-sb-item"><span class="mia-sb-dot done"></span><div><div class="mia-sb-count" id="sb-ent-hoje">0</div><div class="mia-sb-label">Entregues Hoje</div></div></div>
  </div>

  <!-- KANBAN -->
  <div class="kanban">

    <!-- COLUNA: PEDIDO -->
    <div class="kan-col pedido">
      <div class="col-header pedido">
        <span class="col-title"><i class="fa fa-clock-o" style="color:#d97706"></i> Pedido</span>
        <span class="col-badge pedido" id="badge-pedido">0</span>
      </div>
      <div class="col-cards" id="col-pedido"></div>
    </div>

    <!-- COLUNA: SEPARAÇÃO -->
    <div class="kan-col separacao">
      <div class="col-header separacao">
        <span class="col-title"><i class="fa fa-check-circle" style="color:#2563eb"></i> Separação</span>
        <span class="col-badge separacao" id="badge-separacao">0</span>
      </div>
      <div class="col-cards" id="col-separacao"></div>
    </div>

    <!-- COLUNA: ROTA -->
    <div class="kan-col rota">
      <div class="col-header rota">
        <span class="col-title"><i class="fa fa-motorcycle" style="color:#7c3aed"></i> Rota</span>
        <span class="col-badge rota" id="badge-rota">0</span>
      </div>
      <div class="col-cards" id="col-rota"></div>
    </div>

    <!-- COLUNA: ENTREGUE -->
    <div class="kan-col entregue">
      <div class="col-header entregue">
        <span class="col-title"><i class="fa fa-check-circle" style="color:#059669"></i> Entregue</span>
        <span class="col-badge entregue" id="badge-entregue">0</span>
      </div>
      <div class="col-cards" id="col-entregue"></div>
    </div>

  </div><!-- /kanban -->
</div><!-- /#mia-root -->

  </section>
</div><!-- /content-wrapper -->

<!-- SEARCH MODAL -->
<div class="srch-ov hide" id="srch-ov">
  <div class="srch-box">
    <div class="srch-hd">
      <div class="srch-iw">
        <i class="fa fa-search"></i>
        <input type="text" id="srch-input" placeholder="Buscar por nome, telefone ou nº do pedido..." oninput="renderSrch(this.value)">
      </div>
      <button class="srch-x" onclick="fecharBusca()"><i class="fa fa-times"></i></button>
    </div>
    <div class="srch-chips">
      <span class="srch-chip-label">Status:</span>
      <button class="srch-chip on" onclick="srchFiltrar(this,'todos')">Todos</button>
      <button class="srch-chip" onclick="srchFiltrar(this,'pedido')"><i class="fa fa-clock-o"></i> Pedido</button>
      <button class="srch-chip" onclick="srchFiltrar(this,'separacao')"><i class="fa fa-check-circle" style="color:#2563eb"></i> Separação</button>
      <button class="srch-chip" onclick="srchFiltrar(this,'rota')"><i class="fa fa-motorcycle" style="color:#7c3aed"></i> Rota</button>
      <button class="srch-chip" onclick="srchFiltrar(this,'entregue')"><i class="fa fa-check-circle" style="color:#059669"></i> Entregue</button>
    </div>
    <div class="srch-body" id="srch-body">
      <div class="srch-empty"><i class="fa fa-search"></i><p>Digite para buscar pedidos</p><small>Por nome, telefone, nº do pedido ou produto</small></div>
    </div>
    <div class="srch-ft">
      <span class="srch-count" id="srch-count"></span>
      <button class="btn btn-secondary btn-sm" onclick="fecharBusca()"><i class="fa fa-times"></i> Fechar</button>
    </div>
  </div>
</div>

<!-- DRAWER OVERLAY -->
<div class="drawer-overlay" id="drawer-ov" onclick="fecharDrawer()"></div>

<!-- DRAWER -->
<div class="mia-drawer" id="drawer">
  <div class="dh">
    <div class="dh-info">
      <div class="dt" id="drawer-title"><i class="fa fa-shopping-cart"></i> Pedido</div>
      <div class="ds" id="drawer-sub">—</div>
    </div>
    <button class="dh-close" onclick="fecharDrawer()"><i class="fa fa-times"></i></button>
  </div>
  <div class="db">
    <div class="ia-ctrl on" style="margin-bottom:16px">
      <div class="ia-ctrl-left">
        <div class="ia-robot on" id="drawer-ia-robot"><i class="fa fa-magic"></i></div>
        <div>
          <div class="ia-ctrl-title" id="drawer-ia-title">IA Ativa</div>
          <div class="ia-ctrl-sub" id="drawer-ia-sub">Conversando com cliente</div>
        </div>
      </div>
      <button class="btn-pause" id="drawer-ia-btn" onclick="pausarIA()"><i class="fa fa-pause"></i> Pausar</button>
    </div>
    <div class="d-section">
      <div class="d-section-title">Perfil do Cliente</div>
      <div class="d-client-block">
        <div class="d-client-av" id="drawer-client-av">—</div>
        <div>
          <div class="d-client-name" id="drawer-client-name">—</div>
          <div class="d-client-info" id="drawer-client-info"></div>
          <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
            <div class="d-pref-chips" id="drawer-client-prefs" style="margin-top:0"></div>
            <button class="btn-view-profile" onclick="abrirModalPerfil()" title="Ver detalhes do perfil">
              <i class="fa fa-eye"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
    <div class="d-section">
      <div class="d-section-title">Itens do Pedido</div>
      <div id="drawer-items"></div>
    </div>
    <div class="d-total" style="margin-bottom:16px">
      <span class="d-total-label">Total do Pedido</span>
      <span class="d-total-val" id="drawer-total">—</span>
    </div>
    <div class="d-section" id="drawer-pix-section">
      <div class="d-section-title"><i class="fa fa-qrcode" style="margin-right:5px;color:#6d28d9"></i>Confirmação de Pix</div>
      <div class="pix-confirm-compact">
        <div class="pix-proof-container">
          <div class="proof-glow"></div>
          <div class="proof-preview-premium">
            <i class="fa fa-qrcode" style="font-size:44px;color:#6d28d9;margin-bottom:10px;display:block"></i>
            <div class="proof-text-main">Comprovante PIX</div>
            <div class="proof-text-sub" id="drawer-pix-phone">—</div>
            <div class="proof-status" id="drawer-pix-status"><i class="fa fa-clock-o" style="color:#d97706"></i> Pendente</div>
          </div>
          <button class="btn-view-proof" onclick="showToast('Ampliando comprovante...')"><i class="fa fa-expand"></i> Ampliar Imagem</button>
        </div>
        <div class="pix-action-buttons">
          <button class="btn-action reject" onclick="recusarPIX()"><i class="fa fa-times-circle"></i><span>Recusar</span></button>
          <button class="btn-action approve" onclick="confirmarPIX()"><i class="fa fa-check-circle"></i><span>Confirmar Pagamento</span></button>
        </div>
      </div>
    </div>
    <div class="d-section">
      <div class="d-section-title">Resumo da Conversa</div>
      <div class="conversation-summary" id="drawer-summary"></div>
    </div>
  </div>
  <div class="df" style="gap:6px">
    <button class="btn btn-danger btn-sm" id="drawer-delete-btn" onclick="confirmarExcluir()" title="Excluir pedido" style="padding:8px 11px"><i class="fa fa-trash"></i></button>
    <button class="btn btn-warning btn-sm" id="drawer-back-btn" style="display:none;padding:8px 11px" onclick="confirmarVoltarStatus()" title="Voltar etapa"><i class="fa fa-arrow-left"></i></button>
    <button class="btn btn-secondary btn-sm" onclick="fecharDrawer()" style="padding:8px 12px"><i class="fa fa-times"></i></button>
    <button class="btn btn-primary" id="drawer-next-btn" style="flex:1" onclick="avancarStatus()"><i class="fa fa-check"></i> Atualizar Status</button>
  </div>
</div>

<!-- CONFIRM MODAL -->
<div class="mia-confirm-ov hide" id="confirm-ov">
  <div class="mia-confirm-box">
    <div class="mia-confirm-icon" id="confirm-icon"><i class="fa fa-exclamation-triangle" style="color:#d97706"></i></div>
    <div class="mia-confirm-title" id="confirm-title">Confirmar</div>
    <div class="mia-confirm-msg" id="confirm-msg">Tem certeza?</div>
    <div class="mia-confirm-btns">
      <button class="btn btn-secondary btn-sm" onclick="fecharConfirm()"><i class="fa fa-times"></i> Cancelar</button>
      <button id="confirm-ok-btn"><i class="fa fa-check"></i> Confirmar</button>
    </div>
  </div>
</div>

<!-- MODAL PERFIL DETALHADO -->
<div class="mia-confirm-ov hide" id="profile-modal-ov" onclick="fecharModalPerfil()">
  <div class="mia-confirm-box" style="width:450px" onclick="event.stopPropagation()">
    <div class="mia-confirm-icon"><i class="fa fa-user-circle" style="color:#7c3aed"></i></div>
    <div class="mia-confirm-title">Perfil Detalhado do Cliente</div>
    <div class="mia-confirm-msg" style="text-align:left;margin-bottom:0">
      <div id="profile-details-body" style="display:flex;flex-direction:column;gap:12px">
        <!-- Preenchido via JS -->
      </div>
    </div>
    <div class="mia-confirm-btns" style="margin-top:20px">
      <button class="btn btn-secondary btn-sm" onclick="fecharModalPerfil()"><i class="fa fa-times"></i> Fechar</button>
    </div>
  </div>
</div>

<div class="mia-toast" id="toast"><i class="fa fa-magic"></i> <span id="toast-msg">Operação concluída</span></div>

<script>
let _currentOrderId = 0;
let _currentRemoteJid = '';
let _currentProfile = null;

function fecharModalPerfil(){document.getElementById('profile-modal-ov').classList.add('hide');}
function abrirModalPerfil(){
  if(!_currentProfile){showToast('Dados do perfil não carregados.');return;}
  const body=document.getElementById('profile-details-body');
  const p=_currentProfile;
  
  const prefsStr = p.preferences_json ? (typeof p.preferences_json === 'object' ? JSON.stringify(p.preferences_json, null, 2) : p.preferences_json) : 'Nenhuma';
  
  body.innerHTML = `
    <div class="profile-row">
      <span class="profile-label">Interesse / Dúvida</span>
      <div class="profile-value">${escapeHtml(p.interesse_duvida || 'Nenhuma registrada')}</div>
    </div>
    <div class="profile-row">
      <span class="profile-label">Preferências (JSON)</span>
      <div class="profile-value" style="font-family:monospace;white-space:pre-wrap;font-size:11px;background:#fff;padding:6px;border-radius:4px">${escapeHtml(prefsStr)}</div>
    </div>
    <div class="profile-row">
      <span class="profile-label">Última Interação</span>
      <div class="profile-value">${escapeHtml(p.last_interaction || '—')}</div>
    </div>
    <div class="profile-row">
      <span class="profile-label">Primeiro Contato</span>
      <div class="profile-value">${escapeHtml(p.created_at || '—')}</div>
    </div>
  `;
  document.getElementById('profile-modal-ov').classList.remove('hide');
}

function abrirDrawer(orderId){
  _currentOrderId = orderId || 0;
  document.getElementById('drawer-ov').classList.add('open');
  document.getElementById('drawer').classList.add('open');
  if (_currentOrderId) carregarDrawer(_currentOrderId);
}
function fecharDrawer(){document.getElementById('drawer-ov').classList.remove('open');document.getElementById('drawer').classList.remove('open')}
let _tt;
function showToast(msg){const t=document.getElementById('toast');document.getElementById('toast-msg').textContent=msg;t.classList.add('show');clearTimeout(_tt);_tt=setTimeout(()=>t.classList.remove('show'),2800)}
function abrirWhatsApp(telefone){const numero=telefone.replace(/\D/g,'');window.open('https://wa.me/55'+numero+'?text=Ol%C3%A1!%20Sobre%20seu%20pedido...','_blank');}
function confirmarPIX(){
  if(!_currentOrderId){showToast('Pedido inválido.');return;}
  const fd=new FormData();fd.append('action','update_status');fd.append('order_id',_currentOrderId);fd.append('status','pago');
  fetch('../_inc/ai_pedidos_actions.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      if(d.error) return showToast('Erro: '+(d.message||''));
      showToast('✓ Pagamento confirmado!');
      carregarPedidos();
      carregarDrawer(_currentOrderId);
    }).catch(()=>showToast('Erro de conexão.'));
}
function recusarPIX(){if(confirm('Tem certeza que deseja recusar este Pix?')){showToast('Pix recusado.')} }
function pausarIA(){
  if(!_currentRemoteJid){showToast('Contato inválido.');return;}
  const isOn=document.querySelector('.ia-ctrl').classList.contains('on');
  const target=isOn?'Manual':'Ativo';
  const fd=new FormData();fd.append('action','toggle_atendimento');fd.append('remote_jid',_currentRemoteJid);fd.append('status',target);
  fetch('../_inc/ai_pedidos_actions.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      if(d.error) return showToast('Erro: '+(d.message||''));
      atualizarUiAtendimento(target);
      carregarPedidos();
    }).catch(()=>showToast('Erro de conexão.'));
}

function atualizarUiAtendimento(status){
  const iaCtrl=document.querySelector('.ia-ctrl');
  const iaRobot=document.getElementById('drawer-ia-robot');
  const iaCtrTitle=document.getElementById('drawer-ia-title');
  const iaCtrSub=document.getElementById('drawer-ia-sub');
  const btn=document.getElementById('drawer-ia-btn');
  if(status==='Manual'){
    iaCtrl.classList.remove('on');iaCtrl.classList.add('off');
    iaRobot.classList.remove('on');iaRobot.classList.add('off');
    iaRobot.innerHTML='<i class="fa fa-user"></i>';
    iaCtrTitle.textContent='Humano Assumiu';
    iaCtrSub.textContent='Lojista respondendo';
    btn.className='btn-resume';
    btn.innerHTML='<i class="fa fa-play"></i> Retomar';
  } else {
    iaCtrl.classList.add('on');iaCtrl.classList.remove('off');
    iaRobot.classList.add('on');iaRobot.classList.remove('off');
    iaRobot.innerHTML='<i class="fa fa-magic"></i>';
    iaCtrTitle.textContent='IA Ativa';
    iaCtrSub.textContent='Conversando com cliente';
    btn.className='btn-pause';
    btn.innerHTML='<i class="fa fa-pause"></i> Pausar';
  }
}

function avancarStatus(){
  if(!_currentOrderId){showToast('Pedido inválido.');return;}
  const nextBtn=document.getElementById('drawer-next-btn');
  const st=(nextBtn.dataset.status||'').toLowerCase();
  let next='separando';
  if(st==='pendente'||st==='pago') next='separando';
  else if(st==='separando') next='rota';
  else if(st==='rota') next='entregue';
  else next='entregue';
  const fd=new FormData();fd.append('action','update_status');fd.append('order_id',_currentOrderId);fd.append('status',next);
  fetch('../_inc/ai_pedidos_actions.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      if(d.error) return showToast('Erro: '+(d.message||''));
      showToast('Status atualizado para: '+next);
      carregarPedidos();
      carregarDrawer(_currentOrderId);
    }).catch(()=>showToast('Erro de conexão.'));
}

function carregarDrawer(orderId){
  const url='../_inc/ai_pedidos_actions.php?action=get_order&order_id='+encodeURIComponent(orderId);
  fetch(url).then(r=>r.json()).then(d=>{
    if(d.error) return showToast('Erro: '+(d.message||''));
    const o=d.order||{};
    const items=d.items||[];
    const prof=d.profile||null;
    _currentProfile=prof;
    _currentRemoteJid=d.remote_jid||'';

    const title=document.getElementById('drawer-title');
    title.innerHTML='<i class="fa fa-shopping-cart"></i> Pedido #'+o.id;
    document.getElementById('drawer-sub').textContent=(o.status||'').toUpperCase();

    const name=(o.profile_name||o.customer_name||'Cliente');
    document.getElementById('drawer-client-name').textContent=name;
    document.getElementById('drawer-client-av').textContent=name.substring(0,1).toUpperCase();
    const phone=(o.whatsapp_phone||'');
    const info=document.getElementById('drawer-client-info');
    const phoneDigits=(phone||'').replace(/\D/g,'');
    const compras=prof && prof.total_interactions ? (prof.total_interactions+' interações') : '';
    info.innerHTML='<span style=\"cursor:pointer;color:#25d366;font-weight:600\" onclick=\"abrirWhatsApp(\''+phoneDigits+'\')\" ><i class=\"fa fa-whatsapp\"></i> '+phoneDigits+(compras?(' · '+compras):'')+'</span>';

    const prefsEl=document.getElementById('drawer-client-prefs');
    prefsEl.innerHTML='';
    const chips=[];
    if(prof && prof.usual_size) chips.push('Veste '+prof.usual_size);
    if(prof && prof.preferences){
      const p=prof.preferences;
      Object.keys(p).slice(0,3).forEach(k=>{
        const v=p[k];
        if(typeof v==='string' && v.trim()) chips.push(v.trim());
      });
    }
    prefsEl.innerHTML=chips.map(c=>'<span class="d-pref">'+escapeHtml(c)+'</span>').join('');

    document.getElementById('drawer-total').textContent=money(o.total_amount||0);
    document.getElementById('drawer-pix-phone').textContent=phoneDigits;

    const pixStatusEl=document.getElementById('drawer-pix-status');
    if((o.status||'')==='pago' || (o.status||'')==='separando' || (o.status||'')==='rota' || (o.status||'')==='entregue'){
      pixStatusEl.innerHTML='<i class="fa fa-check-circle" style="color:#059669"></i> Confirmado';
    } else {
      pixStatusEl.innerHTML='<i class="fa fa-clock-o" style="color:#d97706"></i> Pendente';
    }

    const itemsEl=document.getElementById('drawer-items');
    itemsEl.innerHTML=items.map(it=>{
      const img=it.photo_url ? '<img src="'+escapeAttr(it.photo_url)+'" style="width:50px;height:50px;object-fit:cover;border-radius:2px">' : '<i class="fa fa-camera"></i>';
      return '<div class="d-item">'
        + '<div class="d-item-img">'+img+'</div>'
        + '<div><div class="d-item-name">'+escapeHtml(it.model_name||'')+'</div>'
        + '<div class="d-item-var">'+escapeHtml((it.color||'')+' · Tamanho '+(it.size||'')+' · Qtd: '+(it.qty||1))+'</div>'
        + '<div class="d-item-price">'+money(it.subtotal||0)+'</div></div></div>';
    }).join('');

    const att=d.atendimento||{status:'Ativo'};
    atualizarUiAtendimento(att.status==='Manual'?'Manual':'Ativo');

    document.getElementById('drawer-summary').innerHTML='<p>'+escapeHtml(d.summary || 'A IA ainda não gerou um resumo para este atendimento.')+'</p>';

    const nextBtn=document.getElementById('drawer-next-btn');
    nextBtn.dataset.status=(o.status||'');
    const st=(o.status||'').toLowerCase();
    if(st==='pendente' || st==='pago'){ nextBtn.innerHTML='<i class="fa fa-check"></i> Marcar como Separando'; }
    else if(st==='separando'){ nextBtn.innerHTML='<i class="fa fa-motorcycle"></i> Iniciar Rota'; }
    else if(st==='rota'){ nextBtn.innerHTML='<i class="fa fa-check-circle"></i> Confirmar Entrega'; }
    else { nextBtn.innerHTML='<i class="fa fa-check-circle"></i> Entregue'; }
    // Ocultar botões PIX quando pagamento já confirmado/avançado
    const pixActBtns=document.querySelector('#drawer-pix-section .pix-action-buttons');
    if(pixActBtns) pixActBtns.style.display=st==='pendente'?'flex':'none';
    // Exibir botão Voltar apenas quando houver etapa anterior
    const backBtn=document.getElementById('drawer-back-btn');
    if(backBtn) backBtn.style.display=statusAnterior(st)?'inline-flex':'none';
  }).catch(()=>showToast('Erro ao carregar pedido.'));
}

function escapeHtml(s){return String(s).replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));}
function escapeAttr(s){return escapeHtml(s).replace(/`/g,'&#96;');}
function money(v){v=parseFloat(v||0);return 'R$ '+v.toFixed(2).replace('.',',');}

// ───── DATE FILTER ─────
const _meses=['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
let _curDate=new Date();
let _curPeriod='hoje';
function _isToday(d){const t=new Date();return d.getDate()===t.getDate()&&d.getMonth()===t.getMonth()&&d.getFullYear()===t.getFullYear();}
function _fmtPT(d){return d.getDate()+' de '+_meses[d.getMonth()]+' de '+d.getFullYear();}
function _fmtShort(d){return String(d.getDate()).padStart(2,'0')+'/'+String(d.getMonth()+1).padStart(2,'0')+'/'+d.getFullYear();}
function _updateDD(){
  const lbl=document.getElementById('dd-date-label'),val=document.getElementById('dd-date-val'),inp=document.getElementById('dd-custom');
  if(lbl) lbl.textContent=_isToday(_curDate)?'Hoje':_fmtShort(_curDate);
  if(val) val.textContent=_fmtPT(_curDate);
  if(inp){const y=_curDate.getFullYear(),m=String(_curDate.getMonth()+1).padStart(2,'0'),d=String(_curDate.getDate()).padStart(2,'0');inp.value=y+'-'+m+'-'+d;}
}
function _setQB(p){document.querySelectorAll('.dd-qb').forEach(b=>b.classList.remove('on'));const a=document.querySelector('.dd-qb[data-period="'+p+'"]');if(a)a.classList.add('on');}
function abrirDateDropdown(){const p=document.getElementById('dd-panel');if(p.classList.contains('hide')){p.classList.remove('hide');_updateDD();_setQB(_curPeriod);}else fecharDateDropdown();}
function fecharDateDropdown(){document.getElementById('dd-panel').classList.add('hide');}
function navegarData(dir){_curDate=new Date(_curDate.getTime()+dir*86400000);_curPeriod=_isToday(_curDate)?'hoje':'custom';_setQB(_curPeriod);_updateDD();}
function setPeriodo(period,el){
  _curPeriod=period;
  const h=new Date();
  if(period==='hoje')_curDate=h;
  else if(period==='ontem')_curDate=new Date(h.getTime()-86400000);
  _setQB(period);_updateDD();
}
function aplicarDataCustom(v){if(!v)return;const p=v.split('-');_curDate=new Date(parseInt(p[0]),parseInt(p[1])-1,parseInt(p[2]));_curPeriod=_isToday(_curDate)?'hoje':'custom';_setQB(_curPeriod);_updateDD();}
function aplicarFiltroData(){
  fecharDateDropdown();
  const labels={hoje:'Hoje',ontem:'Ontem',semana:'Esta semana',mes:'Este mês'};
  const txt=labels[_curPeriod]||_fmtShort(_curDate);
  const bar=document.getElementById('mia-actions-bar');
  let badge=document.getElementById('date-filter-badge');
  if(!badge){badge=document.createElement('span');badge.id='date-filter-badge';badge.className='mia-filter-badge';bar.prepend(badge);}
  badge.innerHTML='<i class="fa fa-calendar"></i> '+txt+' <button onclick="removerFiltroData()">&#215;</button>';
  carregarPedidos();
}
function removerFiltroData(){const b=document.getElementById('date-filter-badge');if(b)b.remove();_curDate=new Date();_curPeriod='hoje';_setQB('hoje');carregarPedidos();}
document.addEventListener('click',function(e){const p=document.getElementById('dd-panel');const w=document.getElementById('dd-wrap');if(p&&!p.classList.contains('hide')&&w&&!w.contains(e.target))fecharDateDropdown();});
// ───── SEARCH ─────
let _srchFilter='todos';
function abrirBusca(){document.getElementById('srch-ov').classList.remove('hide');setTimeout(()=>{const i=document.getElementById('srch-input');if(i){i.value='';i.focus();}},80);renderSrch('');}
function fecharBusca(){document.getElementById('srch-ov').classList.add('hide');}
let _srchTimer=null;
function renderSrch(q){
  clearTimeout(_srchTimer);
  _srchTimer=setTimeout(()=>{carregarBusca(q);},180);
}
function carregarBusca(q){
  const body=document.getElementById('srch-body'),countEl=document.getElementById('srch-count');
  if(!q.trim() && _srchFilter==='todos'){
    body.innerHTML='<div class="srch-empty"><i class="fa fa-search"></i><p>Digite para buscar pedidos</p><small>Por nome, telefone, nº do pedido ou produto</small></div>';
    if(countEl)countEl.textContent='';
    return;
  }
  fetch('../_inc/ai_pedidos_actions.php?action=search&q='+encodeURIComponent(q)+'&filter='+encodeURIComponent(_srchFilter))
    .then(r=>r.json()).then(d=>{
      if(d.error){body.innerHTML='<div class="srch-empty"><i class="fa fa-search"></i><p>Erro ao buscar</p><small>'+escapeHtml(d.message||'')+'</small></div>';return;}
      if(countEl)countEl.textContent=(d.count||0)+' pedido'+((d.count||0)!==1?'s':'')+' encontrado'+((d.count||0)!==1?'s':'');
      if(!d.html){body.innerHTML='<div class="srch-empty"><i class="fa fa-search"></i><p>Nenhum pedido encontrado</p><small>Tente outro nome, telefone ou número</small></div>';return;}
      body.innerHTML=d.html;
    }).catch(()=>{body.innerHTML='<div class="srch-empty"><i class="fa fa-search"></i><p>Erro ao buscar</p><small>Falha de conexão.</small></div>';});
}
function srchFiltrar(el,filter){_srchFilter=filter;document.querySelectorAll('.srch-chip').forEach(c=>c.classList.remove('on'));el.classList.add('on');renderSrch(document.getElementById('srch-input').value);}
document.addEventListener('keydown',function(e){if(e.key==='Escape'){fecharBusca();fecharDateDropdown();fecharConfirm();}});
document.getElementById('srch-ov').addEventListener('click',function(e){if(e.target===this)fecharBusca();});

function carregarPedidos(){
  const d=_curDate;
  const y=d.getFullYear(),m=String(d.getMonth()+1).padStart(2,'0'),dd=String(d.getDate()).padStart(2,'0');
  const dateStr=y+'-'+m+'-'+dd;
  fetch('../_inc/ai_pedidos_actions.php?action=list&period='+encodeURIComponent(_curPeriod)+'&date='+encodeURIComponent(dateStr))
    .then(r=>r.json()).then(data=>{
      if(data.error){showToast('Erro: '+(data.message||''));return;}
      document.getElementById('col-pedido').innerHTML=data.pedido_html||'';
      document.getElementById('col-separacao').innerHTML=data.separacao_html||'';
      document.getElementById('col-rota').innerHTML=data.rota_html||'';
      document.getElementById('col-entregue').innerHTML=data.entregue_html||'';
      const c=data.counts||{};
      document.getElementById('badge-pedido').textContent=c.pedido||0;
      document.getElementById('badge-separacao').textContent=c.separacao||0;
      document.getElementById('badge-rota').textContent=c.rota||0;
      document.getElementById('badge-entregue').textContent=c.entregue||0;
      document.getElementById('sb-pix-pend').textContent=c.pendente_pix||0;
      document.getElementById('sb-sep').textContent=c.separacao||0;
      document.getElementById('sb-rota').textContent=c.rota||0;
      document.getElementById('sb-ent-hoje').textContent=c.entregue_hoje||0;
    }).catch(()=>showToast('Erro ao carregar pedidos.'));
}

// ───── CONFIRM MODAL ─────
function abrirConfirm(title,msg,btnLabel,btnClass,callback){
  document.getElementById('confirm-title').textContent=title;
  document.getElementById('confirm-msg').textContent=msg;
  const btn=document.getElementById('confirm-ok-btn');
  btn.innerHTML=btnLabel;
  btn.className=btnClass;
  btn.onclick=function(){fecharConfirm();callback();};
  document.getElementById('confirm-ov').classList.remove('hide');
}
function fecharConfirm(){document.getElementById('confirm-ov').classList.add('hide');}
document.getElementById('confirm-ov').addEventListener('click',function(e){if(e.target===this)fecharConfirm();});
// ───── DELETE ORDER ─────
function confirmarExcluir(){
  if(!_currentOrderId) return;
  document.getElementById('confirm-icon').innerHTML='<i class="fa fa-trash" style="color:#dc2626"></i>';
  abrirConfirm(
    'Excluir Pedido #'+_currentOrderId,
    'Todos os dados do pedido serão removidos permanentemente. Esta ação não pode ser desfeita.',
    '<i class="fa fa-trash"></i> Excluir','btn-danger',
    excluirPedido
  );
}
function excluirPedido(){
  const fd=new FormData();
  fd.append('action','delete_order');fd.append('order_id',_currentOrderId);
  fetch('../_inc/ai_pedidos_actions.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      if(d.error) return showToast('Erro: '+(d.message||''));
      showToast('Pedido #'+_currentOrderId+' excluído.');
      fecharDrawer();carregarPedidos();
    }).catch(()=>showToast('Erro de conexão.'));
}
// ───── STEP BACK ─────
function statusAnterior(st){
  const map={pago:'pendente',separando:'pago',rota:'separando',entregue:'rota'};
  return map[st]||null;
}
function labelStatus(st){
  const map={pendente:'Aguardando PIX',pago:'Pago',separando:'Separação',rota:'Em Rota',entregue:'Entregue'};
  return map[st]||st;
}
function confirmarVoltarStatus(){
  if(!_currentOrderId) return;
  const st=(document.getElementById('drawer-next-btn').dataset.status||'').toLowerCase();
  const prev=statusAnterior(st);
  if(!prev) return;
  document.getElementById('confirm-icon').innerHTML='<i class="fa fa-arrow-left" style="color:#d97706"></i>';
  abrirConfirm(
    'Voltar Etapa do Pedido',
    'Deseja mover o Pedido #'+_currentOrderId+' de volta para “'+labelStatus(prev)+'”?',
    '<i class="fa fa-arrow-left"></i> Voltar','btn-warning',
    voltarStatus
  );
}
function voltarStatus(){
  if(!_currentOrderId) return;
  const st=(document.getElementById('drawer-next-btn').dataset.status||'').toLowerCase();
  const prev=statusAnterior(st);
  if(!prev){showToast('Não é possível voltar.');return;}
  const fd=new FormData();
  fd.append('action','update_status');fd.append('order_id',_currentOrderId);fd.append('status',prev);
  fetch('../_inc/ai_pedidos_actions.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      if(d.error) return showToast('Erro: '+(d.message||''));
      showToast('Pedido voltou para: '+labelStatus(prev));
      carregarPedidos();carregarDrawer(_currentOrderId);
    }).catch(()=>showToast('Erro de conexão.'));
}
document.addEventListener('DOMContentLoaded',()=>{
  const ddVal=document.getElementById('dd-date-val');
  if(ddVal) ddVal.textContent=_fmtPT(new Date());
  carregarPedidos();
  const upd=document.querySelector('#mia-actions-bar .btn.btn-primary.btn-sm');
  if(upd) upd.setAttribute('onclick','carregarPedidos()');
});
</script>

<?php include ("footer.php"); ?>
