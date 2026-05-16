<?php
ob_start();
session_start();
include realpath(__DIR__.'/../').'/_init.php';

if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_plan_gate.php';
require_once DIR_HELPER . 'ai_evolution.php';
$tid = ai_tenant_id();
$settings = ai_get_settings($tid);
$webhookToken = ai_evolution_store_token($tid);
$schedule = ai_get_schedule($tid);
$pixKeys = [];
$pixRaw = (string)($settings['ai_pix_keys_json'] ?? '');
if ($pixRaw !== '') {
  // Decodificação robusta para lidar com entidades HTML do XSS Clean do sistema
  $tmp = json_decode($pixRaw, true);
  if (!is_array($tmp)) {
    $cleaned = html_entity_decode($pixRaw, ENT_QUOTES, 'UTF-8');
    $tmp = json_decode($cleaned, true);
  }
  if (is_array($tmp)) $pixKeys = $tmp;
}
if (!$pixKeys) {
  $pixKeys = [
    ['type' => 'CNPJ', 'key' => '12.345.678/0001-90'],
    ['type' => 'E-mail', 'key' => 'modafeminina@gmail.com'],
  ];
}
$gwProvider = (string)($settings['ai_payment_provider'] ?? 'mercadopago');
$evoGlobalConfig = ai_evolution_global_config();
$statusPostingMode = strtolower((string)($evoGlobalConfig['status_posting_mode'] ?? 'n8n'));
if (!in_array($statusPostingMode, ['n8n', 'system'], true)) {
  $statusPostingMode = 'n8n';
}
$isStatusPostingSystem = $statusPostingMode === 'system';

$document->setTitle('Configurações IA · Moda IA');
$document->setBodyClass('concierge_config');

include ("header.php");
include ("left_sidebar.php");
?>
<!-- Lottie Library -->
<script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
<div class="content-wrapper">
  <section class="content-header">
    <h1><i class="fa fa-cog" style="color:#6d28d9;margin-right:8px"></i>Configurações do Moda IA</h1>
    <ol class="breadcrumb">
      <li><a href="dashboard.php"><i class="fa fa-home"></i> Home</a></li>
      <li>Moda IA</li>
      <li class="active">Configurações</li>
    </ol>
  </section>
  <section class="content">
