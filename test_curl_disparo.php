<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/_inc/helper/ai_groups_helper.php';

$tenantId = 347;
$campaignId = 21;

echo "=== Testando disparo da campanha ID $campaignId ===" . PHP_EOL;

try {
    $targets = ai_get_campaign_targets($tenantId, $campaignId);
    echo "✅ Targets carregados: " . count($targets) . PHP_EOL;

    $conflict = ai_groups_find_campaign_schedule_conflict($tenantId, date('Y-m-d H:i:s'), $campaignId, 5);
    if ($conflict) {
        echo "❌ Conflito de agendamento: " . var_export($conflict, true) . PHP_EOL;
    } else {
        echo "✅ Sem conflito de agendamento" . PHP_EOL;
    }

    $result = ai_dispatch_concierge_campaign_now($tenantId, $campaignId, 0);
    echo "Resultado de ai_dispatch_concierge_campaign_now: " . var_export($result, true) . PHP_EOL;

    if ($result) {
        echo "=== Verificando o status atual da campanha ===" . PHP_EOL;
        $campaign = ai_get_concierge_campaign($tenantId, $campaignId);
        echo "Status da campanha: " . var_export($campaign['status'], true) . PHP_EOL;
        echo "Scheduled at: " . var_export($campaign['scheduled_at'], true) . PHP_EOL;
        echo "Webhook requested at: " . var_export($campaign['webhook_requested_at'], true) . PHP_EOL;
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
