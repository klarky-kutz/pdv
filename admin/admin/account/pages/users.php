<?php
// Seção: Usuários da Conta (conteúdo migrado de account_users.php)
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'usuarios';
?>
<style>
/* =========================================
   MODAL PREMIUM STYLE (SaaS Design System)
   ========================================= */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');

:root {
    --primary-grad: linear-gradient(135deg, #006CFF 0%, #8A2BE2 100%);
    --modal-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    --text-dark: #0f172a;
    --text-gray: #64748b;
    --border-color: #e2e8f0;
    --success: #10B981;
    --danger: #ef4444;
}

/* Modal Overlay Premium */
.modal-premium-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(8px);
    z-index: 9999;
    display: none;
    justify-content: center;
    align-items: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    padding: 20px;
}

.modal-premium-overlay.active {
    display: flex;
    opacity: 1;
}

/* Modal Card Premium */
.modal-premium-card {
    background: #fff;
    width: 100%;
    max-width: 560px;
    border-radius: 20px;
    box-shadow: var(--modal-shadow);
    overflow: hidden;
    transform: scale(0.95) translateY(10px);
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    display: flex;
    flex-direction: column;
    max-height: 90vh;
}

.modal-premium-card.modal-wide {
    max-width: 1150px;
    width: calc(100% - 40px);
}

/* Modal Posicionada na Direita */
.modal-premium-overlay.modal-right-side {
    justify-content: flex-end;
    padding: 0;
}

.modal-premium-card.modal-right-position {
    max-width: 520px;
    height: 100vh;
    max-height: 100vh;
    border-radius: 20px 0 0 20px;
    transform: translateX(100%);
    margin: 0;
}

.modal-premium-overlay.modal-right-side.active .modal-premium-card.modal-right-position {
    transform: translateX(0);
}

.modal-premium-overlay.active .modal-premium-card {
    transform: scale(1) translateY(0);
}

/* Header Premium */
.modal-premium-header {
    height: 72px;
    background: var(--primary-grad);
    position: relative;
    padding: 0 24px;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-premium-header::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: 
        radial-gradient(circle at 15% 50%, rgba(255,255,255,0.15) 0%, transparent 20%),
        radial-gradient(circle at 85% 30%, rgba(255,255,255,0.15) 0%, transparent 20%),
        linear-gradient(45deg, transparent 48%, rgba(255,255,255,0.08) 50%, transparent 52%),
        linear-gradient(-45deg, transparent 48%, rgba(255,255,255,0.08) 50%, transparent 52%);
    background-size: 100% 100%, 100% 100%, 30px 30px, 30px 30px;
    pointer-events: none;
}

.modal-premium-title {
    position: relative;
    z-index: 1;
    font-size: 20px;
    font-weight: 600;
    margin: 0;
    letter-spacing: 0.02em;
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-close-premium {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.2);
    border: none;
    color: #fff;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    transition: all 0.2s;
    z-index: 2;
}

.btn-close-premium:hover { 
    background: rgba(255,255,255,0.4); 
    transform: translateY(-50%) rotate(90deg);
}

/* Body Premium */
.modal-premium-body {
    padding: 24px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #d1d5db transparent;
}

.modal-premium-body::-webkit-scrollbar { width: 6px; }
.modal-premium-body::-webkit-scrollbar-track { background: transparent; }
.modal-premium-body::-webkit-scrollbar-thumb { background-color: #d1d5db; border-radius: 20px; }

/* Form Section Title */
.form-section-title {
    font-size: 13px;
    text-transform: uppercase;
    font-weight: 700;
    color: #1C1C1C;
    margin: 25px 0 12px;
    display: block;
    letter-spacing: 0.5px;
}

.form-section-title:first-child { margin-top: 0; }

/* Form Group Premium */
.form-group-premium { margin-bottom: 18px; }

.label-premium {
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.label-premium .ic-modal {
    color: #407BFF;
    font-size: 15px;
}

/* Input Premium */
.input-premium {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    font-size: 14px;
    font-family: 'Poppins', sans-serif;
    color: #1e293b;
    transition: all 0.2s;
    background: #f8fafc;
    height: 48px;
}

.input-premium:focus {
    background: #fff;
    border-color: #8a2be2;
    box-shadow: 0 0 0 4px rgba(138, 43, 226, 0.1);
    outline: none;
}

/* Input Group Premium */
.input-group-premium {
    display: flex;
    align-items: center;
    background: #fff;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.2s;
    height: 48px;
}

.input-group-premium:focus-within {
    border-color: #8a2be2;
    box-shadow: 0 0 0 4px rgba(138, 43, 226, 0.1);
}

.input-prefix {
    background-color: #F1F5F9;
    color: #64748B;
    width: 48px;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    border-right: 1px solid var(--border-color);
    font-size: 16px;
    flex-shrink: 0;
}

.input-group-field {
    border: none;
    outline: none;
    padding: 0 12px;
    flex-grow: 1;
    font-size: 14px;
    color: #1e293b;
    font-weight: 500;
    background: transparent;
    height: 100%;
}

/* Form Row */
.form-row-premium {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

@media(max-width: 600px) {
    .form-row-premium { grid-template-columns: 1fr; }
}

/* Footer Premium */
.modal-premium-footer {
    background: #ffffff;
    padding: 20px 24px;
    border-top: 1px solid #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    border-bottom-left-radius: 20px;
    border-bottom-right-radius: 20px;
}

/* Botão Cancelar Premium */
.btn-cancel-premium {
    background: transparent;
    border: 1px solid #E2E8F0;
    color: #64748B;
    padding: 12px 28px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-cancel-premium:hover {
    background: #F8FAFC;
    color: #334155;
    border-color: #CBD5E1;
    transform: translateY(-1px);
}

/* Botão Save Premium (com Glow) */
.btn-save-premium {
    background: linear-gradient(90deg, #2563EB 0%, #7C3AED 100%);
    color: white;
    border: none;
    padding: 12px 28px;
    border-radius: 50px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(124, 58, 237, 0.35);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-save-premium:hover {
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 8px 25px rgba(124, 58, 237, 0.5);
}

/* Botão Danger Premium */
.btn-danger-premium {
    background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
    color: white;
    border: none;
    padding: 12px 28px;
    border-radius: 50px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.35);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-danger-premium:hover {
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.5);
}

/* =========================================
   SEÇÃO DE CREDENCIAIS DESTACADA
   ========================================= */
.credentials-section-highlight {
    margin-top: 28px;
    padding: 20px;
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.05) 0%, rgba(37, 99, 235, 0.05) 100%);
    border: 1px solid rgba(124, 58, 237, 0.2);
    border-radius: 16px;
    position: relative;
}

.credentials-section-highlight::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #7c3aed 0%, #2563eb 100%);
    border-radius: 16px 16px 0 0;
}

.credentials-section-highlight .form-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* =========================================
   BOTÕES DE AÇÃO (Estilo SaaS - Cores ModernPOS)
   ========================================= */
.user-action-buttons {
    display: flex;
    gap: 6px;
    align-items: center;
}

.btn-action-icon {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    background-color: transparent;
}

/* Editar (azul ModernPOS) */
.btn-action-edit {
    color: #2563eb;
}
.btn-action-edit:hover {
    background-color: #2563eb;
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
}

/* Senha (roxo ModernPOS) */
.btn-action-password {
    color: #7c3aed;
}
.btn-action-password:hover {
    background-color: #7c3aed;
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(124, 58, 237, 0.2);
}

/* Excluir (vermelho) */
.btn-action-delete {
    color: #dc2626;
}
.btn-action-delete:hover {
    background-color: #dc2626;
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(220, 38, 38, 0.2);
}

/* Status Toggle */
.btn-action-status {
    color: #16a34a;
}
.btn-action-status:hover {
    background-color: #16a34a;
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(22, 163, 74, 0.2);
}

.btn-action-status.inactive {
    color: #64748b;
}
.btn-action-status.inactive:hover {
    background-color: #64748b;
    color: #ffffff;
}

/* =========================================
   CARD DE USO/LIMITE PREMIUM (Usuários, Lojas, etc.)
   ========================================= */
.usage-card-premium {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.05);
    position: relative;
    overflow: hidden;
}

.usage-card-premium::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #2563eb 0%, #7c3aed 100%);
}

.usage-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.usage-card-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.usage-card-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.usage-card-icon.users {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.15) 0%, rgba(124, 58, 237, 0.15) 100%);
    color: #2563eb;
}

.usage-card-icon.stores {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.15) 100%);
    color: #10b981;
}

.usage-card-icon.clients {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(217, 119, 6, 0.15) 100%);
    color: #f59e0b;
}

.usage-card-label {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
}

.usage-card-sublabel {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}