<style>
@keyframes mia-fadeIn{from{opacity:0}to{opacity:1}}
@keyframes blink{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.45;transform:scale(.7)}}
/* Actions */
.mia-actions{display:flex;justify-content:flex-end;gap:8px;margin-bottom:14px}
#mia-root .btn{display:inline-flex!important;align-items:center;gap:6px;padding:8px 14px;border-radius:2px!important;font-size:13px;font-weight:600;transition:all .18s;cursor:pointer}
#mia-root .btn-primary{background:linear-gradient(135deg,#6d28d9,#7c3aed)!important;color:#fff!important;box-shadow:0 2px 8px rgba(109,40,217,.35)!important;border:none!important}
#mia-root .btn-primary:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(109,40,217,.45)!important;background:linear-gradient(135deg,#6d28d9,#7c3aed)!important;color:#fff!important}
#mia-root .btn-secondary{background:#fff!important;border:1px solid #d1d5db!important;color:#374151!important}
#mia-root .btn-secondary:hover{border-color:#a78bfa!important;color:#6d28d9!important}
#mia-root .btn-danger{background:#fff!important;border:1px solid #fca5a5!important;color:#dc2626!important}
#mia-root .btn-danger:hover{background:#fee2e2!important}
#mia-root .btn-sm{padding:5px 10px!important;font-size:12px!important}
/* AI Hero */
.ai-hero{border-radius:2px;padding:18px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;transition:all .3s;box-shadow:0 2px 12px rgba(0,0,0,.1);margin-bottom:14px}
.ai-hero.off{background:#fff;border:1px solid #e5e7eb}
.ai-hero.on{background:linear-gradient(135deg,#3b0d8a,#5b21b6,#7c3aed);border:1px solid #6d28d9}
.ai-hero-left{display:flex;align-items:center;gap:14px}
.ai-hero-icon{width:52px;height:52px;border-radius:2px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.ai-hero.off .ai-hero-icon{background:#f5f3ff;color:#6d28d9}
.ai-hero.on .ai-hero-icon{background:rgba(255,255,255,.15);color:#fff}
.ai-hero-title{font-size:16px;font-weight:700}
.ai-hero.off .ai-hero-title{color:#111827}
.ai-hero.on .ai-hero-title{color:#fff}
.ai-hero-sub{font-size:12px;margin-top:3px}
.ai-hero.off .ai-hero-sub{color:#6b7280}
.ai-hero.on .ai-hero-sub{color:#c4b5fd}
.ai-hero-right{display:flex;align-items:center;gap:14px;flex-shrink:0}
.ai-hero-status{font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px}
.ai-hero.off .ai-hero-status{background:#f3f4f6;color:#6b7280}
.ai-hero.on .ai-hero-status{background:rgba(255,255,255,.2);color:#fff}
/* Toggles */
.toggle-track{width:44px;height:24px;border-radius:20px;background:#d1d5db;position:relative;cursor:pointer;transition:background .2s;flex-shrink:0}
.toggle-track.on{background:linear-gradient(135deg,#6d28d9,#7c3aed)}
.toggle-track.on-white{background:linear-gradient(135deg,#fff,rgba(255,255,255,.8))}
.toggle-thumb{width:20px;height:20px;border-radius:50%;background:#fff;position:absolute;top:2px;left:2px;transition:left .2s;box-shadow:0 1px 4px rgba(0,0,0,.25)}
.toggle-track.on .toggle-thumb,.toggle-track.on-white .toggle-thumb{left:22px}
.toggle-track.on-white .toggle-thumb{background:#6d28d9}
/* Box */
.mia-box{background:#fff;border:1px solid #e5e7eb;border-radius:2px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;margin-bottom:14px}
.bh{border-top:3px solid #6d28d9;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f3f4f6}
.bt{font-size:14px;font-weight:700;color:#374151;display:flex;align-items:center;gap:8px}
.bt i{color:#6d28d9}
/* Tabs */
.tabs-bar{display:flex;border-bottom:1px solid #e5e7eb;background:#fff;padding:0 16px;overflow-x:auto;flex-shrink:0;gap:2px}
.tabs-bar::-webkit-scrollbar{display:none}
.tab-btn{display:flex;align-items:center;gap:8px;padding:14px 18px;font-size:13px;font-weight:600;color:#6b7280;border:none;background:transparent;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-1px;white-space:nowrap;transition:all .2s ease}
.tab-btn:hover{color:#6d28d9;background:#f5f3ff}
.tab-btn.act{color:#6d28d9;border-bottom-color:#6d28d9;background:#f5f3ff}
.tab-btn i{font-size:14px;opacity:.8}
.tab-panel{padding:24px;display:none;animation:mia-fadeIn .3s ease}
.tab-panel.act{display:block}
/* Form */
#mia-root .form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:14px}
#mia-root .form-row-3{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:14px}
#mia-root .form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
#mia-root .form-group:last-child{margin-bottom:0}
#mia-root .form-label{font-size:12px;font-weight:700;color:#374151;display:flex;align-items:center;gap:6px;margin-bottom:2px}
#mia-root .form-label .req{color:#f9264c}
#mia-root .form-control{border:1px solid #d1d5db;border-radius:2px;padding:8px 10px;font-size:13.5px;color:#374151;background:#fff;transition:all .15s;outline:none;width:100%}
#mia-root .form-control:focus{border-color:#7c3aed;box-shadow:0 0 0 2px rgba(124,58,237,.12)}
#mia-root .form-control:disabled{background:#f9fafb;color:#9ca3af;cursor:not-allowed}
#mia-root .form-hint{font-size:11px;color:#9ca3af}
#mia-root .form-control-copy{display:flex;gap:6px;align-items:center}
#mia-root .form-control-copy .form-control{flex:1}
#mia-root textarea.form-control{resize:vertical;min-height:90px;font-family: 'Courier New', Courier, monospace !important; font-size: 13.5px !important; line-height: 1.5 !important; background: transparent; position: relative; z-index: 2; overflow-y: auto; padding: 10px !important; border: 1px solid #d1d5db !important; margin: 0 !important; display: block !important; width: 100% !important; box-sizing: border-box !important;}
.textarea-container{position:relative; width: 100%; background: #fff; margin-bottom: 0;}
.textarea-backdrop{position:absolute; top:0; left:0; right:0; bottom:0; padding: 10px !important; font-size: 13.5px !important; font-family: 'Courier New', Courier, monospace !important; line-height: 1.5 !important; color: transparent !important; white-space: pre-wrap !important; word-wrap: break-word !important; pointer-events: none; z-index: 1; overflow-y: auto; border: 1px solid transparent; box-sizing: border-box !important; margin: 0 !important;}
.sc-highlight{background-color: #ede9fe !important; color: transparent !important; border-radius: 3px !important; border: 1px solid #c4b5fd !important; padding: 0 2px !important; font-weight: bold !important; visibility: visible !important; display: inline !important;}
.char-count{font-size:11px;color:#9ca3af;text-align:right;margin-top:3px}
/* Config sections */
.cfg-section{margin-bottom:24px}
.cfg-section:last-child{margin-bottom:0}
.cfg-section-title{font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:6px}
.cfg-section-title i{color:#a78bfa}
.setting-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f9fafb}
.setting-row:last-child{border-bottom:none}
.sr-info{flex:1;padding-right:24px}
.sr-label{font-size:13px;font-weight:600;color:#374151}
.sr-desc{font-size:11px;color:#9ca3af;margin-top:2px}
.sr-ctrl{flex-shrink:0;display:flex;align-items:center;gap:8px}
.store-notify-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px;margin-bottom:12px}
.store-notify-card{border:1px solid #e5e7eb;border-radius:10px;background:linear-gradient(180deg,#fff,#fafafa);padding:14px;box-shadow:0 1px 5px rgba(0,0,0,.04)}
.store-notify-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
.store-notify-title{font-size:12px;font-weight:800;color:#374151;display:flex;align-items:center;gap:6px}
.store-notify-title i{color:#6d28d9}
.store-notify-desc{font-size:11px;color:#9ca3af;margin-bottom:10px;line-height:1.4}
.store-notify-input-wrap{display:flex;align-items:center;gap:8px}
.store-notify-input-wrap .wa-prefix{background:#f3f4f6;border:1px solid #d1d5db;border-radius:2px;padding:7px 9px;font-size:12px;font-weight:700;color:#6b7280;line-height:1;white-space:nowrap}
.store-notify-input-wrap .form-control{font-family:monospace}
.store-notify-lock{font-size:11px;color:#9ca3af;display:flex;align-items:center;gap:5px;margin-top:8px}
/* Schedule */
.schedule-grid{display:flex;flex-direction:column;gap:0;border:1px solid #e5e7eb;border-radius:2px;overflow:hidden}
.schedule-row{display:grid;grid-template-columns:90px 60px 1fr 16px 1fr;align-items:center;gap:10px;padding:9px 14px;border-bottom:1px solid #f3f4f6;background:#fff;transition:background .12s}
.schedule-row:last-child{border-bottom:none}
.schedule-row.inactive{background:#fafafa}
.schedule-row.inactive .sched-time{opacity:.4;pointer-events:none}
.sched-day{font-size:13px;font-weight:700;color:#374151}
.schedule-row.inactive .sched-day{color:#9ca3af}
.sched-time{border:1px solid #d1d5db;border-radius:2px;padding:5px 8px;font-size:12px;font-weight:600;color:#374151;outline:none;width:80px;background:#fff;transition:border-color .15s}
.sched-time:focus{border-color:#7c3aed}
.sched-sep{font-size:11px;color:#9ca3af;text-align:center}
.sched-closed{font-size:11px;color:#9ca3af;font-weight:600;grid-column:3/6}
/* Pix keys */
.pix-list{display:flex;flex-direction:column;gap:8px}
.pix-key-row{display:grid;grid-template-columns:140px 1fr 36px;gap:8px;align-items:center;background:#f9fafb;border:1px solid #e5e7eb;border-radius:2px;padding:8px 12px}
.pix-key-row select,.pix-key-row input{border:1px solid #d1d5db;border-radius:2px;padding:6px 8px;font-size:12.5px;background:#fff;outline:none;width:100%;color:#374151;transition:border-color .15s}
.pix-key-row select:focus,.pix-key-row input:focus{border-color:#7c3aed}
.pix-key-remove{width:28px;height:28px;display:flex;align-items:center;justify-content:center;background:transparent;border:1px solid #fca5a5;border-radius:2px;color:#dc2626;font-size:13px;cursor:pointer;flex-shrink:0;transition:background .15s}
.pix-key-remove:hover{background:#fee2e2}
/* Gateway */
.gateway-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.gw-card{border:1.5px solid #d1d5db;border-radius:2px;padding:12px;cursor:pointer;transition:all .15s;text-align:center;position:relative}
.gw-card:hover{border-color:#a78bfa}
.gw-card.sel{border-color:#6d28d9;background:#f5f3ff}
.gw-card input[type=radio]{position:absolute;opacity:0;width:0;height:0}
.gw-icon{font-size:20px;margin-bottom:6px}
.gw-name{font-size:12px;font-weight:700;color:#374151}
.gw-card.sel .gw-name{color:#6d28d9}
.gw-badge{font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;margin-top:4px;display:inline-block}
.gw-badge.free{background:#d1fae5;color:#065f46}
.gw-badge.paid{background:#f3f4f6;color:#6b7280}
/* Range */
.range-wrap{display:flex;align-items:center;gap:8px}
.range-wrap input[type=range]{flex:1;accent-color:#6d28d9}
.range-val{font-size:12px;font-weight:700;color:#374151;min-width:30px;text-align:right}
/* Btn copy */
.btn-copy{background:#f5f3ff;border:1px solid #c4b5fd;color:#6d28d9;padding:4px 10px;font-size:11px;font-weight:700;border-radius:2px;cursor:pointer}
.btn-copy:hover{background:#ede9fe}
.api-tool-card{border:1px solid #e5e7eb;border-radius:2px;padding:12px;margin-bottom:10px;background:#fff}
.api-tool-title{font-size:13px;font-weight:700;color:#374151;display:flex;align-items:center;gap:6px;margin-bottom:6px}
.api-tool-desc{font-size:12px;color:#6b7280;line-height:1.5;margin-bottom:8px}
/* ─── Conexão WhatsApp ─────────────────────────────── */
.evo-status-card{border-radius:2px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;transition:all .3s;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.evo-status-card.connected{background:linear-gradient(135deg,#065f46,#047857);border:1px solid #065f46}
.evo-status-card.waiting{background:linear-gradient(135deg,#78350f,#b45309);border:1px solid #92400e}
.evo-status-card.disconnected{background:#fff;border:1px solid #e5e7eb}
.evo-status-left{display:flex;align-items:center;gap:12px}
.evo-status-icon{width:44px;height:44px;border-radius:2px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.evo-status-card.connected .evo-status-icon{background:rgba(255,255,255,.15);color:#fff}
.evo-status-card.waiting .evo-status-icon{background:rgba(255,255,255,.15);color:#fff}
.evo-status-card.disconnected .evo-status-icon{background:#f5f3ff;color:#6d28d9}
.evo-status-title{font-size:14px;font-weight:700}
.evo-status-card.connected .evo-status-title,.evo-status-card.waiting .evo-status-title{color:#fff}
.evo-status-card.disconnected .evo-status-title{color:#111827}
.evo-status-sub{font-size:11px;margin-top:2px}
.evo-status-card.connected .evo-status-sub{color:rgba(255,255,255,.75)}
.evo-status-card.waiting .evo-status-sub{color:rgba(255,255,255,.75)}
.evo-status-card.disconnected .evo-status-sub{color:#6b7280}
.evo-status-badge{font-size:10px;font-weight:700;padding:3px 10px;border-radius:20px;white-space:nowrap}
.evo-status-card.connected .evo-status-badge{background:rgba(255,255,255,.2);color:#fff}
.evo-status-card.waiting .evo-status-badge{background:rgba(255,255,255,.2);color:#fff}
.evo-status-card.disconnected .evo-status-badge{background:#f3f4f6;color:#6b7280}
.evo-qr-wrap{display:flex;flex-direction:column;align-items:center;gap:10px;padding:16px;background:#fff;border:1px solid #e5e7eb;border-radius:2px;margin-bottom:14px}
.evo-qr-wrap img{width:200px;height:200px;border:3px solid #6d28d9;border-radius:4px;image-rendering:pixelated}
.evo-qr-hint{font-size:12px;color:#6b7280;text-align:center}
.evo-qr-actions{display:flex;gap:8px}
.evo-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;padding-top:16px;border-top:1px solid #f3f4f6}
.evo-log-table{width:100%;border-collapse:collapse;font-size:12px}
.evo-log-table th{padding:7px 12px;background:#f9fafb;text-align:left;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb}
.evo-log-table td{padding:7px 12px;border-bottom:1px solid #f9fafb;color:#374151;vertical-align:middle}
.evo-log-table tr:last-child td{border-bottom:none}
.evo-badge{font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;white-space:nowrap}
.evo-badge-ok{background:#d1fae5;color:#065f46}
.evo-badge-err{background:#fee2e2;color:#991b1b}
.evo-badge-ig{background:#fef3c7;color:#92400e}
/* Toast */
.mia-toast{position:fixed;bottom:20px;right:20px;background:#222d32;color:#fff;border-left:3px solid #6d28d9;padding:10px 16px;border-radius:2px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;z-index:9999;transform:translateX(120%);transition:transform .3s cubic-bezier(.34,1.56,.64,1);min-width:260px;box-shadow:0 4px 20px rgba(0,0,0,.3)}
.mia-toast.show{transform:translateX(0)}
.mia-toast i{color:#a78bfa}
/* Notif disabled stage */
.notif-stage-disabled{opacity:.45;pointer-events:none}
/* Shortcode enhancement */
.sc-badge{background:#ede9fe;color:#6d28d9;border:1px solid #c4b5fd;border-radius:20px;font-size:11px;font-weight:700;padding:2px 10px;font-family:monospace;cursor:pointer;transition:all .2s;display:inline-block;user-select:none}
.sc-badge:hover{background:#6d28d9;color:#fff;transform:scale(1.05)}
.sc-badge:active{transform:scale(.95)}
.bt i{font-family: 'FontAwesome' !important; color:#6d28d9; margin-right: 8px; width: 20px; text-align: center; display: inline-block !important;}
/* Toolbar for textarea */
.ta-toolbar{display:flex;gap:6px;margin-bottom:6px;background:#f8fafc;padding:6px;border:1px solid #e2e8f0;border-bottom:none;border-radius:2px 2px 0 0}
.ta-btn{background:#fff;border:1px solid #d1d5db;color:#475569;width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:2px;cursor:pointer;font-size:12px;transition:all .15s}
.ta-btn:hover{border-color:#6d28d9;color:#6d28d9;background:#f5f3ff}
.ta-btn i{font-size:13px; font-family: 'FontAwesome' !important; display: inline-block !important;}
#mia-root textarea.form-control.has-toolbar{border-top-left-radius:0;border-top-right-radius:0}

/* ─── Modal Overlay/Modal ─── */
.mia-overlay{position:fixed;inset:0;background:rgba(15,23,42,.65);backdrop-filter:blur(4px);z-index:1060;display:flex;align-items:center;justify-content:center;animation:mia-fadeIn .2s;padding:20px}
.mia-overlay.hide{display:none}
.mia-modal{background:#fff;border-radius:12px;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);overflow:hidden;animation:mia-slideUp .3s cubic-bezier(0.34, 1.56, 0.64, 1);width:100%;max-width:480px;border:1px solid rgba(255,255,255,0.1)}
@keyframes mia-slideUp{from{opacity:0;transform:translateY(30px) scale(0.95)}to{opacity:1;transform:translateY(0) scale(1)}}

.mh-new{background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:30px 25px;text-align:center;position:relative;overflow:hidden}
.mh-new::before{content:'';position:absolute;top:-20%;left:-10%;width:140%;height:140%;background:radial-gradient(circle,rgba(255,255,255,0.15) 0%,transparent 70%);pointer-events:none}
.mh-icon-wrap{width:64px;height:64px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 15px;color:#fff;font-size:30px;box-shadow:0 8px 16px rgba(0,0,0,0.1);border:2px solid rgba(255,255,255,0.3)}
.mt-new{font-size:20px;font-weight:800;color:#fff;margin:0;letter-spacing:-0.02em}
.ms-new{font-size:13px;color:rgba(255,255,255,0.85);margin-top:5px;font-weight:500}
.mh-close-new{position:absolute;top:15px;right:15px;background:rgba(0,0,0,0.2);border:none;color:#fff;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.2s;font-size:14px}
.mh-close-new:hover{background:rgba(255,255,255,0.2);transform:rotate(90deg)}

.mb-new{padding:30px 25px}
.modal-section-title{font-size:11px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:15px;display:flex;align-items:center;gap:8px}
.modal-section-title::after{content:'';flex:1;height:1px;background:#f1f5f9}

.input-group-new{background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:5px 15px;display:flex;align-items:center;gap:12px;transition:all 0.2s;margin-bottom:5px}
.input-group-new:focus-within{background:#fff;border-color:#7c3aed;box-shadow:0 0 0 4px rgba(124,58,237,0.1)}
.input-group-new i{color:#94a3b8;font-size:16px;width:20px;text-align:center}
.input-group-new .flag-icon{width:20px;height:14px;object-fit:cover;border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,0.1)}
.input-group-new .country-prefix{font-size:14.5px;font-weight:700;color:#64748b;padding-right:8px;border-right:1px solid #e2e8f0;margin-right:4px;display:flex;align-items:center;gap:6px}
.input-group-new input{border:none;background:transparent;padding:10px 0;font-size:14.5px;color:#1e293b;width:100%;outline:none;font-weight:500}
.input-group-new input::placeholder{color:#cbd5e1;font-weight:400}

.mf-new{padding:20px 25px;background:#f8fafc;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;gap:12px}
.btn-modern{padding:12px 24px;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;gap:8px;border:none}
.btn-modern-secondary{background:#fff;color:#64748b;border:1.5px solid #e2e8f0}
.btn-modern-secondary:hover{background:#f1f5f9;color:#1e293b;border-color:#cbd5e1}
.btn-modern-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;box-shadow:0 10px 15px -3px rgba(124,58,237,0.3)}
.btn-modern-primary:hover{transform:translateY(-2px);box-shadow:0 15px 20px -3px rgba(124,58,237,0.4);filter:brightness(1.1)}
.btn-modern-primary:active{transform:translateY(0)}
</style>

<div id="mia-root" ng-non-bindable>

  <!-- Ações -->
  <div class="mia-actions">
    <button class="btn btn-secondary" onclick="showToast('Alterações descartadas.')"><i class="fa fa-undo"></i> Descartar</button>
    <button class="btn btn-primary" onclick="salvarConfig()"><i class="fa fa-check"></i> Salvar Alterações</button>
  </div>

  <!-- AI HERO STATUS CARD -->
  <div class="ai-hero <?= ($settings['ai_enabled'] ?? '1') == '1' ? 'on' : 'off' ?>" id="ai-hero">
    <div class="ai-hero-left">
      <div class="ai-hero-icon"><i class="fa fa-magic"></i></div>
      <div>
        <div class="ai-hero-title">Moda IA — Atendimento Automático</div>
        <div class="ai-hero-sub" id="ai-hero-sub"><?= ($settings['ai_enabled'] ?? '1') == '1' ? 'A IA está ativa e atendendo clientes via WhatsApp agora mesmo' : 'O atendimento automático está pausado. Ative para começar a atender.' ?></div>
      </div>
    </div>
    <div class="ai-hero-right">
      <span class="ai-hero-status" id="ai-hero-status"><?= ($settings['ai_enabled'] ?? '1') == '1' ? '● ATIVO' : '○ INATIVO' ?></span>
      <div class="toggle-track <?= ($settings['ai_enabled'] ?? '1') == '1' ? 'on-white' : 'on' ?>" id="ai-main-toggle" onclick="toggleMainIA(this)" title="Ativar / Desativar atendimento automático">
        <div class="toggle-thumb"></div>
      </div>
    </div>
  </div>

  <!-- TABS BOX -->
  <div class="mia-box">
    <div class="tabs-bar">
      <button class="tab-btn act" onclick="switchTab('geral',this)"><i class="fa fa-sliders"></i> Geral</button>
      <button class="tab-btn" onclick="switchTab('conexao',this)" id="tab-btn-conexao"><i class="fa fa-plug"></i> Conexão</button>
      <button class="tab-btn" onclick="switchTab('apis',this)"><i class="fa fa-code-fork"></i> APIs N8N</button>
      <button class="tab-btn" onclick="switchTab('respostas',this)"><i class="fa fa-comments-o"></i> Respostas</button>
      <button class="tab-btn" onclick="switchTab('pix',this)"><i class="fa fa-qrcode"></i> Pix</button>
      <button class="tab-btn" onclick="switchTab('checkout',this)"><i class="fa fa-shopping-cart"></i> Checkout</button>
      <button class="tab-btn" onclick="switchTab('limites',this)"><i class="fa fa-tachometer"></i> Limites</button>
      <button class="tab-btn" onclick="switchTab('notificacoes',this)"><i class="fa fa-bell-o"></i> Notificações</button>
    </div>

    <!-- TAB: GERAL -->
    <div class="tab-panel act" id="tab-geral">
      <div class="form-row" style="margin-bottom:14px">
        <div class="cfg-section">
          <div class="cfg-section-title"><i class="fa fa-magic"></i> Identidade da IA</div>
          <div class="form-group">
            <label class="form-label">Nome da IA <span class="req">*</span></label>
            <input id="ai_name" name="ai_name" class="form-control" type="text" value="<?= htmlspecialchars($settings['ai_name'] ?? 'Sofia') ?>" placeholder="Ex: Sofia, Maya, Bia..." autocomplete="off">
            <span class="form-hint">Este nome será usado nas mensagens de atendimento</span>
          </div>
          <div class="form-group">
            <label class="form-label">Saudação padrão</label>
            <select id="ai_greeting" name="ai_greeting" class="form-control" autocomplete="off">
              <option <?= ($settings['ai_greeting'] ?? '') == 'Olá, [nome]! 😊' ? 'selected' : '' ?>>Olá, [nome]! 😊</option>
              <option <?= ($settings['ai_greeting'] ?? '') == 'Oi, [nome]! Tudo bem?' ? 'selected' : '' ?>>Oi, [nome]! Tudo bem?</option>
              <option <?= ($settings['ai_greeting'] ?? '') == 'Bem-vinda, [nome]! ✨' ? 'selected' : '' ?>>Bem-vinda, [nome]! ✨</option>
              <option <?= ($settings['ai_greeting'] ?? '') == 'Personalizada' ? 'selected' : '' ?>>Personalizada</option>
            </select>
          </div>
        </div>
        <div class="cfg-section">
          <div class="cfg-section-title"><i class="fa fa-comment"></i> Mensagem de Boas-vindas</div>
          <div class="form-group">
            <label class="form-label">Texto da mensagem inicial</label>
            
            <div class="ta-toolbar">
              <button class="ta-btn" onclick="applyFormat('ai_offline_msg', '*', '*')" title="Negrito (WhatsApp)"><i class="fa fa-bold"></i></button>
              <button class="ta-btn" onclick="applyFormat('ai_offline_msg', '_', '_')" title="Itálico (WhatsApp)"><i class="fa fa-italic"></i></button>
              <button class="ta-btn" onclick="applyFormat('ai_offline_msg', '~', '~')" title="Tachado (WhatsApp)"><i class="fa fa-strikethrough"></i></button>
              <button class="ta-btn" onclick="applyFormat('ai_offline_msg', '```', '```')" title="Monoespaçado (WhatsApp)"><i class="fa fa-code"></i></button>
            </div>

            <div class="textarea-container">
              <div class="textarea-backdrop" id="backdrop-ai_offline_msg"></div>
              <textarea id="ai_offline_msg" name="ai_offline_msg" class="form-control has-toolbar" rows="6" oninput="handleTextareaInput(this, 'wc', 'backdrop-ai_offline_msg')" autocomplete="off"><?= htmlspecialchars($settings['ai_offline_msg'] ?? "Olá! 👋 Eu sou a Sofia, assistente virtual da {{nome_loja}}.\n\nPosso te ajudar a encontrar peças incríveis do nosso catálogo, verificar disponibilidade e fechar seu pedido direto pelo WhatsApp!\n\nComo posso te ajudar hoje?") ?></textarea>
            </div>
            <div class="char-count"><span id="wc">0</span>/600 caracteres</div>
          </div>
        </div>
      </div>
      <div class="cfg-section">
        <div class="cfg-section-title" style="display:flex; justify-content:space-between; align-items:center;">
          <span><i class="fa fa-clock-o"></i> Horário de Atendimento</span>
          <div style="display:flex; align-items:center; gap:10px; font-size:12px; font-weight:normal;">
            <span>Atendimento 24h:</span>
            <div class="toggle-track <?= ($settings['ai_24h_mode'] ?? '0') == '1' ? 'on' : '' ?>" id="ai_24h_mode_toggle" onclick="toggleSetting(this, 'ai_24h_mode')" title="Ativar atendimento 24h por dia, 7 dias por semana">
              <div class="toggle-thumb"></div>
            </div>
          </div>
        </div>
        <div class="schedule-grid" id="schedule-grid">
          <?php
            $dayMap = [
              'mon' => 'Seg',
              'tue' => 'Ter',
              'wed' => 'Qua',
              'thu' => 'Qui',
              'fri' => 'Sex',
              'sat' => 'Sáb',
              'sun' => 'Dom',
            ];
            foreach ($dayMap as $k => $label) {
              $enabled = !empty($schedule[$k]['enabled']);
              $allDay  = !empty($schedule[$k]['all_day']);
              $start = (string)($schedule[$k]['start'] ?? '09:00');
              $end = (string)($schedule[$k]['end'] ?? '18:00');
          ?>
            <div class="schedule-row <?= $enabled ? '' : 'inactive' ?>" data-day="<?= $k ?>">
              <div class="sched-day"><?= $label ?></div>
              <div class="sr-ctrl">
                <div class="toggle-track <?= $enabled ? 'on' : '' ?>" onclick="toggleDay(this)">
                  <div class="toggle-thumb"></div>
                </div>
              </div>
              
              <div class="sched-times-wrap" style="display:flex; align-items:center; gap:8px; <?= $allDay ? 'opacity:0.4; pointer-events:none;' : '' ?>">
                <input id="sched_start_<?= $k ?>" name="sched_start_<?= $k ?>" class="sched-time" type="time" value="<?= htmlspecialchars($start) ?>" autocomplete="off">
                <span class="sched-sep">às</span>
                <input id="sched_end_<?= $k ?>" name="sched_end_<?= $k ?>" class="sched-time" type="time" value="<?= htmlspecialchars($end) ?>" autocomplete="off">
              </div>

              <div style="margin-left:auto; display:flex; align-items:center; gap:5px;">
                <label style="font-size:11px; font-weight:normal; cursor:pointer; display:flex; align-items:center; gap:4px; margin:0;">
                  <input id="sched_all_day_<?= $k ?>" name="sched_all_day_<?= $k ?>" type="checkbox" class="sched-all-day" <?= $allDay ? 'checked' : '' ?> onchange="toggleAllDay(this)" autocomplete="off"> 24h
                </label>
              </div>
            </div>
          <?php } ?>
        </div>
        <span class="form-hint">Fora do horário, a IA envia a mensagem configurada e não chama o n8n.</span>
      </div>
    </div><!-- /tab-geral -->

    <!-- TAB: CONEXÃO WHATSAPP -->
    <div class="tab-panel" id="tab-conexao">

      <!-- Estado Inicial: Sem Instância -->
      <div id="evo-no-instance" style="display:none; flex-direction:column; align-items:center; justify-content:center; padding:40px 20px; text-align:center;">
        <div style="width:200px; height:200px; margin-bottom:20px; background:#f8fafc; border-radius:50%; display:flex; align-items:center; justify-content:center; overflow:hidden;">
          <video src="../storage/concierge/Imagens/My-Store-animated.webm" style="width: 180px; height: 180px; object-fit: contain;" autoplay loop muted playsinline></video>
        </div>
        <h3 style="font-weight:700; color:#1e293b; margin-bottom:10px;">Conecte seu WhatsApp</h3>
        <p style="color:#64748b; max-width:400px; margin-bottom:25px; line-height:1.6;">Ative o atendimento automático e as notificações da Moda IA conectando seu número via Evolution API.</p>
        <button class="btn btn-primary" style="padding:12px 30px!important; font-size:15px!important;" onclick="abrirModal('conectar-whatsapp')">
          <i class="fa fa-whatsapp"></i> Começar Conexão
        </button>
      </div>

      <!-- Estado: Instância Criada (Hero) -->
      <div id="evo-instance-configured" style="display:none;">
        <div class="evo-status-card disconnected" id="evo-hero">
          <div class="evo-status-left">
            <div class="evo-status-icon"><i class="fa fa-whatsapp"></i></div>
            <div>
              <div class="evo-status-title" id="evo-hero-title">Instância: ---</div>
              <div class="evo-status-sub" id="evo-hero-sub">Verificando status da conexão...</div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
            <span class="evo-status-badge" id="evo-status-badge">○ DESCONECTADO</span>
            <button class="btn btn-secondary btn-sm" onclick="atualizarStatus()" id="evo-btn-refresh-status" title="Verificar status atual">
              <i class="fa fa-refresh"></i>
            </button>
          </div>
        </div>

        <!-- QR Code Widget -->
        <div class="evo-qr-wrap" id="evo-qr-widget" style="display:none">
          <p style="font-size:12px;font-weight:700;color:#374151;margin:0"><i class="fa fa-qrcode" style="color:#6d28d9"></i> Escaneie com o WhatsApp</p>
          <img id="evo-qr-img" src="" alt="QR Code" />
          <div class="evo-qr-hint">
            <i class="fa fa-mobile"></i> Abra o WhatsApp &rsaquo; Dispositivos Vinculados &rsaquo; Vincular um dispositivo<br>
            <span id="evo-qr-countdown" style="color:#b45309;font-weight:700"></span>
          </div>
          <div class="evo-qr-actions">
            <button class="btn btn-secondary btn-sm" onclick="refreshQRCode(this)"><i class="fa fa-refresh"></i> Novo QR Code</button>
          </div>
        </div>

        <!-- Configurações Locais -->
        <div class="mia-box" style="margin-bottom:14px">
          <div class="bh"><div class="bt"><i class="fa fa-cog"></i> Configurações de Integração</div></div>
          <div style="padding:16px">
            <div class="cfg-section">
              <div class="form-group">
                <label class="form-label">Token de Autenticação (X-Concierge-Token)</label>
                <div class="form-control-copy">
                  <input id="evo_webhook_token" name="evo_webhook_token" class="form-control" type="password" readonly value="<?= htmlspecialchars($webhookToken) ?>" style="font-family:monospace;font-size:11.5px" autocomplete="new-password">
                  <button class="btn-copy" onclick="toggleVisible('evo_webhook_token',this)"><i class="fa fa-eye"></i></button>
                  <button class="btn-copy" onclick="copyText('evo_webhook_token')"><i class="fa fa-copy"></i></button>
                </div>
                <small class="form-hint">Token interno da loja para validar chamadas entre ModernPOS e n8n.</small>
              </div>
              <div class="form-group">
                <label class="form-label">Webhook n8n (ModernPOS &rarr; n8n) <span class="req">*</span></label>
                <div class="form-control-copy">
                  <input id="evo_webhook_n8n" name="evo_webhook_n8n" class="form-control" type="url" placeholder="https://n8n.seudominio.com/webhook/moda-ia" value="<?= htmlspecialchars($settings['ai_webhook_target_url'] ?? '') ?>" autocomplete="off">
                  <button class="btn btn-secondary btn-sm" onclick="testarWebhookConexao(this)"><i class="fa fa-paper-plane"></i> Testar</button>
                </div>
                <small class="form-hint">URL do fluxo n8n que processa mensagens e retorna respostas da IA.</small>
              </div>
            </div>

            <!-- Botões de Ação -->
            <div class="evo-actions">
              <button class="btn btn-secondary" onclick="atualizarStatus()" id="evo-btn-status">
                <i class="fa fa-refresh"></i> Atualizar Status
              </button>
              <button class="btn btn-danger" onclick="desconectarInstancia()" id="evo-btn-delete" style="margin-left:auto">
                <i class="fa fa-times-circle"></i> Deletar Instância
              </button>
            </div>
          </div>
        </div>

        <!-- Logs Recentes -->
        <div class="mia-box">
          <div class="bh">
            <div class="bt"><i class="fa fa-list-alt"></i> Última Mensagem Recebida</div>
            <button class="btn btn-secondary btn-sm" onclick="carregarLogsEvo(1)"><i class="fa fa-refresh"></i> Atualizar</button>
          </div>
          <div id="evo-logs-container">
            <p style="padding:14px;color:#9ca3af;font-size:12px">Clique em atualizar para ver o log mais recente.</p>
          </div>
        </div>
      </div>

    </div><!-- /tab-conexao -->

    <!-- TAB: APIS N8N -->
    <div class="tab-panel" id="tab-apis">
      <div class="cfg-section">
        <div class="cfg-section-title"><i class="fa fa-code-fork"></i> APIs para Tools no n8n</div>
        <div class="form-group" style="margin-bottom:14px">
          <label class="form-label">Token para autenticação (header <code>X-Concierge-Token</code>)</label>
          <div class="form-control-copy">
            <input id="api-tools-token" name="api-tools-token" class="form-control" type="password" readonly value="<?= htmlspecialchars($webhookToken) ?>" style="font-family:monospace;font-size:11.5px" autocomplete="off">
            <button class="btn-copy" onclick="toggleVisible('api-tools-token',this)"><i class="fa fa-eye"></i></button>
            <button class="btn-copy" onclick="copyText('api-tools-token')"><i class="fa fa-copy"></i></button>
          </div>
          <span class="form-hint">Use o mesmo token em todas as chamadas das Tools no n8n.</span>
        </div>
        <?php
          $apiBase = rtrim(ROOT_URL, '/') . '/api/concierge/webhook.php?loja_id=' . $tid . '&action=';
          $apiTools = [
            ['id' => 'buscar_produto', 'label' => 'buscar_produto', 'desc' => 'Busca inteligente por SKU, categoria, tags, cor, tamanho e descrição para a AI montar recomendações.'],
            ['id' => 'perfil_cliente', 'label' => 'perfil_cliente', 'desc' => 'Consulta e atualiza preferências do cliente (tamanho, nome e histórico de perfil).'],
            ['id' => 'conversa_contexto', 'label' => 'conversa_contexto', 'desc' => 'Retorna contexto consolidado da conversa: resumo, perfil, ativação IA, pedido atual e dados PIX.'],
            ['id' => 'conversa_resumo', 'label' => 'conversa_resumo', 'desc' => 'Consulta e salva resumo da conversa para manter continuidade em contextos complexos.'],
            ['id' => 'conversa_ia_status', 'label' => 'conversa_ia_status', 'desc' => 'Consulta ou alterna o atendimento da conversa entre IA (Ativo) e Humano (Manual).'],
            ['id' => 'criar_pedido', 'label' => 'criar_pedido', 'desc' => 'Cria pedido a partir dos itens selecionados pela AI no n8n.'],
            ['id' => 'pedido_itens_update', 'label' => 'pedido_itens_update', 'desc' => 'Atualiza itens do pedido (substituir, adicionar ou remover), com validação de estoque.'],
            ['id' => 'pix_confirmacao', 'label' => 'pix_confirmacao', 'desc' => 'Consulta ou atualiza status de pagamento PIX (pendente, pago ou cancelado).'],
          ];
          foreach ($apiTools as $tool):
            $inputId = 'api-tool-url-' . $tool['id'];
            $endpoint = $apiBase . $tool['id'];
        ?>
        <div class="api-tool-card">
          <div class="api-tool-title"><i class="fa fa-link" style="color:#7c3aed"></i><?= htmlspecialchars($tool['label']) ?></div>
          <div class="api-tool-desc"><?= htmlspecialchars($tool['desc']) ?></div>
          <div class="form-control-copy">
            <input id="<?= htmlspecialchars($inputId) ?>" name="<?= htmlspecialchars($inputId) ?>" class="form-control" type="text" readonly value="<?= htmlspecialchars($endpoint) ?>" style="font-family:monospace;font-size:11.5px" autocomplete="off">
            <button class="btn-copy" onclick="copyText('<?= htmlspecialchars($inputId) ?>')"><i class="fa fa-copy"></i> Copiar</button>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$isStatusPostingSystem): ?>
        <div class="cfg-section-title" style="margin-top:18px"><i class="fa fa-users"></i> APIs do módulo Grupos IA</div>
        <?php
          $groupsApiBase = rtrim(ROOT_URL, '/') . '/api/concierge/';
          $groupsApis = [
            [
              'id' => 'groups_list',
              'label' => 'Listar grupos (GET)',
              'desc' => 'Lista os grupos sincronizados da loja, ativos e inativos.',
              'endpoint' => $groupsApiBase . 'groups.php?loja_id=' . $tid . '&include_inactive=1',
            ],
            [
              'id' => 'groups_sync',
              'label' => 'Sincronizar grupos (POST)',
              'desc' => 'Dispara sincronização dos grupos WhatsApp da Evolution para o banco da loja.',
              'endpoint' => $groupsApiBase . 'groups.php?loja_id=' . $tid . '&action=sync',
            ],
            [
              'id' => 'campaigns_list',
              'label' => 'Listar campanhas (GET)',
              'desc' => 'Retorna campanhas e indicadores de envio para a página de Grupos IA.',
              'endpoint' => $groupsApiBase . 'campaigns.php?loja_id=' . $tid . '&page=1&limit=50',
            ],
            [
              'id' => 'campaign_send_now',
              'label' => 'Disparar campanha (POST)',
              'desc' => 'Solicita disparo imediato de uma campanha existente via action=send_now.',
              'endpoint' => $groupsApiBase . 'campaigns.php?loja_id=' . $tid . '&action=send_now',
            ],
            [
              'id' => 'groups_categories',
              'label' => 'Categorias para IA (GET)',
              'desc' => 'Retorna categorias reais da loja para o filtro de categorias permitidas da IA.',
              'endpoint' => $groupsApiBase . 'products.php?action=categories',
            ],
          ];
          foreach ($groupsApis as $api):
            $inputId = 'api-groups-url-' . $api['id'];
        ?>
        <div class="api-tool-card">
          <div class="api-tool-title"><i class="fa fa-link" style="color:#7c3aed"></i><?= htmlspecialchars($api['label']) ?></div>
          <div class="api-tool-desc"><?= htmlspecialchars($api['desc']) ?></div>
          <div class="form-control-copy">
            <input id="<?= htmlspecialchars($inputId) ?>" name="<?= htmlspecialchars($inputId) ?>" class="form-control" type="text" readonly value="<?= htmlspecialchars($api['endpoint']) ?>" style="font-family:monospace;font-size:11.5px" autocomplete="off">
            <button class="btn-copy" onclick="copyText('<?= htmlspecialchars($inputId) ?>')"><i class="fa fa-copy"></i> Copiar</button>
          </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="cfg-section-title" style="margin-top:18px"><i class="fa fa-users"></i> APIs do módulo Grupos IA</div>
        <div class="api-tool-card" style="background:#f8fafc;border-color:#e2e8f0">
          <div class="api-tool-title"><i class="fa fa-info-circle" style="color:#2563eb"></i> Exibição oculta pelo modo global</div>
          <div class="api-tool-desc">
            O dono do SaaS configurou <strong>Postagem de Status via Sistema</strong> no painel global.<br>
            Por isso, esta lista de APIs do módulo Grupos IA foi ocultada para todas as lojas (multi-tenant).
          </div>
        </div>
        <?php endif; ?>
        <div class="cfg-section-title" style="margin-top:18px"><i class="fa fa-bolt"></i> APIs operacionais (Status, Disparos, Cronjobs e Webhooks)</div>
        <?php
          $opsApiBase = rtrim(ROOT_URL, '/') . '/api/concierge/';
          $opsApis = [
            [
              'id' => 'whatsapp_status_direct',
              'label' => 'Status WhatsApp direto (GET)',
              'desc' => 'Consulta estado da instância na Evolution sem depender do n8n (use include_qrcode=1 para solicitar novo QR).',
              'endpoint' => $opsApiBase . 'whatsapp_status.php?loja_id=' . $tid,
            ],
            [
              'id' => 'status_posts_list',
              'label' => 'Listar postagens de status (GET)',
              'desc' => 'Retorna postagens/agendamentos de Status WhatsApp da loja.',
              'endpoint' => $opsApiBase . 'status.php?loja_id=' . $tid . '&page=1&limit=50',
            ],
            [
              'id' => 'status_posts_create',
              'label' => 'Criar postagem de status (POST)',
              'desc' => 'Cria uma nova postagem de status para envio imediato ou agendado.',
              'endpoint' => $opsApiBase . 'status.php?loja_id=' . $tid,
            ],
            [
              'id' => 'dispatch_status_cron',
              'label' => 'Cronjob de status (POST)',
              'desc' => 'Processa fila de status pendentes via action=status.',
              'endpoint' => $opsApiBase . 'dispatch.php?loja_id=' . $tid . '&action=status',
            ],
            [
              'id' => 'dispatch_campaigns_cron',
              'label' => 'Cronjob de disparos (POST)',
              'desc' => 'Processa fila de campanhas/disparos pendentes via action=campaigns.',
              'endpoint' => $opsApiBase . 'dispatch.php?loja_id=' . $tid . '&action=campaigns',
            ],
            [
              'id' => 'dispatch_all_cron',
              'label' => 'Cronjob completo (POST)',
              'desc' => 'Executa status + campanhas no mesmo processamento (action=run).',
              'endpoint' => $opsApiBase . 'dispatch.php?loja_id=' . $tid . '&action=run',
            ],
            [
              'id' => 'status_callback_webhook',
              'label' => 'Webhook retorno de status (POST)',
              'desc' => 'Endpoint de callback para atualização do resultado de postagem de status.',
              'endpoint' => $opsApiBase . 'status_webhook.php?loja_id=' . $tid . '&status_id={STATUS_ID}',
            ],
            [
              'id' => 'campaign_callback_webhook',
              'label' => 'Webhook retorno de disparos (POST)',
              'desc' => 'Endpoint de callback para atualização de disparos em campanhas.',
              'endpoint' => $opsApiBase . 'campaign_status_webhook.php?loja_id=' . $tid,
            ],
            [
              'id' => 'evolution_inbound_webhook',
              'label' => 'Webhook inbound Evolution (POST)',
              'desc' => 'Webhook de entrada de mensagens/eventos WhatsApp vindos da Evolution API.',
              'endpoint' => $opsApiBase . 'evolution_webhook.php?loja_id=' . $tid,
            ],
          ];
          foreach ($opsApis as $api):
            $inputId = 'api-ops-url-' . $api['id'];
        ?>
        <div class="api-tool-card">
          <div class="api-tool-title"><i class="fa fa-link" style="color:#7c3aed"></i><?= htmlspecialchars($api['label']) ?></div>
          <div class="api-tool-desc"><?= htmlspecialchars($api['desc']) ?></div>
          <div class="form-control-copy">
            <input id="<?= htmlspecialchars($inputId) ?>" name="<?= htmlspecialchars($inputId) ?>" class="form-control" type="text" readonly value="<?= htmlspecialchars($api['endpoint']) ?>" style="font-family:monospace;font-size:11.5px" autocomplete="off">
            <button class="btn-copy" onclick="copyText('<?= htmlspecialchars($inputId) ?>')"><i class="fa fa-copy"></i> Copiar</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div><!-- /tab-apis -->

    <!-- TAB: RESPOSTAS -->
    <div class="tab-panel" id="tab-respostas">
      <div class="cfg-section">
        <div class="cfg-section-title"><i class="fa fa-magic"></i> Prompt do Sistema</div>
        <div class="form-group">
          <label class="form-label">Instruções para a IA <span class="req">*</span></label>
          
          <div class="ta-toolbar">
            <button class="ta-btn" onclick="applyFormat('ai_personality', '*', '*')" title="Negrito (WhatsApp)"><i class="fa fa-bold"></i></button>
            <button class="ta-btn" onclick="applyFormat('ai_personality', '_', '_')" title="Itálico (WhatsApp)"><i class="fa fa-italic"></i></button>
            <button class="ta-btn" onclick="applyFormat('ai_personality', '~', '~')" title="Tachado (WhatsApp)"><i class="fa fa-strikethrough"></i></button>
            <button class="ta-btn" onclick="applyFormat('ai_personality', '```', '```')" title="Monoespaçado (WhatsApp)"><i class="fa fa-code"></i></button>
            </div>

          <div class="textarea-container">
            <div class="textarea-backdrop" id="backdrop-ai_personality"></div>
            <textarea id="ai_personality" name="ai_personality" class="form-control has-toolbar" rows="12" oninput="handleTextareaInput(this, 'spc', 'backdrop-ai_personality')" style="font-size:12.5px;line-height:1.6" autocomplete="off"><?= htmlspecialchars($settings['ai_personality'] ?? "Você é a vendedora virtual da loja {{nome_loja}}. Seu nome é {{nome_ia}}.\nVocê é especialista em moda feminina e conhece cada peça do catálogo.\nSeja calorosa, consultiva e use emojis com moderação.\n\nRegras:\n- Nunca invente preços ou disponibilidade; consulte sempre a API.\n- Se uma peça estiver esgotada, sugira similares imediatamente.\n- Lembre das preferências da cliente e use-as nas sugestões.\n- Ao fechar pedido, confirme os itens antes de gerar o link.\n- Máximo de 3 produtos por mensagem para não sobrecarregar.\n- Use linguagem natural e feminina, como uma amiga consultiva.") ?></textarea>
          </div>
          <div class="char-count"><span id="spc">0</span>/2000 caracteres</div>
          <span class="form-hint">Variáveis disponíveis: {{nome_loja}} · {{nome_ia}} · {{cidade}} · {{horario}}</span>
        </div>
      </div>
      
      <div class="cfg-section">
        <div class="cfg-section-title"><i class="fa fa-sliders"></i> Comportamento</div>
        <div class="setting-row"><div class="sr-info"><div class="sr-label">Delay de resposta</div><div class="sr-desc">Simula digitação humana (recomendado: 2–4s)</div></div><div class="sr-ctrl" style="width:220px"><div class="range-wrap"><input type="range" id="ai_response_delay" name="ai_response_delay" min="0" max="10" value="<?= htmlspecialchars($settings['ai_response_delay'] ?? '3') ?>" oninput="document.getElementById('delay-val').textContent=this.value+'s'" autocomplete="off"><span class="range-val" id="delay-val"><?= htmlspecialchars($settings['ai_response_delay'] ?? '3') ?>s</span></div></div></div>
        <div class="setting-row"><div class="sr-info"><div class="sr-label">Máx. produtos por mensagem</div><div class="sr-desc">Limita quantos produtos a IA envia de uma vez</div></div><div class="sr-ctrl"><input id="ai_max_products" name="ai_max_products" class="form-control" type="number" value="<?= htmlspecialchars($settings['ai_max_products'] ?? '3') ?>" min="1" max="10" style="width:70px;text-align:center" autocomplete="off"></div></div>
        <div class="setting-row"><div class="sr-info"><div class="sr-label">Máx. fotos por produto</div><div class="sr-desc">Número de imagens enviadas ao apresentar um item</div></div><div class="sr-ctrl"><input id="ai_max_photos" name="ai_max_photos" class="form-control" type="number" value="<?= htmlspecialchars($settings['ai_max_photos'] ?? '2') ?>" min="1" max="5" style="width:70px;text-align:center" autocomplete="off"></div></div>
        <div class="setting-row"><div class="sr-info"><div class="sr-label">Enviar fotos em alta resolução</div><div class="sr-desc">Usa WebP otimizado (recomendado para economia de dados)</div></div><div class="sr-ctrl"><div class="toggle-track <?= ($settings['ai_send_high_res'] ?? '1') == '1' ? 'on' : '' ?>" id="ai_send_high_res" onclick="toggleSetting(this, 'ai_send_high_res')"><div class="toggle-thumb"></div></div></div></div>
        <div class="setting-row"><div class="sr-info"><div class="sr-label">Lembrar histórico de preferências</div><div class="sr-desc">Usa o perfil da cliente para personalizar sugestões</div></div><div class="sr-ctrl"><div class="toggle-track <?= ($settings['ai_remember_history'] ?? '1') == '1' ? 'on' : '' ?>" id="ai_remember_history" onclick="toggleSetting(this, 'ai_remember_history')"><div class="toggle-thumb"></div></div></div></div>
        <div class="setting-row"><div class="sr-info"><div class="sr-label">Sugerir peças complementares</div><div class="sr-desc">A IA oferece "look completo" (upsell automático)</div></div><div class="sr-ctrl"><div class="toggle-track <?= ($settings['ai_suggest_complementary'] ?? '1') == '1' ? 'on' : '' ?>" id="ai_suggest_complementary" onclick="toggleSetting(this, 'ai_suggest_complementary')"><div class="toggle-thumb"></div></div></div></div>
      </div>
    </div><!-- /tab-respostas -->

    <!-- TAB: PIX -->
    <div class="tab-panel" id="tab-pix">
      <div class="cfg-section">
        <div class="cfg-section-title"><i class="fa fa-qrcode"></i> Chaves Pix</div>
        <div class="pix-list" id="pix-list">
          <?php foreach ($pixKeys as $i => $pk) { $type = (string)($pk['type'] ?? 'Aleatória'); $key = (string)($pk['key'] ?? ''); ?>
            <div class="pix-key-row">
              <select name="pix_type_<?= $i ?>" id="pix_type_<?= $i ?>" autocomplete="off">
                <?php foreach (['CNPJ','CPF','E-mail','Telefone','Aleatória'] as $opt) { ?>
                  <option <?= $type === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php } ?>
              </select>
              <input type="text" name="pix_key_<?= $i ?>" id="pix_key_<?= $i ?>" value="<?= htmlspecialchars($key) ?>" placeholder="Digite a chave Pix" autocomplete="off">
              <button class="pix-key-remove" onclick="removePix(this)"><i class="fa fa-times"></i></button>
            </div>
          <?php } ?>
        </div>
        <button class="btn btn-secondary btn-sm" style="margin-top:10px" onclick="addPixKey()"><i class="fa fa-plus"></i> Adicionar Chave Pix</button>
      </div>
      <div class="cfg-section">
        <div class="cfg-section-title"><i class="fa fa-credit-card"></i> Gateway de Pagamento</div>
        <div class="gateway-grid" id="gw-grid">
          <label class="gw-card <?= $gwProvider === 'mercadopago' ? 'sel' : '' ?>" data-provider="mercadopago" onclick="selectGW(this)"><input type="radio" name="gateway" <?= $gwProvider === 'mercadopago' ? 'checked' : '' ?>><div class="gw-icon">💳</div><div class="gw-name">Mercado Pago</div><span class="gw-badge paid">Popular</span></label>
          <label class="gw-card <?= $gwProvider === 'asaas' ? 'sel' : '' ?>" data-provider="asaas" onclick="selectGW(this)"><input type="radio" name="gateway" <?= $gwProvider === 'asaas' ? 'checked' : '' ?>><div class="gw-icon">⚡</div><div class="gw-name">Asaas</div><span class="gw-badge free">BR</span></label>
          <label class="gw-card <?= $gwProvider === 'stripe' ? 'sel' : '' ?>" data-provider="stripe" onclick="selectGW(this)"><input type="radio" name="gateway" <?= $gwProvider === 'stripe' ? 'checked' : '' ?>><div class="gw-icon">🌐</div><div class="gw-name">Stripe</div><span class="gw-badge paid">Global</span></label>
          <label class="gw-card <?= $gwProvider === 'manual' ? 'sel' : '' ?>" data-provider="manual" onclick="selectGW(this)"><input type="radio" name="gateway" <?= $gwProvider === 'manual' ? 'checked' : '' ?>><div class="gw-icon">📲</div><div class="gw-name">Manual</div><span class="gw-badge free">Grátis</span></label>
        </div>
      </div>
      <div class="cfg-section">
        <div class="cfg-section-title"><i class="fa fa-lock"></i> Credenciais</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Mercado Pago (Access Token)</label>
            <div class="form-control-copy">
              <input id="ai_mp_access_token_enc" name="ai_mp_access_token_enc" class="form-control" type="password" value="<?= htmlspecialchars($settings['ai_mp_access_token_enc'] ?? '') ?>" placeholder="APP_USR-..." autocomplete="off">
              <button class="btn-copy" onclick="toggleVisible('ai_mp_access_token_enc',this)"><i class="fa fa-eye"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Asaas (API Key)</label>
            <div class="form-control-copy">
              <input id="ai_asaas_api_key_enc" name="ai_asaas_api_key_enc" class="form-control" type="password" value="<?= htmlspecialchars($settings['ai_asaas_api_key_enc'] ?? '') ?>" placeholder="$aact_..." autocomplete="off">
              <button class="btn-copy" onclick="toggleVisible('ai_asaas_api_key_enc',this)"><i class="fa fa-eye"></i></button>
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Stripe (Secret)</label>
            <div class="form-control-copy">
              <input id="ai_stripe_secret_enc" name="ai_stripe_secret_enc" class="form-control" type="password" value="<?= htmlspecialchars($settings['ai_stripe_secret_enc'] ?? '') ?>" placeholder="sk_live_..." autocomplete="off">
              <button class="btn-copy" onclick="toggleVisible('ai_stripe_secret_enc',this)"><i class="fa fa-eye"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Idioma da IA</label>
            <select id="ai_language" name="ai_language" class="form-control" autocomplete="off">
              <option value="pt-BR" <?= ($settings['ai_language'] ?? 'pt-BR') === 'pt-BR' ? 'selected' : '' ?>>pt-BR</option>
              <option value="en" <?= ($settings['ai_language'] ?? '') === 'en' ? 'selected' : '' ?>>en</option>
              <option value="es" <?= ($settings['ai_language'] ?? '') === 'es' ? 'selected' : '' ?>>es</option>
            </select>
          </div>
        </div>
      </div>
      <div class="cfg-section">
        <div class="cfg-section-title"><i class="fa fa-clock-o"></i> Configurações do QR Code</div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Validade do QR Code</label><select class="form-control" id="ai_pix_validity" name="ai_pix_validity" autocomplete="off"><option <?= ($settings['ai_pix_validity'] ?? '') == '15 minutos' ? 'selected' : '' ?>>15 minutos</option><option <?= ($settings['ai_pix_validity'] ?? '') == '30 minutos' ? 'selected' : '' ?>>30 minutos</option><option <?= ($settings['ai_pix_validity'] ?? '') == '1 hora' ? 'selected' : '' ?>>1 hora</option><option <?= ($settings['ai_pix_validity'] ?? '') == '2 horas' ? 'selected' : '' ?>>2 horas</option></select></div>
          <div class="form-group"><label class="form-label">Reenviar cobrança após</label><select class="form-control" id="ai_pix_retry" name="ai_pix_retry" autocomplete="off"><option <?= ($settings['ai_pix_retry'] ?? '') == 'Nunca' ? 'selected' : '' ?>>Nunca</option><option <?= ($settings['ai_pix_retry'] ?? '') == '30 minutos sem pagamento' ? 'selected' : '' ?>>30 minutos sem pagamento</option><option <?= ($settings['ai_pix_retry'] ?? '') == '1 hora' ? 'selected' : '' ?>>1 hora</option><option <?= ($settings['ai_pix_retry'] ?? '') == '2 horas' ? 'selected' : '' ?>>2 horas</option></select></div>
        </div>
        <div class="form-group">
          <label class="form-label">Mensagem após confirmação do Pix</label>
          
          <div class="ta-toolbar">
            <button class="ta-btn" onclick="applyFormat('ai_pix_confirm_msg', '*', '*')" title="Negrito (WhatsApp)"><i class="fa fa-bold"></i></button>
            <button class="ta-btn" onclick="applyFormat('ai_pix_confirm_msg', '_', '_')" title="Itálico (WhatsApp)"><i class="fa fa-italic"></i></button>
            <button class="ta-btn" onclick="applyFormat('ai_pix_confirm_msg', '~', '~')" title="Tachado (WhatsApp)"><i class="fa fa-strikethrough"></i></button>
            <button class="ta-btn" onclick="applyFormat('ai_pix_confirm_msg', '```', '```')" title="Monoespaçado (WhatsApp)"><i class="fa fa-code"></i></button>
            </div>

          <div class="textarea-container">
            <div class="textarea-backdrop" id="backdrop-ai_pix_confirm_msg"></div>
            <textarea id="ai_pix_confirm_msg" name="ai_pix_confirm_msg" class="form-control has-toolbar" rows="2" oninput="handleTextareaInput(this, 'apc', 'backdrop-ai_pix_confirm_msg')" autocomplete="off"><?= htmlspecialchars($settings['ai_pix_confirm_msg'] ?? "✅ Pagamento confirmado! Seu pedido entrou em preparação. Em breve você receberá atualizações sobre a entrega. Obrigada pela compra! 🛍️") ?></textarea>
          </div>
          <div class="char-count"><span id="apc">0</span> caracteres</div>
        </div>
      </div>
    </div><!-- /tab-pix -->

    <!-- TAB: CHECKOUT PIX -->
    <div class="tab-panel" id="tab-checkout">
      <div class="cfg-section">
        <div class="cfg-section-title"><i class="fa fa-tag"></i> Identidade da Página de Pagamento</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nome da Empresa <span class="req">*</span></label>
            <input id="ai_checkout_nome_empresa" name="ai_checkout_nome_empresa" class="form-control" type="text" value="<?= htmlspecialchars($settings['ai_checkout_nome_empresa'] ?? $settings['ai_name'] ?? '') ?>" placeholder="Ex: Boutique Moda Feminina" autocomplete="off">
            <span class="form-hint">Exibido no cabeçalho da página de pagamento</span>
          </div>
          <div class="form-group">
            <label class="form-label">Titular do Pix <span class="req">*</span></label>
            <input id="ai_checkout_titular" name="ai_checkout_titular" class="form-control" type="text" value="<?= htmlspecialchars($settings['ai_checkout_titular'] ?? '') ?>" placeholder="Nome do favorecido no QR Code" autocomplete="off">
            <span class="form-hint">Nome exibido no app do banco ao confirmar o pagamento</span>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Cidade (EMV Pix)</label>
            <input id="ai_checkout_cidade" name="ai_checkout_cidade" class="form-control" type="text" value="<?= htmlspecialchars($settings['ai_checkout_cidade'] ?? '') ?>" placeholder="Ex: SAO PAULO" maxlength="15" autocomplete="off">
            <span class="form-hint">Máx. 15 caracteres — campo obrigatório no padrão EMV</span>
          </div>
          <div class="form-group">
            <label class="form-label">WhatsApp do Suporte</label>
            <input id="ai_checkout_whatsapp" name="ai_checkout_whatsapp" class="form-control" type="tel" value="<?= htmlspecialchars($settings['ai_checkout_whatsapp'] ?? '') ?>" placeholder="5511999999999" autocomplete="off">
            <span class="form-hint">DDI+DDD+número (só dígitos). Usado no botão de envio do comprovante.</span>
          </div>
        </div>
      </div>
      <div class="cfg-section">
        <div class="cfg-section-title"><i class="fa fa-clock-o"></i> Temporizador &amp; Visual</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Minutos para expirar QR Code</label>
            <input id="ai_checkout_minutos" name="ai_checkout_minutos" class="form-control" type="number" value="<?= htmlspecialchars($settings['ai_checkout_minutos'] ?? '10') ?>" min="5" max="60" style="width:90px" autocomplete="off">
            <span class="form-hint">Contagem regressiva exibida ao cliente (5–60 min). Padrão: 10 min.</span>
          </div>
          <div class="form-group">
            <label class="form-label">Cor de Destaque</label>
            <div style="display:flex;gap:8px;align-items:center;">
              <input type="color" id="ai_checkout_cor_acento_picker" name="ai_checkout_cor_acento_picker" value="<?= htmlspecialchars($settings['ai_checkout_cor_acento'] ?? '#22c55e') ?>" style="height:36px;width:48px;border:1px solid #d1d5db;border-radius:2px;cursor:pointer;padding:2px;" oninput="document.getElementById('ai_checkout_cor_acento').value=this.value" autocomplete="off">
              <input id="ai_checkout_cor_acento" name="ai_checkout_cor_acento" class="form-control" type="text" value="<?= htmlspecialchars($settings['ai_checkout_cor_acento'] ?? '#22c55e') ?>" placeholder="#22c55e" maxlength="7" oninput="document.getElementById('ai_checkout_cor_acento_picker').value=this.value" style="flex:1;font-family:monospace" autocomplete="off">
            </div>
            <span class="form-hint">Cor principal dos botões e badges (hex). Padrão: #22c55e (verde).</span>
          </div>
        </div>
      </div>
      <div class="cfg-section">
        <div class="cfg-section-title"><i class="fa fa-check-circle"></i> Página de Confirmação (Obrigado)</div>
        <div class="form-group">
          <label class="form-label">Mensagem após pagamento confirmado</label>
          
          <div class="ta-toolbar">
            <button class="ta-btn" onclick="applyFormat('ai_checkout_msg_pago', '*', '*')" title="Negrito (WhatsApp)"><i class="fa fa-bold"></i></button>
            <button class="ta-btn" onclick="applyFormat('ai_checkout_msg_pago', '_', '_')" title="Itálico (WhatsApp)"><i class="fa fa-italic"></i></button>
            <button class="ta-btn" onclick="applyFormat('ai_checkout_msg_pago', '~', '~')" title="Tachado (WhatsApp)"><i class="fa fa-strikethrough"></i></button>
            <button class="ta-btn" onclick="applyFormat('ai_checkout_msg_pago', '```', '```')" title="Monoespaçado (WhatsApp)"><i class="fa fa-code"></i></button>
          </div>

          <div class="textarea-container">
            <div class="textarea-backdrop" id="backdrop-ai_checkout_msg_pago"></div>
            <textarea id="ai_checkout_msg_pago" name="ai_checkout_msg_pago" class="form-control has-toolbar" rows="3" oninput="handleTextareaInput(this, 'cmp', 'backdrop-ai_checkout_msg_pago')" autocomplete="off"><?= htmlspecialchars($settings['ai_checkout_msg_pago'] ?? 'Pagamento confirmado! Obrigado pela compra. Seu pedido será preparado em breve. 🎉') ?></textarea>
          </div>
          <div class="char-count"><span id="cmp">0</span> caracteres</div>
          <span class="form-hint">Texto exibido na página de confirmação após o admin marcar o pedido como pago</span>
        </div>
      </div>
      <div class="cfg-section">
        <div class="cfg-section-title"><i class="fa fa-eye"></i> Pré-visualização</div>
        <div class="form-group">
          <label class="form-label">URL — Checkout Pix (pública)</label>
          <div class="form-control-copy">
            <input class="form-control" type="text" readonly value="<?= rtrim(ROOT_URL, '/') ?>/checkout_pix.php?order_id={ID}" style="font-family:monospace;font-size:12px">
            <a href="<?= rtrim(ROOT_URL, '/') ?>/checkout_pix.php?order_id=1" target="_blank" class="btn-copy"><i class="fa fa-external-link"></i> Abrir</a>
          </div>
          <span class="form-hint">A IA gera e envia este link automaticamente ao criar um pedido via Pix</span>
        </div>
        <div class="form-group">
          <label class="form-label">URL — Confirmação (pública)</label>
          <div class="form-control-copy">
            <input class="form-control" type="text" readonly value="<?= rtrim(ROOT_URL, '/') ?>/checkout_pix_obrigado.php?order_id={ID}" style="font-family:monospace;font-size:12px">
            <a href="<?= rtrim(ROOT_URL, '/') ?>/checkout_pix_obrigado.php?order_id=1" target="_blank" class="btn-copy"><i class="fa fa-external-link"></i> Abrir</a>
          </div>
        </div>
      </div>
    </div><!-- /tab-checkout -->

    <!-- TAB: LIMITES -->
    <div class="tab-panel" id="tab-limites">
      <div class="cfg-section">
        <div class="cfg-section-title"><i class="fa fa-tachometer"></i> Limites de Atendimento</div>
        <div class="setting-row"><div class="sr-info"><div class="sr-label">Conversas simultâneas</div><div class="sr-desc">Máx. de clientes sendo atendidos ao mesmo tempo</div></div><div class="sr-ctrl"><input id="ai_limit_concurrent" name="ai_limit_concurrent" class="form-control" type="number" value="20" min="1" max="500" style="width:80px;text-align:center" autocomplete="off"><span class="form-hint" style="white-space:nowrap">0 = ilimitado</span></div></div>
        <div class="setting-row"><div class="sr-info"><div class="sr-label">Produtos por pedido</div><div class="sr-desc">Limite de itens que a IA permite em um único pedido</div></div><div class="sr-ctrl"><input id="ai_limit_products" name="ai_limit_products" class="form-control" type="number" value="10" min="1" max="50" style="width:80px;text-align:center" autocomplete="off"></div></div>
        <div class="setting-row"><div class="sr-info"><div class="sr-label">Tentativas de pagamento</div><div class="sr-desc">Quantas vezes a IA reenvia o link antes de encerrar</div></div><div class="sr-ctrl"><input id="ai_limit_retries" name="ai_limit_retries" class="form-control" type="number" value="3" min="1" max="10" style="width:80px;text-align:center" autocomplete="off"></div></div>
        <div class="setting-row"><div class="sr-info"><div class="sr-label">Timeout de inatividade</div><div class="sr-desc">Encerra a conversa após este período sem resposta do cliente</div></div><div class="sr-ctrl"><select id="ai_limit_timeout" name="ai_limit_timeout" class="form-control" style="width:160px" autocomplete="off"><option>5 minutos</option><option>10 minutos</option><option selected>20 minutos</option><option>30 minutos</option><option>1 hora</option></select></div></div>
      </div>
      <div class="cfg-section">
        <div class="cfg-section-title"><i class="fa fa-bell-o"></i> Notificações do Lojista</div>
        <div class="store-notify-grid">
          <div class="store-notify-card">
            <div class="store-notify-head">
              <div class="store-notify-title"><i class="fa fa-whatsapp"></i> Número do Lojista (Principal)</div>
              <div class="toggle-track <?= ($settings['ai_notify_store_primary_enabled'] ?? '1') == '1' ? 'on' : '' ?>" id="ai_notify_store_primary_enabled" onclick="toggleSetting(this,'ai_notify_store_primary_enabled')" title="Ativar/desativar envio para o número principal">
                <div class="toggle-thumb"></div>
              </div>
            </div>
            <div class="store-notify-desc">Recebe notificações de novos pedidos. O número principal é usado para alertas via WhatsApp.</div>
            <div class="store-notify-input-wrap">
              <span class="wa-prefix">+55</span>
              <input type="text" id="ai_whatsapp_number" class="form-control" value="<?= htmlspecialchars($settings['ai_whatsapp_number'] ?? '') ?>" placeholder="27999999999">
            </div>
          </div>
          <div class="store-notify-card">
            <div class="store-notify-head">
              <div class="store-notify-title"><i class="fa fa-bell-o"></i> Segundo Número (Opcional)</div>
              <div class="toggle-track <?= ($settings['ai_notify_store_secondary_enabled'] ?? '1') == '1' ? 'on' : '' ?>" id="ai_notify_store_secondary_enabled" onclick="toggleSetting(this,'ai_notify_store_secondary_enabled');syncSecondaryNotifyState();" title="Ativar/desativar envio para o segundo número">
                <div class="toggle-thumb"></div>
              </div>
            </div>
            <div class="store-notify-desc">Outro número para receber as mesmas notificações. Pode ser alterado e desativado.</div>
            <div class="store-notify-input-wrap">
              <span class="wa-prefix">+55</span>
              <input type="text" id="ai_whatsapp_number_2" class="form-control" value="<?= htmlspecialchars($settings['ai_whatsapp_number_2'] ?? '') ?>" placeholder="27999999999">
            </div>
          </div>
        </div>
        <div class="setting-row"><div class="sr-info"><div class="sr-label">Alerta de novo pedido</div><div class="sr-desc">Notifica quando um pedido é criado via WhatsApp</div></div><div class="sr-ctrl"><div class="toggle-track <?= ($settings['ai_notify_new_order'] ?? '1') == '1' ? 'on' : '' ?>" id="ai_notify_new_order" onclick="toggleSetting(this,'ai_notify_new_order')"><div class="toggle-thumb"></div></div></div></div>
        <div class="setting-row"><div class="sr-info"><div class="sr-label">Alerta de pagamento confirmado</div><div class="sr-desc">Notifica ao receber confirmação do gateway</div></div><div class="sr-ctrl"><div class="toggle-track <?= ($settings['ai_notify_payment_confirmed'] ?? '1') == '1' ? 'on' : '' ?>" id="ai_notify_payment_confirmed" onclick="toggleSetting(this,'ai_notify_payment_confirmed')"><div class="toggle-thumb"></div></div></div></div>
        <div class="setting-row"><div class="sr-info"><div class="sr-label">Alerta de estoque crítico</div><div class="sr-desc">Avisa quando um SKU cai abaixo de 3 unidades</div></div><div class="sr-ctrl"><div class="toggle-track <?= ($settings['ai_notify_stock_critical'] ?? '1') == '1' ? 'on' : '' ?>" id="ai_notify_stock_critical" onclick="toggleSetting(this,'ai_notify_stock_critical')"><div class="toggle-thumb"></div></div></div></div>
        <div class="setting-row"><div class="sr-info"><div class="sr-label">Relatório semanal por e-mail</div><div class="sr-desc">Resumo de conversas, vendas e performance toda segunda-feira</div></div><div class="sr-ctrl"><div class="toggle-track <?= ($settings['ai_weekly_report'] ?? '0') == '1' ? 'on' : '' ?>" id="ai_weekly_report" onclick="toggleSetting(this,'ai_weekly_report')"><div class="toggle-thumb"></div></div></div></div>
      </div>
      <div style="padding:14px;background:#fff8f0;border:1px solid #fed7aa;border-radius:2px;display:flex;gap:12px;align-items:flex-start">
        <i class="fa fa-exclamation-triangle" style="color:#d97706;font-size:16px;margin-top:2px;flex-shrink:0"></i>
        <?php 
          $plan_gate = ai_check_plan_gate($tid);
          $usage = $plan_gate['usage'] ?? [];
          $plan_info = $plan_gate['plan'] ?? [];
          $calls_used = (int)($usage['webhook_calls'] ?? 0);
          $calls_limit = (int)($plan_info['ai_webhook_calls'] ?? 0);
          $pct = $calls_limit > 0 ? round(($calls_used / $calls_limit) * 100) : 0;
          $token_balance = (int)($plan_gate['token_balance'] ?? 0);
        ?>
        <div>
          <div style="font-size:13px;font-weight:700;color:#92400e">Plano <?= htmlspecialchars($plan_info['plan_name'] ?? 'Atual') ?> — <?= $calls_limit ?> chamadas/mês</div>
          <div style="font-size:12px;color:#b45309;margin-top:3px">
            Você utilizou <strong><?= $calls_used ?> chamadas</strong> neste mês (<?= $pct ?>%). 
            <?php if ($token_balance > 0): ?>
              Saldo de tokens extras: <strong><?= $token_balance ?> créditos</strong>.
            <?php endif; ?>
          </div>
        </div>
        <a href="concierge_tokens.php" class="btn btn-sm" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border:none;font-weight:700;flex-shrink:0;margin-left:auto;text-decoration:none;">
          <i class="fa fa-database"></i> Créditos IA
        </a>
      </div>
    </div><!-- /tab-limites -->

    <!-- TAB: NOTIFICAÇÕES AO CLIENTE -->
    <div class="tab-panel" id="tab-notificacoes">
<?php
  $nEnabled  = (string)($settings['ai_notify_customer_enabled'] ?? '1');
  $nDefaults = [
    'separando' => "✅ Olá, {{nome}}! Seu pedido #{{pedido}} está sendo separado. Em breve estará a caminho! 🛍️",
    'rota'      => "🛵 {{nome}}, seu pedido #{{pedido}} saiu para entrega! Total: {{total}}. Aguarde, estamos a caminho! 😊",
    'entregue'  => "🎉 {{nome}}, seu pedido #{{pedido}} foi entregue com sucesso! Obrigada pela compra! 💜",
    'pago'      => "✅ {{nome}}, seu pagamento Pix foi confirmado! Pedido #{{pedido}} no valor de {{total}}. Já estamos preparando! 🛍️",
  ];
  $nLabels = [
    'separando' => ['icon'=>'fa-check-circle','color'=>'#2563eb','title'=>'Em Separação'],
    'rota'      => ['icon'=>'fa-motorcycle','color'=>'#7c3aed','title'=>'Saiu para Entrega'],
    'entregue'  => ['icon'=>'fa-check-circle','color'=>'#059669','title'=>'Pedido Entregue'],
    'pago'      => ['icon'=>'fa-qrcode','color'=>'#6d28d9','title'=>'PIX Confirmado'],
  ];
?>
      <!-- Hero toggle mestre -->
      <div style="background:<?= $nEnabled==='1' ? 'linear-gradient(135deg,#3b0d8a,#5b21b6,#7c3aed)' : '#fff' ?>;border:1px solid <?= $nEnabled==='1' ? '#6d28d9' : '#e5e7eb' ?>;border-radius:2px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px;box-shadow:0 2px 12px rgba(0,0,0,.1)" id="notif-hero">
        <div style="display:flex;align-items:center;gap:14px">
          <div style="width:48px;height:48px;border-radius:2px;display:flex;align-items:center;justify-content:center;font-size:22px;background:<?= $nEnabled==='1' ? 'rgba(255,255,255,.15)' : '#f5f3ff' ?>;color:<?= $nEnabled==='1' ? '#fff' : '#6d28d9' ?>">
            <i class="fa fa-whatsapp"></i>
          </div>
          <div>
            <div style="font-size:15px;font-weight:700;color:<?= $nEnabled==='1' ? '#fff' : '#111827' ?>" id="notif-hero-title">Notificações WhatsApp ao Cliente</div>
            <div style="font-size:12px;margin-top:3px;color:<?= $nEnabled==='1' ? '#c4b5fd' : '#6b7280' ?>" id="notif-hero-sub"><?= $nEnabled==='1' ? 'Os clientes recebem atualizações automáticas no WhatsApp ao avançar etapas.' : 'Notificações desativadas. Ative para avisar clientes automaticamente.' ?></div>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:14px;flex-shrink:0">
          <span style="font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;background:<?= $nEnabled==='1' ? 'rgba(255,255,255,.2)' : '#f3f4f6' ?>;color:<?= $nEnabled==='1' ? '#fff' : '#6b7280' ?>" id="notif-hero-badge"><?= $nEnabled==='1' ? '● ATIVO' : '○ INATIVO' ?></span>
          <div class="toggle-track <?= $nEnabled==='1' ? 'on-white' : 'on' ?>" id="notif-master-toggle" onclick="toggleNotifMaster(this)" title="Ativar / Desativar notificações ao cliente">
            <div class="toggle-thumb"></div>
          </div>
        </div>
      </div>

      <!-- Card de ShortCodes -->
      <div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:2px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:flex-start;gap:12px">
        <i class="fa fa-code" style="color:#7c3aed;font-size:16px;margin-top:2px;flex-shrink:0"></i>
        <div>
          <div style="font-size:12px;font-weight:700;color:#4c1d95;margin-bottom:6px">Clique nos ShortCodes para copiar</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <span class="sc-badge" onclick="copyShortcode('{{nome}}')">{{nome}}</span>
            <span style="font-size:11px;color:#6b7280;display:flex;align-items:center">Nome do cliente</span>
            <span class="sc-badge" onclick="copyShortcode('{{pedido}}')">{{pedido}}</span>
            <span style="font-size:11px;color:#6b7280;display:flex;align-items:center">Nº do pedido</span>
            <span class="sc-badge" onclick="copyShortcode('{{total}}')">{{total}}</span>
            <span style="font-size:11px;color:#6b7280;display:flex;align-items:center">Valor total</span>
          </div>
        </div>
      </div>

      <!-- Info: primeiro estágio sem mensagem -->
      <div style="background:#fff8f0;border:1px solid #fed7aa;border-radius:2px;padding:10px 14px;display:flex;align-items:center;gap:10px;margin-bottom:18px">
        <i class="fa fa-info-circle" style="color:#d97706;flex-shrink:0"></i>
        <span style="font-size:12px;color:#92400e;font-weight:600">O primeiro estágio <strong>Pedido</strong> nunca envia notificação — apenas os estágios abaixo disparam mensagens.</span>
      </div>

      <!-- Estágios -->
      <?php foreach ($nLabels as $stage => $meta): ?>
      <?php
        $stEnabled = (string)($settings['ai_notify_stage_'.$stage] ?? '1');
        $stMsg     = (string)($settings['ai_notify_msg_'.$stage] ?? $nDefaults[$stage]);
        $charId    = 'nc-'.$stage;
        $backdropId = 'backdrop-ai_notify_msg_'.$stage;
      ?>
      <div class="mia-box" style="margin-bottom:14px">
        <div class="bh" style="border-top-color:<?= $meta['color'] ?>">
          <div class="bt">
            <i class="fa <?= $meta['icon'] ?> fa-fw" style="color:<?= $meta['color'] ?> !important;"></i>
            <?= htmlspecialchars($meta['title']) ?>
          </div>
          <div class="sr-ctrl" style="gap:10px">
            <span style="font-size:11px;color:#9ca3af" id="notif-label-<?= $stage ?>"><?= $stEnabled==='1' ? 'Enviar mensagem' : 'Desativado' ?></span>
            <div class="toggle-track <?= $stEnabled==='1' ? 'on' : '' ?>" id="notif-toggle-<?= $stage ?>" onclick="toggleNotifStage(this,'<?= $stage ?>')">
              <div class="toggle-thumb"></div>
            </div>
          </div>
        </div>
        <div style="padding:14px 16px" id="notif-body-<?= $stage ?>" class="<?= $stEnabled!=='1' ? 'notif-stage-disabled' : '' ?>">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label"><i class="fa fa-comment-o" style="color:#7c3aed"></i> Mensagem enviada ao cliente</label>
            
            <div class="ta-toolbar">
              <button class="ta-btn" onclick="applyFormat('ai_notify_msg_<?= $stage ?>', '*', '*')" title="Negrito (WhatsApp)"><i class="fa fa-bold"></i></button>
              <button class="ta-btn" onclick="applyFormat('ai_notify_msg_<?= $stage ?>', '_', '_')" title="Itálico (WhatsApp)"><i class="fa fa-italic"></i></button>
              <button class="ta-btn" onclick="applyFormat('ai_notify_msg_<?= $stage ?>', '~', '~')" title="Tachado (WhatsApp)"><i class="fa fa-strikethrough"></i></button>
              <button class="ta-btn" onclick="applyFormat('ai_notify_msg_<?= $stage ?>', '```', '```')" title="Monoespaçado (WhatsApp)"><i class="fa fa-code"></i></button>
              </div>
            
            <div class="textarea-container">
              <div class="textarea-backdrop" id="<?= $backdropId ?>"></div>
              <textarea id="ai_notify_msg_<?= $stage ?>" name="ai_notify_msg_<?= $stage ?>" class="form-control has-toolbar" rows="3" oninput="handleTextareaInput(this, '<?= $charId ?>', '<?= $backdropId ?>')" style="font-size:13px" autocomplete="off"><?= htmlspecialchars($stMsg) ?></textarea>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px">
              <span class="form-hint">Dica: Selecione o texto e clique nos botões para formatar</span>
              <span class="char-count"><span id="<?= $charId ?>">0</span>/320 caracteres</span>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

    </div><!-- /tab-notificacoes -->

  </div><!-- /mia-box -->
</div><!-- /#mia-root -->

  </section>
</div><!-- /content-wrapper -->

<!-- Modal: Conectar WhatsApp (Novo Design) -->
<div class="mia-overlay hide" id="ov-conectar-whatsapp">
  <div class="mia-modal">
    <div class="mh-new">
      <button class="mh-close-new" onclick="fecharModal('conectar-whatsapp')"><i class="fa fa-times"></i></button>
      <div class="mh-icon-wrap">
        <i class="fa fa-whatsapp"></i>
      </div>
      <h2 class="mt-new">Nova Conexão</h2>
      <p class="ms-new">Conecte sua instância da Evolution API em segundos</p>
    </div>
    
    <div class="mb-new">
      <div style="margin-bottom:25px">
        <div class="modal-section-title">Identificação</div>
        <div class="form-group">
          <label class="form-label">Nome da Instância <span class="req">*</span></label>
          <div class="input-group-new">
            <i class="fa fa-tag"></i>
            <input id="evo_modal_instance_name" name="evo_modal_instance_name" type="text" placeholder="Ex: minha-loja-ia" value="<?= htmlspecialchars($settings['ai_evolution_instance_name'] ?? '') ?>" autocomplete="off">
          </div>
          <small class="form-hint">Dica: Use apenas letras minúsculas e hifens.</small>
        </div>
      </div>

      <div>
        <div class="modal-section-title">Opcional</div>
        <div class="form-group">
          <label class="form-label">Número do WhatsApp (Brasil)</label>
          <div class="input-group-new">
            <div class="country-prefix">
              <img src="../assets/itsolution24/img/flags/br.png" class="flag-icon" alt="BR">
              <span>+55</span>
            </div>
            <input id="evo_modal_phone" name="evo_modal_phone" type="tel" placeholder="(00) 00000-0000" autocomplete="off" oninput="maskPhone(this)">
          </div>
          <small class="form-hint">Insira apenas o DDD e o número (ex: 11 99999-9999).</small>
        </div>
      </div>
    </div>

    <div class="mf-new">
      <button class="btn-modern btn-modern-secondary" onclick="fecharModal('conectar-whatsapp')">
        Cancelar
      </button>
      <button class="btn-modern btn-modern-primary" id="evo-btn-modal-connect" onclick="conectarInstanciaModal()">
        <i class="fa fa-plug"></i> Criar e Conectar Agora
      </button>
    </div>
  </div>
</div>

<div class="mia-toast" id="toast"><i class="fa fa-magic"></i> <span id="toast-msg">Operação concluída</span></div>


<script>
let _tt;
function showToast(msg){const t=document.getElementById('toast');document.getElementById('toast-msg').textContent=msg;t.classList.add('show');clearTimeout(_tt);_tt=setTimeout(()=>t.classList.remove('show'),2800)}
let _evoConfigLoaded = false;
let _evoPolling = null;
let _evoQrTimer = null;
let _evoCanQueryStatus = false;
let _evoInstanceName = '';
let _evoWarnedInvalidBase = false;

function abrirModal(id){
  console.log('Abrindo modal:', id);
  const ov = document.getElementById('ov-'+id);
  if(ov) {
    ov.classList.remove('hide');
    // Forçar reflow para animação
    ov.offsetHeight; 
  } else {
    console.error('Modal não encontrado: ov-' + id);
  }
}
function fecharModal(id){
  const ov = document.getElementById('ov-'+id);
  if(ov) ov.classList.add('hide');
}

// Funções de utilidade para Textarea
function applyFormat(id, startTag, endTag) {
  const el = document.getElementById(id);
  const start = el.selectionStart;
  const end = el.selectionEnd;
  const text = el.value;
  const selectedText = text.substring(start, end);
  const before = text.substring(0, start);
  const after = text.substring(end);
  
  el.value = before + startTag + selectedText + endTag + after;
  el.focus();
  el.setSelectionRange(start + startTag.length, start + startTag.length + selectedText.length);
  
  // Disparar input para atualizar contador e backdrop
  el.dispatchEvent(new Event('input'));
}

function copyShortcode(code) {
  navigator.clipboard.writeText(code).then(() => {
    showToast('Shortcode ' + code + ' copiado!');
  });
}

function handleTextareaInput(el, charId, backdropId) {
  countChars(el, charId);
  updateBackdrop(el, backdropId);
}

function updateBackdrop(el, backdropId) {
  const backdrop = document.getElementById(backdropId);
  if (!backdrop) return;
  
  let text = el.value;
  // Escapar HTML e destacar shortcodes
  text = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  text = text.replace(/\{\{([^{}]+)\}\}/g, '<span class="sc-highlight">{{$1}}</span>');
  
  // Garantir que quebras de linha funcionem e adicionar um caractere invisível se terminar em newline para alinhar
  if (text.endsWith('\n')) text += '\n ';
  else text += ' ';
  
  backdrop.innerHTML = text;
  backdrop.scrollTop = el.scrollTop;
  backdrop.scrollLeft = el.scrollLeft;
}

function switchTab(name,btn){
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('act'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('act'));
  document.getElementById('tab-'+name).classList.add('act');
  btn.classList.add('act');
  if (name === 'conexao' && !_evoConfigLoaded) {
    _evoConfigLoaded = true;
    loadEvolutionConfig();
  }
}
function toggleMainIA(t){
  t.classList.toggle('on');t.classList.toggle('on-white');
  const hero=document.getElementById('ai-hero');
  const sub=document.getElementById('ai-hero-sub');
  const status=document.getElementById('ai-hero-status');
  const isOn=t.classList.contains('on-white');
  hero.className='ai-hero '+(isOn?'on':'off');
  sub.textContent=isOn?'A IA está ativa e atendendo clientes via WhatsApp agora mesmo':'O atendimento automático está pausado. Ative para começar a atender.';
  status.textContent=isOn?'● ATIVO':'○ INATIVO';

  // Salvar estado
  const formData = new FormData();
  formData.append('ai_enabled', isOn ? '1' : '0');
  fetch('../_inc/ai_config_salvar.php', { method: 'POST', body: formData });

  showToast(isOn?'Concierge IA ativado com sucesso!':'Concierge IA desativado.');
}
function toggleSetting(t, key){
  t.classList.toggle('on');
  const isOn = t.classList.contains('on');
  
  // Salvar imediatamente via AJAX
  const formData = new FormData();
  formData.append(key, isOn ? '1' : '0');
  
  fetch('../_inc/ai_config_salvar.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.errorMsg) showToast('Erro ao salvar: ' + data.errorMsg);
    else showToast('Configuração atualizada.');
  });
}
function normalizePhoneDigits(value){
  return (value || '').replace(/\D+/g, '');
}
function syncSecondaryNotifyState(){
  const toggle = document.getElementById('ai_notify_store_secondary_enabled');
  const input = document.getElementById('ai_whatsapp_number_2');
  if (!toggle || !input) return;
  const enabled = toggle.classList.contains('on');
  input.disabled = !enabled;
  input.style.opacity = enabled ? '1' : '.55';
}
function toggleDay(t){
  t.classList.toggle('on');
  const row=t.closest('.schedule-row');
  if(!row) return;
  row.classList.toggle('inactive', !t.classList.contains('on'));
}
function toggleAllDay(cb){
  const row = cb.closest('.schedule-row');
  if(!row) return;
  const wrap = row.querySelector('.sched-times-wrap');
  if(wrap){
    wrap.style.opacity = cb.checked ? '0.4' : '1';
    wrap.style.pointerEvents = cb.checked ? 'none' : 'auto';
  }
}
function countChars(el,id){document.getElementById(id).textContent=el.value.length}

document.addEventListener('DOMContentLoaded',()=>{
  // Inicializar textareas com backdrop
  const tas = [
    {id: 'ai_offline_msg', char: 'wc', backdrop: 'backdrop-ai_offline_msg'},
    {id: 'ai_personality', char: 'spc', backdrop: 'backdrop-ai_personality'},
    {id: 'ai_notify_msg_separando', char: 'nc-separando', backdrop: 'backdrop-ai_notify_msg_separando'},
    {id: 'ai_notify_msg_rota', char: 'nc-rota', backdrop: 'backdrop-ai_notify_msg_rota'},
    {id: 'ai_notify_msg_entregue', char: 'nc-entregue', backdrop: 'backdrop-ai_notify_msg_entregue'},
    {id: 'ai_notify_msg_pago', char: 'nc-pago', backdrop: 'backdrop-ai_notify_msg_pago'},
    {id: 'ai_checkout_msg_pago', char: 'cmp', backdrop: 'backdrop-ai_checkout_msg_pago'},
    {id: 'ai_pix_confirm_msg', char: 'apc', backdrop: 'backdrop-ai_pix_confirm_msg'}
  ];
  
  tas.forEach(item => {
    const el = document.getElementById(item.id);
    if (el) {
      handleTextareaInput(el, item.char, item.backdrop);
      // Sincronizar scroll
      el.addEventListener('scroll', () => {
        const backdrop = document.getElementById(item.backdrop);
        if (backdrop) backdrop.scrollTop = el.scrollTop;
      });
    }
  });

  const phoneMain = document.getElementById('ai_whatsapp_number');
  const phoneSecond = document.getElementById('ai_whatsapp_number_2');
  [phoneMain, phoneSecond].forEach((input) => {
    if (!input) return;
    input.addEventListener('input', () => {
      input.value = normalizePhoneDigits(input.value);
    });
    input.value = normalizePhoneDigits(input.value);
  });
  syncSecondaryNotifyState();
});

function addPixKey(){
  const list=document.getElementById('pix-list');
  const row=document.createElement('div');row.className='pix-key-row';
  row.innerHTML='<select><option>Aleatória</option><option>CPF</option><option>CNPJ</option><option>E-mail</option><option>Telefone</option></select><input type="text" placeholder="Digite a chave Pix"><button class="pix-key-remove" onclick="removePix(this)"><i class="fa fa-times"></i></button>';
  list.appendChild(row);row.querySelector('input').focus();
}
function removePix(btn){
  const list=document.getElementById('pix-list');
  if(list.children.length>1){btn.closest('.pix-key-row').remove();showToast('Chave Pix removida.')}
  else showToast('É necessário manter ao menos uma chave Pix.')
}
let _gwProvider = '';
function selectGW(label){
  document.querySelectorAll('.gw-card').forEach(c=>c.classList.remove('sel'));
  label.classList.add('sel');
  _gwProvider = label.getAttribute('data-provider') || '';
}
function copyText(id){const el=document.getElementById(id);navigator.clipboard.writeText(el.value).then(()=>showToast('Copiado para a área de transferência!'))}
function toggleVisible(id,btn){const el=document.getElementById(id);const show=el.type==='password';el.type=show?'text':'password';btn.innerHTML=show?'<i class="fa fa-eye-slash"></i>':'<i class="fa fa-eye"></i>';}
function serializeSchedule(){
  const out = {};
  document.querySelectorAll('#schedule-grid .schedule-row').forEach(row=>{
    const day = row.getAttribute('data-day');
    if(!day) return;
    const enabled = !row.classList.contains('inactive');
    const allDay  = row.querySelector('.sched-all-day').checked;
    const times   = row.querySelectorAll('input.sched-time');
    const start   = times[0] ? times[0].value : '09:00';
    const end     = times[1] ? times[1].value : '18:00';
    out[day] = { enabled: enabled ? 1 : 0, all_day: allDay ? 1 : 0, start, end };
  });
  return JSON.stringify(out);
}
function serializePixKeys(){
  const list = [];
  document.querySelectorAll('#pix-list .pix-key-row').forEach(row=>{
    const sel = row.querySelector('select');
    const inp = row.querySelector('input');
    const type = sel ? sel.value : 'Aleatória';
    const key = inp ? inp.value.trim() : '';
    if(key) list.push({ type, key });
  });
  return JSON.stringify(list);
}
function getSelectedGateway(){
  if(_gwProvider) return _gwProvider;
  const el = document.querySelector('#gw-grid .gw-card.sel');
  return el ? (el.getAttribute('data-provider') || '') : '';
}
function salvarConfig(btnEl){
  const btn = btnEl || document.querySelector('.mia-actions .btn-primary');
  const originalHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Salvando...';

  const formData = new FormData();
  
  // Mapear todos os campos que queremos salvar
  const fields = [
    'ai_name', 'ai_greeting', 'ai_offline_msg',
    'ai_whatsapp_provider', 'ai_instance_url', 'ai_instance_name',
    'ai_whatsapp_number', 'ai_whatsapp_number_2', 'ai_api_key', 'ai_webhook_target_url',
    'ai_personality',
    'ai_language',
    'ai_mp_access_token_enc',
    'ai_asaas_api_key_enc',
    'ai_stripe_secret_enc',
    // Checkout PIX
    'ai_checkout_nome_empresa', 'ai_checkout_titular', 'ai_checkout_cidade',
    'ai_checkout_whatsapp', 'ai_checkout_minutos', 'ai_checkout_cor_acento',
    'ai_checkout_msg_pago',
    'ai_pix_confirm_msg',
    'ai_pix_validity', 'ai_pix_retry',
    // Comportamento
    'ai_max_products', 'ai_max_photos',
    // Limites
    'ai_limit_concurrent', 'ai_limit_products', 'ai_limit_retries', 'ai_limit_timeout',
    // Mensagens de notificação
    'ai_notify_msg_separando','ai_notify_msg_rota','ai_notify_msg_entregue','ai_notify_msg_pago'
  ];

  fields.forEach(id => {
    const el = document.getElementById(id);
    if (el) formData.append(id, el.value);
  });

  // Toggles de Comportamento e Notificação (Lojista)
   const toggles = [
     'ai_send_high_res', 'ai_remember_history', 'ai_suggest_complementary',
     'ai_notify_new_order', 'ai_notify_payment_confirmed', 'ai_notify_stock_critical', 'ai_weekly_report',
     'ai_notify_store_primary_enabled', 'ai_notify_store_secondary_enabled'
   ];
  toggles.forEach(id => {
    const el = document.getElementById(id);
    if (el) formData.append(id, el.classList.contains('on') ? '1' : '0');
  });

  // Status principal da IA (HERO)
  const isAiOn = document.getElementById('ai-main-toggle').classList.contains('on-white');
  formData.append('ai_enabled', isAiOn ? '1' : '0');

  // Modo 24h
  const is24hOn = document.getElementById('ai_24h_mode_toggle').classList.contains('on');
  formData.append('ai_24h_mode', is24hOn ? '1' : '0');

  // Comportamento (Slider)
  const delayVal = document.getElementById('ai_response_delay') ? document.getElementById('ai_response_delay').value : '3';
  formData.append('ai_response_delay', delayVal);

  formData.append('ai_schedule_json', serializeSchedule());
  formData.append('ai_pix_keys_json', serializePixKeys());
  formData.append('ai_payment_provider', getSelectedGateway());
  
  fetch('../_inc/ai_config_salvar.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.errorMsg) {
      showToast('Erro: ' + data.errorMsg);
    } else {
      showToast('Configurações salvas com sucesso!');
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Erro ao conectar com o servidor.');
  })
  .finally(() => {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  });
}

// ─── Notificações ao Cliente ─────────────────────────────────────────────────
function toggleNotifMaster(t){
  const isOn = t.classList.contains('on-white');
  // Inverter estado
  const willBeOn = !isOn;
  t.classList.toggle('on-white', willBeOn);
  t.classList.toggle('on', !willBeOn);
  const hero = document.getElementById('notif-hero');
  const title = document.getElementById('notif-hero-title');
  const sub   = document.getElementById('notif-hero-sub');
  const badge = document.getElementById('notif-hero-badge');
  if (willBeOn) {
    hero.style.background = 'linear-gradient(135deg,#3b0d8a,#5b21b6,#7c3aed)';
    hero.style.borderColor = '#6d28d9';
    title.style.color = '#fff'; sub.style.color = '#c4b5fd'; badge.style.color = '#fff';
    sub.textContent = 'Os clientes recebem atualizações automáticas no WhatsApp ao avançar etapas.';
    badge.textContent = '● ATIVO'; badge.style.background = 'rgba(255,255,255,.2)';
  } else {
    hero.style.background = '#fff';
    hero.style.borderColor = '#e5e7eb';
    title.style.color = '#111827'; sub.style.color = '#6b7280'; badge.style.color = '#6b7280';
    sub.textContent = 'Notificações desativadas. Ative para avisar clientes automaticamente.';
    badge.textContent = '○ INATIVO'; badge.style.background = '#f3f4f6';
  }
  const fd = new FormData();
  fd.append('ai_notify_customer_enabled', willBeOn ? '1' : '0');
  fetch('../_inc/ai_config_salvar.php', { method:'POST', body:fd })
    .then(r=>r.json()).then(d=>{
      if (d.errorMsg) showToast('Erro: ' + d.errorMsg);
      else showToast(willBeOn ? 'Notificações ativadas!' : 'Notificações desativadas.');
    });
}
function toggleNotifStage(t, stage){
  t.classList.toggle('on');
  const isOn = t.classList.contains('on');
  const label = document.getElementById('notif-label-'+stage);
  const body  = document.getElementById('notif-body-'+stage);
  if (label) label.textContent = isOn ? 'Enviar mensagem' : 'Desativado';
  if (body) body.classList.toggle('notif-stage-disabled', !isOn);
  const fd = new FormData();
  fd.append('ai_notify_stage_'+stage, isOn ? '1' : '0');
  fetch('../_inc/ai_config_salvar.php', { method:'POST', body:fd })
    .then(r=>r.json()).then(d=>{
      if (d.errorMsg) showToast('Erro: ' + d.errorMsg);
      else showToast(isOn ? 'Notificação ativada para este estágio.' : 'Notificação desativada para este estágio.');
    });
}

// ─── Evolution WhatsApp Connection ─────────────────────────────────────────

function evoRequest(formData, onSuccess, btnEl) {
  const orig = btnEl ? btnEl.innerHTML : null;
  if (btnEl) { btnEl.disabled = true; btnEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i>'; }
  fetch('../_inc/ai_evolution_actions.php', { method: 'POST', body: formData })
    .then(async r => {
      const text = await r.text();
      let data = null;
      try { data = JSON.parse(text); } catch (e) {}
      if (!data || typeof data !== 'object') {
        const msg = text && text.length ? text.substring(0, 200) : 'Resposta inválida do servidor.';
        throw new Error(msg);
      }
      if (data.error) {
        const msg = data.message || 'Erro ao processar ação.';
        throw new Error(msg);
      }
      return data;
    })
    .then(data => onSuccess(data))
    .catch(err => showToast('Erro: ' + (err && err.message ? err.message : 'Falha de conexão.')))
    .finally(() => { if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = orig; } });
}

function renderEvoStatus(status, qrcode, instanceName, instanceNumber) {
  const hero   = document.getElementById('evo-hero');
  const badge  = document.getElementById('evo-status-badge');
  const sub    = document.getElementById('evo-hero-sub');
  const qrWrap = document.getElementById('evo-qr-widget');
  const title  = document.getElementById('evo-hero-title');

  const noInstanceDiv = document.getElementById('evo-no-instance');
  const configuredDiv = document.getElementById('evo-instance-configured');

  if (!instanceName) {
    _evoInstanceName = '';
    noInstanceDiv.style.display = 'flex';
    configuredDiv.style.display = 'none';
    return;
  }
  _evoInstanceName = instanceName;

  noInstanceDiv.style.display = 'none';
  configuredDiv.style.display = 'block';
  title.textContent = 'Instância: ' + instanceName;

  const isConn = status === 'Conectado';
  const isWait = status === 'Aguardando leitura';

  hero.className = 'evo-status-card ' + (isConn ? 'connected' : isWait ? 'waiting' : 'disconnected');
  badge.textContent = isConn ? '● CONECTADO' : isWait ? '◌ AGUARDANDO QR' : '○ DESCONECTADO';
  sub.textContent  = isConn
    ? 'WhatsApp conectado e recebendo mensagens normalmente'
    : isWait
      ? 'Escaneie o QR Code abaixo com o WhatsApp no celular'
      : 'Instância criada. Clique em atualizar para conectar';

  if (isWait && qrcode) {
    const img = document.getElementById('evo-qr-img');
    img.src = qrcode.startsWith('data:') ? qrcode : 'data:image/png;base64,' + qrcode;
    qrWrap.style.display = 'flex';
    if (_evoCanQueryStatus) startEvoPolling();
    startQrCountdown(60);
  } else {
    qrWrap.style.display = 'none';
    stopQrCountdown();
    if (isConn) stopEvoPolling();
  }
}

function startEvoPolling() {
  if (_evoPolling) return;
  _evoPolling = setInterval(() => {
    const fd = new FormData(); fd.append('action','get_status');
    fetch('../_inc/ai_evolution_actions.php', { method:'POST', body:fd })
      .then(r => r.json())
      .then(d => {
        if (d && !d.error) {
          renderEvoStatus(d.status, d.qrcode, d.instance_name || _evoInstanceName || '---', d.instance_number);
          return;
        }
        const msg = String((d && d.message) || '').toLowerCase();
        if (msg.includes('http 401') || msg.includes('url base da evolution inválida')) {
          renderEvoStatus('Desconectado', '', _evoInstanceName);
          stopEvoPolling();
        }
      })
      .catch(() => {});
  }, 10000);
}
function stopEvoPolling() {
  if (_evoPolling) { clearInterval(_evoPolling); _evoPolling = null; }
}
function startQrCountdown(secs) {
  stopQrCountdown();
  let n = secs;
  const el = document.getElementById('evo-qr-countdown');
  const tick = () => { if(el) el.textContent = 'QR expira em ' + n + 's'; n--; if(n<0) n=secs; };
  tick(); _evoQrTimer = setInterval(tick, 1000);
}
function stopQrCountdown() {
  if (_evoQrTimer) { clearInterval(_evoQrTimer); _evoQrTimer = null; }
  const el = document.getElementById('evo-qr-countdown'); if(el) el.textContent = '';
}

function loadEvolutionConfig() {
  fetch('../_inc/ai_evolution_actions.php?action=get_config')
    .then(r => r.json())
    .then(data => {
      if (data.error) return;
      const c = data.config;
      const baseUrlInvalid = !!c.base_url_invalid;
      _evoCanQueryStatus = !!(c.base_url && c.has_global_token && !baseUrlInvalid);

      if (baseUrlInvalid && !_evoWarnedInvalidBase) {
        _evoWarnedInvalidBase = true;
        showToast('A URL Base da Evolution está inválida. Corrija no SaaS para usar criar/atualizar/deletar.');
      }

      renderEvoStatus(c.status_label, c.last_qrcode, c.instance_name, c.instance_number);

      if (!_evoCanQueryStatus || !c.instance_name) {
        if (baseUrlInvalid && c.instance_name) {
          renderEvoStatus('Desconectado', '', c.instance_name, c.instance_number);
        }
        return;
      }

      const fd = new FormData(); fd.append('action', 'get_status');
      fetch('../_inc/ai_evolution_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
          if (d && !d.error) {
            renderEvoStatus(d.status, d.qrcode, d.instance_name || c.instance_name, d.instance_number);
            return;
          }
          const msg = String((d && d.message) || '').toLowerCase();
          if (msg.includes('http 401') || msg.includes('url base da evolution inválida')) {
            renderEvoStatus('Desconectado', '', c.instance_name, c.instance_number);
          }
        })
        .catch(() => {});
    })
    .catch(() => {});
}

function maskPhone(el) {
  let v = el.value.replace(/\D/g, "");
  if (v.length > 11) v = v.substring(0, 11);
  if (v.length === 0) {
    el.value = "";
    return;
  }
  if (v.length > 10) {
    v = v.replace(/^(\d{2})(\d{5})(\d{4}).*/, "($1) $2-$3");
  } else if (v.length > 5) {
    v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, "($1) $2-$3");
  } else if (v.length > 2) {
    v = v.replace(/^(\d{2})(\d{0,5}).*/, "($1) $2");
  } else {
    v = v.replace(/^(\d*)/, "($1");
  }
  el.value = v;
}

function conectarInstanciaModal() {
  const instanceName = document.getElementById('evo_modal_instance_name').value.trim();
  let phoneNumber  = document.getElementById('evo_modal_phone').value.replace(/\D/g, "");
  const webhookN8n   = document.getElementById('evo_webhook_n8n').value.trim();

  if (!instanceName) {
    showToast('Informe o nome da instância.');
    return;
  }

  // Garantir prefixo 55 se o número for informado
  if (phoneNumber && !phoneNumber.startsWith('55')) {
    phoneNumber = '55' + phoneNumber;
  }

  const fd = new FormData();
  fd.append('action', 'create_instance');
  fd.append('instance_name', instanceName);
  if (phoneNumber) fd.append('phone_number', phoneNumber);
  if (webhookN8n)  fd.append('webhook_target_url', webhookN8n);

  const btn = document.getElementById('evo-btn-modal-connect');
  evoRequest(fd, data => {
    fecharModal('conectar-whatsapp');
    showToast('✅ Instância preparada!');
    renderEvoStatus(data.status, data.qrcode, data.instance_name || instanceName, data.instance_number);
  }, btn);
}

function atualizarStatus() {
  if (!_evoCanQueryStatus) {
    showToast('Defina URL Base e Token Global da Evolution no SaaS.');
    return;
  }
  const fd = new FormData(); fd.append('action','get_status');
  const btn = document.getElementById('evo-btn-refresh-status') || document.getElementById('evo-btn-status');
  evoRequest(fd, data => {
    renderEvoStatus(data.status, data.qrcode, data.instance_name || _evoInstanceName || '---', data.instance_number);
    showToast('Status: ' + data.status);
  }, btn);
}

function refreshQRCode(btnEl) {
  if (!_evoCanQueryStatus) {
    showToast('Defina URL Base e Token Global da Evolution no SaaS.');
    return;
  }
  const fd = new FormData(); fd.append('action','refresh_qrcode');
  const btn = btnEl || null;
  evoRequest(fd, data => {
    renderEvoStatus(data.status, data.qrcode, data.instance_name || _evoInstanceName || '---', data.instance_number);
    showToast('QR Code atualizado!');
  }, btn);
}

function desconectarInstancia() {
  if (!confirm('Deletar permanentemente esta instância e desconectar o WhatsApp?')) return;
  const fd = new FormData(); fd.append('action','delete_instance');
  const btn = document.getElementById('evo-btn-delete');
  evoRequest(fd, data => {
    renderEvoStatus('Desconectado', '', '');
    showToast('Instância removida.');
    stopEvoPolling();
  }, btn);
}

function carregarLogsEvo(limit) {
  limit = limit || 1;
  fetch('../_inc/ai_evolution_actions.php?action=get_logs&limit=' + limit)
    .then(r => r.json())
    .then(data => {
      const c = document.getElementById('evo-logs-container');
      if (!c) return;
      if (data.error || !data.logs || data.logs.length === 0) {
        c.innerHTML = '<p style="padding:14px;color:#9ca3af;font-size:12px"><i class="fa fa-inbox"></i> Nenhum log registrado ainda.</p>';
        return;
      }
      let html = '<table class="evo-log-table"><thead><tr>';
      html += '<th>Data/Hora</th><th>Contato</th><th>Evento</th><th>Tipo</th><th>Status</th>';
      html += '</tr></thead><tbody>';
      data.logs.forEach(function(log) {
        const badgeCls = log.status === 'Sucesso' ? 'evo-badge-ok' : log.status === 'Erro' ? 'evo-badge-err' : 'evo-badge-ig';
        html += '<tr>';
        html += '<td style="white-space:nowrap">' + (log.created_at || '—') + '</td>';
        html += '<td>' + (log.push_name || log.remote_jid || '—') + '</td>';
        html += '<td><code style="font-size:11px">' + (log.event_name || '—') + '</code></td>';
        html += '<td>' + (log.message_type || '—') + '</td>';
        html += '<td><span class="evo-badge ' + badgeCls + '">' + log.status + '</span></td>';
        html += '</tr>';
      });
      html += '</tbody></table>';
      c.innerHTML = html;
    })
    .catch(() => {
      const c = document.getElementById('evo-logs-container');
      if (c) c.innerHTML = '<p style="padding:14px;color:#dc2626;font-size:12px">Erro ao carregar logs.</p>';
    });
}

// ─── fim Evolution ────────────────────────────────────────────────────────────

function testarWebhookConexao(btnEl){
  const btn = btnEl || null;
  const orig = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testando...';
  }
  const targetUrl = document.getElementById('evo_webhook_n8n').value.trim();
  if (!targetUrl) {
    showToast('Informe a URL do Webhook n8n primeiro.');
    if (btn) { btn.disabled = false; btn.innerHTML = orig; }
    return;
  }
  const fd = new FormData();
  fd.append('webhook_url', targetUrl);
  fetch('../_inc/ai_config_webhook_test.php', { method: 'POST', body: fd })
  .then(r => r.json())
  .then(data => {
    if (data.errorMsg) showToast('Erro: ' + data.errorMsg);
    else showToast('✅ n8n respondeu! (HTTP ' + data.http_code + ')');
  })
  .catch(() => showToast('Falha ao testar webhook.'))
  .finally(() => { if (btn) { btn.disabled = false; btn.innerHTML = orig; } });
}

function testarWebhook(btnEl){
  const btn = btnEl || null;
  const originalHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testando...';
  }

  // Primeiro salva a URL atual antes de testar
  const targetUrl = document.getElementById('ai_webhook_target_url').value;
  if (!targetUrl) {
    showToast('Informe a URL do Webhook primeiro.');
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
    return;
  }

  const formData = new FormData();
  formData.append('webhook_url', targetUrl);
  fetch('../_inc/ai_config_webhook_test.php', { method: 'POST', body: formData })
  .then(res => res.json())
  .then(data => {
    if (data.errorMsg) {
      showToast('Erro no teste: ' + data.errorMsg);
    } else {
      showToast('Sucesso! Webhook recebido pelo destino (HTTP ' + data.http_code + ')');
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Erro ao testar webhook.');
  })
  .finally(() => {
    btn.disabled = false;
    btn.innerHTML = originalHtml;
  });
}
</script>

<?php include ("footer.php"); ?>
