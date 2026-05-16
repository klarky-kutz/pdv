<?php
ob_start();
session_start();
include realpath(__DIR__.'/../').'/_init.php';

if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

// ── Carregar helpers do Moda IA ───────────────────────────────────────────────
require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_plan_gate.php';

// ── Dados reais ───────────────────────────────────────────────────────────────
$_ai_tid       = ai_tenant_id();
$_ai_enabled   = ai_plan_is_enabled($_ai_tid);
$_ai_plan      = ai_get_active_plan($_ai_tid);
$_ai_usage     = ai_get_usage($_ai_tid);
$_ai_catLimit  = ai_check_catalog_limit($_ai_tid);
$_ai_categories = ai_get_catalogo_categories();
$_ai_categoriesById = [];
foreach ($_ai_categories as $_cat) {
  $_ai_categoriesById[(int)($_cat['id'] ?? 0)] = (string)($_cat['name'] ?? '');
}
$_ai_models    = $_ai_enabled ? ai_get_catalogo_models() : [];
$_ai_stockSum  = $_ai_enabled ? ai_get_stock_summary() : ['total_skus'=>0,'zerados'=>0,'criticos'=>0,'valor_total'=>0];

// Métricas
$_ai_catUsed   = (int)$_ai_catLimit['used'];
$_ai_catLim    = (int)$_ai_catLimit['limit'];
$_ai_catPct    = $_ai_catLim > 0 ? round(($_ai_catUsed/$_ai_catLim)*100) : 0;
$_ai_callUsed  = (int)$_ai_usage['webhook_calls'];
$_ai_callLim   = (int)($_ai_plan['ai_webhook_calls'] ?? 0);
$_ai_callPct   = $_ai_callLim > 0 ? round(($_ai_callUsed/$_ai_callLim)*100) : 0;
$_ai_storageMB = (float)$_ai_usage['storage_mb_used'];
$_ai_storageLim = (int)($_ai_plan['storage_mb'] ?? 0);
$_ai_storageUnlimited = $_ai_storageLim <= 0;
$_ai_storagePct = $_ai_storageUnlimited ? 0 : min(100, round(($_ai_storageMB / $_ai_storageLim) * 100, 1));
$_ai_storageFillClass = $_ai_storageUnlimited ? '' : ($_ai_storagePct >= 80 ? ($_ai_storagePct >= 95 ? 'crit' : 'warn') : '');

$_ai_totalModels  = count($_ai_models);
$_ai_activeCount  = count(array_filter($_ai_models, fn($m) => (int)$m['is_active'] === 1));
$_ai_inactiveCount= $_ai_totalModels - $_ai_activeCount;
$_ai_zeroStock    = (int)$_ai_stockSum['zerados'];
$_ai_maxDemand    = $_ai_totalModels > 0
    ? max(array_column($_ai_models,'demand_count'))
    : 1;
if (!$_ai_maxDemand) $_ai_maxDemand = 1;

// ─────────────────────────────────────────────────────────────────────────────
$document->setTitle('Catálogo IA · Moda IA');
$document->setBodyClass('concierge_catalogo');

include ("header.php");
include ("left_sidebar.php");
?>
<div class="content-wrapper">
  <section class="content-header">
    <h1><i class="fa fa-tag" style="color:#6d28d9;margin-right:8px"></i>Catálogo IA</h1>
    <ol class="breadcrumb">
      <li><a href="dashboard.php"><i class="fa fa-home"></i> Home</a></li>
      <li>Moda IA</li>
      <li class="active">Catálogo IA</li>
    </ol>
  </section>
  <section class="content">