.usage-card-badge {
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.usage-card-badge.success {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.usage-card-badge.warning {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}

.usage-card-badge.danger {
    background: rgba(239, 68, 68, 0.12);
    color: #dc2626;
}

.usage-card-body {
    margin-bottom: 12px;
}

.usage-card-numbers {
    display: flex;
    align-items: baseline;
    gap: 6px;
}

.usage-card-current {
    font-size: 32px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
}

.usage-card-separator {
    font-size: 20px;
    color: #94a3b8;
    font-weight: 500;
}

.usage-card-max {
    font-size: 20px;
    font-weight: 600;
    color: #64748b;
}

.usage-card-unlimited {
    font-size: 14px;
    color: #10b981;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
}

.usage-progress-container {
    height: 8px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}

.usage-progress-bar {
    height: 100%;
    border-radius: 999px;
    transition: width 0.5s ease;
}

.usage-progress-bar.success {
    background: linear-gradient(90deg, #10b981 0%, #059669 100%);
}

.usage-progress-bar.warning {
    background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
}

.usage-progress-bar.danger {
    background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
}

.usage-card-footer {
    margin-top: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.usage-card-percent {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
}

.usage-card-action {
    font-size: 12px;
    color: #2563eb;
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s;
}

.usage-card-action:hover {
    color: #7c3aed;
    gap: 6px;
}

/* =========================================
   PERMISSIONS GRID (Features Style)
   ========================================= */
.plan-features-toolbar {
    display: flex;
    gap: 14px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.plan-features-search {
    flex: 1;
    min-width: 240px;
}

.plan-features-stats {
    display: flex;
    gap: 10px;
}

.plan-features-stat {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 10px 12px;
    min-width: 100px;
    text-align: center;
}

.plan-features-stat .k {
    font-size: 12px;
    color: #64748b;
    font-weight: 700;
}

.plan-features-stat .v {
    font-size: 18px;
    color: #0f172a;
    font-weight: 900;
}

.plan-features-actions {
    margin-top: 12px;
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 12px 14px;
}

.plan-features-allowall {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: #334155;
    font-weight: 600;
    cursor: pointer;
}

.plan-features-allowall input {
    width: 18px;
    height: 18px;
    accent-color: #407BFF;
}

.plan-features-buttons {
    display: flex;
    gap: 10px;
    align-items: center;
}

.plan-features-grid {
    margin-top: 14px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

@media(max-width:1100px) { .plan-features-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media(max-width:720px) { .plan-features-grid { grid-template-columns: 1fr; } }

.plan-features-card {
    background: #fff;
    border-radius: 18px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    box-shadow: 0 10px 20px rgba(15,23,42,.06);
}

.plan-features-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(180deg, rgba(37,99,235,.08), rgba(255,255,255,0));
}

.plan-features-card-header .t {
    display: flex;
    align-items: center;
    gap: 10px;
}

.plan-features-card-header .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--cat-color, #2563eb);
    box-shadow: 0 0 0 6px rgba(0,0,0,.03);
}

.plan-features-card-header .title {
    font-weight: 900;
    color: #0f172a;
    font-size: 14px;
}

.plan-features-card-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.plan-features-card-all {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 900;
    color: #0f172a;
    cursor: pointer;
    user-select: none;
}

.plan-features-card-all input {
    width: 16px;
    height: 16px;
    accent-color: #407BFF;
    cursor: pointer;
}

.plan-features-card-header .count {
    font-weight: 900;
    color: #0f172a;
    background: #f1f5f9;
    border: 1px solid var(--border-color);
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 12px;
}

.plan-features-card-body {
    padding: 10px 12px;
    max-height: 320px;
    overflow-y: auto;
}

.plan-feature-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px;
    border-radius: 12px;
    border: 1px solid #eef2f7;
    background: #ffffff;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all .15s ease;
}

.plan-feature-item:hover {
    border-color: rgba(64,123,255,.35);
    box-shadow: 0 8px 14px rgba(64,123,255,.08);
}

.plan-feature-item input {
    margin-top: 3px;
    width: 18px;
    height: 18px;
    accent-color: #407BFF;
    flex: 0 0 auto;
}

.plan-feature-item .meta .name {
    font-weight: 800;
    color: #0f172a;
    font-size: 13px;
}

.plan-feature-item .meta .desc {
    margin-top: 2px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.25;
}

.plan-feature-item.is-hidden { display: none; }
.plan-features-card.is-hidden { display: none; }

/* =========================================
   BADGES DE FUNÇÃO PREMIUM
   ========================================= */
.badge-role-premium {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-role-owner {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    color: #78350f;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.badge-role-owner::before {
    content: '\f521';
    font-family: 'bootstrap-icons';
    font-size: 11px;
}

.badge-role-admin {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.badge-role-admin::before {
    content: '\f4da';
    font-family: 'bootstrap-icons';
    font-size: 11px;
}

.badge-role-manager {
    background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(6, 182, 212, 0.3);
}

.badge-role-default {
    background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(100, 116, 139, 0.3);
}

/* =========================================
   ALERT PREMIUM (Para permissões)
   ========================================= */
.alert-premium {
    border-radius: 16px;
    padding: 20px 24px;
    border: none;
    position: relative;
    overflow: hidden;
}

.alert-premium::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 6px;
    height: 100%;
}

.alert-premium-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(52, 211, 153, 0.05));
    color: #065f46;
}

.alert-premium-success::before {
    background: linear-gradient(180deg, #10b981, #059669);
}

.alert-premium-success .alert-icon {
    color: #10b981;
    font-size: 24px;
}

.alert-premium-title {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-premium-text {
    font-size: 14px;
    line-height: 1.6;
    opacity: 0.9;
}

/* Delete Confirmation Specific */
.delete-warning-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.delete-warning-icon i {
    font-size: 36px;
    color: #ef4444;
}

.delete-user-name {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
    text-align: center;
    margin-bottom: 10px;
}

.delete-warning-text {
    font-size: 14px;
    color: #64748b;
    text-align: center;
    line-height: 1.6;
}

.delete-blocked-message {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
    border-radius: 12px;
    padding: 16px;
    margin-top: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.delete-blocked-message i {
    color: #ef4444;
    font-size: 20px;
}

.delete-blocked-message span {
    font-size: 13px;
    color: #991b1b;
    font-weight: 600;
}

/* Toggle Switch Premium */
.toggle-switch-premium {
    position: relative;
    width: 52px;
    height: 28px;
    background-color: #D1D5DB;
    border-radius: 50px;
    cursor: pointer;
    transition: 0.3s;
}

.toggle-switch-premium.active {
    background-color: #10B981;
}

.toggle-switch-premium .circle {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 22px;
    height: 22px;
    background-color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.3s;
    color: #D1D5DB;
    font-size: 12px;
}

.toggle-switch-premium.active .circle {
    transform: translateX(24px);
    color: #10B981;
}

/* Checkbox List Container */
.checkbox-list-container {
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 12px;
    max-height: 200px;
    overflow-y: auto;
    background: #f8fafc;
}

.checkbox-list-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    margin-bottom: 6px;
    background: #fff;
    border: 1px solid #eef2f7;
    cursor: pointer;
    transition: all 0.15s;
}

.checkbox-list-item:hover {
    border-color: rgba(64,123,255,.35);
    box-shadow: 0 4px 8px rgba(64,123,255,.08);
}

.checkbox-list-item:last-child {
    margin-bottom: 0;
}

.checkbox-list-item input {
    width: 18px;
    height: 18px;
    accent-color: #407BFF;
}

.checkbox-list-item label {
    font-size: 14px;
    color: #334155;
    font-weight: 500;
    cursor: pointer;
    flex: 1;
}
</style>
<?php

// =======================================================
// SaaS Multi-tenant: Carregar usuários do tenant atual
// =======================================================
if (!class_exists('SaasLimitsBridge')) {
    $saasLimitsPath = ROOT . '/../saas/includes/SaasLimitsBridge.php';
    if (file_exists($saasLimitsPath)) {
        require_once $saasLimitsPath;
    }
}

$tenantId = 0;
$tenantUsers = array();
$tenantLimits = array();
$tenantUsage = 0;

// Contexto do usuário atual
$currentUserId = function_exists('user_id') ? (int)user_id() : 0;
$currentUserIsAdmin = (function_exists('user_group_id') && user_group_id() == 1) || (function_exists('is_tenant_owner') && is_tenant_owner());

// Opções para selects (criar/editar usuário)
$availableGroups = [];
$availableStores = [];

// Para regras de hierarquia: grupos que o usuário atual pode visualizar/atribuir
$allowedGroupIdsForCurrent = [];

// Grupos disponíveis (para realocação ao excluir grupos com usuários)
$groupsForReassign = [];

// Resolver tenant_id do usuário logado
if (class_exists('SaasLimitsBridge') && function_exists('user_id')) {
    try {
        $sessionTid = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
        $uid = (int)user_id();
        $tenantId = SaasLimitsBridge::resolveTenantId(db(), $uid, $sessionTid > 0 ? $sessionTid : null);
        
        if ($tenantId > 0) {
            // Carregar limites do plano
            $tenantLimits = SaasLimitsBridge::getPlanLimits(db(), $tenantId);

            // Contagem de limite: total de usuários cadastrados no tenant (inclui inativos)
            if (method_exists('SaasLimitsBridge', 'countTenantUsersTotal')) {
                $tenantUsage = SaasLimitsBridge::countTenantUsersTotal(db(), $tenantId);
                // DEBUG TEMP - remover após resolver bug
                error_log("[USERS_DEBUG] countTenantUsersTotal usado. tenantId={$tenantId}, tenantUsage={$tenantUsage}");
            } else {
                // Fallback compatível (instalações antigas)
                $tenantUsage = SaasLimitsBridge::countUsedUsers(db(), $tenantId);
                // DEBUG TEMP - remover após resolver bug
                error_log("[USERS_DEBUG] countUsedUsers FALLBACK usado. tenantId={$tenantId}, tenantUsage={$tenantUsage}");
            }
            // DEBUG TEMP - verificação direta
            $debugDirectCount = db()->query("SELECT COUNT(*) FROM users WHERE tenant_id = {$tenantId}")->fetchColumn();
            error_log("[USERS_DEBUG] Verificação direta SQL: COUNT(*) FROM users WHERE tenant_id={$tenantId} = {$debugDirectCount}");

            // Carregar lojas do tenant (select)
            try {
                $stStores = db()->prepare("SELECT store_id, name FROM stores WHERE tenant_id = ? ORDER BY name ASC");
                $stStores->execute([$tenantId]);
                $availableStores = $stStores->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Exception $e) {
                $availableStores = [];
            }

            // Carregar grupos disponíveis para o tenant (select)
            // IMPORTANTE: grupo Admin (group_id=1) NÃO deve aparecer no painel /conta (nem para Owner).
            try {
                $sqlGroups = "
                    SELECT group_id, name, slug, tenant_id, permission
                    FROM user_group
                    WHERE group_id != 1
                      AND (tenant_id IS NULL OR tenant_id = ? OR tenant_scope = ?)
                    ORDER BY name ASC
                ";

                $stGroups = db()->prepare($sqlGroups);
                $stGroups->execute([$tenantId, $tenantId]);
                $availableGroups = $stGroups->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Exception $e) {
                $availableGroups = [];
            }

            // -------------------------------------------------------
            // Hierarquia de grupos: não-admin não pode ver/atribuir
            // grupos com permissões superiores ao seu.
            // -------------------------------------------------------
            if (!empty($availableGroups)) {
                if (!empty($currentUserIsAdmin)) {
                    $allowedGroupIdsForCurrent = array_values(array_map('intval', array_column($availableGroups, 'group_id')));
                } else {
                    $currentUserGroupId = function_exists('user_group_id') ? (int)user_group_id() : 0;

                    $extractAccessKeys = function ($serializedOrArray) {
                        $arr = [];
                        if (is_array($serializedOrArray)) {
                            $arr = $serializedOrArray;
                        } elseif (is_string($serializedOrArray) && $serializedOrArray !== '') {
                            $tmp = null;
                            if (function_exists('valid_unserialize')) {
                                $tmp = valid_unserialize($serializedOrArray);
                            } else {
                                $tmp = @unserialize($serializedOrArray);
                            }
                            if (is_array($tmp)) {
                                $arr = $tmp;
                            }
                        }

                        $keys = [];
                        if (isset($arr['access']) && is_array($arr['access'])) {
                            foreach ($arr['access'] as $k => $v) {
                                if ($v) {
                                    $keys[] = (string)$k;
                                }
                            }
                        }
                        return $keys;
                    };

                    // Permissões do usuário atual (set)
                    $currentAccessSet = [];
                    if ($currentUserGroupId > 0) {
                        try {
                            $stMy = db()->prepare('SELECT permission FROM user_group WHERE group_id = ? LIMIT 1');
                            $stMy->execute([(int)$currentUserGroupId]);
                            $myPerm = (string)$stMy->fetchColumn();
                            $myKeys = $extractAccessKeys($myPerm);
                            foreach ($myKeys as $k) {
                                $currentAccessSet[$k] = true;
                            }
                        } catch (Exception $e) {
                            $currentAccessSet = [];
                        }
                    }

                    $filtered = [];
                    foreach ($availableGroups as $grp) {
                        $grpId = (int)($grp['group_id'] ?? 0);
                        if ($grpId <= 0) continue;

                        // Sempre permitir o próprio grupo aparecer no select
                        if ($currentUserGroupId > 0 && $grpId === (int)$currentUserGroupId) {
                            $filtered[] = $grp;
                            continue;
                        }

                        // Se não conseguirmos carregar permissões do grupo atual, limita a visibilidade ao próprio grupo
                        if ($currentUserGroupId > 0 && empty($currentAccessSet)) {
                            continue;
                        }

                        $grpKeys = $extractAccessKeys($grp['permission'] ?? '');
                        $isSubset = true;
                        foreach ($grpKeys as $k) {
                            if (!isset($currentAccessSet[$k])) {
                                $isSubset = false;
                                break;
                            }
                        }

                        if ($isSubset) {
                            $filtered[] = $grp;
                        }
                    }

                    $availableGroups = $filtered;
                    $allowedGroupIdsForCurrent = array_values(array_unique(array_merge(
                        $currentUserGroupId > 0 ? [(int)$currentUserGroupId] : [],
                        array_map('intval', array_column($availableGroups, 'group_id'))
                    )));
                }
            }
            
            // Carregar usuários do tenant
            $stmt = db()->prepare("
                SELECT DISTINCT
                    u.id, u.username, u.email, u.mobile, u.group_id, u.status,
                    ug.name as group_name, ug.slug as group_slug,
                    GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as store_names,
                    COUNT(DISTINCT u2s.store_id) as total_stores
                FROM users u
                LEFT JOIN user_group ug ON ug.group_id = u.group_id
                LEFT JOIN user_to_store u2s ON u2s.user_id = u.id AND u2s.status = 1
                LEFT JOIN stores s ON s.store_id = u2s.store_id AND s.tenant_id = ?
                WHERE u.tenant_id = ?
                GROUP BY u.id
                ORDER BY u.id ASC
            ");
            $stmt->execute([$tenantId, $tenantId]);
            $tenantUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // Silenciar erro em produção
        error_log('Erro ao carregar usuários do tenant: ' . $e->getMessage());
    }
}

// Helper para gerar iniciais do nome
function getUserInitials($username) {
    $parts = explode(' ', trim($username));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($username, 0, 2));
}

// Helper para badge de função (Premium Design)
function getUserRoleBadge($groupSlug, $isOwner = false) {
    // Se for o owner da conta, sempre mostrar "Admin" (independente de plano)
    if ($isOwner) {
        return ['class' => 'badge-role-premium badge-role-owner', 'label' => 'Admin'];
    }
    
    // Mapear grupos para labels amigáveis
    $badges = [
        'admin' => ['class' => 'badge-role-premium badge-role-admin', 'label' => 'Admin'],
        'manager' => ['class' => 'badge-role-premium badge-role-manager', 'label' => 'Gerente'],
        'gerente' => ['class' => 'badge-role-premium badge-role-manager', 'label' => 'Gerente'],
        'cashier' => ['class' => 'badge-role-premium badge-role-default', 'label' => 'Caixa'],
        'caixa' => ['class' => 'badge-role-premium badge-role-default', 'label' => 'Caixa'],
        'vendedor' => ['class' => 'badge-role-premium badge-role-default', 'label' => 'Vendedor'],
        'operador' => ['class' => 'badge-role-premium badge-role-default', 'label' => 'Operador'],
    ];
    
    $slug = strtolower(trim($groupSlug));
    if (isset($badges[$slug])) {
        return $badges[$slug];
    }
    
    // Para qualquer outro grupo, usar o nome do grupo
    return ['class' => 'badge-role-premium badge-role-default', 'label' => ucfirst($groupSlug)];
}

// Helper para texto abaixo do nome do usuário
function getUserSubtitle($groupName, $isOwner = false) {
    if ($isOwner) {
        // Para todos os planos: o owner aparece como Admin (não "Proprietário")
        return 'Admin';
    }
    return $groupName ?? 'Sem grupo';
}

// Verificar se o usuário é o owner do tenant
function isUserOwner($userId, $tenantId, $db) {
    try {
        $stmt = $db->prepare("SELECT owner_user_id FROM tenants WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        return $tenant && (int)$tenant['owner_user_id'] === (int)$userId;
    } catch (Exception $e) {
        return false;
    }
}
?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6">
        <h3 class="mb-0">Usuários da Conta</h3>
        <p class="text-secondary mb-0">
          Gerencie quem pode acessar suas lojas e as permissões gerais da conta.
        </p>
      </div>
      <div class="col-sm-6 mt-2 mt-sm-0">
        <?php if ($tenantId > 0 && isset($tenantLimits['max_users'])): ?>
          <?php 
            $maxUsers = (int)$tenantLimits['max_users'];

            // Percentual bruto (pode passar de 100 se houver inconsistência no banco)
            $rawPercent = $maxUsers > 0 ? (($tenantUsage / $maxUsers) * 100) : 0;
            $usagePercent = $maxUsers > 0 ? min(100, $rawPercent) : 0;

            // IMPORTANTE: para label/badge, usar a contagem (evita bug de arredondamento
            // onde o UI pode mostrar 100% mas o badge ainda cairia em "Limite Próximo")
            $isLimitReached = ($maxUsers > 0 && $tenantUsage >= $maxUsers);

            // Percentual exibido (evita mostrar 100% sem ter atingido de fato)
            $usagePercentLabel = $isLimitReached ? 100 : (int)floor(min(99.0, $rawPercent));

            // Determinar status e label baseado em contagem + percentual
            if ($isLimitReached) {
                $progressStatus = 'danger';
                $badgeLabel = 'Limite Atingido';
            } elseif ($rawPercent >= 90) {
                $progressStatus = 'danger';
                $badgeLabel = 'Limite Próximo';
            } elseif ($rawPercent >= 75) {
                $progressStatus = 'warning';
                $badgeLabel = 'Atenção';
            } else {
                $progressStatus = 'success';
                $badgeLabel = 'Disponível';
            }
            
            // Verificar se pode criar mais usuários
            $canCreateUsers = $maxUsers <= 0 || $tenantUsage < $maxUsers;
            
            // Contar grupos do tenant (limite = max_users)
            $tenantGroupsCount = 0;
            try {
                $stmtGroups = db()->prepare("SELECT COUNT(*) as total FROM user_group WHERE tenant_id = ?");
                $stmtGroups->execute([$tenantId]);
                $tenantGroupsCount = (int)$stmtGroups->fetch(PDO::FETCH_ASSOC)['total'];
            } catch (Exception $e) {
                $tenantGroupsCount = 0;
            }
            $maxGroups = $maxUsers; // Limite de grupos = limite de usuários
            $canCreateGroups = $maxGroups <= 0 || $tenantGroupsCount < $maxGroups;
          ?>
          <div class="usage-card-premium">
            <div class="usage-card-header">
              <div class="usage-card-title">
                <div class="usage-card-icon users">
                  <i class="bi bi-people-fill"></i>
                </div>
                <div>
                  <div class="usage-card-label">Usuários cadastrados</div>
                  <div class="usage-card-sublabel">Limite do seu plano (inclui inativos)</div>
                </div>
              </div>
              <?php if ($maxUsers > 0): ?>
                <span class="usage-card-badge <?php echo $progressStatus; ?>"><?php echo $badgeLabel; ?></span>
              <?php endif; ?>
            </div>
            <div class="usage-card-body">
              <div class="usage-card-numbers">
                <span class="usage-card-current"><?php echo $tenantUsage; ?></span>
                <?php if ($maxUsers > 0): ?>
                  <span class="usage-card-separator">/</span>
                  <span class="usage-card-max"><?php echo $maxUsers; ?></span>
                <?php else: ?>
                  <span class="usage-card-unlimited"><i class="bi bi-infinity"></i> Ilimitado</span>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($maxUsers > 0): ?>
              <div class="usage-progress-container">
                <div class="usage-progress-bar <?php echo $progressStatus; ?>" style="width: <?php echo $usagePercent; ?>%"></div>
              </div>
              <div class="usage-card-footer">
                <span class="usage-card-percent"><?php echo (int)$usagePercentLabel; ?>% utilizado</span>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <!-- Tabs locais: Usuários | Permissões -->
    <ul class="nav nav-pills mb-3 account-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <a
          href="#tab-usuarios"
          class="nav-link <?php echo $tab === 'usuarios' ? 'active' : ''; ?>"
          data-bs-toggle="tab"
          role="tab"
        >
          Usuários com acesso às lojas
        </a>
      </li>
      <li class="nav-item" role="presentation">
        <a
          href="#tab-permissoes"
          class="nav-link <?php echo $tab === 'permissoes' ? 'active' : ''; ?>"
          data-bs-toggle="tab"
          role="tab"
        >
          Permissões gerais
        </a>
      </li>
    </ul>

    <div class="tab-content">
      <!-- Aba: Usuários -->
      <div
        class="tab-pane fade <?php echo $tab === 'usuarios' ? 'show active' : ''; ?>"
        id="tab-usuarios"
        role="tabpanel"
      >
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Usuários com acesso às lojas</h5>
            <button class="btn btn-primary btn-sm" onclick="openAddUserModal(); return false;">
              <i class="bi bi-plus-circle me-2"></i>Adicionar Usuário
            </button>
          </div>
          <div class="card-body table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Usuário</th>
                  <th>E-mail</th>
                  <th>Função na conta</th>
                  <th>Lojas com acesso</th>
                  <th>Status</th>
                  <th class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($tenantUsers)): ?>
                  <tr>
                    <td colspan="6" class="text-center text-secondary py-4">
                      <i class="bi bi-people" style="font-size: 2rem;"></i>
                      <p class="mb-0 mt-2">Nenhum usuário cadastrado neste tenant.</p>
                    </td>
                  </tr>
                <?php else: ?>
                <?php foreach ($tenantUsers as $u): ?>
                    <?php
                      $initials = getUserInitials($u['username']);
                      $userIsOwner = isUserOwner($u['id'], $tenantId, db());
                      $roleBadge = getUserRoleBadge($u['group_slug'] ?? '', $userIsOwner);
                      $userSubtitle = getUserSubtitle($u['group_name'] ?? null, $userIsOwner);
                      $statusBadge = (int)$u['status'] === 1 ? ['class' => 'bg-success', 'label' => 'Ativo'] : ['class' => 'bg-secondary', 'label' => 'Inativo'];
                      $storesDisplay = $u['store_names'] ? htmlspecialchars($u['store_names'], ENT_QUOTES, 'UTF-8') : 'Sem lojas';
                      if ((int)$u['total_stores'] === 0) {
                        $storesDisplay = 'Sem acesso';
                      }
                    ?>
                    <tr>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <div class="user-avatar-initials"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
                          <div>
                            <div><?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <small class="text-secondary"><?php echo htmlspecialchars($userSubtitle, ENT_QUOTES, 'UTF-8'); ?></small>
                          </div>
                        </div>
                      </td>
                      <td><?php echo htmlspecialchars($u['email'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><span class="<?php echo $roleBadge['class']; ?>"><?php echo $roleBadge['label']; ?></span></td>
                      <td><?php echo $storesDisplay; ?></td>
                      <td><span class="badge <?php echo $statusBadge['class']; ?>"><?php echo $statusBadge['label']; ?></span></td>
                      <td class="text-end">
                        <div class="user-action-buttons">
                          <button type="button" class="btn-action-icon btn-action-edit" title="Editar usuário" onclick="openEditUserModalPremium(<?php echo (int)$u['id']; ?>, '<?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($u['mobile'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', <?php echo (int)($u['group_id'] ?? 0); ?>, <?php echo (int)$u['status']; ?>, <?php echo $userIsOwner ? 'true' : 'false'; ?>); return false;">
                            <i class="bi bi-pencil-fill"></i>
                          </button>
                          <button type="button" class="btn-action-icon btn-action-password" title="Alterar Senha" onclick="abrirModalAlterarSenha(<?php echo (int)$u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['username']), ENT_QUOTES, 'UTF-8'); ?>', <?php echo (int)($u['group_id'] ?? 0); ?>, <?php echo $userIsOwner ? 'true' : 'false'; ?>); return false;">
                            <i class="bi bi-key-fill"></i>
                          </button>
                          <button type="button" class="btn-action-icon" title="Histórico de Login" onclick="openLoginHistoryModal(<?php echo (int)$u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['username']), ENT_QUOTES, 'UTF-8'); ?>'); return false;">
                            <i class="bi bi-clock-history"></i>
                          </button>
                          <?php if (!$userIsOwner): ?>
                          <button type="button" class="btn-action-icon btn-action-status <?php echo (int)$u['status'] === 1 ? '' : 'inactive'; ?>" title="<?php echo (int)$u['status'] === 1 ? 'Desativar' : 'Ativar'; ?>" onclick="toggleUserStatus(<?php echo $u['id']; ?>, <?php echo (int)$u['status']; ?>)">
                            <i class="bi bi-<?php echo (int)$u['status'] === 1 ? 'toggle-on' : 'toggle-off'; ?>"></i>
                          </button>
                          <?php else: ?>
                          <button type="button" class="btn-action-icon" style="color: #10b981; cursor: default;" title="Admin - Sempre ativo">
                            <i class="bi bi-shield-check"></i>
                          </button>
                          <?php endif; ?>
                          <?php if (!$userIsOwner): ?>
                          <button type="button" class="btn-action-icon btn-action-delete" title="Excluir usuário" onclick="confirmDeleteUserPremium(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['username']), ENT_QUOTES, 'UTF-8'); ?>', false)">
                            <i class="bi bi-trash-fill"></i>
                          </button>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Aba: Permissões gerais -->
      <div
        class="tab-pane fade <?php echo $tab === 'permissoes' ? 'show active' : ''; ?>"
        id="tab-permissoes"
        role="tabpanel"
      >
        <div class="card mb-3">
          <div class="card-header">
            <h5 class="card-title mb-0">Permissões do Plano</h5>
          </div>
          <div class="card-body">
            <?php if ($tenantId > 0 && class_exists('SaasLimitsBridge')): ?>
              <?php
                try {
                  $planFeatures = SaasLimitsBridge::getPlanFeatures(db(), $tenantId);
                  $isPermissive = in_array('*', $planFeatures, true);
                  $planInfo = SaasLimitsBridge::getTenantPlan(db(), $tenantId);

                  // -----------------------------
                  // Resumo de permissões ativas (por módulo)
                  // -----------------------------
                  $permissionSummaryTotal = $isPermissive ? null : count($planFeatures);
                  $permissionSummaryByModule = [];

                  if (class_exists('SaasLimitsBridge') && method_exists('SaasLimitsBridge', 'getPermissionCatalog')) {
                      $catalog = SaasLimitsBridge::getPermissionCatalog();
                      $keyToModule = [];
                      foreach ($catalog as $moduleName => $items) {
                          if (!is_array($items)) continue;
                          foreach ($items as $it) {
                              if (!is_array($it) || !isset($it['key'])) continue;
                              $keyToModule[(string)$it['key']] = (string)$moduleName;
                          }
                      }

                      if ($isPermissive) {
                          // Plano permissivo: mostra como "Ilimitadas" no total, mas ainda exibe contagem por módulo
                          $permissionSummaryTotal = null;
                          foreach ($catalog as $moduleName => $items) {
                              if (!is_array($items)) continue;
                              $cnt = count($items);
                              $permissionSummaryByModule[(string)$moduleName] = $cnt;
                          }
                      } else {
                          foreach ($planFeatures as $k) {
                              $k = (string)$k;
                              $moduleName = $keyToModule[$k] ?? 'Outros';
                              if (!isset($permissionSummaryByModule[$moduleName])) {
                                  $permissionSummaryByModule[$moduleName] = 0;
                              }
                              $permissionSummaryByModule[$moduleName]++;
                          }
                      }

                      // Ordenar por contagem desc
                      arsort($permissionSummaryByModule);
                  }
                } catch (Exception $e) {
                  $planFeatures = ['*'];
                  $isPermissive = true;
                  $planInfo = null;
                  $permissionSummaryTotal = null;
                  $permissionSummaryByModule = [];
                }
              ?>
              
              <!-- Resumo: Permissões Ativas (por módulo) -->
              <div style="margin-bottom: 16px; padding: 14px 16px; border-radius: 14px; border: 1px solid #e2e8f0; background: #ffffff;">
                <div style="display:flex; align-items:center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                  <div style="display:flex; align-items:center; gap: 10px;">
                    <div style="width:36px; height:36px; border-radius: 12px; display:flex; align-items:center; justify-content:center; background: rgba(124,58,237,0.10); color:#6d28d9;">
                      <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                      <div style="font-weight: 800; color:#0f172a;">Permissões ativas</div>
                      <div style="font-size: 12px; color:#64748b;">Resumo por categoria (módulos do sistema)</div>
                    </div>
                  </div>
                  <div>
                    <?php if ($permissionSummaryTotal === null): ?>
                      <span class="usage-card-badge success">Ilimitadas</span>
                    <?php else: ?>
                      <span class="usage-card-badge success"><?php echo (int)$permissionSummaryTotal; ?> ativas</span>
                    <?php endif; ?>
                  </div>
                </div>

                <?php if (!empty($permissionSummaryByModule)): ?>
                  <div style="margin-top: 12px; display:flex; gap: 8px; flex-wrap: wrap;">
                    <?php foreach ($permissionSummaryByModule as $moduleName => $cnt): ?>
                      <span style="display:inline-flex; align-items:center; gap:8px; padding: 7px 10px; border-radius: 999px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 12px; font-weight: 700; color:#0f172a;">
                        <span><?php echo htmlspecialchars($moduleName, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span style="display:inline-flex; align-items:center; justify-content:center; min-width: 26px; height: 20px; padding: 0 8px; border-radius: 999px; background: #0f172a; color: #fff; font-size: 11px; font-weight: 800;">
                          <?php echo (int)$cnt; ?>
                        </span>
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

              <?php if ($isPermissive): ?>
                <!-- Alert Premium - Todas as Permissões -->
                <div class="alert-premium alert-premium-success">
                  <div class="alert-premium-title">
                    <i class="bi bi-shield-check alert-icon"></i>
                    <span>Plano com Acesso Completo</span>
                    <?php if ($planInfo && isset($planInfo['name'])): ?>
                      <span class="badge-role-premium badge-role-owner" style="margin-left: 12px; font-size: 11px;"><?php echo htmlspecialchars($planInfo['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="alert-premium-text">
                    <strong>Parabéns!</strong> Seu plano oferece acesso ilimitado a todas as funcionalidades do sistema.
                    Todos os usuários podem utilizar qualquer recurso, sendo limitados apenas pelas permissões 
                    individuais definidas nos Grupos de Acesso (RBAC) abaixo.
                  </div>
                  <div style="margin-top: 16px; display: flex; gap: 20px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <i class="bi bi-infinity" style="color: #10b981; font-size: 18px;"></i>
                      <span style="font-size: 13px; font-weight: 600;">Funcionalidades ilimitadas</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <i class="bi bi-people-fill" style="color: #10b981; font-size: 18px;"></i>
                      <span style="font-size: 13px; font-weight: 600;">Controle por grupos RBAC</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <i class="bi bi-lightning-charge-fill" style="color: #10b981; font-size: 18px;"></i>
                      <span style="font-size: 13px; font-weight: 600;">Sem restrições de plano</span>
                    </div>
                  </div>
                </div>
              <?php else: ?>
                <div class="alert alert-info" role="alert">
                  <i class="bi bi-info-circle-fill me-2"></i>
                  <strong>Permissões restritas por plano</strong>
                  <p class="mb-0 mt-2">
                    O plano atual possui <strong><?php echo count($planFeatures); ?> permissões específicas</strong>. Apenas as funcionalidades listadas abaixo estarão disponíveis para os usuários deste tenant.
                  </p>
                </div>
                
                <div class="mt-3">
                  <h6 class="mb-3">Permissões liberadas neste plano:</h6>
                  <div class="row g-2">
                    <?php foreach ($planFeatures as $feature): ?>
                      <div class="col-md-4 col-lg-3">
                        <div class="d-flex align-items-center">
                          <i class="bi bi-check-circle text-success me-2"></i>
                          <small><code><?php echo htmlspecialchars($feature, ENT_QUOTES, 'UTF-8'); ?></code></small>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
              
              <hr class="my-4">
              
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-3" style="background: #f8fafc; padding: 16px 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <div>
                  <h6 class="mb-1" style="font-weight: 700; color: #0f172a;">Grupos de Acesso (RBAC)</h6>
                  <p class="text-secondary mb-0 small">
                    Crie e gerencie grupos de permissões para controlar o acesso dos usuários.
                  </p>
                </div>
                <button class="btn-save-premium" onclick="openCreateGroupModal(); return false;">
                  <i class="bi bi-plus-circle"></i> Criar Novo Grupo
                </button>
              </div>
              
            <?php else: ?>
              <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Modo single-tenant</strong>
                <p class="mb-0 mt-2">
                  O sistema não está rodando em modo multi-tenant ou o tenant_id não foi detectado.
                </p>
              </div>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- Card adicional: Grupos de Acesso -->
        <div class="card mb-3">
          <div class="card-header">
            <h5 class="card-title mb-0">Lista de Grupos Disponíveis</h5>
          </div>
          <div class="card-body">
            
            <?php if ($tenantId > 0): ?>
              <?php
                try {
                  // Buscar o plano do tenant para filtrar grupos
                  $tenantPlanInfo = SaasLimitsBridge::getTenantPlan(db(), $tenantId);
                  $planName = (isset($tenantPlanInfo['name']) && $tenantPlanInfo['name']) ? strtolower($tenantPlanInfo['name']) : '';
                  
                  // Buscar apenas grupos do tenant (excluindo Admin)
                  $stmt = db()->prepare("
                    SELECT ug.group_id, ug.name, ug.slug, ug.tenant_id,
                           COUNT(DISTINCT u.id) as total_users
                    FROM user_group ug
                    LEFT JOIN users u ON u.group_id = ug.group_id AND u.tenant_id = ?
                    WHERE (ug.tenant_id = ? OR ug.tenant_scope = ?) AND ug.group_id != 1
                    GROUP BY ug.group_id
                    ORDER BY ug.group_id ASC
                  ");
                  $stmt->execute([$tenantId, $tenantId, $tenantId]);
                  $allGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

                  // Lista para modal de realocação (não filtra por plano)
                  $groupsForReassign = [];
                  foreach ($allGroups as $gg) {
                    $gid2 = (int)$gg['group_id'];
                    if ($gid2 === 1) continue;
                    $groupsForReassign[] = [
                      'id' => $gid2,
                      'name' => (string)$gg['name'],
                      'tenant_id' => $gg['tenant_id'],
                    ];
                  }
                  
                  // Filtrar grupos: mostrar apenas grupos do plano atual
                  $groups = [];
                  foreach ($allGroups as $g) {
                    $gid = (int)$g['group_id'];
                    $gname = strtolower($g['name']);
                    
                    // NÃO incluir Admin (group_id = 1) - apenas grupos do tenant
                    if ($gid === 1) {
                      continue;
                    }
                    
                    // Incluir grupos do tenant específico
                    if ($g['tenant_id'] !== null && (int)$g['tenant_id'] === $tenantId) {
                      $groups[] = $g;
                      continue;
                    }
                    
                    // Incluir grupos que correspondem ao plano atual
                    if ($planName && stripos($gname, $planName) !== false) {
                      $groups[] = $g;
                    }
                  }
                } catch (Exception $e) {
                  $groups = [];
                  error_log('Erro ao buscar grupos: ' . $e->getMessage());
                }
              ?>
              
              <?php if (!empty($groups)): ?>
                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Grupo</th>
                        <th>Tipo</th>
                        <th>Usuários</th>
                        <th class="text-end">Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($groups as $g): ?>
                        <?php
                          $isSystem = $g['tenant_id'] === null;
                          $typeBadge = $isSystem ? '<span class="badge bg-secondary">Sistema</span>' : '<span class="badge bg-primary">Tenant</span>';
                        ?>
                        <tr>
                          <td>
                            <strong><?php echo htmlspecialchars($g['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <br>
                            <small class="text-secondary"><?php echo htmlspecialchars($g['slug'], ENT_QUOTES, 'UTF-8'); ?></small>
                          </td>
                          <td><?php echo $typeBadge; ?></td>
                          <td><?php echo (int)$g['total_users']; ?> usuário(s)</td>
                          <td class="text-end">
                            <?php if (!$isSystem): ?>
                              <button class="btn btn-outline-secondary btn-sm" onclick="openEditGroupModalPremium(<?php echo (int)$g['group_id']; ?>, '<?php echo htmlspecialchars(addslashes($g['name']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($g['slug'], ENT_QUOTES, 'UTF-8'); ?>'); return false;" title="Editar grupo">
                                <i class="bi bi-pencil"></i>
                              </button>
                              <button class="btn btn-outline-danger btn-sm" onclick="confirmDeleteGroupPremium(<?php echo (int)$g['group_id']; ?>, '<?php echo htmlspecialchars(addslashes($g['name']), ENT_QUOTES, 'UTF-8'); ?>', <?php echo (int)$g['total_users']; ?>); return false;" title="Excluir grupo<?php echo ((int)$g['total_users'] > 0) ? ' (realocar usuários)' : ''; ?>">
                                <i class="bi bi-trash"></i>
                              </button>
                            <?php else: ?>
                              <button class="btn btn-outline-secondary btn-sm" disabled title="Grupo do sistema não pode ser editado">
                                <i class="bi bi-lock"></i>
                              </button>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p class="text-secondary mb-0">Nenhum grupo de acesso encontrado.</p>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// =========================================================
// JavaScript para ações da página de usuários
// Integração com modais AngularJS do ModernPOS
// =========================================================

/**
 * Abrir modal de adicionar usuário
 */
function openAddUserModal() {
  // Limpar formulário
  document.getElementById('addUserForm').reset();
  
  // Abrir modal
  const modal = new bootstrap.Modal(document.getElementById('addUserModal'));
  modal.show();
}

/**
 * Salvar novo usuário (estático - apenas exibe dados)
 */
function saveNewUser() {
  const name = document.getElementById('newUserName').value.trim();
  const email = document.getElementById('newUserEmail').value.trim();
  const mobile = document.getElementById('newUserMobile').value.trim();
  const password = document.getElementById('newUserPassword').value;
  const groupId = document.getElementById('newUserGroup').value;
  const status = document.getElementById('newUserStatus').checked ? 1 : 0;
  
  // Validações básicas
  if (!name) {
    alert('Nome é obrigatório');
    return;
  }
  if (!email) {
    alert('E-mail é obrigatório');
    return;
  }
  if (!password || password.length < 6) {
    alert('Senha deve ter no mínimo 6 caracteres');
    return;
  }
  if (!groupId) {
    alert('Selecione um grupo de permissão');
    return;
  }
  
  // Coletar lojas selecionadas
  const storeCheckboxes = document.querySelectorAll('#addUserModal input[name="user_stores[]"]');
  const selectedStores = [];
  storeCheckboxes.forEach(cb => {
    if (cb.checked) selectedStores.push(cb.value);
  });
  
  if (selectedStores.length === 0) {
    alert('Selecione pelo menos uma loja');
    return;
  }
  
  // Exibir dados coletados (modo estático para teste)
  const userData = {
    username: name,
    email: email,
    mobile: mobile,
    password: '******',
    group_id: groupId,
    stores: selectedStores,
    status: status
  };
  
  console.log('Dados do novo usuário:', userData);
  
  // Por enquanto, mostrar confirmação e fechar modal
  alert(`✅ Usuário preparado para criação:\n\nNome: ${name}\nE-mail: ${email}\nGrupo: ${groupId}\nLojas: ${selectedStores.join(', ')}\nStatus: ${status ? 'Ativo' : 'Inativo'}\n\n⚠️ A API de criação será implementada em breve.`);
  
  // Fechar modal
  const modal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
  if (modal) modal.hide();
}

/**
 * Função legada - redireciona para interface AngularJS
 */
function openCreateUserModal() {
  // Agora usa a nova modal
  openAddUserModal();
}

/**
 * Abrir modal de edição de usuário
 */
function openEditUserModal(userId, username) {
  alert('⚠️ Funcionalidade em desenvolvimento.\n\nPor enquanto, use a interface principal do ModernPOS para editar usuários.\n\nVocê será redirecionado.');
  setTimeout(function() {
    window.parent.location.href = '<?php echo root_url(); ?>app.php#/pos/user/edit/' + userId;
  }, 1000);
}

/**
 * Alternar status do usuário (Ativar/Desativar)
 * IMPORTANTE: Utiliza a mesma rota do UPDATE completo
 */
function toggleUserStatus(userId, currentStatus) {
  const newStatus = currentStatus === 1 ? 0 : 1;
  const action = newStatus === 1 ? 'ativar' : 'desativar';
  
  if (!confirm(`Tem certeza que deseja ${action} este usuário?`)) {
    return;
  }
  
  // Buscar dados completos do usuário para fazer UPDATE completo
  fetch(`<?php echo root_url(); ?>_inc/user.php?id=${userId}&action_type=EDIT`)
    .then(response => response.text())
    .then(html => {
      // Parse do HTML para extrair valores do formulário
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      const form = doc.querySelector('form');
      
      if (!form) {
        throw new Error('Formulário não encontrado');
      }
      
      // Coletar todos os dados do formulário
      const formData = new URLSearchParams();
      formData.append('action_type', 'UPDATE');
      formData.append('id', userId);
      formData.append('status', newStatus);
      
      // Campos obrigatórios
      const username = form.querySelector('[name="username"]')?.value || '';
      const email = form.querySelector('[name="email"]')?.value || '';
      const mobile = form.querySelector('[name="mobile"]')?.value || '';
      const group_id = form.querySelector('[name="group_id"]')?.value || '';
      const dob = form.querySelector('[name="dob"]')?.value || '';
      const sort_order = form.querySelector('[name="sort_order"]')?.value || '0';
      const user_image = form.querySelector('[name="user_image"]')?.value || '';
      
      formData.append('username', username);
      formData.append('email', email);
      formData.append('mobile', mobile);
      formData.append('group_id', group_id);
      formData.append('dob', dob);
      formData.append('sort_order', sort_order);
      formData.append('user_image', user_image);
      
      // Coletar lojas selecionadas (checkboxes)
      const storeCheckboxes = form.querySelectorAll('[name="user_store[]"]');
      storeCheckboxes.forEach(checkbox => {
        if (checkbox.checked) {
          formData.append('user_store[]', checkbox.value);
        }
      });
      
      // Fazer UPDATE completo
      return fetch('<?php echo root_url(); ?>_inc/user.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData.toString()
      });
    })
    .then(response => response.json())
    .then(data => {
      if (data.msg) {
        alert(`Usuário ${action === 'ativar' ? 'ativado' : 'desativado'} com sucesso!`);
        window.location.reload();
      } else {
        throw new Error(data.errorMsg || 'Erro desconhecido');
      }
    })
    .catch(error => {
      console.error('Erro ao alterar status:', error);
      alert('Erro ao alterar status: ' + error.message);
    });
}

/**
 * Confirmar exclusão de usuário
 */
function confirmDeleteUser(userId, username) {
  if (confirm(`ATENÇÃO: Deseja realmente excluir o usuário "${username}"?\n\nEsta ação não pode ser desfeita.`)) {
    // Redirecionar para interface do ModernPOS
    window.parent.location.href = '<?php echo root_url(); ?>app.php#/pos/user/delete/' + userId;
  }
}

// =========================================================
// Gestão de Grupos RBAC
// =========================================================

/**
 * Abrir modal de criação de grupo
 */
function openCreateGroupModal() {
  // Buscar permissões disponíveis
  fetch('<?php echo root_url(); ?>api/groups/get_available_permissions.php')
    .then(response => response.json())
    .then(data => {
      if (!data.success) {
        throw new Error(data.error || 'Erro ao buscar permissões');
      }
      
      // Preencher modal com permissões
      const permissionsContainer = document.getElementById('groupPermissionsContainer');
      permissionsContainer.innerHTML = '';
      
      // Adicionar alerta com info do plano
      const planInfo = document.createElement('div');
      planInfo.className = 'alert alert-info mb-3';
      planInfo.innerHTML = `
        <i class="bi bi-info-circle me-2"></i>
        <strong>Plano: ${data.plan.name}</strong><br>
        <small>${data.total_available} permissões disponíveis${data.plan.is_permissive ? ' (Todas liberadas)' : ''}</small>
      `;
      permissionsContainer.appendChild(planInfo);
      
      // Adicionar permissões por categoria
      for (const [category, permissions] of Object.entries(data.permissions)) {
        const categoryDiv = document.createElement('div');
        categoryDiv.className = 'mb-3';
        
        const categoryTitle = document.createElement('h6');
        categoryTitle.className = 'text-primary mb-2';
        categoryTitle.textContent = category;
        categoryDiv.appendChild(categoryTitle);
        
        for (const [permKey, permLabel] of Object.entries(permissions)) {
          const checkDiv = document.createElement('div');
          checkDiv.className = 'form-check';
          checkDiv.innerHTML = `
            <input class="form-check-input" type="checkbox" value="${permKey}" id="perm_${permKey}" name="permissions[]">
            <label class="form-check-label" for="perm_${permKey}">
              <small><code>${permKey}</code></small> - ${permLabel}
            </label>
          `;
          categoryDiv.appendChild(checkDiv);
        }
        
        permissionsContainer.appendChild(categoryDiv);
      }
      
      // Abrir modal
      const modal = new bootstrap.Modal(document.getElementById('createGroupModal'));
      modal.show();
    })
    .catch(error => {
      console.error('Erro:', error);
      alert('Erro ao carregar permissões: ' + error.message);
    });
}

/**
 * Salvar novo grupo
 */
function saveNewGroup() {
  const name = document.getElementById('groupName').value.trim();
  const slug = document.getElementById('groupSlug').value.trim();
  
  if (!name) {
    alert('Nome do grupo é obrigatório');
    return;
  }
  
  // Coletar permissões selecionadas
  const permissionsCheckboxes = document.querySelectorAll('#groupPermissionsContainer input[type="checkbox"]:checked');
  const permissions = { access: {} };
  
  permissionsCheckboxes.forEach(checkbox => {
    permissions.access[checkbox.value] = true;
  });
  
  // Enviar para API
  fetch('<?php echo root_url(); ?>api/groups/create.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      name: name,
      slug: slug,
      permissions: permissions
    })
  })
  .then(response => response.json())
  .then(data => {
    if (!data.success) {
      throw new Error(data.error || 'Erro ao criar grupo');
    }
    
    alert('Grupo criado com sucesso!');
    window.location.reload();
  })
  .catch(error => {
    console.error('Erro:', error);
    alert('Erro ao criar grupo: ' + error.message);
  });
}
</script>

<!-- Modal Premium: Adicionar Usuário -->
<div id="modalAddUserPremium" class="modal-premium-overlay" data-modal="add-user">
  <div class="modal-premium-card" style="max-width: 600px;">
    <div class="modal-premium-header">
      <h4 class="modal-premium-title">
        <i class="bi bi-person-plus-fill"></i>
        Adicionar Novo Usuário
      </h4>
      <button class="btn-close-premium" onclick="fecharModalPremium('add-user')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body" style="max-height: 70vh; overflow-y: auto;">
      <input type="hidden" id="addUserId">
      
      <!-- 1. Informações Pessoais -->
      <span class="form-section-title">Informações Pessoais</span>
      
      <div class="form-row-premium">
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-person ic-modal"></i> Nome Completo *
          </label>
          <input type="text" id="addUserName" class="input-premium" placeholder="Ex: João da Silva">
        </div>
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-envelope ic-modal"></i> E-mail *
          </label>
          <input type="email" id="addUserEmail" class="input-premium" placeholder="joao@empresa.com">
        </div>
      </div>
      
      <div class="form-row-premium">
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-phone ic-modal"></i> Celular
          </label>
          <input type="text" id="addUserMobile" class="input-premium" placeholder="(00) 00000-0000" inputmode="numeric" maxlength="15" autocomplete="tel">
        </div>
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-calendar-date ic-modal"></i> Data de Nascimento
          </label>
          <input type="date" id="addUserDob" class="input-premium">
        </div>
      </div>
      
      <!-- 2. Acesso e Permissões -->
      <span class="form-section-title" style="margin-top: 28px;">Acesso e Permissões</span>
      
      <div class="form-row-premium">
        <div class="form-group-premium" style="grid-column: 1 / -1;">
          <label class="label-premium">
            <i class="bi bi-people-fill ic-modal"></i> Grupo de Permissão *
          </label>
          <?php if (!empty($currentUserIsAdmin)): ?>
            <select id="addUserGroup" class="input-premium" style="padding-right: 40px;">
              <option value="">Selecione um grupo...</option>
              <?php
              if ($tenantId > 0 && !empty($availableGroups)) {
                  foreach ($availableGroups as $grp) {
                      echo '<option value="' . (int)$grp['group_id'] . '">' . htmlspecialchars($grp['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                  }
              }
              ?>
            </select>
          <?php else: ?>
            <div class="delete-blocked-message" style="display:flex;">
              <i class="bi bi-lock-fill"></i>
              <span>Somente Administrador pode alterar grupo de permissão.</span>
            </div>
            <select id="addUserGroup" class="input-premium" style="display:none;" disabled>
              <option value="">Selecione um grupo...</option>
              <?php
              if ($tenantId > 0 && !empty($availableGroups)) {
                  foreach ($availableGroups as $grp) {
                      echo '<option value="' . (int)$grp['group_id'] . '">' . htmlspecialchars($grp['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                  }
              }
              ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="form-group-premium" style="grid-column: 1 / -1;">
          <label class="label-premium">
            <i class="bi bi-shop ic-modal"></i> Lojas Vinculadas *
          </label>
          <div class="checkbox-list-container" id="addUserStoresContainer">
            <?php
            if ($tenantId > 0 && !empty($availableStores)) {
                foreach ($availableStores as $store) {
                    echo '<label class="checkbox-list-item">';
                    echo '<input type="checkbox" class="add-user-store-checkbox" value="' . (int)$store['store_id'] . '">';
                    echo '<span>' . htmlspecialchars($store['name'], ENT_QUOTES, 'UTF-8') . '</span>';
                    echo '</label>';
                }
            } else {
                echo '<div style="padding: 12px; color: #64748b; text-align: center;">Nenhuma loja disponível</div>';
            }
            ?>
          </div>
          <small style="color: #64748b; font-size: 12px; margin-top: 6px; display: block;">Selecione uma ou mais lojas que o usuário terá acesso</small>
        </div>
      </div>
      
      <div class="form-row-premium">
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-sort-numeric-up ic-modal"></i> Ordem
          </label>
          <input type="number" id="addUserOrder" class="input-premium" placeholder="0" min="0" value="0">
        </div>
        <div class="form-group-premium">
          <label class="label-premium">Status do Usuário</label>
          <div style="display: flex; align-items: center; gap: 12px; margin-top: 8px; height: 48px;">
            <div id="addUserStatusToggle" class="toggle-switch-premium active" onclick="toggleAddUserStatus()">
              <div class="circle"><i class="bi bi-check-lg"></i></div>
            </div>
            <span id="addUserStatusLabel" style="font-weight: 600; color: #334155; font-size: 14px;">Ativo</span>
          </div>
        </div>
      </div>
      
      <!-- 3. Credenciais de Acesso (Destacada) -->
      <div class="credentials-section-highlight">
        <span class="form-section-title" style="margin-top: 0;">
          <i class="bi bi-shield-lock" style="color: #7c3aed;"></i> Credenciais de Acesso
        </span>
        
        <div class="form-row-premium">
          <div class="form-group-premium">
            <label class="label-premium">
              <i class="bi bi-key ic-modal"></i> Senha *
            </label>
            <input type="password" id="addUserPassword" class="input-premium" placeholder="Mínimo 6 caracteres">
          </div>
          <div class="form-group-premium">
            <label class="label-premium">
              <i class="bi bi-key-fill ic-modal"></i> Repetir Senha *
            </label>
            <input type="password" id="addUserPasswordConfirm" class="input-premium" placeholder="Confirme a senha">
          </div>
        </div>
      </div>
    </div>
    <div class="modal-premium-footer">
      <button class="btn-cancel-premium" onclick="fecharModalPremium('add-user')">Cancelar</button>
      <button class="btn-save-premium" onclick="saveAddUserPremium()">
        <i class="bi bi-person-plus"></i> Criar Usuário
      </button>
    </div>
  </div>
</div>

<!-- =============================================
     MODAIS PREMIUM (Design SaaS)
     ============================================= -->

<!-- Modal Premium: Criar Grupo -->
<div id="modalCreateGroupPremium" class="modal-premium-overlay" data-modal="create-group">
  <div class="modal-premium-card modal-wide">
    <div class="modal-premium-header">
      <h4 class="modal-premium-title">
        <i class="bi bi-people-fill"></i>
        Criar Grupo de Permissões
      </h4>
      <button class="btn-close-premium" onclick="fecharModalPremium('create-group')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body">
      <span class="form-section-title">Informações do Grupo</span>
      
      <div class="form-row-premium">
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-tag ic-modal"></i> Nome do Grupo *
          </label>
          <input type="text" id="premiumGroupName" class="input-premium" placeholder="Ex: Gerente de Vendas">
        </div>
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-hash ic-modal"></i> Identificador (Slug)
          </label>
          <input type="text" id="premiumGroupSlug" class="input-premium" placeholder="gerente_vendas (auto)">
          <small style="color: #64748b; font-size: 12px;">Deixe vazio para gerar automaticamente</small>
        </div>
      </div>
      
      <span class="form-section-title" style="margin-top: 28px;">Permissões do Grupo</span>
      <p style="font-size: 13px; color: #64748b; margin-bottom: 15px;">
        Selecione as permissões que este grupo terá. Apenas permissões disponíveis no seu plano aparecerão.
      </p>
      
      <!-- Toolbar de Busca e Stats -->
      <div class="plan-features-toolbar">
        <div class="plan-features-search">
          <label class="label-premium" for="groupPermSearch">Buscar permissão</label>
          <input id="groupPermSearch" type="text" class="input-premium" placeholder="Digite para filtrar (ex.: product, sell, report...)" oninput="filterGroupPermissions(this.value)">
        </div>
        <div class="plan-features-stats">
          <div class="plan-features-stat">
            <div class="k">Total</div>
            <div class="v" id="groupPermTotal">0</div>
          </div>
          <div class="plan-features-stat">
            <div class="k">Selecionadas</div>
            <div class="v" id="groupPermSelected">0</div>
          </div>
        </div>
      </div>
      
      <!-- Ações Rápidas -->
      <div class="plan-features-actions">
        <label class="plan-features-allowall">
          <input type="checkbox" id="groupSelectAll" onchange="toggleAllGroupPermissions(this.checked)">
          <span><b>Selecionar todas</b></span>
        </label>
        <div class="plan-features-buttons">
          <button type="button" class="btn-cancel-premium" onclick="clearAllGroupPermissions()">Limpar</button>
        </div>
      </div>
      
      <!-- Grid de Permissões -->
      <div id="premiumGroupPermissionsGrid" class="plan-features-grid">
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Carregando...</span>
          </div>
          <p style="margin-top: 12px; color: #64748b;">Carregando permissões...</p>
        </div>
      </div>
    </div>
    <div class="modal-premium-footer">
      <button class="btn-cancel-premium" onclick="fecharModalPremium('create-group')">Cancelar</button>
      <button class="btn-save-premium" onclick="saveGroupPremium()">
        <i class="bi bi-check-lg"></i> Criar Grupo
      </button>
    </div>
  </div>
</div>

<!-- Modal Premium: Editar Grupo -->
<div id="modalEditGroupPremium" class="modal-premium-overlay" data-modal="edit-group">
  <div class="modal-premium-card modal-wide">
    <div class="modal-premium-header">
      <h4 class="modal-premium-title">
        <i class="bi bi-pencil-square"></i>
        Editar Grupo de Permissões
      </h4>
      <button class="btn-close-premium" onclick="fecharModalPremium('edit-group')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body">
      <input type="hidden" id="editGroupId">
      
      <span class="form-section-title">Informações do Grupo</span>
      
      <div class="form-row-premium">
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-tag ic-modal"></i> Nome do Grupo *
          </label>
          <input type="text" id="editGroupName" class="input-premium" placeholder="Ex: Gerente de Vendas">
        </div>
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-hash ic-modal"></i> Identificador (Slug)
          </label>
          <input type="text" id="editGroupSlug" class="input-premium" placeholder="gerente_vendas" readonly style="background: #f1f5f9;">
        </div>
      </div>
      
      <span class="form-section-title" style="margin-top: 28px;">Permissões do Grupo</span>
      
      <!-- Grid de Permissões (Edit) -->
      <div id="editGroupPermissionsGrid" class="plan-features-grid">
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Carregando...</span>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-premium-footer">
      <button class="btn-cancel-premium" onclick="fecharModalPremium('edit-group')">Cancelar</button>
      <button class="btn-save-premium" onclick="updateGroupPremium()">
        <i class="bi bi-check-lg"></i> Salvar Alterações
      </button>
    </div>
  </div>
</div>

<!-- Modal Premium: Editar Usuário -->
<div id="modalEditUserPremium" class="modal-premium-overlay" data-modal="edit-user">
  <div class="modal-premium-card" style="max-width: 600px;">
    <div class="modal-premium-header">
      <h4 class="modal-premium-title">
        <i class="bi bi-person-gear"></i>
        Editar Usuário
      </h4>
      <button class="btn-close-premium" onclick="fecharModalPremium('edit-user')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body" style="max-height: 70vh; overflow-y: auto;">
      <input type="hidden" id="editUserId">
      
      <span class="form-section-title">Informações Pessoais</span>
      
      <div class="form-row-premium">
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-person ic-modal"></i> Nome Completo *
          </label>
          <input type="text" id="editUserName" class="input-premium" placeholder="Ex: João da Silva">
        </div>
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-envelope ic-modal"></i> E-mail *
          </label>
          <input type="email" id="editUserEmail" class="input-premium" placeholder="joao@empresa.com">
        </div>
      </div>
      
      <div class="form-row-premium">
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-phone ic-modal"></i> Celular
          </label>
          <input type="text" id="editUserMobile" class="input-premium" placeholder="(00) 00000-0000" inputmode="numeric" maxlength="15" autocomplete="tel">
        </div>
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-calendar-date ic-modal"></i> Data de Nascimento
          </label>
          <input type="date" id="editUserDob" class="input-premium">
        </div>
      </div>
      
      <span class="form-section-title" style="margin-top: 28px;">Acesso e Permissões</span>
      
      <div class="form-row-premium">
        <div class="form-group-premium" style="grid-column: 1 / -1;">
          <label class="label-premium">
            <i class="bi bi-people-fill ic-modal"></i> Grupo de Permissão *
          </label>
          <?php if (!empty($currentUserIsAdmin)): ?>
            <select id="editUserGroup" class="input-premium" style="padding-right: 40px;">
              <option value="">Selecione um grupo...</option>
              <?php
              if ($tenantId > 0 && !empty($availableGroups)) {
                  foreach ($availableGroups as $grp) {
                      echo '<option value="' . (int)$grp['group_id'] . '">' . htmlspecialchars($grp['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                  }
              }
              ?>
            </select>
            <!-- Aviso quando for Owner -->
            <div id="ownerGroupWarning" class="delete-blocked-message" style="display:none; margin-top: 8px;">
              <i class="bi bi-shield-lock-fill"></i>
              <span>O proprietário da conta não pode ter seu grupo alterado.</span>
            </div>
          <?php else: ?>
            <div class="delete-blocked-message" style="display:flex;">
              <i class="bi bi-lock-fill"></i>
              <span>Somente Administrador pode alterar grupo de permissão.</span>
            </div>
            <select id="editUserGroup" class="input-premium" style="display:none;" disabled>
              <option value="">Selecione um grupo...</option>
              <?php
              if ($tenantId > 0 && !empty($availableGroups)) {
                  foreach ($availableGroups as $grp) {
                      echo '<option value="' . (int)$grp['group_id'] . '">' . htmlspecialchars($grp['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                  }
              }
              ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="form-group-premium" style="grid-column: 1 / -1;">
          <label class="label-premium">
            <i class="bi bi-shop ic-modal"></i> Lojas Vinculadas *
          </label>
          <div class="checkbox-list-container" id="editUserStoresContainer">
            <?php
            if ($tenantId > 0 && !empty($availableStores)) {
                foreach ($availableStores as $store) {
                    echo '<label class="checkbox-list-item">';
                    echo '<input type="checkbox" class="edit-user-store-checkbox" value="' . (int)$store['store_id'] . '">';
                    echo '<span>' . htmlspecialchars($store['name'], ENT_QUOTES, 'UTF-8') . '</span>';
                    echo '</label>';
                }
            } else {
                echo '<div style="padding: 12px; color: #64748b; text-align: center;">Nenhuma loja disponível</div>';
            }
            ?>
          </div>
          <small style="color: #64748b; font-size: 12px; margin-top: 6px; display: block;">Selecione uma ou mais lojas que o usuário terá acesso</small>
        </div>
      </div>
      
      <div class="form-row-premium">
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-sort-numeric-up ic-modal"></i> Ordem
          </label>
          <input type="number" id="editUserOrder" class="input-premium" placeholder="0" min="0" value="0">
        </div>
        <div class="form-group-premium">
          <label class="label-premium">Status do Usuário</label>
          <div style="display: flex; align-items: center; gap: 12px; margin-top: 8px; height: 48px;">
            <div id="editUserStatusToggle" class="toggle-switch-premium active" onclick="toggleEditUserStatus()">
              <div class="circle"><i class="bi bi-check-lg"></i></div>
            </div>
            <span id="editUserStatusLabel" style="font-weight: 600; color: #334155; font-size: 14px;">Ativo</span>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-premium-footer">
      <button class="btn-cancel-premium" onclick="fecharModalPremium('edit-user')">Cancelar</button>
      <button class="btn-save-premium" onclick="saveEditUserPremium()">
        <i class="bi bi-check-lg"></i> Salvar Alterações
      </button>
    </div>
  </div>
</div>

<!-- Modal Premium: Histórico de Login -->
<div id="modalLoginHistory" class="modal-premium-overlay" data-modal="login-history">
  <div class="modal-premium-card" style="max-width: 760px;">
    <div class="modal-premium-header" style="background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);">
      <h4 class="modal-premium-title">
        <i class="bi bi-clock-history"></i>
        Histórico de Login
      </h4>
      <button class="btn-close-premium" onclick="fecharModalPremium('login-history')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body" style="max-height: 70vh; overflow-y: auto;">
      <input type="hidden" id="loginHistoryUserId">

      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px 16px; margin-bottom: 16px;">
        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Usuário</div>
        <div id="loginHistoryUserName" style="margin-top: 4px; font-size: 16px; color: #0f172a; font-weight: 700;"></div>
      </div>

      <div id="loginHistoryContent" style="border: 1px solid #e2e8f0; border-radius: 14px; background: #ffffff; overflow: hidden;">
        <div style="padding: 16px; text-align:center; color:#64748b;">
          Carregando...
        </div>
      </div>
    </div>
    <div class="modal-premium-footer" style="justify-content: center;">
      <button class="btn-cancel-premium" onclick="fecharModalPremium('login-history')">Fechar</button>
    </div>
  </div>
</div>

<!-- Modal Premium: Alterar Senha (Estilo ModernPOS) -->
<div id="modalAlterarSenha" class="modal-premium-overlay" data-modal="alterar-senha">
  <div class="modal-premium-card" style="max-width: 480px;">
    <div class="modal-premium-header" style="background: linear-gradient(135deg, #7c3aed 0%, #2563eb 100%);">
      <h4 class="modal-premium-title">
        <i class="bi bi-key-fill"></i>
        Alterar Senha
      </h4>
      <button class="btn-close-premium" onclick="fecharModalPremium('alterar-senha')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body">
      <input type="hidden" id="alterarSenhaUserId">
      
      <!-- Info do usuário -->
      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px 16px; margin-bottom: 24px;">
        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Usuário</div>
        <div id="alterarSenhaUserName" style="margin-top: 4px; font-size: 16px; color: #0f172a; font-weight: 600;">Nome do Usuário</div>
      </div>
      
      <div class="form-group-premium">
        <label class="label-premium">
          <i class="bi bi-key ic-modal"></i> Nova Senha *
        </label>
        <div style="position: relative;">
          <input type="password" id="novaSenhaInput" class="input-premium" placeholder="Digite a nova senha" style="padding-right: 50px;">
          <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility('novaSenhaInput', this)" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); border: none; background: none; color: #94a3b8; cursor: pointer; padding: 4px;">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>
      
      <div class="form-group-premium">
        <label class="label-premium">
          <i class="bi bi-key-fill ic-modal"></i> Confirmar Senha *
        </label>
        <div style="position: relative;">
          <input type="password" id="confirmarSenhaInput" class="input-premium" placeholder="Confirme a nova senha" style="padding-right: 50px;">
          <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility('confirmarSenhaInput', this)" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); border: none; background: none; color: #94a3b8; cursor: pointer; padding: 4px;">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>
      
      <div style="background: linear-gradient(135deg, rgba(124, 58, 237, 0.08), rgba(37, 99, 235, 0.08)); border-radius: 12px; padding: 12px 14px; margin-top: 16px;">
        <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #4b5563;">
          <i class="bi bi-info-circle" style="color: #7c3aed;"></i>
          <span>A senha deve ter no mínimo 6 caracteres.</span>
        </div>
      </div>
    </div>
    <div class="modal-premium-footer">
      <button class="btn-cancel-premium" onclick="fecharModalPremium('alterar-senha')" style="border-color: rgba(124, 58, 237, 0.3); color: #6b21a8;">
        Cancelar
      </button>
      <button class="btn-save-premium" onclick="salvarNovaSenha()">
        <i class="bi bi-check-lg"></i> Alterar Senha
      </button>
    </div>
  </div>
</div>

<!-- Modal Premium: Confirmar Exclusão de Usuário -->
<div id="modalDeleteUserPremium" class="modal-premium-overlay" data-modal="delete-user">
  <div class="modal-premium-card" style="max-width: 480px;">
    <div class="modal-premium-header" style="background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);">
      <h4 class="modal-premium-title">
        <i class="bi bi-exclamation-triangle"></i>
        Excluir Usuário
      </h4>
      <button class="btn-close-premium" onclick="fecharModalPremium('delete-user')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body" style="text-align: center;">
      <input type="hidden" id="deleteUserId">
      
      <div class="delete-warning-icon">
        <i class="bi bi-person-x"></i>
      </div>
      
      <div class="delete-user-name" id="deleteUserName">Nome do Usuário</div>
      
      <div class="delete-warning-text" id="deleteWarningText">
        Tem certeza que deseja excluir este usuário?<br>
        Esta ação não pode ser desfeita.
      </div>
      
      <!-- Mensagem de bloqueio (hidden por padrão) -->
      <div class="delete-blocked-message" id="deleteBlockedMessage" style="display: none;">
        <i class="bi bi-shield-lock"></i>
        <span>Este usuário é o proprietário da conta e não pode ser excluído.</span>
      </div>
    </div>
    <div class="modal-premium-footer" style="justify-content: center;" id="deleteUserFooter">
      <button class="btn-cancel-premium" onclick="fecharModalPremium('delete-user')">Cancelar</button>
      <button class="btn-danger-premium" id="btnConfirmDelete" onclick="executeDeleteUser()">
        <i class="bi bi-trash"></i> Confirmar Exclusão
      </button>
    </div>
</div>
</div>

<!-- Modal Premium: Bloqueio / Upgrade (limites do plano) -->
<div id="modalUpgradeBlockPremium" class="modal-premium-overlay" data-modal="upgrade-block">
  <div class="modal-premium-card" style="max-width: 520px;">
    <div class="modal-premium-header" style="background: linear-gradient(135deg, #f97316 0%, #ef4444 100%);">
      <h4 class="modal-premium-title">
        <i class="bi bi-stars"></i>
        <span id="upgradeModalTitle">Limite Atingido</span>
      </h4>
      <button class="btn-close-premium" onclick="fecharModalPremium('upgrade-block')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body">
      <div style="display:flex; gap:14px; align-items:flex-start;">
        <div style="width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; background: rgba(249,115,22,0.12); color:#c2410c; flex: 0 0 auto;">
          <i class="bi bi-shield-lock-fill" style="font-size:22px;"></i>
        </div>
        <div>
          <div id="upgradeModalMessage" style="font-size: 14px; color:#334155; font-weight:600; line-height:1.35;">
            Você atingiu o limite do seu plano.
          </div>
          <div style="margin-top: 10px; font-size: 13px; color:#64748b;">
            Uso atual: <b id="upgradeModalUsage">0/0</b>
          </div>
          <div style="margin-top: 14px; background: #fff7ed; border: 1px solid rgba(249,115,22,0.25); border-radius: 12px; padding: 12px 14px;">
            <div style="display:flex; gap:10px; align-items:flex-start; font-size: 13px; color:#9a3412;">
              <i class="bi bi-info-circle" style="margin-top: 2px;"></i>
              <div>
                Para liberar mais recursos, faça upgrade do seu plano. A atualização é imediata e mantém seus dados.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-premium-footer" style="justify-content: space-between;">
      <button class="btn-cancel-premium" onclick="fecharModalPremium('upgrade-block')">Fechar</button>
      <button class="btn-save-premium" onclick="goToUpgradePlans()">
        <i class="bi bi-arrow-up-right-circle"></i> Fazer Upgrade
      </button>
    </div>
  </div>
</div>

<!-- Modal Premium: Confirmar Exclusão de Grupo -->
<div id="modalDeleteGroupPremium" class="modal-premium-overlay" data-modal="delete-group">
  <div class="modal-premium-card" style="max-width: 520px;">
    <div class="modal-premium-header" style="background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);">
      <h4 class="modal-premium-title">
        <i class="bi bi-exclamation-triangle"></i>
        Excluir Grupo
      </h4>
      <button class="btn-close-premium" onclick="fecharModalPremium('delete-group')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body">
      <input type="hidden" id="deleteGroupId">
      <div style="font-size: 14px; color: #334155;">
        Você tem certeza que deseja excluir o grupo <b id="deleteGroupName">(grupo)</b>?
      </div>
      <div style="margin-top: 14px; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); border-radius: 12px; padding: 12px 14px;">
        <div style="display:flex; gap:10px; align-items:flex-start; font-size: 13px; color:#991b1b;">
          <i class="bi bi-exclamation-octagon" style="margin-top: 2px;"></i>
          <div>
            Esta ação não pode ser desfeita.
            <div style="margin-top: 6px; color:#7f1d1d;">Dica: se houver usuários vinculados ao grupo, remova/vincule-os a outro grupo antes.</div>
          </div>
        </div>
      </div>
      <div class="delete-blocked-message" id="deleteGroupBlockedMessage" style="display:none; margin-top: 16px;">
        <i class="bi bi-lock-fill"></i>
        <span>Não é possível excluir um grupo que possui usuários vinculados.</span>
      </div>
    </div>
    <div class="modal-premium-footer" style="justify-content: center;" id="deleteGroupFooter">
      <button class="btn-cancel-premium" onclick="fecharModalPremium('delete-group')">Cancelar</button>
      <button class="btn-danger-premium" id="btnConfirmDeleteGroup" onclick="executeDeleteGroup()">
        <i class="bi bi-trash"></i> Confirmar Exclusão
      </button>
    </div>
</div>
</div>

<!-- Modal Premium: Realocar Usuários e Excluir Grupo -->
<div id="modalReassignGroupPremium" class="modal-premium-overlay" data-modal="reassign-group">
  <div class="modal-premium-card" style="max-width: 560px;">
    <div class="modal-premium-header" style="background: linear-gradient(135deg, #0ea5e9 0%, #7c3aed 100%);">
      <h4 class="modal-premium-title">
        <i class="bi bi-people"></i>
        Realocar usuários do grupo
      </h4>
      <button class="btn-close-premium" onclick="fecharModalPremium('reassign-group')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body">
      <input type="hidden" id="reassignFromGroupId">
      <div style="font-size: 14px; color: #334155;">
        O grupo <b id="reassignFromGroupName">(grupo)</b> possui <b id="reassignFromGroupTotalUsers">0</b> usuário(s) vinculado(s).
      </div>

      <div style="margin-top: 14px; background: rgba(14,165,233,0.08); border: 1px solid rgba(14,165,233,0.2); border-radius: 12px; padding: 12px 14px;">
        <div style="display:flex; gap:10px; align-items:flex-start; font-size: 13px; color:#0f172a;">
          <i class="bi bi-info-circle" style="margin-top: 2px; color:#0284c7;"></i>
          <div>
            Para excluir este grupo, primeiro altere os usuários para outro grupo.
          </div>
        </div>
      </div>

      <div id="reassignGroupSelectWrap" style="margin-top: 16px;">
        <label class="label-premium" for="reassignToGroupId">
          <i class="bi bi-arrow-left-right ic-modal"></i> Mover usuários para o grupo *
        </label>
        <select id="reassignToGroupId" class="input-premium" style="padding-right: 40px;">
          <option value="">Selecione um grupo...</option>
        </select>
        <small style="color: #64748b; font-size: 12px;">Todos os usuários deste grupo serão movidos para o grupo selecionado.</small>
      </div>

      <div class="delete-blocked-message" id="reassignNoGroupsMessage" style="display:none; margin-top: 16px;">
        <i class="bi bi-lock-fill"></i>
        <span>Você precisa criar outro grupo antes de excluir este.</span>
      </div>
    </div>
    <div class="modal-premium-footer" style="justify-content: space-between;" id="reassignGroupFooter">
      <button class="btn-cancel-premium" onclick="fecharModalPremium('reassign-group')">Cancelar</button>
      <div style="display:flex; gap:10px;">
        <button class="btn-cancel-premium" id="btnCreateGroupFromReassign" onclick="fecharModalPremium('reassign-group'); openCreateGroupModal();" style="display:none;">Criar Grupo</button>
        <button class="btn-save-premium" id="btnConfirmReassignAndDelete" onclick="executeReassignAndDeleteGroup()">
          <i class="bi bi-arrow-repeat"></i> Realocar e Excluir
        </button>
      </div>
    </div>
</div>
</div>

<!-- Modal Premium: Ação restrita (somente Administrador) -->
<div id="modalAdminOnlyPremium" class="modal-premium-overlay" data-modal="admin-only">
  <div class="modal-premium-card" style="max-width: 520px;">
    <div class="modal-premium-header" style="background: linear-gradient(135deg, #0f172a 0%, #334155 100%);">
      <h4 class="modal-premium-title">
        <i class="bi bi-shield-lock"></i>
        Ação restrita
      </h4>
      <button class="btn-close-premium" onclick="fecharModalPremium('admin-only')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body">
      <div style="display:flex; gap:12px; align-items:flex-start;">
        <div style="width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; background: rgba(15,23,42,0.10); color:#0f172a; flex: 0 0 auto;">
          <i class="bi bi-person-badge" style="font-size:22px;"></i>
        </div>
        <div>
          <div id="adminOnlyMessage" style="font-size: 14px; color:#0f172a; font-weight:700; line-height:1.35;">
            Somente Administrador pode alterar contas do grupo Administrador.
          </div>
          <div style="margin-top: 8px; font-size: 13px; color:#64748b;">
            Se você precisar alterar este usuário, solicite ao Administrador da conta.
          </div>
        </div>
      </div>
    </div>
    <div class="modal-premium-footer" style="justify-content: center;">
      <button class="btn-save-premium" onclick="fecharModalPremium('admin-only')">
        Entendi
      </button>
    </div>
  </div>
</div>

<script>
// =========================================================
// FUNÇÕES PREMIUM PARA MODAIS
// =========================================================

/**
 * Abrir modal premium
 */
function abrirModalPremium(tipo) {
  const modal = document.querySelector(`.modal-premium-overlay[data-modal="${tipo}"]`);
  if (modal) {
    modal.classList.add('active');
  }
}

// Limites do plano (para bloqueio e modal de upgrade)
const ACCOUNT_LIMITS = {
  users: {
    max: <?php echo isset($maxUsers) ? (int)$maxUsers : 0; ?>,
    used: <?php echo isset($tenantUsage) ? (int)$tenantUsage : 0; ?>,
    canCreate: <?php echo (isset($canCreateUsers) && !$canCreateUsers) ? 'false' : 'true'; ?>
  },
  groups: {
    max: <?php echo isset($maxGroups) ? (int)$maxGroups : (isset($maxUsers) ? (int)$maxUsers : 0); ?>,
    used: <?php echo isset($tenantGroupsCount) ? (int)$tenantGroupsCount : 0; ?>,
    canCreate: <?php echo (isset($canCreateGroups) && !$canCreateGroups) ? 'false' : 'true'; ?>
  },
  upgradeUrl: '<?php echo root_url(); ?>saas/landing/index.php#pricing'
};

// Usuário atual (para regras de admin-only)
const CURRENT_USER_ID = <?php echo isset($currentUserId) ? (int)$currentUserId : 0; ?>;
const CURRENT_USER_IS_ADMIN = <?php echo (!empty($currentUserIsAdmin)) ? 'true' : 'false'; ?>;
const CURRENT_ALLOWED_GROUP_IDS = <?php echo json_encode(is_array($allowedGroupIdsForCurrent) ? array_values(array_map('intval', $allowedGroupIdsForCurrent)) : [], JSON_UNESCAPED_UNICODE); ?>;

function openAdminOnlyModal(message) {
  const el = document.getElementById('adminOnlyMessage');
  if (el && message) {
    el.textContent = String(message);
  }
  abrirModalPremium('admin-only');
}

// =========================================================
// TOAST PREMIUM (substitui alert para confirmações)
// =========================================================
function showPremiumToast(message, type) {
  const t = String(type || 'info');

  let container = document.getElementById('premiumToastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'premiumToastContainer';
    container.style.position = 'fixed';
    container.style.top = '18px';
    container.style.right = '18px';
    container.style.zIndex = '999999';
    container.style.display = 'flex';
    container.style.flexDirection = 'column';
    container.style.gap = '10px';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.style.minWidth = '260px';
  toast.style.maxWidth = '380px';
  toast.style.padding = '12px 14px';
  toast.style.borderRadius = '14px';
  toast.style.boxShadow = '0 18px 45px rgba(2,6,23,.20)';
  toast.style.border = '1px solid rgba(226,232,240,1)';
  toast.style.background = '#ffffff';
  toast.style.color = '#0f172a';
  toast.style.fontSize = '13px';
  toast.style.fontWeight = '700';
  toast.style.display = 'flex';
  toast.style.alignItems = 'flex-start';
  toast.style.gap = '10px';

  const dot = document.createElement('div');
  dot.style.width = '10px';
  dot.style.height = '10px';
  dot.style.borderRadius = '999px';
  dot.style.marginTop = '3px';
  dot.style.flex = '0 0 auto';

  if (t === 'success') dot.style.background = '#10b981';
  else if (t === 'error') dot.style.background = '#ef4444';
  else if (t === 'warning') dot.style.background = '#f59e0b';
  else dot.style.background = '#3b82f6';

  const text = document.createElement('div');
  text.textContent = String(message || '');
  text.style.fontWeight = '700';
  text.style.lineHeight = '1.35';

  toast.appendChild(dot);
  toast.appendChild(text);

  container.appendChild(toast);

  // Auto close
  window.setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-6px)';
    toast.style.transition = 'all .18s ease';
    window.setTimeout(() => {
      if (toast && toast.parentNode) toast.parentNode.removeChild(toast);
    }, 220);
  }, 2600);
}

// Grupos disponíveis para realocação (select no modal)
const ACCOUNT_GROUP_OPTIONS = <?php echo json_encode(is_array($groupsForReassign) ? $groupsForReassign : [], JSON_UNESCAPED_UNICODE); ?>;

function openUpgradeLimitModal(resource, payload) {
  const info = payload && typeof payload === 'object'
    ? {
        used: Number(payload.used ?? payload.current ?? 0),
        max: Number(payload.max ?? 0)
      }
    : (resource === 'groups' ? ACCOUNT_LIMITS.groups : ACCOUNT_LIMITS.users);

  const titleEl = document.getElementById('upgradeModalTitle');
  const msgEl = document.getElementById('upgradeModalMessage');
  const usageEl = document.getElementById('upgradeModalUsage');

  const resourceLabel = resource === 'groups' ? 'grupos' : 'usuários';

  if (titleEl) titleEl.textContent = `Limite de ${resourceLabel} atingido`;
  if (msgEl) msgEl.textContent = `Você atingiu o limite de ${resourceLabel} do seu plano e não pode criar novos no momento.`;
  if (usageEl) {
    usageEl.textContent = info.max > 0 ? `${info.used}/${info.max}` : `${info.used}`;
  }

  abrirModalPremium('upgrade-block');
}

function goToUpgradePlans() {
  window.location.href = ACCOUNT_LIMITS.upgradeUrl;
}

// =========================================================
// TELEFONE (máscara + apenas números)
// =========================================================
function phoneDigits(value) {
  return String(value || '').replace(/\D+/g, '');
}

function formatPhoneBR(digits) {
  const d = phoneDigits(digits).slice(0, 11);
  if (d.length <= 2) return d;

  const ddd = d.slice(0, 2);
  const rest = d.slice(2);

  // (00) 0000-0000 (10 dígitos) ou (00) 00000-0000 (11 dígitos)
  if (rest.length <= 4) {
    return `(${ddd}) ${rest}`;
  }

  if (rest.length <= 8) {
    return `(${ddd}) ${rest.slice(0, 4)}-${rest.slice(4)}`;
  }

  return `(${ddd}) ${rest.slice(0, 5)}-${rest.slice(5)}`;
}

function bindPhoneMask(inputId) {
  const el = document.getElementById(inputId);
  if (!el) return;

  const apply = () => {
    const d = phoneDigits(el.value);
    el.value = formatPhoneBR(d);
  };

  el.addEventListener('input', apply);
  el.addEventListener('blur', apply);

  // aplica imediatamente (caso venha valor do backend)
  apply();
}

(function initPhoneMasks() {
  const run = () => {
    bindPhoneMask('addUserMobile');
    bindPhoneMask('editUserMobile');
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();

/**
 * Fechar modal premium
 */
function fecharModalPremium(tipo) {
  const modal = document.querySelector(`.modal-premium-overlay[data-modal="${tipo}"]`);
  if (modal) {
    modal.classList.remove('active');
  }
}

// Fechar com ESC
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-premium-overlay.active').forEach(m => m.classList.remove('active'));
  }
});

// REMOVIDO: Fechar ao clicar fora
// Modais só fecham pelo botão X ou ESC
// document.addEventListener('click', function(e) {
//   if (e.target.classList.contains('modal-premium-overlay')) {
//     e.target.classList.remove('active');
//   }
// });

// =========================================================
// GRUPO - FUNÇÕES PREMIUM
// =========================================================
let groupPermissionsData = {};

/**
 * Abrir modal de criar grupo (versão premium)
 */
function openCreateGroupModal() {
  if (ACCOUNT_LIMITS.groups.max > 0 && !ACCOUNT_LIMITS.groups.canCreate) {
    openUpgradeLimitModal('groups');
    return;
  }

  // Limpar campos
  document.getElementById('premiumGroupName').value = '';
  document.getElementById('premiumGroupSlug').value = '';
  document.getElementById('groupSelectAll').checked = false;
  document.getElementById('groupPermSearch').value = '';
  
  // Carregar permissões
  loadGroupPermissions('premiumGroupPermissionsGrid', 'create');
  
  // Abrir modal
  abrirModalPremium('create-group');
}

/**
 * Carregar permissões para o grid
 */
async function loadGroupPermissions(containerId, mode) {
  const container = document.getElementById(containerId);
  if (!container) return;
  
  container.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 40px;"><div class="spinner-border text-primary" role="status"></div></div>';
  
  try {
    const response = await fetch('<?php echo root_url(); ?>api/groups/get_available_permissions.php');
    const data = await response.json();
    
    if (!data.success) {
      throw new Error(data.error || 'Erro ao carregar permissões');
    }
    
    groupPermissionsData = data.permissions;
    renderPermissionsGrid(container, data.permissions, mode);
    updatePermissionCounts();
    
  } catch (error) {
    console.error('Erro:', error);
    container.innerHTML = '<div style="grid-column: 1 / -1;" class="alert alert-danger">Erro ao carregar permissões: ' + error.message + '</div>';
  }
}

/**
 * Renderizar grid de permissões
 */
function renderPermissionsGrid(container, permissions, mode) {
  const catColors = ['#2563eb', '#7c3aed', '#16a34a', '#d97706', '#dc2626', '#0ea5e9', '#9333ea', '#059669', '#64748b'];
  let html = '';
  let catIndex = 0;
  
  for (const [category, perms] of Object.entries(permissions)) {
    const color = catColors[catIndex % catColors.length];
    catIndex++;
    
    const permCount = Object.keys(perms).length;
    
    html += `
      <div class="plan-features-card" data-category="${category}" style="--cat-color: ${color};">
        <div class="plan-features-card-header">
          <div class="t">
            <span class="dot"></span>
            <span class="title">${category}</span>
          </div>
          <div class="plan-features-card-header-right">
            <label class="plan-features-card-all">
              <input type="checkbox" onchange="toggleCategoryPermissions('${category}', this.checked)">
              <span>All</span>
            </label>
            <div class="count">${permCount}</div>
          </div>
        </div>
        <div class="plan-features-card-body">
    `;
    
    for (const [permKey, permLabel] of Object.entries(perms)) {
      const searchText = (permKey + ' ' + permLabel + ' ' + category).toLowerCase();
      html += `
        <label class="plan-feature-item" data-search="${searchText}" data-category="${category}">
          <input type="checkbox" class="perm-checkbox" data-perm="${permKey}" data-category="${category}" onchange="updatePermissionCounts()">
          <div class="meta">
            <div class="name">${permKey}</div>
            <div class="desc">${permLabel}</div>
          </div>
        </label>
      `;
    }
    
    html += '</div></div>';
  }
  
  container.innerHTML = html;
}

/**
 * Filtrar permissões
 */
function filterGroupPermissions(term) {
  term = (term || '').toLowerCase().trim();
  
  document.querySelectorAll('.plan-feature-item').forEach(item => {
    const search = item.getAttribute('data-search') || '';
    const show = !term || search.includes(term);
    item.classList.toggle('is-hidden', !show);
  });
  
  document.querySelectorAll('.plan-features-card').forEach(card => {
    const hasVisible = card.querySelector('.plan-feature-item:not(.is-hidden)');
    card.classList.toggle('is-hidden', !hasVisible);
  });
}

/**
 * Alternar todas as permissões
 */
function toggleAllGroupPermissions(checked) {
  document.querySelectorAll('.perm-checkbox').forEach(cb => {
    cb.checked = checked;
  });
  updatePermissionCounts();
}

/**
 * Limpar todas as permissões
 */
function clearAllGroupPermissions() {
  document.querySelectorAll('.perm-checkbox').forEach(cb => {
    cb.checked = false;
  });
  document.getElementById('groupSelectAll').checked = false;
  updatePermissionCounts();
}

/**
 * Alternar permissões de uma categoria
 */
function toggleCategoryPermissions(category, checked) {
  document.querySelectorAll(`.perm-checkbox[data-category="${category}"]`).forEach(cb => {
    cb.checked = checked;
  });
  updatePermissionCounts();
}

/**
 * Atualizar contadores
 */
function updatePermissionCounts() {
  const total = document.querySelectorAll('.perm-checkbox').length;
  const selected = document.querySelectorAll('.perm-checkbox:checked').length;
  
  const totalEl = document.getElementById('groupPermTotal');
  const selectedEl = document.getElementById('groupPermSelected');
  
  if (totalEl) totalEl.textContent = total;
  if (selectedEl) selectedEl.textContent = selected;
}

/**
 * Abrir modal de editar grupo (versão premium)
 */
function openEditGroupModalPremium(groupId, groupName, groupSlug) {
  document.getElementById('editGroupId').value = groupId;
  document.getElementById('editGroupName').value = groupName;
  document.getElementById('editGroupSlug').value = groupSlug;
  
  // Carregar permissões e marcar as do grupo
  loadGroupPermissionsForEdit(groupId);
  
  abrirModalPremium('edit-group');
}

/**
 * Carregar permissões para edição de grupo
 */
async function loadGroupPermissionsForEdit(groupId) {
  const container = document.getElementById('editGroupPermissionsGrid');
  if (!container) return;

  container.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 40px;"><div class="spinner-border text-primary" role="status"></div></div>';

  try {
    // 1) Carregar permissões disponíveis (filtradas pelo plano)
    const response = await fetch('<?php echo root_url(); ?>api/groups/get_available_permissions.php');
    const data = await response.json();

    if (!data.success) {
      throw new Error(data.error || 'Erro ao carregar permissões');
    }

    // Renderizar grid
    renderPermissionsGrid(container, data.permissions, 'edit');

    // 2) Buscar permissões atuais do grupo e marcar checkboxes
    const resGroup = await fetch('<?php echo root_url(); ?>api/groups/get.php?id=' + encodeURIComponent(String(groupId)));
    const groupData = await resGroup.json();

    if (!groupData.success) {
      throw new Error(groupData.error || 'Erro ao carregar permissões do grupo');
    }

    const perms = (groupData.group && groupData.group.permissions) ? groupData.group.permissions : {};
    const access = (perms && perms.access) ? perms.access : {};

    // Escape simples para seletor
    const esc = (s) => String(s).replace(/\\/g, '\\\\').replace(/"/g, '\\"');

    Object.keys(access).forEach((k) => {
      if (!access[k]) return;
      const selector = '.perm-checkbox[data-perm="' + esc(k) + '"]';
      const cb = container.querySelector(selector);
      if (cb) cb.checked = true;
    });

    updatePermissionCounts();

  } catch (error) {
    console.error('Erro:', error);
    container.innerHTML = '<div style="grid-column: 1 / -1;" class="alert alert-danger">Erro ao carregar permissões: ' + error.message + '</div>';
  }
}

/**
 * Atualizar grupo (versão premium)
 */
async function updateGroupPremium() {
  const groupId = document.getElementById('editGroupId').value;
  const name = document.getElementById('editGroupName').value.trim();
  
  if (!name) {
    alert('Nome do grupo é obrigatório');
    return;
  }
  
  const permissions = { access: {} };
  document.querySelectorAll('#editGroupPermissionsGrid .perm-checkbox:checked').forEach(cb => {
    permissions.access[cb.getAttribute('data-perm')] = true;
  });
  
  // Mostrar mensagem (modo estático por enquanto)
  alert(`✅ Grupo preparado para atualização:\n\nID: ${groupId}\nNome: ${name}\nPermissões: ${Object.keys(permissions.access).length}\n\n⚠️ A API de atualização será implementada em breve.`);
  fecharModalPremium('edit-group');
}

/**
 * Salvar grupo (versão premium)
 */
async function saveGroupPremium() {
  if (ACCOUNT_LIMITS.groups.max > 0 && !ACCOUNT_LIMITS.groups.canCreate) {
    openUpgradeLimitModal('groups');
    return;
  }

  const name = document.getElementById('premiumGroupName').value.trim();
  const slug = document.getElementById('premiumGroupSlug').value.trim();
  
  if (!name) {
    alert('Nome do grupo é obrigatório');
    return;
  }
  
  const permissions = { access: {} };
  document.querySelectorAll('.perm-checkbox:checked').forEach(cb => {
    permissions.access[cb.getAttribute('data-perm')] = true;
  });
  
  try {
    const response = await fetch('<?php echo root_url(); ?>api/groups/create.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, slug, permissions })
    });
    
    const data = await response.json();
    
    if (!data.success) {
      if (data.code === 'LIMIT_REACHED' && data.limit_type === 'groups') {
        fecharModalPremium('create-group');
        openUpgradeLimitModal('groups', data);
        return;
      }
      throw new Error(data.error || 'Erro ao criar grupo');
    }
    
    alert('Grupo criado com sucesso!');
    window.location.reload();
    
  } catch (error) {
    console.error('Erro:', error);
    alert('Erro ao criar grupo: ' + error.message);
  }
}

// =========================================================
// USUÁRIO - FUNÇÕES PREMIUM
// =========================================================

/**
 * Abrir modal de editar usuário (versão premium)
 */
async function openEditUserModalPremium(userId, username, email, mobile, groupId, status, isOwner) {
  // Regra: usuário não-admin não pode editar usuários com grupo superior ao seu
  if (!CURRENT_USER_IS_ADMIN) {
    const gid = Number(groupId);
    if (gid > 0 && Array.isArray(CURRENT_ALLOWED_GROUP_IDS) && CURRENT_ALLOWED_GROUP_IDS.indexOf(gid) === -1) {
      openAdminOnlyModal('Você não pode editar usuários com grupo superior ao seu.');
      return;
    }
  }

  // Regra: somente Administrador pode alterar conta do grupo Administrador (ou Admin/Owner)
  if (!CURRENT_USER_IS_ADMIN && (Number(groupId) === 1 || Boolean(isOwner))) {
    openAdminOnlyModal('Somente Administrador pode editar contas do grupo Administrador.');
    return;
  }

  // Guardar se é owner para bloquear alteração de grupo
  window._editingUserIsOwner = Boolean(isOwner);

  document.getElementById('editUserId').value = userId;

  // Preenche imediato (UX)
  document.getElementById('editUserName').value = username || '';
  document.getElementById('editUserEmail').value = email || '';
  document.getElementById('editUserMobile').value = formatPhoneBR(phoneDigits(mobile || ''));
  document.getElementById('editUserGroup').value = groupId || '';

  // Bloquear alteração de grupo se for Owner
  const editGroupSelect = document.getElementById('editUserGroup');
  const ownerGroupWarning = document.getElementById('ownerGroupWarning');
  if (window._editingUserIsOwner) {
    editGroupSelect.disabled = true;
    editGroupSelect.style.opacity = '0.6';
    editGroupSelect.style.cursor = 'not-allowed';
    if (ownerGroupWarning) ownerGroupWarning.style.display = 'flex';
  } else {
    editGroupSelect.disabled = false;
    editGroupSelect.style.opacity = '1';
    editGroupSelect.style.cursor = '';
    if (ownerGroupWarning) ownerGroupWarning.style.display = 'none';
  }

  // Defaults enquanto carrega
  document.getElementById('editUserDob').value = '';
  document.getElementById('editUserOrder').value = '0';
  
  // Limpar seleção de lojas (checkboxes)
  document.querySelectorAll('.edit-user-store-checkbox').forEach(cb => cb.checked = false);

  // Status toggle
  const statusToggle = document.getElementById('editUserStatusToggle');
  const statusLabel = document.getElementById('editUserStatusLabel');
  if (Number(status) === 1) {
    statusToggle.classList.add('active');
    statusLabel.textContent = 'Ativo';
  } else {
    statusToggle.classList.remove('active');
    statusLabel.textContent = 'Inativo';
  }

  abrirModalPremium('edit-user');

  // Buscar dados completos no backend (dob, loja, sort_order, etc.)
  try {
    const res = await fetch('<?php echo root_url(); ?>api/users/get.php?id=' + encodeURIComponent(String(userId)));
    const data = await res.json();

    if (!data.success) {
      throw new Error(data.error || 'Erro ao buscar usuário');
    }

    const u = data.user || {};

    document.getElementById('editUserName').value = u.username || '';
    document.getElementById('editUserEmail').value = u.email || '';
    document.getElementById('editUserMobile').value = formatPhoneBR(phoneDigits(u.mobile || ''));
    document.getElementById('editUserDob').value = u.dob || '';
    document.getElementById('editUserGroup').value = u.group_id || '';
    document.getElementById('editUserOrder').value = String(u.sort_order ?? 0);
    
    // Marcar checkboxes das lojas vinculadas
    document.querySelectorAll('.edit-user-store-checkbox').forEach(cb => cb.checked = false);
    const userStoreIds = u.store_ids || (u.store_id ? [u.store_id] : []);
    userStoreIds.forEach(storeId => {
      const checkbox = document.querySelector('.edit-user-store-checkbox[value="' + storeId + '"]');
      if (checkbox) checkbox.checked = true;
    });

    const s = Number(u.status ?? status);
    if (s === 1) {
      statusToggle.classList.add('active');
      statusLabel.textContent = 'Ativo';
    } else {
      statusToggle.classList.remove('active');
      statusLabel.textContent = 'Inativo';
    }

  } catch (error) {
    console.error('Erro:', error);
    // Mantém modal aberta com o que já temos
    alert('Erro ao carregar dados completos do usuário: ' + error.message);
  }
}

/**
 * Toggle status do usuário no edit
 */
function toggleEditUserStatus() {
  const toggle = document.getElementById('editUserStatusToggle');
  const label = document.getElementById('editUserStatusLabel');
  toggle.classList.toggle('active');
  label.textContent = toggle.classList.contains('active') ? 'Ativo' : 'Inativo';
}

/**
 * Salvar edição do usuário
 */
async function saveEditUserPremium() {
  const userId = document.getElementById('editUserId').value;
  const name = document.getElementById('editUserName').value.trim();
  const email = document.getElementById('editUserEmail').value.trim();
  const mobile = document.getElementById('editUserMobile').value.trim();
  const dob = document.getElementById('editUserDob').value;
  const groupId = document.getElementById('editUserGroup').value;
  
  // Coletar lojas selecionadas (múltiplas)
  const storeCheckboxes = document.querySelectorAll('.edit-user-store-checkbox:checked');
  const storeIds = Array.from(storeCheckboxes).map(cb => Number(cb.value));
  
  const order = document.getElementById('editUserOrder').value || 0;
  const status = document.getElementById('editUserStatusToggle').classList.contains('active') ? 1 : 0;

  if (!userId) {
    alert('Usuário inválido');
    return;
  }

  if (!name) {
    alert('Nome é obrigatório');
    return;
  }

  if (!email && !mobile) {
    alert('Informe E-mail ou Celular');
    return;
  }

  if (!groupId) {
    alert('Selecione um grupo de permissão');
    return;
  }

  if (!CURRENT_USER_IS_ADMIN) {
    const gid = Number(groupId);
    if (gid > 0 && Array.isArray(CURRENT_ALLOWED_GROUP_IDS) && CURRENT_ALLOWED_GROUP_IDS.indexOf(gid) === -1) {
      openAdminOnlyModal('Você não pode atribuir um grupo superior ao seu.');
      return;
    }
  }

  if (storeIds.length === 0) {
    alert('Selecione pelo menos uma loja');
    return;
  }

  try {
    const response = await fetch('<?php echo root_url(); ?>api/users/update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id: Number(userId),
        username: name,
        email,
        mobile: phoneDigits(mobile),
        dob,
        group_id: Number(groupId),
        store_ids: storeIds,
        sort_order: Number(order),
        status: Number(status),
      })
    });

    const data = await response.json();

    if (!data.success) {
      if (data.code === 'LIMIT_REACHED' && data.limit_type === 'users') {
        fecharModalPremium('edit-user');
        openUpgradeLimitModal('users', data);
        return;
      }
      throw new Error(data.error || 'Erro ao atualizar usuário');
    }

    fecharModalPremium('edit-user');
    alert('Usuário atualizado com sucesso!');
    window.location.reload();

  } catch (error) {
    console.error('Erro:', error);
    alert('Erro ao atualizar usuário: ' + error.message);
  }
}

// =========================================================
// ALTERAR SENHA - FUNÇÕES PREMIUM
// =========================================================

/**
 * Abrir modal de alterar senha
 */
function abrirModalAlterarSenha(userId, username, targetGroupId, isOwner) {
  // Regra: usuário não-admin não pode alterar senha de usuários com grupo superior ao seu
  if (!CURRENT_USER_IS_ADMIN) {
    const gid = Number(targetGroupId);
    if (gid > 0 && Array.isArray(CURRENT_ALLOWED_GROUP_IDS) && CURRENT_ALLOWED_GROUP_IDS.indexOf(gid) === -1) {
      openAdminOnlyModal('Você não pode alterar senha de usuários com grupo superior ao seu.');
      return;
    }
  }

  if (!CURRENT_USER_IS_ADMIN && (Number(targetGroupId) === 1 || Boolean(isOwner))) {
    openAdminOnlyModal('Somente Administrador pode alterar a senha de contas do grupo Administrador.');
    return;
  }
  document.getElementById('alterarSenhaUserId').value = userId;
  document.getElementById('alterarSenhaUserName').textContent = username;
  document.getElementById('novaSenhaInput').value = '';
  document.getElementById('confirmarSenhaInput').value = '';
  
  abrirModalPremium('alterar-senha');
}

/**
 * Toggle de visibilidade da senha
 */
function togglePasswordVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  const icon = btn.querySelector('i');
  
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('bi-eye');
    icon.classList.add('bi-eye-slash');
    btn.style.color = '#7c3aed';
  } else {
    input.type = 'password';
    icon.classList.remove('bi-eye-slash');
    icon.classList.add('bi-eye');
    btn.style.color = '#94a3b8';
  }
}

/**
 * Salvar nova senha
 */
async function openLoginHistoryModal(userId, username) {
  // Regra: somente Admin/Owner pode ver histórico de login de outros usuários
  if (!CURRENT_USER_IS_ADMIN && Number(userId) !== Number(CURRENT_USER_ID)) {
    openAdminOnlyModal('Somente Administrador pode ver o histórico de login de outros usuários.');
    return;
  }

  document.getElementById('loginHistoryUserId').value = userId;
  document.getElementById('loginHistoryUserName').textContent = username || '';

  const content = document.getElementById('loginHistoryContent');
  content.innerHTML = '<div style="padding: 16px; text-align:center; color:#64748b;">Carregando...</div>';

  abrirModalPremium('login-history');

  try {
    const res = await fetch('<?php echo root_url(); ?>api/users/login_logs.php?id=' + encodeURIComponent(String(userId)) + '&limit=80');
    const data = await res.json();

    if (!data.success) {
      throw new Error(data.error || 'Erro ao buscar histórico');
    }

    const logs = Array.isArray(data.logs) ? data.logs : [];

    if (!logs.length) {
      content.innerHTML = '<div style="padding: 16px; color:#64748b;">Nenhum login registrado para este usuário.</div>';
      return;
    }

    let html = '';
    html += '<div style="width:100%; overflow-x:auto;">';
    html += '<table style="width:100%; border-collapse: collapse;">';
    html += '<thead><tr style="background:#f8fafc; color:#0f172a;">';
    html += '<th style="text-align:left; padding: 10px 12px; font-size:12px; border-bottom:1px solid #e2e8f0;">Data</th>';
    html += '<th style="text-align:left; padding: 10px 12px; font-size:12px; border-bottom:1px solid #e2e8f0;">IP</th>';
    html += '</tr></thead>';
    html += '<tbody>';

    logs.forEach(l => {
      const dt = String(l.created_at || '');
      const ip = String(l.ip || '-');
      html += '<tr>';
      html += '<td style="padding: 10px 12px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#0f172a;">' + dt + '</td>';
      html += '<td style="padding: 10px 12px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#0f172a;">' + ip + '</td>';
      html += '</tr>';
    });

    html += '</tbody></table></div>';
    content.innerHTML = html;

  } catch (e) {
    console.error(e);
    content.innerHTML = '<div style="padding: 16px; color:#b91c1c;">Erro ao carregar histórico: ' + String(e.message || e) + '</div>';
  }
}

async function salvarNovaSenha() {
  const userId = document.getElementById('alterarSenhaUserId').value;
  const novaSenha = document.getElementById('novaSenhaInput').value;
  const confirmarSenha = document.getElementById('confirmarSenhaInput').value;
  const username = document.getElementById('alterarSenhaUserName').textContent;
  
  if (!userId) {
    alert('Usuário inválido');
    return;
  }

  if (!novaSenha) {
    alert('Digite a nova senha');
    return;
  }
  
  if (novaSenha.length < 6) {
    alert('A senha deve ter no mínimo 6 caracteres');
    return;
  }
  
  if (novaSenha !== confirmarSenha) {
    alert('As senhas não coincidem');
    return;
  }

  try {
    const response = await fetch('<?php echo root_url(); ?>api/users/change_password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id: Number(userId),
        password: novaSenha,
        password_confirm: confirmarSenha,
      })
    });

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.error || 'Erro ao alterar senha');
    }

    fecharModalPremium('alterar-senha');
    showPremiumToast(`Senha alterada com sucesso para: ${username}`, 'success');

  } catch (error) {
    console.error('Erro:', error);
    alert('Erro ao alterar senha: ' + error.message);
  }
}

// =========================================================
// ADICIONAR USUÁRIO - FUNÇÕES PREMIUM
// =========================================================

/**
 * Abrir modal de adicionar usuário (versão premium)
 */
function openAddUserModal() {
  // Regra: somente Administrador/Owner pode adicionar novos usuários
  if (!CURRENT_USER_IS_ADMIN) {
    openAdminOnlyModal('Somente Administrador pode adicionar novos usuários.');
    return;
  }

  if (ACCOUNT_LIMITS.users.max > 0 && !ACCOUNT_LIMITS.users.canCreate) {
    openUpgradeLimitModal('users');
    return;
  }

  // Limpar campos
  document.getElementById('addUserName').value = '';
  document.getElementById('addUserEmail').value = '';
  document.getElementById('addUserMobile').value = '';
  document.getElementById('addUserDob').value = '';
  document.getElementById('addUserPassword').value = '';
  document.getElementById('addUserPasswordConfirm').value = '';
  document.getElementById('addUserGroup').value = '';
  document.getElementById('addUserOrder').value = '0';
  
  // Limpar seleção de lojas (checkboxes)
  document.querySelectorAll('.add-user-store-checkbox').forEach(cb => cb.checked = false);
  
  // Ativar status por padrão
  const statusToggle = document.getElementById('addUserStatusToggle');
  const statusLabel = document.getElementById('addUserStatusLabel');
  statusToggle.classList.add('active');
  statusLabel.textContent = 'Ativo';
  
  abrirModalPremium('add-user');
}

/**
 * Toggle status do usuário no adicionar
 */
function toggleAddUserStatus() {
  const toggle = document.getElementById('addUserStatusToggle');
  const label = document.getElementById('addUserStatusLabel');
  toggle.classList.toggle('active');
  label.textContent = toggle.classList.contains('active') ? 'Ativo' : 'Inativo';
}

/**
 * Salvar novo usuário (versão premium)
 */
async function saveAddUserPremium() {
  // Regra: somente Administrador/Owner pode adicionar novos usuários
  if (!CURRENT_USER_IS_ADMIN) {
    openAdminOnlyModal('Somente Administrador pode adicionar novos usuários.');
    return;
  }

  if (ACCOUNT_LIMITS.users.max > 0 && !ACCOUNT_LIMITS.users.canCreate) {
    openUpgradeLimitModal('users');
    return;
  }

  const name = document.getElementById('addUserName').value.trim();
  const email = document.getElementById('addUserEmail').value.trim();
  const mobile = document.getElementById('addUserMobile').value.trim();
  const dob = document.getElementById('addUserDob').value;
  const password = document.getElementById('addUserPassword').value;
  const passwordConfirm = document.getElementById('addUserPasswordConfirm').value;
  const groupId = document.getElementById('addUserGroup').value;
  
  // Coletar lojas selecionadas (múltiplas)
  const storeCheckboxes = document.querySelectorAll('.add-user-store-checkbox:checked');
  const storeIds = Array.from(storeCheckboxes).map(cb => Number(cb.value));
  
  const order = document.getElementById('addUserOrder').value || 0;
  const status = document.getElementById('addUserStatusToggle').classList.contains('active') ? 1 : 0;

  // Validações
  if (!name) {
    alert('Nome é obrigatório');
    return;
  }

  if (!email && !mobile) {
    alert('Informe E-mail ou Celular');
    return;
  }

  if (!password) {
    alert('Senha é obrigatória para novos usuários');
    return;
  }

  if (password.length < 6) {
    alert('A senha deve ter no mínimo 6 caracteres');
    return;
  }

  if (password !== passwordConfirm) {
    alert('As senhas não coincidem');
    return;
  }

  if (!groupId) {
    alert('Selecione um grupo de permissão');
    return;
  }

  if (!CURRENT_USER_IS_ADMIN) {
    const gid = Number(groupId);
    if (gid > 0 && Array.isArray(CURRENT_ALLOWED_GROUP_IDS) && CURRENT_ALLOWED_GROUP_IDS.indexOf(gid) === -1) {
      openAdminOnlyModal('Você não pode atribuir um grupo superior ao seu.');
      return;
    }
  }

  if (storeIds.length === 0) {
    alert('Selecione pelo menos uma loja');
    return;
  }

  try {
    const response = await fetch('<?php echo root_url(); ?>api/users/create.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        username: name,
        email,
        mobile: phoneDigits(mobile),
        dob,
        group_id: Number(groupId),
        store_ids: storeIds,
        sort_order: Number(order),
        status: Number(status),
        password,
        password_confirm: passwordConfirm,
      })
    });

    const data = await response.json();

    if (!data.success) {
      if (data.code === 'LIMIT_REACHED' && data.limit_type === 'users') {
        fecharModalPremium('add-user');
        openUpgradeLimitModal('users', data);
        return;
      }
      throw new Error(data.error || 'Erro ao criar usuário');
    }

    fecharModalPremium('add-user');
    alert('Usuário criado com sucesso!');
    window.location.reload();

  } catch (error) {
    console.error('Erro:', error);
    alert('Erro ao criar usuário: ' + error.message);
  }
}

// =========================================================
// EXCLUSÃO DE USUÁRIO - FUNÇÕES PREMIUM
// =========================================================

/**
 * Abrir modal de confirmação de exclusão (versão premium)
 */
function confirmDeleteUserPremium(userId, username, isOwner) {
  // Regra: somente Administrador/Owner pode excluir usuários
  if (!CURRENT_USER_IS_ADMIN) {
    openAdminOnlyModal('Somente Administrador pode excluir usuários.');
    return;
  }

  document.getElementById('deleteUserId').value = userId;
  document.getElementById('deleteUserName').textContent = username;
  
  const blockedMsg = document.getElementById('deleteBlockedMessage');
  const warningText = document.getElementById('deleteWarningText');
  const confirmBtn = document.getElementById('btnConfirmDelete');
  
  if (isOwner) {
    // Usuário é owner - bloquear exclusão
    blockedMsg.style.display = 'flex';
    warningText.style.display = 'none';
    confirmBtn.style.display = 'none';
  } else {
    blockedMsg.style.display = 'none';
    warningText.style.display = 'block';
    confirmBtn.style.display = 'inline-flex';
  }
  
  abrirModalPremium('delete-user');
}

// =========================================================
// EXCLUSÃO DE GRUPO - FUNÇÕES PREMIUM
// =========================================================
function confirmDeleteGroupPremium(groupId, groupName, totalUsers) {
  // Se houver usuários, solicitar realocação antes de excluir
  if (Number(totalUsers) > 0) {
    openReassignGroupModal(groupId, groupName, totalUsers);
    return;
  }

  // Caso não haja usuários, abrir confirmação direta
  document.getElementById('deleteGroupId').value = groupId;
  document.getElementById('deleteGroupName').textContent = groupName;
  abrirModalPremium('delete-group');
}

function openReassignGroupModal(groupId, groupName, totalUsers) {
  document.getElementById('reassignFromGroupId').value = groupId;
  document.getElementById('reassignFromGroupName').textContent = groupName;
  document.getElementById('reassignFromGroupTotalUsers').textContent = String(totalUsers || 0);

  const selectWrap = document.getElementById('reassignGroupSelectWrap');
  const select = document.getElementById('reassignToGroupId');
  const noGroupsMsg = document.getElementById('reassignNoGroupsMessage');
  const btnConfirm = document.getElementById('btnConfirmReassignAndDelete');
  const btnCreate = document.getElementById('btnCreateGroupFromReassign');

  // Reset
  if (select) {
    select.innerHTML = '<option value="">Selecione um grupo...</option>';
  }

  const options = Array.isArray(ACCOUNT_GROUP_OPTIONS) ? ACCOUNT_GROUP_OPTIONS : [];
  const candidates = options.filter(g => Number(g.id) !== Number(groupId));

  if (candidates.length > 0 && select) {
    candidates.forEach(g => {
      const opt = document.createElement('option');
      opt.value = String(g.id);
      opt.textContent = String(g.name || ('Grupo #' + g.id));
      select.appendChild(opt);
    });
  }

  const hasCandidates = candidates.length > 0;

  if (selectWrap) selectWrap.style.display = hasCandidates ? 'block' : 'none';
  if (noGroupsMsg) noGroupsMsg.style.display = hasCandidates ? 'none' : 'flex';
  if (btnConfirm) btnConfirm.style.display = hasCandidates ? 'inline-flex' : 'none';
  if (btnCreate) btnCreate.style.display = hasCandidates ? 'none' : 'inline-flex';

  abrirModalPremium('reassign-group');
}

async function executeReassignAndDeleteGroup() {
  const groupId = document.getElementById('reassignFromGroupId').value;
  const toGroupId = document.getElementById('reassignToGroupId').value;

  if (!groupId) {
    alert('Grupo inválido');
    return;
  }

  if (!toGroupId) {
    alert('Selecione o grupo de destino para realocar os usuários');
    return;
  }

  if (String(groupId) === String(toGroupId)) {
    alert('Selecione um grupo diferente do atual');
    return;
  }

  try {
    const response = await fetch('<?php echo root_url(); ?>api/groups/delete.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ group_id: groupId, reassign_to_group_id: toGroupId })
    });

    const data = await response.json();

    if (!data.success) {
      if (data.code === 'GROUP_HAS_USERS') {
        alert(data.error || 'Realocação necessária antes de excluir');
        return;
      }
      throw new Error(data.error || 'Erro ao realocar/excluir grupo');
    }

    alert('Usuários realocados e grupo excluído com sucesso!');
    window.location.reload();

  } catch (error) {
    console.error('Erro:', error);
    alert('Erro ao realocar/excluir grupo: ' + error.message);
  } finally {
    fecharModalPremium('reassign-group');
  }
}

async function executeDeleteGroup() {
  const groupId = document.getElementById('deleteGroupId').value;
  if (!groupId) {
    alert('Grupo inválido');
    return;
  }

  try {
    const response = await fetch('<?php echo root_url(); ?>api/groups/delete.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ group_id: groupId })
    });

    const data = await response.json();

    if (!data.success) {
      if (data.code === 'GROUP_HAS_USERS') {
        alert(data.error || 'Não é possível excluir um grupo com usuários vinculados');
        return;
      }
      throw new Error(data.error || 'Erro ao excluir grupo');
    }

    alert('Grupo excluído com sucesso!');
    window.location.reload();

  } catch (error) {
    console.error('Erro:', error);
    alert('Erro ao excluir grupo: ' + error.message);
  } finally {
    fecharModalPremium('delete-group');
  }
}

/**
 * Executar exclusão do usuário
 */
async function executeDeleteUser() {
  const userId = document.getElementById('deleteUserId').value;
  const username = document.getElementById('deleteUserName').textContent;

  if (!CURRENT_USER_IS_ADMIN) {
    openAdminOnlyModal('Somente Administrador pode excluir usuários.');
    return;
  }

  // Confirmar novamente
  if (!confirm(`Confirma a exclusão do usuário "${username}"?`)) {
    return;
  }

  try {
    const response = await fetch('<?php echo root_url(); ?>api/users/delete.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: Number(userId) })
    });

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.error || 'Erro ao excluir usuário');
    }

    fecharModalPremium('delete-user');
    showPremiumToast(`Usuário excluído: ${username}`, 'success');
    window.location.reload();

  } catch (error) {
    console.error('Erro:', error);
    alert('Erro ao excluir usuário: ' + error.message);
  }
}
</script>

<!-- Modal Bootstrap Antiga: Criar Grupo (mantida para compatibilidade) -->
<div class="modal fade" id="createGroupModal" tabindex="-1" aria-labelledby="createGroupModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="createGroupModalLabel">
          <i class="bi bi-plus-circle me-2"></i>Criar Novo Grupo RBAC
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="groupName" class="form-label">Nome do Grupo *</label>
          <input type="text" class="form-control" id="groupName" placeholder="Ex: Gerente de Vendas" required>
          <small class="text-muted">Nome descritivo do cargo/função</small>
        </div>
        
        <div class="mb-3">
          <label for="groupSlug" class="form-label">Identificador (Slug)</label>
          <input type="text" class="form-control" id="groupSlug" placeholder="Ex: gerente_vendas">
          <small class="text-muted">Deixe vazio para gerar automaticamente</small>
        </div>
        
        <hr>
        
        <div>
          <label class="form-label fw-bold">Permissões do Grupo</label>
          <p class="text-secondary small mb-3">
            Selecione as permissões que este grupo terá acesso. Apenas permissões disponíveis no seu plano aparecerão abaixo.
          </p>
          <div id="groupPermissionsContainer" style="max-height: 400px; overflow-y: auto;">
            <div class="text-center py-4">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="saveNewGroup()">
          <i class="bi bi-save me-2"></i>Criar Grupo
        </button>
      </div>
    </div>
  </div>
</div>
