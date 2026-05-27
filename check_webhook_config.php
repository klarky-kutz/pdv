<?php
ob_start();
include '_init.php';
require_once DIR_HELPER . 'ai_groups_helper.php';

$tenantId = 347; // Seu tenant ID
echo '<h1>Configurações de Webhook para Tenant ID: ' . $tenantId . '</h1>';

// 1. Verifica webhook para campanhas
$campaignWebhook = ai_groups_dispatch_webhook_url($tenantId, 'campaign');
echo '<h3>Webhook para campanhas:</h3>';
echo '<div style="background:#f8f9fa;padding:10px;border-radius:6px;font-family:monospace">' . htmlspecialchars($campaignWebhook) . '</div>';

// 2. Verifica webhook para status
$statusWebhook = ai_groups_dispatch_webhook_url($tenantId, 'status');
echo '<h3>Webhook para status:</h3>';
echo '<div style="background:#f8f9fa;padding:10px;border-radius:6px;font-family:monospace">' . htmlspecialchars($statusWebhook) . '</div>';

// 3. Verifica o token da loja
try {
    $token = ai_groups_store_token($tenantId);
    echo '<h3>Token da loja (ai_webhook_token):</h3>';
    echo '<div style="background:#f8f9fa;padding:10px;border-radius:6px;font-family:monospace">' . htmlspecialchars($token) . '</div>';
} catch (Throwable $e) {
    echo '<h3>Erro ao buscar token:</h3>';
    echo '<div style="background:#fee2e2;padding:10px;border-radius:6px;color:#dc2626">' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>