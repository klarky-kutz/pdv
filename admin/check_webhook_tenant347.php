<?php
ob_start();
include '_init.php';
require_once DIR_HELPER . 'ai_groups_helper.php';

$tenantId = 347; // Seu tenant ID
echo '<h1>Configurações de Webhook para Tenant ID: ' . $tenantId . '</h1>';

try {
    // Consulta ai_settings diretamente
    $stmt = db()->prepare("SELECT * FROM ai_settings WHERE tenant_id = :tid");
    $stmt->execute([':tid' => $tenantId]);
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<h3>Todas as configurações do ai_settings:</h3>';
    echo '<div style="background:#f8f9fa;padding:10px;border-radius:6px;font-family:monospace;max-height:500px;overflow:auto">';
    foreach ($configs as $c) {
        $key = htmlspecialchars($c['key_name'] ?? '');
        $val = htmlspecialchars($c['value'] ?? '');
        echo '<div style="padding:4px 0;border-bottom:1px solid #e2e8f0">';
        echo '<span style="font-weight:700;color:#7c3aed">' . $key . '</span> = ' . $val;
        echo '</div>';
    }
    echo '</div>';

    // Verifica token da loja na tabela stores
    echo '<h3>Token da loja (stores.ai_webhook_token):</h3>';
    $stmt2 = db()->prepare('SELECT ai_webhook_token FROM stores WHERE store_id = :tid LIMIT 1');
    $stmt2->execute([':tid' => $tenantId]);
    $token = $stmt2->fetchColumn();
    echo '<div style="background:#f8f9fa;padding:10px;border-radius:6px;font-family:monospace">' . htmlspecialchars($token) . '</div>';
} catch (Throwable $e) {
    echo '<h3>Erro:</h3>';
    echo '<div style="background:#fee2e2;padding:10px;border-radius:6px;color:#dc2626">' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}
?>