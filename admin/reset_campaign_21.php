<?php
require_once __DIR__ . '/_init.php';

$tenantId = 347;
$campaignId = 21;

echo "=== Atualizando campanha ID $campaignId para status 'pending' ===\n";

try {
    $stmt = db()->prepare("UPDATE concierge_campaigns SET status = 'pending', updated_at = NOW() WHERE tenant_id = ? AND id = ? LIMIT 1");
    $stmt->execute([$tenantId, $campaignId]);
    echo "✅ Linhas afetadas: " . $stmt->rowCount() . "\n";
    
    echo "\n=== Verificando o status final ===\n";
    $stmt = db()->prepare("SELECT id, status, title FROM concierge_campaigns WHERE tenant_id = ? AND id = ? LIMIT 1");
    $stmt->execute([$tenantId, $campaignId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Status: " . var_export($campaign['status'], true) . "\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