<style>
@keyframes mia-fadeIn{from{opacity:0}to{opacity:1}}
@keyframes mia-slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
/* Buttons */
#mia-root .btn,.mia-overlay .btn{display:inline-flex!important;align-items:center;gap:6px;padding:8px 14px;border-radius:2px!important;font-size:13px;font-weight:600;transition:all .18s;cursor:pointer}
#mia-root .btn-primary,.mia-overlay .btn-primary{background:linear-gradient(135deg,#6d28d9,#7c3aed)!important;color:#fff!important;box-shadow:0 2px 8px rgba(109,40,217,.35)!important;border:none!important}
#mia-root .btn-primary:hover,.mia-overlay .btn-primary:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(109,40,217,.45)!important;background:linear-gradient(135deg,#6d28d9,#7c3aed)!important;color:#fff!important}
#mia-root .btn-secondary,.mia-overlay .btn-secondary{background:#fff!important;border:1px solid #d1d5db!important;color:#374151!important}
#mia-root .btn-secondary:hover,.mia-overlay .btn-secondary:hover{border-color:#a78bfa!important;color:#6d28d9!important}
#mia-root .btn-danger,.mia-overlay .btn-danger{background:#fff!important;border:1px solid #fca5a5!important;color:#dc2626!important}
#mia-root .btn-danger:hover,.mia-overlay .btn-danger:hover{background:#fee2e2!important}
#mia-root .btn-stats,.mia-overlay .btn-stats{background:#fff!important;border:1px solid #c4b5fd!important;color:#6d28d9!important}
#mia-root .btn-stats:hover,.mia-overlay .btn-stats:hover{background:#ede9fe!important;color:#6d28d9!important}
#mia-root .btn-sm,.mia-overlay .btn-sm{padding:5px 10px!important;font-size:12px!important}
.btn-upgrade{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff!important;font-weight:700;box-shadow:0 2px 8px rgba(217,119,6,.3);border:none!important;padding:5px 10px;border-radius:2px!important;font-size:12px;cursor:pointer}
.btn-upgrade:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(217,119,6,.4)}
/* Actions */
.mia-actions{display:flex;justify-content:flex-end;gap:8px;margin-bottom:14px}
/* Usage bar */
.usage-bar{background:#fff;border:1px solid #e5e7eb;border-radius:2px;padding:12px 16px;display:flex;align-items:center;gap:16px;box-shadow:0 1px 4px rgba(0,0,0,.06);flex-wrap:wrap;margin-bottom:14px}
.usage-item{display:flex;align-items:center;gap:10px;flex:1;min-width:150px}
.usage-label{font-size:12px;font-weight:600;color:#6b7280;white-space:nowrap}
.usage-track{flex:1;height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden;min-width:80px}
.usage-fill{height:100%;border-radius:3px;background:#6d28d9;transition:width .3s}
.usage-fill.warn{background:#d97706}
.usage-fill.crit{background:#dd4b39}
.usage-num{font-size:12px;font-weight:700;color:#374151;white-space:nowrap}
.usage-sep{width:1px;height:30px;background:#e5e7eb}
/* Info boxes */
.ib-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:14px}
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
.ib-badge.hot{background:linear-gradient(135deg,#7c3aed,#a78bfa);color:#fff}
/* Filter bar */
.filter-bar{background:#fff;border:1px solid #e5e7eb;border-radius:2px;padding:10px 14px;display:flex;align-items:center;gap:10px;box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:14px}
.fb-label{font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;display:flex;align-items:center;gap:5px}
.fb-chips{display:flex;gap:6px;flex-wrap:wrap}
.fb-chip{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid #d1d5db;color:#6b7280;cursor:pointer;transition:all .15s;background:#fff;display:flex;align-items:center;gap:4px}
.fb-chip:hover{border-color:#a78bfa;color:#6d28d9;background:#f5f3ff}
.fb-chip.on{background:#ede9fe;border-color:#c4b5fd;color:#4c1d95}
.fb-spacer{flex:1}
.fb-search{display:flex;align-items:center;gap:7px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:2px;padding:5px 10px;transition:border-color .15s}
.fb-search:focus-within{border-color:#7c3aed;box-shadow:0 0 0 2px rgba(124,58,237,.12)}
.fb-search i{color:#9ca3af;font-size:13px}
.fb-search input{border:none;background:transparent;outline:none;font-size:13px;color:#374151;width:180px}
.fb-search input::placeholder{color:#9ca3af}
/* Table box */
.mia-box{background:#fff;border:1px solid #e5e7eb;border-radius:2px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden}
.bh{border-top:3px solid #6d28d9;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f3f4f6}
.bt{font-size:14px;font-weight:700;color:#374151;display:flex;align-items:center;gap:8px}
.bt i{color:#6d28d9}
.bt .count{background:#ede9fe;color:#4c1d95;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px}
.bh-actions{display:flex;gap:8px;align-items:center}
#mia-root table{width:100%;border-collapse:collapse}
#mia-root thead th{background:#f9fafb;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;padding:10px 14px;text-align:left;border-bottom:1px solid #e5e7eb;white-space:nowrap}
#mia-root thead th.sort{cursor:pointer}
#mia-root thead th.sort:hover{color:#6d28d9}
#mia-root tbody tr{border-bottom:1px solid #f3f4f6;transition:background .12s}
#mia-root tbody tr:hover{background:#faf5ff}
#mia-root tbody tr:last-child{border-bottom:none}
#mia-root td{padding:10px 14px;vertical-align:middle}
.td-foto .thumb{width:48px;height:48px;border-radius:2px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;font-size:18px;color:#c4b5fd;border:1px solid #e5e7eb;overflow:hidden}
.td-produto .pname{font-weight:700;color:#111827;font-size:13.5px}
.td-produto .pid{font-size:11px;color:#9ca3af;margin-top:2px}
.td-produto .pcat{display:inline-block;background:#f3f4f6;color:#6b7280;font-size:10px;padding:2px 6px;border-radius:10px;margin-top:3px;font-weight:600}
.td-variantes .swatches{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:4px}
.swatch{width:16px;height:16px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.15);flex-shrink:0;position:relative;display:flex;align-items:center;justify-content:center}
.swatch.out-of-stock::after{content:'\2715';position:absolute;top:-2px;left:-2px;right:-2px;bottom:-2px;display:flex;align-items:center;justify-content:center;color:#ff0000;font-size:14px;font-weight:900;text-shadow:1px 1px 2px rgba(0,0,0,0.3),-1px -1px 0 #fff;line-height:1;transform:scale(1.2);pointer-events:none}
.td-variantes .vinfo{font-size:11px;color:#6b7280;font-weight:600}
.td-demand{min-width:130px}
.demand-num{font-size:20px;font-weight:900;color:#4c1d95;line-height:1}
.demand-num.hot{color:#7c3aed}
.demand-sub{font-size:10px;color:#9ca3af;font-weight:600;margin-top:1px}
.demand-bar{height:5px;background:#ede9fe;border-radius:3px;margin-top:6px;overflow:hidden}
.demand-fill{height:100%;background:linear-gradient(90deg,#7c3aed,#a78bfa);border-radius:3px}
.demand-fill.hot{background:linear-gradient(90deg,#4c1d95,#7c3aed);box-shadow:0 0 6px rgba(109,40,217,.4)}
.toggle-track{width:36px;height:20px;border-radius:20px;background:#e5e7eb;position:relative;cursor:pointer;transition:background .2s;flex-shrink:0}
.toggle-track.on{background:linear-gradient(135deg,#6d28d9,#7c3aed)}
.toggle-thumb{width:16px;height:16px;border-radius:50%;background:#fff;position:absolute;top:2px;left:2px;transition:left .2s;box-shadow:0 1px 4px rgba(0,0,0,.2)}
.toggle-track.on .toggle-thumb{left:18px}
.status-lbl{font-size:11px;font-weight:700;color:#6b7280}
.status-lbl.on{color:#059669}
.td-acoes{white-space:nowrap}
.bf{background:#f9fafb;border-top:1px solid #f3f4f6;padding:10px 16px;display:flex;align-items:center;justify-content:space-between}
.bf-info{font-size:12px;color:#9ca3af;font-weight:600}
#mia-root .pagination{display:flex!important;gap:4px!important;padding:0;margin:0;list-style:none}
.pg-btn{width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:2px;border:1px solid #d1d5db;background:#fff;color:#6b7280;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s}
.pg-btn:hover{border-color:#a78bfa;color:#6d28d9}
.pg-btn.act{background:linear-gradient(135deg,#6d28d9,#7c3aed);border-color:#6d28d9;color:#fff}
/* Modal */
.mia-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1060;display:flex;align-items:center;justify-content:center;animation:mia-fadeIn .2s}
.mia-overlay.hide{display:none}
.mia-modal{background:#fff;border-radius:2px;box-shadow:0 10px 40px rgba(0,0,0,.3);animation:mia-slideUp .2s;width:680px;max-width:calc(100vw - 32px);max-height:90vh;display:flex;flex-direction:column}
.mia-modal.modal-lg{width:840px}
.mh{background:linear-gradient(135deg,#4c1d95,#7c3aed);padding:20px;display:flex;align-items:flex-start;justify-content:space-between;flex-shrink:0}
.mh-info .mt{font-size:16px;font-weight:700;color:#fff;display:flex;align-items:center;gap:8px}
.mh-info .ms{font-size:12px;color:#c4b5fd;margin-top:3px}
.mh-close{background:none;border:none;color:#c4b5fd;font-size:18px;cursor:pointer;padding:2px 6px;border-radius:2px;transition:color .15s;line-height:1}
.mh-close:hover{color:#fff}
.mb{padding:18px;flex:1;overflow-y:auto}
.mb::-webkit-scrollbar{width:4px}
.mb::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:2px}
.mf{background:#faf5ff;border-top:1px solid #ede9fe;padding:12px 18px;display:flex;justify-content:flex-end;gap:8px;flex-shrink:0}
/* Stepper */
.stepper{display:flex;align-items:center;gap:0;margin-bottom:20px}
.step{display:flex;align-items:center;flex:1}
.step:last-child{flex:none}
.step-indicator{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;z-index:1}
.step-indicator.done{background:#059669;color:#fff}
.step-indicator.active{background:linear-gradient(135deg,#6d28d9,#7c3aed);color:#fff;box-shadow:0 0 0 3px rgba(109,40,217,.2)}
.step-indicator.pending{background:#e5e7eb;color:#9ca3af}
.step-label{font-size:11px;font-weight:600;margin-left:6px;white-space:nowrap}
.step-label.done{color:#059669}
.step-label.active{color:#6d28d9}
.step-label.pending{color:#9ca3af}
.step-line{flex:1;height:2px;background:#e5e7eb;margin:0 8px}
.step-line.done{background:#059669}
/* Form (modal) */
.mia-overlay .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.mia-overlay .form-group{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}
.mia-overlay .form-group:last-child{margin-bottom:0}
.mia-overlay .form-label{font-size:12px;font-weight:600;color:#555;display:flex;align-items:center;gap:4px}
.mia-overlay .form-control{border:1px solid #d1d5db;border-radius:2px;padding:8px 10px;font-size:13.5px;color:#374151;background:#fff;transition:all .15s;outline:none;width:100%}
.mia-overlay .form-control:focus{border-color:#7c3aed;box-shadow:0 0 0 2px rgba(124,58,237,.12)}
.mia-overlay .form-hint{font-size:11px;color:#9ca3af}
/* Tags input */
.tags-input{border:1px solid #d1d5db;border-radius:2px;padding:6px 8px;display:flex;flex-wrap:wrap;gap:5px;background:#fff;cursor:text;transition:border-color .15s}
.tags-input:focus-within{border-color:#7c3aed;box-shadow:0 0 0 2px rgba(124,58,237,.12)}
.tag-chip{background:#ede9fe;color:#4c1d95;border:1px solid #c4b5fd;border-radius:20px;padding:2px 8px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:4px}
.tag-chip button{background:none;border:none;color:#7c3aed;font-size:14px;line-height:1;cursor:pointer;padding:0}
.tags-input input{border:none;outline:none;font-size:13px;color:#374151;min-width:80px;flex:1;background:transparent;padding:2px 0}
/* Variant cards */
.variant-card{border:1px solid #e5e7eb;border-radius:2px;margin-bottom:10px;overflow:hidden}
.variant-header{background:#f9fafb;padding:10px 14px;display:flex;align-items:center;gap:10px;cursor:pointer}
.variant-header:hover{background:#f5f3ff}
.vc-swatch{width:22px;height:22px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 0 1.5px rgba(0,0,0,.2)}
.vc-name{font-weight:700;font-size:13px;color:#374151;flex:1}
.vc-badge{background:#ede9fe;color:#4c1d95;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;display:flex;align-items:center;gap:5px}
.vc-badge b{background:#7c3aed;color:#fff;padding:1px 5px;border-radius:10px;font-size:9px}
.variant-photo-wrapper{width:80px;height:80px;border-radius:4px;border:2px dashed #d1d5db;display:flex;align-items:center;justify-content:center;cursor:pointer;overflow:hidden;position:relative;background:#f9fafb;transition:all .15s}
.variant-photo-wrapper:hover{border-color:#7c3aed;background:#f5f3ff}
.variant-photo-wrapper img{width:100%;height:100%;object-fit:cover}
.variant-photo-wrapper i{font-size:20px;color:#c4b5fd}
.variant-photo-wrapper .photo-hint{position:absolute;bottom:0;left:0;right:0;background:rgba(109,40,217,0.7);color:#fff;font-size:9px;text-align:center;padding:2px 0;opacity:0;transition:opacity .15s}
.variant-photo-wrapper:hover .photo-hint{opacity:1}
.empty-variants{text-align:center;padding:40px 20px;background:#f9fafb;border:2px dashed #e5e7eb;border-radius:8px;margin:10px 0}
.empty-variants i{font-size:32px;color:#c4b5fd;margin-bottom:12px;display:block}
.empty-variants div{font-size:14px;color:#6b7280;font-weight:600}
.empty-variants p{font-size:12px;color:#9ca3af;margin-top:4px}
.vc-toggle{color:#9ca3af;transition:transform .2s}
.vc-toggle.open{transform:rotate(-90deg)}
.variant-body{padding:14px;border-top:1px solid #f3f4f6;display:none}
.variant-body.open{display:block}
.size-grid{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
.sz-pill{padding:5px 10px;border-radius:2px;border:1.5px solid #d1d5db;font-size:12px;font-weight:700;color:#6b7280;cursor:pointer;transition:all .15s;background:#fff}
.sz-pill.on{background:#ede9fe;border-color:#7c3aed;color:#4c1d95}
.sz-pill:hover{border-color:#a78bfa;color:#6d28d9}
.webp-badge{background:#ede9fe;color:#6d28d9;border:1px solid #c4b5fd;border-radius:20px;font-size:10px;font-weight:700;padding:2px 7px;display:inline-flex;align-items:center;gap:4px}
.upload-area{border:2px dashed #d1d5db;border-radius:2px;padding:14px;text-align:center;cursor:pointer;transition:all .15s}
.upload-area:hover{border-color:#a78bfa;background:#faf5ff}
.upload-area i{font-size:22px;color:#c4b5fd;margin-bottom:6px;display:block}
.upload-area span{font-size:12px;color:#9ca3af}
/* Stats modal */
.stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:18px}
.stat-box{background:#f9fafb;border:1px solid #e5e7eb;border-radius:2px;padding:14px}
.stat-box .sv{font-size:28px;font-weight:900;color:#4c1d95;line-height:1}
.stat-box .sl{font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.4px;margin-top:3px}
.mini-chart{display:flex;align-items:flex-end;gap:4px;height:60px;padding:0 2px}
.bar-item{flex:1;background:linear-gradient(180deg,#7c3aed,#a78bfa);border-radius:2px 2px 0 0;transition:height .3s}
.bar-item.today{background:linear-gradient(180deg,#4c1d95,#6d28d9);box-shadow:0 0 8px rgba(109,40,217,.35)}
.bar-label{font-size:9px;color:#9ca3af;text-align:center;margin-top:4px}
/* Toast */
.mia-toast{position:fixed;bottom:20px;right:20px;background:#222d32;color:#fff;border-left:3px solid #6d28d9;padding:10px 16px;border-radius:2px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;z-index:9999;transform:translateX(120%);transition:transform .3s cubic-bezier(.34,1.56,.64,1);min-width:260px;box-shadow:0 4px 20px rgba(0,0,0,.3)}
.mia-toast.show{transform:translateX(0)}
.mia-toast i{color:#a78bfa}
/* ── Upgrade Modal ── */
.upg-ov{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1070;display:flex;align-items:center;justify-content:center;animation:mia-fadeIn .2s}
.upg-ov.hide{display:none}
.upg-modal{background:#fff;border-radius:8px;box-shadow:0 24px 80px rgba(0,0,0,.3);width:720px;max-width:calc(100vw - 32px);max-height:90vh;display:flex;flex-direction:column;animation:mia-slideUp .25s;overflow:hidden}
.upg-hd{background:linear-gradient(135deg,#78350f,#b45309,#d97706,#f59e0b);padding:22px 24px;display:flex;align-items:center;justify-content:space-between;position:relative;overflow:hidden;flex-shrink:0}
.upg-hd::before{content:'';position:absolute;top:-50px;right:-30px;width:180px;height:180px;background:rgba(255,255,255,.07);border-radius:50%}
.upg-hd::after{content:'';position:absolute;bottom:-40px;left:30%;width:120px;height:120px;background:rgba(255,255,255,.05);border-radius:50%}
.upg-hd-l{display:flex;align-items:center;gap:14px;position:relative;z-index:1}
.upg-hd-icon{width:50px;height:50px;border-radius:6px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;flex-shrink:0}
.upg-hd-title{font-size:18px;font-weight:700;color:#fff;line-height:1.2}
.upg-hd-sub{font-size:12px;color:rgba(255,255,255,.8);margin-top:3px}
.upg-hd-close{background:rgba(255,255,255,.18);border:none;color:#fff;width:32px;height:32px;border-radius:4px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;position:relative;z-index:1;transition:background .15s;flex-shrink:0}
.upg-hd-close:hover{background:rgba(255,255,255,.32)}
.upg-body{flex:1;overflow-y:auto}
.upg-body::-webkit-scrollbar{width:4px}
.upg-body::-webkit-scrollbar-thumb{background:#e5e7eb;border-radius:2px}
.upg-usage{padding:18px 24px 16px;background:linear-gradient(135deg,#fffbeb,#fef3c7);border-bottom:2px solid #fde68a}
.upg-usage-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.upg-usage-title{font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:5px}
.upg-usage-plan{background:rgba(146,64,14,.12);color:#92400e;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;border:1px solid rgba(146,64,14,.2)}
.upg-usage-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.upg-metric{background:#fff;border:1px solid #fde68a;border-radius:6px;padding:12px 14px;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.upg-metric-lbl{font-size:10px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.3px;margin-bottom:8px;display:flex;align-items:center;gap:4px}
.upg-metric-nums{display:flex;align-items:baseline;gap:3px;margin-bottom:6px}
.upg-metric-num{font-size:20px;font-weight:900;color:#111827;line-height:1}
.upg-metric-total{font-size:12px;color:#9ca3af;font-weight:400}
.upg-track{height:8px;background:#fde68a;border-radius:4px;overflow:hidden;margin-bottom:4px}
.upg-fill{height:100%;border-radius:4px;transition:width .6s cubic-bezier(.4,0,.2,1)}
.upg-fill.low{background:linear-gradient(90deg,#059669,#10b981)}
.upg-fill.med{background:linear-gradient(90deg,#d97706,#f59e0b)}
.upg-fill.high{background:linear-gradient(90deg,#dc2626,#ef4444)}
@keyframes pulse-warn{0%,100%{opacity:1}50%{opacity:.65}}
.upg-fill.high{animation:pulse-warn 2s infinite}
.upg-pct{font-size:10px;font-weight:700}
.upg-pct.low{color:#059669}
.upg-pct.med{color:#d97706}
.upg-pct.high{color:#dc2626}
.upg-plans{padding:18px 24px}
.upg-plans-title{font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;display:flex;align-items:center;gap:6px}
.upg-plan-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.upg-plan{border:2px solid #e5e7eb;border-radius:8px;overflow:hidden;position:relative;transition:transform .15s,box-shadow .15s}
.upg-plan:hover:not(.current){transform:translateY(-3px);box-shadow:0 8px 28px rgba(0,0,0,.12)}
.upg-plan.current{opacity:.65;cursor:default}
.upg-plan.recommended{border-color:#f59e0b;box-shadow:0 4px 20px rgba(245,158,11,.25)}
.upg-plan-badge{text-align:center;background:linear-gradient(135deg,#b45309,#f59e0b);color:#fff;font-size:10px;font-weight:700;padding:4px 0;letter-spacing:.5px}
.upg-plan-hd{padding:16px 14px 12px;text-align:center;border-bottom:1px solid #f3f4f6}
.upg-plan.recommended .upg-plan-hd{background:linear-gradient(135deg,#fffbeb,#fef9e7)}
.upg-plan-name{font-size:13px;font-weight:700;color:#374151;margin-bottom:4px}
.upg-plan-price{font-size:26px;font-weight:900;color:#111827;line-height:1}
.upg-plan-price small{font-size:12px;font-weight:500;color:#9ca3af}
.upg-plan-body{padding:14px}
.upg-feat{display:flex;align-items:flex-start;gap:7px;margin-bottom:8px;font-size:12px;color:#374151;line-height:1.4}
.upg-feat i.ok{color:#059669;font-size:11px;margin-top:1px;flex-shrink:0}
.upg-feat i.no{color:#d1d5db;font-size:11px;margin-top:1px;flex-shrink:0}
.upg-feat.dim{color:#b0b7c3}
.upg-feat strong{color:#111827;font-weight:700}
.upg-cta{width:100%;padding:10px;border-radius:4px;font-size:12px;font-weight:700;cursor:pointer;border:none;transition:all .15s;display:flex;align-items:center;justify-content:center;gap:6px;margin-top:4px}
.upg-cta.disabled{background:#f3f4f6;color:#9ca3af;cursor:default}
.upg-cta.gold{background:linear-gradient(135deg,#b45309,#d97706);color:#fff;box-shadow:0 2px 10px rgba(180,83,9,.3)}
.upg-cta.gold:hover{box-shadow:0 4px 16px rgba(180,83,9,.45);transform:translateY(-1px)}
.upg-cta.outline{background:#fff;border:2px solid #d97706;color:#b45309}
.upg-cta.outline:hover{background:#fffbeb}
.upg-ft{background:linear-gradient(135deg,#1e293b,#0f172a);padding:14px 24px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-shrink:0}
.upg-ft-text{font-size:12px;color:rgba(255,255,255,.65);line-height:1.5}
.upg-ft-text strong{color:#fff;font-weight:700}
.upg-wa{display:inline-flex;align-items:center;gap:7px;background:#25d366;color:#fff;border:none;border-radius:4px;padding:9px 16px;font-size:12px;font-weight:700;cursor:pointer;transition:all .15s;white-space:nowrap}
.upg-wa:hover{background:#22c55e;transform:translateY(-1px);box-shadow:0 4px 12px rgba(37,211,102,.35)}
/* ── Add-on Cards ── */
.upg-addons{padding:18px 24px 20px}
.upg-addons-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.upg-addons-title{font-size:11px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:6px}
.upg-addons-hint{font-size:11px;color:#9ca3af;font-style:italic}
.upg-addon-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px}
.upg-addon{border-radius:10px;overflow:hidden;border:2px solid transparent;transition:transform .18s,box-shadow .18s;position:relative}
.upg-addon:hover{transform:translateY(-4px);box-shadow:0 10px 28px rgba(0,0,0,.13)}
.upg-addon.cat{border-color:#ddd6fe;background:linear-gradient(160deg,#f5f3ff 0%,#ede9fe 100%)}
.upg-addon.ia{border-color:#fde68a;background:linear-gradient(160deg,#fffbeb 0%,#fef3c7 100%)}
.upg-addon.st{border-color:#bfdbfe;background:linear-gradient(160deg,#eff6ff 0%,#dbeafe 100%)}
.upg-addon-top-badge{font-size:9px;font-weight:800;padding:4px 0;text-align:center;letter-spacing:.5px;text-transform:uppercase}
.upg-addon.cat .upg-addon-top-badge{background:linear-gradient(90deg,#6d28d9,#7c3aed);color:#fff}
.upg-addon.ia .upg-addon-top-badge{background:linear-gradient(90deg,#dc2626,#d97706);color:#fff;animation:pulse-warn 2s infinite}
.upg-addon.st .upg-addon-top-badge{background:linear-gradient(90deg,#1d4ed8,#3b82f6);color:#fff}
.upg-addon-body{padding:14px 14px 14px}
.upg-addon-iw{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:19px;margin-bottom:10px;box-shadow:0 2px 8px rgba(0,0,0,.12)}
.upg-addon.cat .upg-addon-iw{background:linear-gradient(135deg,#6d28d9,#7c3aed);color:#fff}
.upg-addon.ia .upg-addon-iw{background:linear-gradient(135deg,#b45309,#f59e0b);color:#fff}
.upg-addon.st .upg-addon-iw{background:linear-gradient(135deg,#1d4ed8,#60a5fa);color:#fff}
.upg-addon-num{font-size:22px;font-weight:900;line-height:1.1}
.upg-addon.cat .upg-addon-num{color:#4c1d95}
.upg-addon.ia .upg-addon-num{color:#92400e}
.upg-addon.st .upg-addon-num{color:#1e40af}
.upg-addon-sub{font-size:11px;font-weight:700;margin-bottom:7px}
.upg-addon.cat .upg-addon-sub{color:#7c3aed}
.upg-addon.ia .upg-addon-sub{color:#d97706}
.upg-addon.st .upg-addon-sub{color:#2563eb}
.upg-addon-desc{font-size:11px;color:#6b7280;line-height:1.55;margin-bottom:10px}
.upg-addon-inc{font-size:11px;font-weight:700;margin-bottom:13px;display:flex;align-items:center;gap:5px}
.upg-addon.cat .upg-addon-inc{color:#6d28d9}
.upg-addon.ia .upg-addon-inc{color:#d97706}
.upg-addon.st .upg-addon-inc{color:#2563eb}
.upg-addon-footer{display:flex;align-items:center;justify-content:space-between;padding-top:10px;border-top:1px solid rgba(0,0,0,.06)}
.upg-addon-price{font-size:19px;font-weight:900;line-height:1}
.upg-addon.cat .upg-addon-price{color:#4c1d95}
.upg-addon.ia .upg-addon-price{color:#92400e}
.upg-addon.st .upg-addon-price{color:#1e40af}
.upg-addon-price small{font-size:11px;font-weight:400;color:#9ca3af}
.upg-addon-btn{padding:6px 13px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;border:none;transition:all .15s;display:inline-flex;align-items:center;gap:4px}
.upg-addon.cat .upg-addon-btn{background:#6d28d9;color:#fff}
.upg-addon.cat .upg-addon-btn:hover{background:#4c1d95;transform:translateY(-1px)}
.upg-addon.ia .upg-addon-btn{background:linear-gradient(135deg,#b45309,#d97706);color:#fff}
.upg-addon.ia .upg-addon-btn:hover{transform:translateY(-1px);box-shadow:0 3px 10px rgba(180,83,9,.35)}
.upg-addon.st .upg-addon-btn{background:#2563eb;color:#fff}
.upg-addon.st .upg-addon-btn:hover{background:#1d4ed8;transform:translateY(-1px)}
/* ── Pack Bundle ── */
.upg-pack{border-radius:12px;overflow:hidden;box-shadow:0 8px 32px rgba(79,70,229,.25)}
.upg-pack-hd{background:linear-gradient(135deg,#1e1b4b 0%,#312e81 30%,#4c1d95 60%,#6d28d9 100%);padding:18px 22px;display:flex;align-items:center;justify-content:space-between;position:relative;overflow:hidden}
.upg-pack-hd::before{content:'';position:absolute;top:-40px;right:-20px;width:150px;height:150px;background:radial-gradient(circle,rgba(255,255,255,.1),transparent);border-radius:50%}
.upg-pack-hd::after{content:'';position:absolute;bottom:-30px;left:20%;width:100px;height:100px;background:radial-gradient(circle,rgba(167,139,250,.15),transparent);border-radius:50%}
.upg-pack-hd-l{position:relative;z-index:1}
.upg-pack-tags{display:flex;align-items:center;gap:6px;margin-bottom:6px}
.upg-pack-tag{font-size:9px;font-weight:800;padding:3px 9px;border-radius:20px;letter-spacing:.4px}
.upg-pack-tag.promo{background:linear-gradient(135deg,#f59e0b,#ef4444);color:#fff}
.upg-pack-tag.save{background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.3)}
.upg-pack-title{font-size:15px;font-weight:800;color:#fff;letter-spacing:-.2px}
.upg-pack-sub{font-size:11px;color:rgba(255,255,255,.65);margin-top:3px}
.upg-pack-hd-r{position:relative;z-index:1;text-align:right}
.upg-pack-old{font-size:12px;color:rgba(255,255,255,.45);text-decoration:line-through;margin-bottom:2px}
.upg-pack-price{font-size:30px;font-weight:900;color:#fff;line-height:1}
.upg-pack-price small{font-size:12px;font-weight:400;color:rgba(255,255,255,.7)}
.upg-pack-bd{background:#fff;padding:16px 22px;border:2px solid #ddd6fe;border-top:none;border-radius:0 0 12px 12px}
.upg-pack-items{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px}
.upg-pack-item{display:flex;align-items:center;gap:8px;background:linear-gradient(135deg,#f5f3ff,#ede9fe);border:1px solid #ddd6fe;border-radius:8px;padding:9px 11px}
.upg-pack-item-icon{width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.upg-pack-item-icon.cat{background:linear-gradient(135deg,#6d28d9,#7c3aed);color:#fff}
.upg-pack-item-icon.ia{background:linear-gradient(135deg,#b45309,#f59e0b);color:#fff}
.upg-pack-item-icon.st{background:linear-gradient(135deg,#1d4ed8,#60a5fa);color:#fff}
.upg-pack-item-n{font-size:11px;font-weight:800;color:#4c1d95;line-height:1.2}
.upg-pack-item-v{font-size:10px;color:#7c3aed;font-weight:600}
.upg-pack-cta{width:100%;padding:13px;border:none;border-radius:8px;background:linear-gradient(135deg,#312e81,#4c1d95,#7c3aed);color:#fff;font-size:13px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .18s;box-shadow:0 4px 16px rgba(79,70,229,.4);letter-spacing:.1px}
.upg-pack-cta:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(79,70,229,.5)}
.upg-pack-off{font-size:10px;font-weight:800;background:rgba(255,255,255,.2);padding:2px 8px;border-radius:12px;margin-left:4px;letter-spacing:.3px}
</style>

<div id="mia-root">

  <!-- Ações -->
  <div class="mia-actions">
    <button class="btn btn-secondary btn-sm"><i class="fa fa-download"></i> Exportar</button>
    <button class="btn btn-primary" onclick="novoModelo()">
      <i class="fa fa-plus"></i> Novo Modelo
    </button>
  </div>

  <!-- PLAN USAGE BANNER -->
  <div class="usage-bar">
    <div class="usage-item">
      <span class="usage-label"><i class="fa fa-tag" style="color:#6d28d9"></i> Catálogo</span>
      <div class="usage-track"><div class="usage-fill <?= $_ai_catPct>=80?($_ai_catPct>=95?'crit':'warn'):'' ?>" style="width:<?= $_ai_catPct ?>%"></div></div>
      <span class="usage-num"><?= $_ai_catUsed ?> / <?= $_ai_catLim>0?$_ai_catLim:'∞' ?> modelos</span>
    </div>
    <div class="usage-sep"></div>
    <div class="usage-item">
      <span class="usage-label"><i class="fa fa-bolt" style="color:#d97706"></i> Chamadas IA</span>
      <div class="usage-track"><div class="usage-fill <?= $_ai_callPct>=80?($_ai_callPct>=95?'crit':'warn'):'' ?>" style="width:<?= $_ai_callPct ?>%"></div></div>
      <span class="usage-num"><?= $_ai_callUsed ?> / <?= $_ai_callLim>0?$_ai_callLim.'/ mês':'∞' ?></span>
    </div>
    <div class="usage-sep"></div>
    <div class="usage-item">
      <span class="usage-label"><i class="fa fa-hdd-o" style="color:#2563eb"></i> Storage</span>
      <div class="usage-track"><div class="usage-fill <?= $_ai_storageFillClass ?>" style="width:<?= $_ai_storageUnlimited ? 0 : $_ai_storagePct ?>%"></div></div>
      <span class="usage-num"><?= number_format($_ai_storageMB,2,',','.') ?> MB <?= $_ai_storageUnlimited ? 'usados (sem limite)' : '/ '.number_format($_ai_storageLim,0,',','.').' MB' ?></span>
    </div>
    <button class="btn-upgrade" onclick="abrirUpgrade()"><i class="fa fa-arrow-circle-up"></i> Fazer Upgrade</button>
  </div>

  <!-- INFO BOXES -->
  <div class="ib-grid">
    <div class="ib">
      <div class="ib-icon green"><i class="fa fa-check-circle"></i></div>
      <div class="ib-content">
        <div class="ib-num" id="ib-active-count"><?= $_ai_activeCount ?></div>
        <div class="ib-label">Produtos Ativos</div>
        <div class="ib-sub">visíveis para a IA</div>
      </div>
    </div>
    <div class="ib">
      <div class="ib-icon amber"><i class="fa fa-warning"></i></div>
      <div class="ib-content">
        <div class="ib-num" id="ib-zero-stock"><?= $_ai_zeroStock ?></div>
        <div class="ib-label">Sem Estoque</div>
        <div class="ib-sub">aguardando reposição</div>
      </div>
      <div class="ib-badge warn" id="ib-zero-badge" style="<?= $_ai_zeroStock > 0 ? '' : 'display:none' ?>">Revisar</div>
    </div>
    <div class="ib">
      <div class="ib-icon violet"><i class="fa fa-star"></i></div>
      <div class="ib-content">
        <div class="ib-num" id="ib-inactive-count"><?= $_ai_inactiveCount ?></div>
        <div class="ib-label">Inativos</div>
        <div class="ib-sub">fora do catálogo IA</div>
      </div>
    </div>
    <div class="ib">
      <div class="ib-icon blue"><i class="fa fa-bolt"></i></div>
      <div class="ib-content">
        <div class="ib-num" id="ib-ai-calls"><?= $_ai_callUsed ?></div>
        <div class="ib-label">Chamadas IA</div>
        <div class="ib-sub">no mês atual</div>
      </div>
    </div>
  </div>

  <!-- FILTER BAR -->
  <div class="filter-bar">
    <span class="fb-label"><i class="fa fa-filter"></i> Filtrar</span>
    <div class="fb-chips">
      <div class="fb-chip on" data-filter="todos" onclick="aplicarFiltro(this)">Todos</div>
      <div class="fb-chip" data-filter="ativos" onclick="aplicarFiltro(this)"><i class="fa fa-check-circle" style="color:#059669"></i> Ativos</div>
      <div class="fb-chip" data-filter="inativos" onclick="aplicarFiltro(this)">Inativos</div>
      <div class="fb-chip" data-filter="quentes" onclick="aplicarFiltro(this)">🔥 Mais quentes</div>
      <div class="fb-chip" data-filter="zerados" onclick="aplicarFiltro(this)"><i class="fa fa-exclamation-circle" style="color:#dd4b39"></i> Zerados</div>
    </div>
    <div class="fb-spacer"></div>
    <div style="display:flex;align-items:center;gap:6px">
      <span class="fb-label">Mostrar:</span>
      <select id="mia-limit" class="form-control" style="height:28px;padding:2px 8px;font-size:12px;width:70px;border-radius:2px" onchange="atualizarTabela(true)">
        <option value="20" selected>20</option>
        <option value="50">50</option>
        <option value="100">100</option>
      </select>
    </div>
    <div style="width:1px;height:20px;background:#e5e7eb;margin:0 4px"></div>
    <div class="fb-search">
      <i class="fa fa-search"></i>
      <input type="text" id="mia-search" placeholder="Buscar modelo ou tag..." onkeyup="debounceSearch()">
    </div>
  </div>

  <!-- TABLE -->
  <div class="mia-box">
    <div class="bh">
      <span class="bt"><i class="fa fa-tag"></i> Catálogo de Modelos <span class="count" id="total-count"><?= $_ai_totalModels ?></span></span>
      <div class="bh-actions">
        <button class="btn btn-secondary btn-sm" onclick="exportarCatalogo()"><i class="fa fa-download"></i> Exportar</button>
        <button class="btn btn-secondary btn-sm" onclick="atualizarTabela(true)"><i class="fa fa-refresh"></i> Atualizar</button>
      </div>
    </div>
    <table>
      <thead>
        <tr>
          <th>Foto</th>
          <th class="sort">Produto <i class="fa fa-sort" style="opacity:.4"></i></th>
          <th>Variantes</th>
          <th class="sort" style="color:#6d28d9">Demanda IA <i class="fa fa-sort" style="opacity:.4"></i></th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody id="table-body">
        <?php if (empty($_ai_models)): ?>
        <tr><td colspan="6" style="text-align:center;padding:48px 20px;">
          <i class="fa fa-tag" style="font-size:40px;color:#c4b5fd;display:block;margin-bottom:14px"></i>
          <div style="font-size:15px;font-weight:700;color:#6b7280;margin-bottom:6px">Nenhum produto no Catálogo IA</div>
          <div style="font-size:13px;color:#9ca3af;margin-bottom:18px">Adicione produtos para que a IA possa atender seus clientes.</div>
          <button class="btn btn-primary" onclick="novoModelo()"><i class="fa fa-plus"></i> Adicionar primeiro produto</button>
        </td></tr>
        <?php else: foreach ($_ai_models as $_m):
          $_m_isActive = (int)$_m['is_active'];
          $_m_demand   = (int)$_m['demand_count'];
          $_m_demandPct = min(100, round($_m_demand / $_ai_maxDemand * 100));
          $_m_isHot    = $_m_demand > 0 && $_m_demandPct >= 60;
          $_m_variants = ai_get_catalogo_variants((int)$_m['id']);
          $_m_colors   = array_unique(array_filter(array_column($_m_variants,'color')));
          $_m_sizes    = array_unique(array_filter(array_column($_m_variants,'size')));
          $_m_totalSkus = 1 + count($_m_variants);
          $_m_coverUrl = $_m['cover_webp']
              ? ROOT_URL . 'storage/' . ltrim(str_replace('\\', '/', $_m['cover_webp']),'/')
              : null;
        ?>
        <tr data-id="<?= (int)$_m['id'] ?>">
          <td class="td-foto">
            <div class="thumb">
              <?php if ($_m_coverUrl): ?>
                <img src="<?= htmlspecialchars($_m_coverUrl) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
              <?php else: ?>
                <i class="fa fa-image"></i>
              <?php endif; ?>
            </div>
          </td>
          <td class="td-produto">
            <div class="pname"><?= htmlspecialchars($_m['name']) ?></div>
            <div style="font-size:11px;color:#9ca3af;margin-bottom:6px;"><?= htmlspecialchars($_m['sku'] ?: 'Sem SKU') ?></div>
            <?php $_m_cat_name = $_ai_categoriesById[(int)($_m['category_id'] ?? 0)] ?? ''; ?>
            <span class="pcat"><?= htmlspecialchars($_m_cat_name !== '' ? $_m_cat_name : 'Sem categoria') ?></span>
          </td>
          <td class="td-variantes">
            <div class="swatches">
              <?php 
                $_unique_swatches = [];
                foreach ($_m_variants as $_v) {
                    $_hex = $_v['color_hex'] ?: '#999';
                    if (!isset($_unique_swatches[$_hex])) {
                        $_unique_swatches[$_hex] = ['color' => $_v['color'], 'stock' => 0];
                    }
                    $_unique_swatches[$_hex]['stock'] += (int)$_v['stock_qty'];
                }
                $_swatch_count = 0;
                foreach ($_unique_swatches as $_hex => $_data): 
                  if ($_swatch_count >= 6) break;
                  $_swatch_count++;
                  $_v_isOutOfStock = $_data['stock'] <= 0;
              ?>
                <span class="swatch<?= $_v_isOutOfStock ? ' out-of-stock' : '' ?>" 
                      style="background:<?= htmlspecialchars($_hex) ?>" 
                      title="<?= htmlspecialchars($_data['color'] ?? '') ?><?= $_v_isOutOfStock ? ' (Sem estoque)' : '' ?>"></span>
              <?php endforeach; ?>
            </div>
            <div class="vinfo">
              <?= count($_m_colors) ?> <?= count($_m_colors)==1?'cor':'cores' ?> · 
              <?= count($_m_sizes) ?> <?= count($_m_sizes)==1?'tamanho':'tamanhos' ?> · 
              <?= $_m_totalSkus ?> SKUs
            </div>
          </td>
          <td class="td-demand">
            <div class="demand-num<?= $_m_isHot?' hot':'' ?>"><?= $_m_demand ?></div>
            <div class="demand-sub">Solicitações IA</div>
            <div class="demand-bar"><div class="demand-fill<?= $_m_isHot?' hot':'' ?>" style="width:<?= $_m_demandPct ?>%"></div></div>
          </td>
          <td class="td-status">
            <div style="display:flex;align-items:center;gap:7px">
              <div class="toggle-track<?= $_m_isActive?' on':'' ?>" data-id="<?= (int)$_m['id'] ?>" onclick="toggleStatus(this)"><div class="toggle-thumb"></div></div>
              <span class="status-lbl<?= $_m_isActive?' on':'' ?>"><?= $_m_isActive?'Ativo':'Inativo' ?></span>
            </div>
          </td>
          <td class="td-acoes">
            <button class="btn btn-secondary btn-sm" onclick="editarModelo(<?= (int)$_m['id'] ?>)"><i class="fa fa-pencil"></i> Editar</button>
            <button class="btn btn-danger btn-sm" onclick="deletarModelo(<?= (int)$_m['id'] ?>, '<?= htmlspecialchars(addslashes($_m['name'])) ?>')"><i class="fa fa-trash-o"></i></button>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    <div class="bf">
      <span class="bf-info">Exibindo <?= $_ai_totalModels ?> modelo<?= $_ai_totalModels!=1?'s':'' ?></span>
      <div class="pagination">
        <div class="pg-btn"><i class="fa fa-chevron-left"></i></div>
        <div class="pg-btn act">1</div>
        <div class="pg-btn"><i class="fa fa-chevron-right"></i></div>
      </div>
    </div>
  </div>

  <div id="table-loader" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.6);z-index:9999;align-items:center;justify-content:center;flex-direction:column;gap:15px">
    <i class="fa fa-spinner fa-spin" style="font-size:40px;color:#6d28d9"></i>
    <span style="font-weight:600;color:#374151">Carregando catálogo...</span>
  </div>

</div><!-- /#mia-root -->

  </section>
</div><!-- /content-wrapper -->

<!-- MODAL: NOVO MODELO -->
<div class="mia-overlay hide" id="ov-novo-modelo" data-static="1">
  <div class="mia-modal">
    <div class="mh">
      <div class="mh-info">
        <div class="mt"><i class="fa fa-tag"></i> Novo Modelo</div>
        <div class="ms">Cadastre um produto para o Catálogo IA</div>
      </div>
      <button class="mh-close" onclick="fecharModal('novo-modelo')"><i class="fa fa-times"></i></button>
    </div>
    <div class="mb">
      <div class="stepper">
        <div class="step"><div class="step-indicator active" id="si1">1</div><span class="step-label active">Informações</span><div class="step-line" id="sl1"></div></div>
        <div class="step"><div class="step-indicator pending" id="si2">2</div><span class="step-label pending">Variações</span><div class="step-line" id="sl2"></div></div>
        <div class="step"><div class="step-indicator pending" id="si3">3</div><span class="step-label pending">Confirmação</span></div>
      </div>
      <input type="hidden" id="mia-edit-id" name="id" value="0">
      <div id="step-1">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nome do Modelo <span style="color:#f9264c">*</span></label>
            <input id="mia-nome" name="name" class="form-control" type="text" placeholder="Ex: Vestido Midi Floral">
          </div>
          <div class="form-group">
            <label class="form-label">SKU Base <span style="color:#f9264c">*</span></label>
            <input id="mia-sku-base" name="sku" class="form-control" type="text" placeholder="Ex: VEST-001">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Categoria <span style="color:#f9264c">*</span></label>
          <div style="display:flex;gap:8px">
            <select id="mia-categoria" name="category_id" class="form-control">
              <option value="">Selecione...</option>
              <?php foreach ($_ai_categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-secondary btn-sm" onclick="abrirModalCategorias()" title="Gerenciar Categorias">
              <i class="fa fa-plus"></i>
            </button>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Descrição</label>
          <textarea id="mia-desc" name="description" class="form-control" rows="2" placeholder="Descreva o modelo para ajudar a IA a identificar e recomendar..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Tags <span style="font-size:11px;color:#9ca3af">(Enter para adicionar)</span></label>
          <div class="tags-input" id="tags-input">
            <!-- Chips serão inseridos aqui -->
            <input type="text" id="mia-tag-input" placeholder="Adicionar tag..." onkeydown="addTag(event,this)">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Foto de Capa <span class="webp-badge"><i class="fa fa-magic"></i> WebP Auto</span></label>
          <input type="file" id="mia-foto-input" style="display:none" accept="image/*" onchange="executarUploadCapa(this)">
          <div class="upload-area" id="mia-upload-area" onclick="document.getElementById('mia-foto-input').click()">
            <i class="fa fa-camera"></i>
            <span>Clique para selecionar a foto</span><br>
            <small style="color:#c4b5fd;font-size:11px">Convertida para WebP automaticamente · Máx. 5MB</small>
          </div>
          <div id="mia-capa-preview" style="display:none;margin-top:10px;text-align:center">
             <img src="" style="max-width:100%;max-height:200px;border-radius:4px;border:1px solid #e5e7eb">
             <div style="margin-top:5px"><button type="button" class="btn btn-xs btn-danger" onclick="removerCapa()"><i class="fa fa-trash"></i> Remover</button></div>
          </div>
        </div>
      </div>
      <div id="step-2" style="display:none">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
          <div style="font-size:13px;font-weight:700;color:#374151">Variantes por cor</div>
          <button class="btn btn-primary btn-sm" onclick="novaVariante()"><i class="fa fa-plus"></i> Nova Cor</button>
        </div>
        <div id="lista-variantes">
          <!-- Variantes serão inseridas aqui via JS -->
        </div>
      </div>
      <div id="step-3" style="display:none">
        <div style="text-align:center;padding:20px 0">
          <i class="fa fa-check-circle" style="font-size:48px;color:#059669;margin-bottom:12px;display:block"></i>
          <div style="font-size:18px;font-weight:700;color:#111827">Modelo Pronto!</div>
          <div style="font-size:13px;color:#9ca3af;margin-top:6px">Verifique o resumo abaixo antes de salvar</div>
        </div>
        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:2px;padding:16px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
            <div style="font-weight:700;color:#374151" id="preview-nome">---</div>
            <div style="font-size:11px;font-family:monospace;background:#f3f4f6;padding:2px 6px;border-radius:3px;color:#6b7280" id="preview-sku-base">---</div>
          </div>
          <div style="font-size:12px;color:#6b7280;margin-bottom:12px;line-height:1.4" id="preview-desc">---</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;color:#6b7280">
            <span>Categoria: <b style="color:#374151" id="preview-cat">---</b></span>
            <span>Variantes: <b style="color:#374151" id="preview-vars">0 cores</b></span>
            <span>Tags: <b style="color:#6d28d9" id="preview-tags">---</b></span>
            <span>Total SKUs: <b style="color:#374151" id="preview-skus">0 itens</b></span>
          </div>
        </div>
      </div>
    </div>
    <div class="mf">
      <button class="btn btn-secondary" id="btn-back" onclick="stepBack()" style="display:none"><i class="fa fa-chevron-left"></i> Voltar</button>
      <button class="btn btn-secondary" onclick="fecharModal('novo-modelo')">Cancelar</button>
      <button class="btn btn-primary" id="btn-next" onclick="stepNext()">Próximo <i class="fa fa-chevron-right"></i></button>
    </div>
  </div>
</div>

<!-- MODAL: UPGRADE -->
<div class="upg-ov hide" id="upg-ov">
  <div class="upg-modal">
    <div class="upg-hd">
      <div class="upg-hd-l">
        <div class="upg-hd-icon"><i class="fa fa-rocket"></i></div>
        <div>
          <div class="upg-hd-title"><i class="fa fa-star" style="font-size:14px;margin-right:4px"></i> Fazer Upgrade do Plano</div>
          <div class="upg-hd-sub">Expanda seus limites e use todo o potencial da Moda IA</div>
        </div>
      </div>
      <button class="upg-hd-close" onclick="fecharUpgrade()"><i class="fa fa-times"></i></button>
    </div>
    <div class="upg-body">
      <!-- Uso atual -->
      <div class="upg-usage">
        <div class="upg-usage-hd">
          <div class="upg-usage-title"><i class="fa fa-tachometer"></i> Seu uso atual</div>
          <span class="upg-usage-plan"><?= htmlspecialchars($_ai_plan['name'] ?? 'Plano Atual') ?></span>
        </div>
        <div class="upg-usage-grid">
          <div class="upg-metric">
            <div class="upg-metric-lbl"><i class="fa fa-tag" style="color:#6d28d9"></i> Catálogo</div>
            <div class="upg-metric-nums"><span class="upg-metric-num"><?= $_ai_catUsed ?></span><span class="upg-metric-total">&nbsp;/ <?= $_ai_catLim > 0 ? $_ai_catLim : '∞' ?> SKUs</span></div>
            <div class="upg-track"><div class="upg-fill <?= $_ai_catPct>=90?'high':($_ai_catPct>=70?'med':'low') ?>" id="upg-fill-cat" style="width:0%"></div></div>
            <div class="upg-pct <?= $_ai_catPct>=90?'high':($_ai_catPct>=70?'med':'low') ?>"><?= $_ai_catLim > 0 ? $_ai_catPct.'% utilizado' : 'Sem limite' ?></div>
          </div>
          <div class="upg-metric">
            <div class="upg-metric-lbl"><i class="fa fa-bolt" style="color:#d97706"></i> Chamadas IA</div>
            <div class="upg-metric-nums"><span class="upg-metric-num"><?= $_ai_callUsed ?></span><span class="upg-metric-total">&nbsp;/ <?= $_ai_callLim > 0 ? $_ai_callLim : '∞' ?>/mês</span></div>
            <div class="upg-track"><div class="upg-fill <?= $_ai_callPct>=90?'high':($_ai_callPct>=70?'med':'low') ?>" id="upg-fill-ia" style="width:0%"></div></div>
            <div class="upg-pct <?= $_ai_callPct>=90?'high':($_ai_callPct>=70?'med':'low') ?>"><?= $_ai_callLim > 0 ? ($_ai_callPct.'% utilizado') : 'Sem limite' ?></div>
          </div>
          <div class="upg-metric">
            <div class="upg-metric-lbl"><i class="fa fa-hdd-o" style="color:#2563eb"></i> Storage</div>
            <div class="upg-metric-nums"><span class="upg-metric-num"><?= number_format($_ai_storageMB,2,',','.') ?></span><span class="upg-metric-total">&nbsp;/ <?= $_ai_storageUnlimited ? '∞' : number_format($_ai_storageLim,0,',','.') ?> MB</span></div>
            <div class="upg-track"><div class="upg-fill <?= $_ai_storageUnlimited ? 'low' : ($_ai_storagePct>=90?'high':($_ai_storagePct>=70?'med':'low')) ?>" id="upg-fill-st" style="width:0%"></div></div>
            <div class="upg-pct <?= $_ai_storageUnlimited ? 'low' : ($_ai_storagePct>=90?'high':($_ai_storagePct>=70?'med':'low')) ?>"><?= $_ai_storageUnlimited ? 'Sem limite' : ($_ai_storagePct.'% utilizado') ?></div>
          </div>
        </div>
      </div>
      <!-- Créditos Adicionais -->
      <div class="upg-addons">
        <div class="upg-addons-hd">
          <div class="upg-addons-title"><i class="fa fa-plus-circle" style="color:#6d28d9"></i> Adicione créditos ao seu plano atual</div>
          <span class="upg-addons-hint">Sem mudar de plano</span>
        </div>
        <div class="upg-addon-grid">

          <!-- Catálogo -->
          <div class="upg-addon cat">
            <div class="upg-addon-top-badge"><i class="fa fa-tag"></i>&nbsp; +Catálogo</div>
            <div class="upg-addon-body">
              <div class="upg-addon-iw"><i class="fa fa-tag"></i></div>
              <div class="upg-addon-num">+50</div>
              <div class="upg-addon-sub">SKUs no Catálogo IA</div>
              <div class="upg-addon-desc">Cadastre mais variações e deixe a IA recomendar para mais clientes automaticamente, 24h por dia.</div>
              <div class="upg-addon-inc"><i class="fa fa-check-circle"></i> 50 novos SKUs ativos</div>
              <div class="upg-addon-footer">
                <div class="upg-addon-price">R$ 29<small>/mês</small></div>
                <button class="upg-addon-btn" onclick="comprarCredito('Catálogo +50 SKUs', 29)"><i class="fa fa-plus"></i> Adicionar</button>
              </div>
            </div>
          </div>

          <!-- Chamadas IA -->
          <div class="upg-addon ia">
            <div class="upg-addon-top-badge"><i class="fa fa-exclamation-triangle"></i>&nbsp; Quase no Limite!</div>
            <div class="upg-addon-body">
              <div class="upg-addon-iw"><i class="fa fa-bolt"></i></div>
              <div class="upg-addon-num">+1.000</div>
              <div class="upg-addon-sub">Chamadas de Atendimento IA</div>
              <div class="upg-addon-desc">Não perca vendas por falta de créditos. Mais conversas ativas significam mais conversões e receita.</div>
              <div class="upg-addon-inc"><i class="fa fa-check-circle"></i> +1.000 atendimentos/mês</div>
              <div class="upg-addon-footer">
                <div class="upg-addon-price">R$ 39<small>/mês</small></div>
                <button class="upg-addon-btn" onclick="comprarCredito('Chamadas IA +1.000', 39)"><i class="fa fa-plus"></i> Adicionar</button>
              </div>
            </div>
          </div>

          <!-- Storage -->
          <div class="upg-addon st">
            <div class="upg-addon-top-badge"><i class="fa fa-hdd-o"></i>&nbsp; +Storage</div>
            <div class="upg-addon-body">
              <div class="upg-addon-iw"><i class="fa fa-hdd-o"></i></div>
              <div class="upg-addon-num">+50 MB</div>
              <div class="upg-addon-sub">de Armazenamento</div>
              <div class="upg-addon-desc">Fotos de alta qualidade sem compressão. Catálogo mais rico e profissional para impressionar seus clientes.</div>
              <div class="upg-addon-inc"><i class="fa fa-check-circle"></i> +50 MB de armazenamento</div>
              <div class="upg-addon-footer">
                <div class="upg-addon-price">R$ 19<small>/mês</small></div>
                <button class="upg-addon-btn" onclick="comprarCredito('Storage +50 MB', 19)"><i class="fa fa-plus"></i> Adicionar</button>
              </div>
            </div>
          </div>

        </div>

        <!-- Pack Completo -->
        <div class="upg-pack">
          <div class="upg-pack-hd">
            <div class="upg-pack-hd-l">
              <div class="upg-pack-tags">
                <span class="upg-pack-tag promo">&#127881; PACK COMPLETO</span>
                <span class="upg-pack-tag save">ECONOMIZE R$ 20/mês</span>
              </div>
              <div class="upg-pack-title">Os 3 créditos juntos com desconto</div>
              <div class="upg-pack-sub">Catálogo &middot; Chamadas IA &middot; Storage &mdash; tudo em uma única compra</div>
            </div>
            <div class="upg-pack-hd-r">
              <div class="upg-pack-old">de R$ 87/mês</div>
              <div class="upg-pack-price">R$ 67<small>/mês</small></div>
            </div>
          </div>
          <div class="upg-pack-bd">
            <div class="upg-pack-items">
              <div class="upg-pack-item">
                <div class="upg-pack-item-icon cat"><i class="fa fa-tag"></i></div>
                <div>
                  <div class="upg-pack-item-n">+50 Modelos</div>
                  <div class="upg-pack-item-v">no Catálogo IA</div>
                </div>
              </div>
              <div class="upg-pack-item">
                <div class="upg-pack-item-icon ia"><i class="fa fa-bolt"></i></div>
                <div>
                  <div class="upg-pack-item-n">+1.000 Chamadas</div>
                  <div class="upg-pack-item-v">Atendimento IA</div>
                </div>
              </div>
              <div class="upg-pack-item">
                <div class="upg-pack-item-icon st"><i class="fa fa-hdd-o"></i></div>
                <div>
                  <div class="upg-pack-item-n">+50 MB</div>
                  <div class="upg-pack-item-v">de Storage</div>
                </div>
              </div>
            </div>
            <button class="upg-pack-cta" onclick="comprarCredito('Pack Completo', 67)">
              <i class="fa fa-rocket"></i>
              Contratar Pack Completo &mdash; R$ 67/mês
              <span class="upg-pack-off">23% OFF</span>
            </button>
          </div>
        </div>

      </div>
    </div>
    <div class="upg-ft">
      <div class="upg-ft-text">Dúvidas sobre os planos?<br><strong>Nossa equipe responde em minutos via WhatsApp</strong></div>
      <button class="upg-wa" onclick="abrirWhatsAppUpgrade()"><i class="fa fa-whatsapp"></i> Falar no WhatsApp</button>
    </div>
  </div>
</div>

<!-- MODAL: CONFIRMAR EXCLUSÃO -->
<div class="mia-overlay hide" id="ov-confirm-del" style="z-index:1200">
  <div class="mia-modal" style="width:380px;border-radius:12px;overflow:hidden">
    <div style="padding:30px 24px;text-align:center">
      <div style="width:64px;height:64px;background:#fee2e2;color:#dc2626;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 18px">
        <i class="fa fa-trash-o"></i>
      </div>
      <div style="font-size:18px;font-weight:800;color:#111827;margin-bottom:8px">Excluir Modelo?</div>
      <div style="font-size:14px;color:#6b7280;line-height:1.5" id="del-msg">Você está prestes a excluir este modelo e todas as suas variantes. Esta ação não pode ser desfeita.</div>
    </div>
    <div style="background:#f9fafb;padding:16px 24px;display:grid;grid-template-columns:1fr 1fr;gap:12px;border-top:1px solid #f3f4f6">
      <button class="btn btn-secondary" style="justify-content:center" onclick="fecharModal('confirm-del')">Cancelar</button>
      <button class="btn btn-danger" style="justify-content:center;background:#dc2626!important;color:#fff!important" id="btn-confirm-del">Excluir Agora</button>
    </div>
  </div>
</div>

<div class="mia-toast" id="toast"><i class="fa fa-magic"></i> <span id="toast-msg">Operação concluída</span></div>

<script>
const _aiCatUsed = <?= (int)$_ai_catUsed ?>;
const _aiCatLimit = <?= (int)$_ai_catLim ?>;

function novoModelo() {
  // Verificar limite de SKUs (se houver limite definido e já tiver atingido)
  if (_aiCatLimit > 0 && _aiCatUsed >= _aiCatLimit) {
    abrirUpgrade(); // Abre a modal de upgrade diretamente
    return;
  }

  // Limpar ID de edição primeiro para garantir que o abrirModal entenda como Novo Produto
  const editIdInput = document.getElementById('mia-edit-id');
  if (editIdInput) editIdInput.value = '0';
  
  // Abrir a modal
  abrirModal('novo-modelo');
}

function abrirModal(id){
  const ov = document.getElementById('ov-'+id);
  if (!ov) return;

  if(id==='novo-modelo'){
    const select = document.getElementById('mia-categoria');
    // Sempre tentar carregar categorias se o select estiver vazio ou quase vazio
    if(select && select.options.length <= 1) {
      fetch('../_inc/ai_catalogo_categoria.php?action=listar')
        .then(r=>r.json())
        .then(d=>{ 
          if(d.categories) {
            atualizarSelectCategorias(d.categories);
            // Re-sincronizar Select2 após carregar
            if (typeof jQuery !== 'undefined' && jQuery(select).data('select2')) {
                jQuery(select).trigger('change');
            }
          }
        })
        .catch(err => console.error('Moda IA: erro ao carregar categorias:', err));
    }

    // Reset form (somente se não for edição)
    const editId = document.getElementById('mia-edit-id').value;
    if (!editId || editId === '0') {
      document.getElementById('mia-nome').value = '';
      document.getElementById('mia-sku-base').value = generateBaseSKU();
      document.getElementById('mia-categoria').value = '';
      document.getElementById('mia-desc').value = '';
      document.querySelectorAll('#tags-input .tag-chip').forEach(c => c.remove());
      document.getElementById('lista-variantes').innerHTML = '';
      removerCapa();
      step = 1;
      document.getElementById('step-1').style.display = 'block';
      document.getElementById('step-2').style.display = 'none';
      document.getElementById('step-3').style.display = 'none';
      document.getElementById('si1').textContent = '1';
      document.getElementById('si1').className = 'step-indicator active';
      document.getElementById('sl1').className = 'step-line';
      document.getElementById('si2').textContent = '2';
      document.getElementById('si2').className = 'step-indicator pending';
      document.getElementById('sl2').className = 'step-line';
      document.getElementById('si3').textContent = '3';
      document.getElementById('si3').className = 'step-indicator pending';
      document.getElementById('btn-back').style.display = 'none';
      document.getElementById('btn-next').innerHTML = 'Próximo <i class="fa fa-chevron-right"></i>';
    }
  }
  
  ov.classList.remove('hide');

  // Forçar atualização do Select2 após a modal estar visível
  if(id==='novo-modelo'){
    setTimeout(() => {
      const select = document.getElementById('mia-categoria');
      if (select && typeof jQuery !== 'undefined') {
        const $select = jQuery(select);
        if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
        $select.select2({ 
          width: '100%', 
          dropdownParent: jQuery('#ov-novo-modelo'),
          placeholder: 'Selecione uma categoria...'
        });
      }
    }, 50);
  }
}

function getColorIcon(val) {
  const map = {
    // Cores Sólidas (Usando emojis de círculos coloridos para representar o circle-fill do Bootstrap)
    'azul': '🔵', 'rosa': '🔴', 'vermelho': '🔴', 'verde': '🟢', 'amarelo': '🟡', 'laranja': '🟠',
    'branco': '⚪', 'preto': '⚫', 'cinza': '⚪', 'bege': '🟡', 'marrom': '🟤', 'vinho': '🔴',
    'navy': '🔵', 'oliva': '🟢', 'roxo': '🟣', 'pink': '🔴', 'dourado': '🟡', 'prata': '⚪',
    
    // Estampas e Padrões (Mantém os emojis originais conforme solicitado)
    'floral': '💐', 'estampado': '🎨', 'listrado': '📏', 'animal': '🐆', 'xadrez': '🏁', 'tie dye': '🌈'
  };
  return map[val] || '⚪';
}

function carregarVariantes(modelId){
  fetch('../_inc/ai_catalogo_variante.php?action=listar&model_id='+modelId)
    .then(r=>r.json()).then(d=>{
      const lista = document.getElementById('lista-variantes');
      lista.innerHTML = '';
      if(d.variantes && d.variantes.length > 0){
        d.variantes.forEach(v => {
          lista.appendChild(renderVariantCard(v));
        });
      } else {
        lista.innerHTML = `
          <div class="empty-variants">
            <i class="fa fa-cubes"></i>
            <div>Nenhuma variante cadastrada</div>
            <p>Adicione cores e tamanhos para este modelo clicando em "Nova Cor".</p>
          </div>
        `;
      }
    });
}

function generateBaseSKU() {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  let prefix = '';
  for (let i = 0; i < 3; i++) {
    prefix += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  const num = Math.floor(100 + Math.random() * 899); // 100-999
  return `${prefix}-${num}`;
}
function isEditingModelo() {
  const editId = (document.getElementById('mia-edit-id')?.value || '0').trim();
  return editId !== '' && editId !== '0';
}

function getVariantDisplayColor(sel) {
  if (!sel) return '';
  const opt = sel.options[sel.selectedIndex];
  if (!opt) return '';
  return opt.text.replace(/^[^\s]+\s/, '').trim();
}

function onVariantColorChange(sel) {
  const card = sel.closest('.variant-card');
  if (!card) return;
  const display = getVariantDisplayColor(sel) || 'Nova Cor';
  const hidden = card.querySelector('.v-color');
  if (hidden) hidden.value = display;
  const name = card.querySelector('.vc-name');
  if (name) name.textContent = display;
}

function validarVariantes(cards) {
  for (let i = 0; i < cards.length; i++) {
    const card = cards[i];
    const idx = i + 1;
    const colorNorm = (card.querySelector('.v-color-norm')?.value || '').trim();
    const sku = (card.querySelector('.v-sku')?.value || '').trim();
    const priceRaw = (card.querySelector('.v-price')?.value || '').replace(',', '.').trim();
    const price = Number(priceRaw);

    if (!colorNorm) {
      showToast(`❌ Selecione a cor da variante ${idx}.`);
      return false;
    }
    if (!sku) {
      showToast(`❌ Informe o SKU da variante ${idx}.`);
      return false;
    }
    if (priceRaw === '' || Number.isNaN(price) || price < 0) {
      showToast(`❌ Informe um preço válido na variante ${idx}.`);
      return false;
    }
  }
  return true;
}

function persistirNovoModeloComVariantes() {
  const btn = document.getElementById('btn-next');
  const oldHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Finalizando...';

  const nome = (document.getElementById('mia-nome')?.value || '').trim();
  const skuBase = (document.getElementById('mia-sku-base')?.value || '').trim();
  const desc = document.getElementById('mia-desc')?.value || '';
  const catId = document.getElementById('mia-categoria')?.value || '';
  const tagsArr = Array.from(document.querySelectorAll('#tags-input .tag-chip')).map(c => c.textContent.replace('×','').trim()).filter(Boolean);
  const cards = Array.from(document.querySelectorAll('.variant-card'));

  if (!validarVariantes(cards)) {
    btn.disabled = false;
    btn.innerHTML = oldHtml;
    return;
  }

  const formData = new FormData();
  formData.append('action', 'salvar');
  formData.append('id', '0');
  formData.append('name', nome);
  formData.append('sku', skuBase);
  formData.append('category_id', catId);
  formData.append('description', desc);
  formData.append('tags', tagsArr.join(','));

  const fotoInput = document.getElementById('mia-foto-input');
  if (fotoInput && fotoInput.files[0]) {
    formData.append('foto', fotoInput.files[0]);
  }

  fetch('../_inc/ai_catalogo_salvar.php', {
    method: 'POST',
    body: formData
  })
  .then(r => {
    if (!r.ok) {
      return r.json().then(json => { throw new Error(json.errorMsg || 'Erro ao salvar produto.'); });
    }
    return r.json();
  })
  .then(d => {
    if (d.errorMsg) throw new Error(d.errorMsg);
    const newModelId = (d.id || 0).toString();
    if (!newModelId || newModelId === '0') {
      throw new Error('Não foi possível obter o ID do novo produto.');
    }
    const previousId = document.getElementById('mia-edit-id').value;
    document.getElementById('mia-edit-id').value = newModelId;

    return Promise.all(cards.map(card => executarSalvarVariante(card))).then(() => {
      document.getElementById('mia-edit-id').value = previousId;
    }).catch((err) => {
      document.getElementById('mia-edit-id').value = previousId;
      throw err;
    });
  })
  .then(() => {
    showToast('✔ Produto criado com sucesso após confirmação da etapa 3.');
    fecharModal('novo-modelo');
    atualizarTabela(true);
  })
  .catch(err => {
    console.error('Moda IA: erro ao concluir novo produto:', err);
    showToast('❌ ' + (err?.message || 'Erro ao concluir cadastro.'));
  })
  .finally(() => {
    btn.disabled = false;
    btn.innerHTML = oldHtml;
  });
}

function novaVariante(){
  const modelId = document.getElementById('mia-edit-id').value || '0';
  const skuBase = document.getElementById('mia-sku-base').value || generateBaseSKU();
  const firstPrice = document.querySelector('.variant-card .v-price');
  const mainPrice = firstPrice ? (firstPrice.value || '0.00') : '0.00';
  const existingVariants = document.querySelectorAll('.variant-card').length;
  const newSku = `${skuBase}-${existingVariants + 1}`;
  
  const v = { id: 0, model_id: modelId, color: 'Nova Cor', color_hex: '#cccccc', size: '', price: mainPrice, stock_qty: 0, sku: newSku };
  // Se houver a mensagem de "vazio", remove ela
  const empty = document.querySelector('.empty-variants');
  if(empty) empty.remove();
  
  const card = renderVariantCard(v);
  document.getElementById('lista-variantes').appendChild(card);

  // Expandir automaticamente
  const body = card.querySelector('.variant-body');
  body.classList.add('open');
  body.style.display = 'block';
}

function onVariantColorChange(sel) {
  const card = sel.closest('.variant-card');
  const colorInput = card.querySelector('.v-color');
  const selectedOption = sel.options[sel.selectedIndex];
  
  if (selectedOption && selectedOption.value) {
    const colorName = selectedOption.text.replace(/^[^\s]+\s/, '');
    colorInput.value = colorName;
    const headerName = card.querySelector('.vc-name');
    if (headerName) headerName.textContent = colorName;
  }
}

function renderVariantCard(v){
  const div = document.createElement('div');
  div.className = 'variant-card';
  div.dataset.id = v.id;
  const photoUrl = v.photo_webp ? '../storage/' + v.photo_webp : '';
  
  div.innerHTML = `
    <div class="variant-header" onclick="toggleVariant(this)">
      <span class="vc-swatch" style="background:${v.color_hex || '#ccc'}"></span>
      <span class="vc-name">${v.color || 'Nova Cor'}</span>
      <div class="vc-badge">
        ${v.size ? '<span>'+v.size+'</span>' : ''}
        ${v.price ? '<b>R$ '+v.price+'</b>' : ''}
      </div>
      <i class="fa fa-angle-down vc-toggle"></i>
    </div>
    <div class="variant-body">
      <div style="display:flex;gap:16px;margin-bottom:12px">
        <div style="flex:none">
          <label class="form-label">Foto da Cor</label>
          <div class="variant-photo-wrapper" onclick="clicarUploadVariante(this)">
            ${photoUrl ? '<img src="'+photoUrl+'">' : '<i class="fa fa-camera"></i>'}
            <div class="photo-hint">Trocar Foto</div>
            <input type="file" class="v-foto-input" style="display:none" accept="image/*" onchange="executarUploadVariante(this)">
          </div>
        </div>
        <div style="flex:1">
          <div class="form-row" style="margin-bottom:12px">
            <div class="form-group">
                <label class="form-label">Nome da Cor (IA)</label>
                <select class="form-control v-color-norm" onchange="onVariantColorChange(this)">
                    <option value="">-- Selecionar --</option>
                    <optgroup label="🎨 Cores Sólidas">
                    ${Object.entries({
                        'azul':'Azul','rosa':'Rosa','vermelho':'Vermelho',
                        'verde':'Verde','amarelo':'Amarelo','laranja':'Laranja',
                        'branco':'Branco / Off White','preto':'Preto',
                        'cinza':'Cinza / Mescla','bege':'Bege / Nude',
                        'marrom':'Marrom','vinho':'Vinho / Bordô',
                        'navy':'Navy / Marinho','oliva':'Oliva / Militar',
                        'roxo':'Roxo / Lilás','pink':'Pink / Fúcsia',
                        'dourado':'Dourado','prata':'Prata'
                    }).map(([val, label]) => {
                        const icon = getColorIcon(val);
                        return `<option value="${val}" ${v.color_normalized === val ? 'selected' : ''}>${icon} ${label}</option>`;
                    }).join('')}
                    </optgroup>
                    <optgroup label="✨ Estampas e Padrões">
                    ${Object.entries({
                        'floral':'Floral','estampado':'Estampado',
                        'listrado':'Listrado','animal':'Animal Print','xadrez':'Xadrez','tie dye':'Tie Dye'
                    }).map(([val, label]) => {
                        const icon = getColorIcon(val);
                        return `<option value="${val}" ${v.color_normalized === val ? 'selected' : ''}>${icon} ${label}</option>`;
                    }).join('')}
                    </optgroup>
                </select>
                <input class="v-color" type="hidden" value="${v.color || ''}">
            </div>
            <div class="form-group"><label class="form-label">Tamanho</label><input class="form-control v-size" type="text" value="${v.size || ''}" placeholder="Ex: P, M, G, 42"></div>
          </div>
          <div class="form-row" style="margin-bottom:12px">
            <div class="form-group"><label class="form-label">Cor Visual (Hex)</label><input class="form-control v-hex" type="color" value="${v.color_hex || '#cccccc'}" style="height:38px;padding:2px"></div>
            <div class="form-group"><label class="form-label">Preço de Venda <span style="color:#f9264c">*</span></label><input class="form-control v-price" type="text" value="${v.price || '0.00'}"></div>
          </div>
          <div class="form-row" style="margin-bottom:12px">
            <div class="form-group"><label class="form-label">Estoque Atual</label><input class="form-control v-stock" type="number" value="${v.stock_qty || 0}"></div>
            <div class="form-group"><label class="form-label">Código SKU <span style="color:#f9264c">*</span></label><input class="form-control v-sku" type="text" value="${v.sku || ''}" placeholder="Ex: VEST-001-AZ-P"></div>
          </div>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:10px;border-top:1px solid #f3f4f6">
        <button class="btn btn-danger btn-sm" onclick="deletarVariante(${v.id}, this)"><i class="fa fa-trash"></i> Excluir</button>
        <button class="btn btn-primary btn-sm" onclick="salvarVariante(this)"><i class="fa fa-save"></i> Salvar Alterações</button>
      </div>
    </div>
  `;
  const sel = div.querySelector('.v-color-norm');
  if (sel) onVariantColorChange(sel);
  return div;
}

function salvarVariante(btn){
  const card = btn.closest('.variant-card');
  return executarSalvarVariante(card).then(() => {
    toggleVariant(card.querySelector('.variant-header'));
  });
}

function executarSalvarVariante(card) {
  const modelId = document.getElementById('mia-edit-id').value;
  const id = card.dataset.id;
  
  if (!modelId || modelId === '0') {
    showToast('❌ Salve o modelo primeiro antes de salvar as variantes.');
    return Promise.reject('model_id ausente');
  }

  const sku = card.querySelector('.v-sku').value.trim();
  if(!sku){
    showToast('❌ O SKU da variante é obrigatório.');
    return Promise.reject('SKU obrigatório');
  }
  
  const data = new URLSearchParams();
  data.append('action', 'salvar');
  data.append('id', id);
  data.append('model_id', modelId);
  
  const colorNorm = card.querySelector('.v-color-norm').value;
  const colorName = card.querySelector('.v-color-norm option:checked').text.replace(/^[^\s]+\s/, ''); // Remove o emoji
  
  data.append('color', colorName);
  data.append('color_normalized', colorNorm);
  data.append('color_hex', card.querySelector('.v-hex').value);
  data.append('size', card.querySelector('.v-size').value);
  data.append('price', card.querySelector('.v-price').value);
  data.append('stock_qty', card.querySelector('.v-stock').value);
  data.append('sku', sku);

  return fetch('../_inc/ai_catalogo_variante.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: data.toString()
  })
  .then(r=>r.json()).then(d=>{
    if(d.errorMsg){
      showToast('❌ '+d.errorMsg);
      throw new Error(d.errorMsg);
    }
    showToast('✔ '+d.msg);
    card.dataset.id = d.id;
    // Atualizar header
    card.querySelector('.vc-name').textContent = card.querySelector('.v-color').value;
    card.querySelector('.vc-swatch').style.background = card.querySelector('.v-hex').value;
    
    // Atualizar Badge Bonito
    const badge = card.querySelector('.vc-badge');
    const size = card.querySelector('.v-size').value;
    const price = card.querySelector('.v-price').value;
    badge.innerHTML = (size ? '<span>'+size+'</span>' : '') + (price ? '<b>R$ '+price+'</b>' : '');
    
    return d;
  });
}

// ───── UPLOAD DE FOTOS ─────
function executarUploadCapa(input) {
  if (!input.files || !input.files[0]) return;
  
  // Mostrar preview local imediatamente
  const reader = new FileReader();
  reader.onload = function(e) {
    const area = document.getElementById('mia-upload-area');
    const preview = document.getElementById('mia-capa-preview');
    area.style.display = 'none';
    preview.style.display = 'block';
    preview.querySelector('img').src = e.target.result;
  }
  reader.readAsDataURL(input.files[0]);
  
  const modelId = document.getElementById('mia-edit-id').value;
  // Se já tiver ID, envia logo. Se não, o stepNext vai enviar depois.
  if (modelId && modelId !== '0') {
    const formData = new FormData();
    formData.append('type', 'capa');
    formData.append('model_id', modelId);
    formData.append('foto', input.files[0]);

    showToast('⌛ Atualizando foto...');
    fetch('../_inc/ai_catalogo_foto.php', {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(d => {
      if (d.errorMsg) { showToast('❌ ' + d.errorMsg); return; }
      showToast('✔ Foto de capa atualizada.');
      // Atualizar com a URL final do servidor
      document.getElementById('mia-capa-preview').querySelector('img').src = d.url + '?t=' + Date.now();
    })
    .catch(() => showToast('❌ Erro no upload.'));
  }
}

function removerCapa() {
  document.getElementById('mia-capa-preview').style.display = 'none';
  document.getElementById('mia-upload-area').style.display = 'block';
  document.getElementById('mia-foto-input').value = '';
}

function clicarUploadVariante(wrapper) {
  wrapper.querySelector('.v-foto-input').click();
}

function executarUploadVariante(input) {
  if (!input.files || !input.files[0]) return;
  const card = input.closest('.variant-card');
  const variantId = card.dataset.id;
  const modelId = document.getElementById('mia-edit-id').value;

  if (!variantId || variantId === '0') {
    showToast('❌ Salve a variante primeiro clicando no botão "Salvar Alterações" para poder adicionar a foto.');
    input.value = '';
    return;
  }

  const formData = new FormData();
  formData.append('type', 'variante');
  formData.append('model_id', modelId);
  formData.append('variant_id', variantId);
  formData.append('foto', input.files[0]);

  showToast('⌛ Enviando foto da variante...');
  fetch('../_inc/ai_catalogo_foto.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(d => {
    if (d.errorMsg) { showToast('❌ ' + d.errorMsg); return; }
    showToast('✔ Foto da variante atualizada.');
    const wrapper = input.closest('.variant-photo-wrapper');
    wrapper.innerHTML = `<img src="${d.url}?t=${Date.now()}"><div class="photo-hint">Trocar Foto</div><input type="file" class="v-foto-input" style="display:none" accept="image/*" onchange="executarUploadVariante(this)">`;
  })
  .catch(() => showToast('❌ Erro no upload.'));
}

function deletarVariante(id, btn){
  if(id === 0 || id === '0'){
    btn.closest('.variant-card').remove();
    return;
  }
  if(!confirm('Excluir esta variante?')) return;
  
  fetch('../_inc/ai_catalogo_variante.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=deletar&id='+id
  })
  .then(r=>r.json()).then(d=>{
    if(d.errorMsg){showToast('❌ '+d.errorMsg);return;}
    showToast('✔ '+d.msg);
    btn.closest('.variant-card').remove();
  });
}

function abrirModalCategorias(){
  // Garantir que o overlay de categorias tenha um z-index maior que o de novo-modelo
  const ov = document.getElementById('ov-categorias');
  if (ov) {
    ov.style.zIndex = '1100';
    ov.classList.remove('hide');
    listarCategorias();
  } else {
    console.error("Moda IA Error: Modal de categorias não encontrada.");
  }
}

function listarCategorias(){
  fetch('../_inc/ai_catalogo_categoria.php?action=listar')
    .then(r=>r.json()).then(d=>{
      const lista = document.getElementById('lista-categorias-modal');
      lista.innerHTML = '';
      if(d.categories && d.categories.length > 0){
        d.categories.forEach(c => {
          // Na listagem de gerenciamento (segunda modal), OCULTAMOS as globais 
          // para evitar que o usuário tente excluir categorias do sistema.
          if (c.is_global) return;

          const item = document.createElement('div');
          item.style = 'display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:4px';
          item.innerHTML = `
            <span style="font-size:13px;font-weight:600;color:#374151">${c.name}</span>
            <button class="btn btn-danger btn-sm" onclick="deletarCategoria(${c.id})" style="padding:2px 6px!important">
              <i class="fa fa-trash-o"></i>
            </button>
          `;
          lista.appendChild(item);
        });
        atualizarSelectCategorias(d.categories);
      } else {
        lista.innerHTML = '<div style="text-align:center;padding:20px;color:#9ca3af;font-size:12px">Nenhuma categoria personalizada.</div>';
      }
    });
}

function salvarCategoria(){
  const nome = document.getElementById('nova-cat-nome').value.trim();
  if(!nome){
    showToast('❌ Informe o nome da categoria.');
    return;
  }
    fetch('../_inc/ai_catalogo_categoria.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=salvar&name='+encodeURIComponent(nome)
  })
  .then(r => {
    if (!r.ok) return r.text().then(t => { throw new Error(t); });
    return r.json();
  })
  .then(d=>{
    if(d.errorMsg){showToast('❌ '+d.errorMsg);return;}
    showToast('✔ '+d.msg);
    document.getElementById('nova-cat-nome').value = '';
    listarCategorias();
  }).catch(e => {
    console.error('Moda IA: erro ao salvar categoria:', e);
    showToast('❌ Erro ao salvar categoria.');
  });
}

function deletarCategoria(id){
  if(!confirm('Excluir esta categoria? Isso não removerá os produtos vinculados, mas eles perderão a tag de categoria no filtro.')) return;
  fetch('../_inc/ai_catalogo_categoria.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=deletar&id='+id
  })
  .then(r=>r.json()).then(d=>{
    if(d.errorMsg){showToast('❌ '+d.errorMsg);return;}
    showToast('✔ '+d.msg);
    listarCategorias();
  });
}

function atualizarSelectCategorias(categories){
  const select = document.getElementById('mia-categoria');
  if (!select) return;
  
  const valorAtual = select.value;
  select.innerHTML = '<option value="">Selecione...</option>';

  (categories || []).forEach(c => {
    const opt = document.createElement('option');
    opt.value = String(c.id);
    opt.textContent = c.name;
    if(String(c.id) === String(valorAtual)) opt.selected = true;
    select.appendChild(opt);
  });

  // Compatibilidade com Select2 (ModernPOS)
  if (typeof jQuery !== 'undefined') {
    const $select = jQuery(select);
    if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
    $select.select2({ width: '100%', dropdownParent: jQuery('#ov-novo-modelo') });
  }
}
function fecharModal(id){
  const el = document.getElementById('ov-'+id);
  if (el) {
    el.classList.add('hide');
    // Resetar o ID de edição e voltar para o passo 1 ao fechar o modal de novo-modelo
    if (id === 'novo-modelo') {
      document.getElementById('mia-edit-id').value = '0';
      step = 1;
      document.getElementById('step-1').style.display = 'block';
      document.getElementById('step-2').style.display = 'none';
      document.getElementById('step-3').style.display = 'none';
      document.getElementById('si1').textContent = '1';
      document.getElementById('si1').className = 'step-indicator active';
      document.getElementById('sl1').className = 'step-line';
      document.getElementById('si2').textContent = '2';
      document.getElementById('si2').className = 'step-indicator pending';
      document.getElementById('sl2').className = 'step-line';
      document.getElementById('si3').textContent = '3';
      document.getElementById('si3').className = 'step-indicator pending';
      document.getElementById('btn-back').style.display = 'none';
      document.getElementById('btn-next').innerHTML = 'Próximo <i class="fa fa-chevron-right"></i>';
    }
  } else {
    const fallback = document.getElementById(id);
    if (fallback) fallback.classList.add('hide');
  }
}
document.querySelectorAll('.mia-overlay').forEach(o => o.addEventListener('click', function (e) {
  if (e.target !== this) return;
  if (this.getAttribute('data-static') === '1') return;
  this.classList.add('hide');
}));
let _tt;
function showToast(msg){const t=document.getElementById('toast');document.getElementById('toast-msg').textContent=msg;t.classList.add('show');clearTimeout(_tt);_tt=setTimeout(()=>t.classList.remove('show'),2800)}

// ───── FILTROS E PESQUISA ─────
let _filtroAtual = 'todos';
let _searchTimer = null;
let _paginaAtual = 1;

function aplicarFiltro(el) {
  document.querySelectorAll('.fb-chip').forEach(c => c.classList.remove('on'));
  el.classList.add('on');
  _filtroAtual = el.dataset.filter;
  _paginaAtual = 1;
  atualizarTabela(true);
}

function debounceSearch() {
  _paginaAtual = 1;
  clearTimeout(_searchTimer);
  _searchTimer = setTimeout(() => atualizarTabela(true), 200);
}

function mudarPagina(p) {
  _paginaAtual = p;
  atualizarTabela(true);
}

function atualizarTabela(isSearch = false) {
  const query = document.getElementById('mia-search').value;
  const limit = document.getElementById('mia-limit')?.value || 20;
  const tbody = document.getElementById('table-body');
  const loader = document.getElementById('table-loader');
  
  if(loader && !isSearch) loader.style.display = 'flex';
  tbody.style.opacity = isSearch ? '0.7' : '0.5';
  tbody.style.pointerEvents = 'none';

  const params = new URLSearchParams();
  params.append('filter', _filtroAtual);
  params.append('search', query);
  params.append('limit', limit);
  params.append('page', _paginaAtual);
  params.append('_t', Date.now().toString());

  fetch('../_inc/ai_catalogo_listar.php?' + params.toString())
    .then(r => {
      if (!r.ok) return r.json().then(err => { throw new Error(err.errorMsg || 'Erro desconhecido'); });
      return r.json();
    })
    .then(d => {
      tbody.innerHTML = d.html || '';
      const totalCount = document.getElementById('total-count');
      if(totalCount) totalCount.textContent = d.total_filtered || 0;
      const bfInfo = document.querySelector('.bf-info');
      
      const total = d.total_filtered || 0;
      const currentPage = d.page || 1;
      const limitVal = d.limit || 20;
      const start = total === 0 ? 0 : (currentPage - 1) * limitVal + 1;
      const end = Math.min(currentPage * limitVal, total);

      if(bfInfo) bfInfo.textContent = `Exibindo ${start}-${end} de ${total} modelo${total != 1 ? 's' : ''}`;

      renderPagination(total, limitVal, currentPage);

      // Atualizar info-boxes dinamicamente
      const ibActive   = document.getElementById('ib-active-count');
      const ibZero     = document.getElementById('ib-zero-stock');
      const ibInactive = document.getElementById('ib-inactive-count');
      const ibCalls    = document.getElementById('ib-ai-calls');
      const ibBadge    = document.getElementById('ib-zero-badge');
      if(ibActive   !== null && d.active_count   !== undefined) ibActive.textContent   = d.active_count;
      if(ibInactive !== null && d.inactive_count !== undefined) ibInactive.textContent = d.inactive_count;
      if(ibCalls    !== null && d.ai_calls       !== undefined) ibCalls.textContent    = d.ai_calls;
      if(ibZero     !== null && d.zero_stock     !== undefined) {
        ibZero.textContent = d.zero_stock;
        if(ibBadge) ibBadge.style.display = d.zero_stock > 0 ? '' : 'none';
      }

      tbody.style.opacity = '1';
      tbody.style.pointerEvents = 'auto';
      if(loader) loader.style.display = 'none';
    })
    .catch(err => {
      console.error('Moda IA: erro ao carregar tabela:', err);
      showToast('❌ Erro ao carregar dados: ' + err.message);
      tbody.style.opacity = '1';
      tbody.style.pointerEvents = 'auto';
      if(loader) loader.style.display = 'none';
    });
}

function renderPagination(total, limit, current) {
  const container = document.querySelector('.pagination');
  if (!container) return;
  container.innerHTML = '';
  
  const pages = Math.max(1, Math.ceil(total / limit));
  const maxVisible = 5;
  let start = Math.max(1, current - 2);
  let end = Math.min(pages, start + maxVisible - 1);
  if (end - start < maxVisible - 1) start = Math.max(1, end - maxVisible + 1);

  if (current > 1) {
    container.innerHTML += `<div class="pg-btn" onclick="mudarPagina(${current-1})"><i class="fa fa-chevron-left"></i></div>`;
  }
  
  for (let i = start; i <= end; i++) {
    container.innerHTML += `<div class="pg-btn ${i===current?'act':''}" onclick="mudarPagina(${i})">${i}</div>`;
  }

  if (current < pages) {
    container.innerHTML += `<div class="pg-btn" onclick="mudarPagina(${current+1})"><i class="fa fa-chevron-right"></i></div>`;
  }
}

function exportarCatalogo() {
  const query = document.getElementById('mia-search').value;
  const params = new URLSearchParams();
  params.append('filter', _filtroAtual);
  params.append('search', query);
  params.append('export', '1');
  
  window.location.href = '../_inc/ai_catalogo_exportar.php?' + params.toString();
}

let step=1;
function stepNext(){
  if(step===1){
    const nome = document.getElementById('mia-nome')?.value || '';
    const skuBase = document.getElementById('mia-sku-base')?.value || '';
    const desc = document.getElementById('mia-desc')?.value || '';
    const catId = document.getElementById('mia-categoria')?.value || '';
    let tagsArr = Array.from(document.querySelectorAll('#tags-input .tag-chip')).map(c => c.textContent.replace('×','').trim());
    
    const tags = tagsArr.join(',');
    const editId = document.getElementById('mia-edit-id')?.value || '0';

    if (!nome) {
      showToast('❌ O nome do modelo é obrigatório.');
      return;
    }
    if (!skuBase) {
      showToast('❌ O SKU Base é obrigatório.');
      return;
    }

    // Persistência imediata na etapa 1 (Novo ou Edição)
    const formData = new FormData();
    formData.append('action', 'salvar');
    formData.append('id', editId);
    formData.append('name', nome);
    formData.append('sku', skuBase);
    formData.append('category_id', catId);
    formData.append('description', desc);
    formData.append('tags', tags);
    
    const fotoInput = document.getElementById('mia-foto-input');
    if (fotoInput && fotoInput.files[0]) {
      formData.append('foto', fotoInput.files[0]);
    }

    fetch('../_inc/ai_catalogo_salvar.php', {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(d=>{
      if(d.errorMsg){
        showToast('❌ '+d.errorMsg);
        return;
      }
      
      showToast('✔ ' + d.msg);
      document.getElementById('mia-edit-id').value = d.id;
      
      if (d.cover_url) {
        const area = document.getElementById('mia-upload-area');
        const preview = document.getElementById('mia-capa-preview');
        area.style.display = 'none';
        preview.style.display = 'block';
        preview.querySelector('img').src = d.cover_url + '?t=' + Date.now();
        if (fotoInput) fotoInput.value = '';
      }

      carregarVariantes(d.id);

      step=2;
      document.getElementById('step-1').style.display='none';
      document.getElementById('step-2').style.display='block';
      
      document.getElementById('si1').innerHTML='<i class="fa fa-check"></i>';
      document.getElementById('si1').className='step-indicator done';
      document.getElementById('sl1').className='step-line done';
      document.getElementById('si2').className='step-indicator active';
      document.getElementById('si2').textContent='2';
      document.getElementById('btn-back').style.display='inline-flex';
      document.getElementById('btn-next').innerHTML = 'Revisar e Finalizar <i class="fa fa-chevron-right"></i>';
    }).catch(err => {
      console.error('Moda IA: erro ao salvar:', err);
      showToast('❌ Erro ao processar informações.');
    });
  }
  else if(step===2){
    const cards = Array.from(document.querySelectorAll('.variant-card'));
    if (cards.length === 0) {
        showToast('⚠ Adicione ao menos uma cor/variante antes de concluir.');
        return;
    }
    if (!validarVariantes(cards)) return;

    // Popula o resumo da etapa 3
    document.getElementById('preview-nome').textContent = document.getElementById('mia-nome').value;
    document.getElementById('preview-sku-base').textContent = document.getElementById('mia-sku-base').value;
    document.getElementById('preview-desc').textContent = document.getElementById('mia-desc').value || 'Sem descrição';
    
    const catSel = document.getElementById('mia-categoria');
    document.getElementById('preview-cat').textContent = catSel.options[catSel.selectedIndex]?.text || 'Sem categoria';
    
    const tagsArr = Array.from(document.querySelectorAll('#tags-input .tag-chip')).map(c => c.textContent.replace('×','').trim());
    document.getElementById('preview-tags').textContent = tagsArr.length > 0 ? tagsArr.join(', ') : 'Nenhuma tag';
    
    document.getElementById('preview-vars').textContent = cards.length + ' cor(es)';
    
    let totalSkus = 0;
    cards.forEach(c => {
        // Como o SKU do modelo conta como 1 e as variantes são adicionais, 
        // mas aqui queremos mostrar o total de itens que a IA vai conhecer.
        totalSkus++;
    });
    document.getElementById('preview-skus').textContent = totalSkus + ' item(ns)';

    step=3;
    document.getElementById('step-2').style.display='none';
    document.getElementById('step-3').style.display='block';
    
    document.getElementById('si2').innerHTML='<i class="fa fa-check"></i>';
    document.getElementById('si2').className='step-indicator done';
    document.getElementById('sl2').className='step-line done';
    document.getElementById('si3').className='step-indicator active';
    document.getElementById('si3').textContent='3';
    
    document.getElementById('btn-next').innerHTML = '<i class="fa fa-check"></i> Confirmar e Salvar';
  }
  else if(step===3){
    const btn = document.getElementById('btn-next');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Salvando...';

    const cards = Array.from(document.querySelectorAll('.variant-card'));
    const promises = cards.map(card => executarSalvarVariante(card));
    
    Promise.all(promises).then(() => {
      showToast('✔ Produto e variantes salvos com sucesso.');
      fecharModal('novo-modelo');
      atualizarTabela(true);
    }).catch(err => {
      console.error('Erro ao salvar variantes:', err);
      showToast('❌ Erro ao salvar variantes.');
    }).finally(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-check"></i> Confirmar e Salvar';
    });
  }
}

function stepBack(){
  if(step===2){
    step=1;
    document.getElementById('step-2').style.display='none';
    document.getElementById('step-1').style.display='block';
    document.getElementById('si1').textContent='1';
    document.getElementById('si1').className='step-indicator active';
    document.getElementById('sl1').className='step-line';
    document.getElementById('si2').textContent='2';
    document.getElementById('si2').className='step-indicator pending';
    document.getElementById('btn-back').style.display='none';
    document.getElementById('btn-next').innerHTML = 'Próximo <i class="fa fa-chevron-right"></i>';
  }
  else if(step===3){
    step=2;
    document.getElementById('step-3').style.display='none';
    document.getElementById('step-2').style.display='block';
    document.getElementById('si2').textContent='2';
    document.getElementById('si2').className='step-indicator active';
    document.getElementById('sl2').className='step-line';
    document.getElementById('si3').textContent='3';
    document.getElementById('si3').className='step-indicator pending';
    document.getElementById('btn-next').innerHTML = 'Revisar e Finalizar <i class="fa fa-chevron-right"></i>';
  }
}
function addTag(e,el){if(e.key==='Enter'&&el.value.trim()){const chip=document.createElement('span');chip.className='tag-chip';chip.innerHTML=el.value.trim()+' <button onclick="removeTag(this)">×</button>';el.parentNode.insertBefore(chip,el);el.value='';e.preventDefault()}}
function removeTag(btn){btn.parentNode.remove()}
function toggleVariant(hdr){const body=hdr.nextElementSibling;const icon=hdr.querySelector('.vc-toggle');body.classList.toggle('open');icon.classList.toggle('open');body.style.display=body.classList.contains('open')?'block':'none'}
document.querySelectorAll('.sz-pill').forEach(p=>p.addEventListener('click',function(){this.classList.toggle('on')}));
// ───── UPGRADE MODAL ─────
function abrirUpgrade(){
  document.getElementById('upg-ov').classList.remove('hide');
  setTimeout(function(){
    const f1=document.getElementById('upg-fill-cat');
    const f2=document.getElementById('upg-fill-ia');
    const f3=document.getElementById('upg-fill-st');
    if(f1)f1.style.width='<?= $_ai_catPct ?>%';
    if(f2)f2.style.width='<?= $_ai_callPct ?>%';
    if(f3)f3.style.width='<?= $_ai_storageUnlimited ? 0 : $_ai_storagePct ?>%';
  },120);
}
// ───── TOGGLE STATUS ─────
function toggleStatus(el){
  const id=el.dataset.id;
  fetch('../_inc/ai_catalogo_salvar.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=toggle_status&id='+id})
    .then(r=>r.json()).then(d=>{
      if(d.errorMsg){showToast('❌ '+d.errorMsg);return;}
      el.classList.toggle('on');
      const lbl=el.nextElementSibling;
      lbl.classList.toggle('on');
      lbl.textContent=el.classList.contains('on')?'Ativo':'Inativo';
      showToast('✔ '+d.msg);
    });
}
// ───── DELETAR MODELO ─────
function deletarModelo(id, nome){
  const ov = document.getElementById('ov-confirm-del');
  const btn = document.getElementById('btn-confirm-del');
  document.getElementById('del-msg').innerHTML = `Você está prestes a excluir o modelo <b>${nome}</b> e todas as suas variantes. Esta ação não pode ser desfeita.`;
  
  ov.classList.remove('hide');

  btn.onclick = function() {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Excluindo...';
    
    fetch('../_inc/ai_catalogo_salvar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=deletar&id='+id
    })
    .then(r=>r.json()).then(d=>{
      if(d.errorMsg){showToast('❌ '+d.errorMsg);return;}
      showToast('✔ '+d.msg);
      fecharModal('confirm-del');
      atualizarTabela(true);
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = 'Excluir Agora';
    });
  };
}
// ───── EDITAR MODELO ─────
function editarModelo(id){
  // Resetar step para 1 antes de qualquer coisa
  step = 1;
  
  fetch('../_inc/ai_catalogo_categoria.php?action=listar')
    .then(r=>r.json()).then(d=>{
      if(d.categories) atualizarSelectCategorias(d.categories);
      
      fetch('../_inc/ai_demanda_ranking.php?limit=200&_t=' + Date.now())
        .then(r=>r.json()).then(d2=>{
          const m = (d2.ranking || []).find(x => x.id == id);
          if (!m) {
            console.error('Moda IA: modelo não encontrado no ranking. ID:', id);
            return;
          }
          
          // Abre a modal resetada
          abrirModal('novo-modelo');

          // Preenche os dados
          document.getElementById('mia-edit-id').value = m.id;
          document.getElementById('mia-nome').value = m.name;
          document.getElementById('mia-sku-base').value = m.sku || '';
          document.getElementById('mia-desc').value = m.description || '';
          
          // Preencher Categoria pelo ID se disponível
          const selectCat = document.getElementById('mia-categoria');
          if (m.category_id) {
            selectCat.value = m.category_id;
            // Se for Select2, atualiza visualmente
            if (typeof jQuery !== 'undefined' && jQuery(selectCat).data('select2')) {
              jQuery(selectCat).trigger('change');
            }
          } else {
            selectCat.value = '';
          }
          
          // Preencher Foto de Capa no Editar
          if (m.cover_url) {
            const area = document.getElementById('mia-upload-area');
            const preview = document.getElementById('mia-capa-preview');
            area.style.display = 'none';
            preview.style.display = 'block';
            preview.querySelector('img').src = m.cover_url + '?t=' + Date.now();
          } else {
            removerCapa();
          }
          
          // Limpar tags
          document.querySelectorAll('#tags-input .tag-chip').forEach(c => c.remove());
          
          if (m.tags) {
            let tagsArr = m.tags.split(',').map(t => t.trim()).filter(t => t !== '');
            
            // Adicionar as tags como chips
            tagsArr.forEach(t => {
              if(!t) return;
              const c = document.createElement('span');
              c.className = 'tag-chip';
              c.innerHTML = t + ' <button onclick="removeTag(this)">×</button>';
              document.getElementById('tags-input').insertBefore(c, document.getElementById('mia-tag-input'));
            });
          }
          
          carregarVariantes(m.id);
        });
    });
}
function fecharUpgrade(){document.getElementById('upg-ov').classList.add('hide');const f1=document.getElementById('upg-fill-cat');const f2=document.getElementById('upg-fill-ia');const f3=document.getElementById('upg-fill-st');if(f1)f1.style.width='0%';if(f2)f2.style.width='0%';if(f3)f3.style.width='0%';}
document.getElementById('upg-ov').addEventListener('click',function(e){if(e.target===this)fecharUpgrade();});
function selecionarPlano(nome,preco){
  fecharUpgrade();
  showToast('🚀 Upgrade para '+nome+' — redirecionando para o pagamento...');
}
function comprarCredito(nome,preco){
  fecharUpgrade();
  if(nome.indexOf('Pack')!==-1){
    showToast('🚀 Pack Completo contratado — créditos serão ativados em instantes!');
  } else {
    showToast('✔ '+nome+' adicionado — redirecionando para o pagamento...');
  }
}
function abrirWhatsAppUpgrade(){
  window.open('https://wa.me/5511999999999?text=Ol%C3%A1!%20Gostaria%20de%20fazer%20upgrade%20do%20meu%20plano%20Moda%20IA.','_blank');
}

// Carregar categorias ao iniciar
document.addEventListener('DOMContentLoaded', function() {
  fetch('../_inc/ai_catalogo_categoria.php?action=listar')
    .then(r=>r.json()).then(d=>{
      if(d.categories) atualizarSelectCategorias(d.categories);
    });
});
</script>

<!-- MODAL: CATEGORIAS -->
<div class="mia-overlay hide" id="ov-categorias" style="z-index:1100">
  <div class="mia-modal" style="width:450px">
    <div class="mh" style="background:linear-gradient(135deg,#6d28d9,#7c3aed)">
      <div class="mh-info">
        <div class="mt"><i class="fa fa-tags"></i> Gerenciar Categorias</div>
        <div class="ms">Cadastre as categorias da Moda IA</div>
      </div>
      <button class="mh-close" onclick="fecharModal('categorias')"><i class="fa fa-times"></i></button>
    </div>
    <div class="mb">
      <div class="form-group">
        <label class="form-label">Nova Categoria</label>
        <div style="display:flex;gap:8px">
          <input type="text" id="nova-cat-nome" class="form-control" placeholder="Ex: Macacões">
          <button class="btn btn-primary" onclick="salvarCategoria()"><i class="fa fa-save"></i> Salvar</button>
        </div>
      </div>
      <div style="margin-top:20px">
        <label class="form-label" style="margin-bottom:10px">Categorias Existentes</label>
        <div id="lista-categorias-modal" style="display:flex;flex-direction:column;gap:8px;max-height:250px;overflow-y:auto;padding-right:5px">
          <!-- Categorias listadas aqui -->
        </div>
      </div>
    </div>
    <div class="mf">
      <button class="btn btn-secondary" onclick="fecharModal('categorias')">Fechar</button>
    </div>
  </div>
</div>

<?php include ("footer.php"); ?>
