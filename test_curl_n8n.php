<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();
session_start();
include('_init.php');

require_once DIR_HELPER . 'ai_groups_helper.php';
require_once DIR_HELPER . 'ai_concierge.php';

ob_end_clean();
header('Content-Type: text/plain; charset=UTF-8');

$tenantId = 347;
$campaignId = 21;
$groupId = 4;

echo "=== Obtendo configurações do tenant $tenantId ===" . PHP_EOL;
$targetUrl = ai_groups_dispatch_webhook_url($tenantId, 'campaign');
$token = ai_groups_store_token($tenantId);

echo "Webhook URL: $targetUrl" . PHP_EOL;
echo "Token: " . (empty($token) ? '(vazio)' : substr($token, 0, 10) . '...') . PHP_EOL;
echo PHP_EOL;

if (empty($targetUrl) || empty($token)) {
    echo "ERRO: URL ou token não configurados!" . PHP_EOL;
    exit;
}

echo "=== Obtendo dados da campanha $campaignId ===" . PHP_EOL;
$campaign = ai_get_concierge_campaign($tenantId, $campaignId);
$targets = ai_get_campaign_targets($tenantId, $campaignId);

if (!$campaign) {
    echo "ERRO: Campanha não encontrada!" . PHP_EOL;
    exit;
}

echo "Título: " . ($campaign['title'] ?? '') . PHP_EOL;
echo "Alvos: " . count($targets) . PHP_EOL;
echo PHP_EOL;

$payloadJson = ai_groups_decode_json($campaign['payload_json'] ?? null);
$callbackUrl = rtrim((string)ROOT_URL, '/') . '/api/concierge/campaign_status_webhook.php?loja_id=' . $tenantId;

$payload = [
    'source' => 'concierge_campaign_dispatch',
    'tenant_id' => $tenantId,
    'campaign_id' => $campaignId,
    'campaign' => [
        'id' => $campaignId,
        'title' => (string)($campaign['title'] ?? ''),
        'content' => (string)($campaign['content'] ?? ''),
        'media_url' => (string)($campaign['media_url'] ?? ''),
        'scheduled_at' => $campaign['scheduled_at'] ?? null,
        'payload_json' => $payloadJson,
    ],
    'targets' => $targets,
    'callback' => [
        'url' => $callbackUrl,
        'method' => 'POST',
        'headers' => [
            'X-Concierge-Token' => $token,
            'ngrok-skip-browser-warning' => '1',
        ],
        'body_template' => [
            'tenant_id' => $tenantId,
            'campaign_id' => $campaignId,
            'group_id' => '{{group_id}}',
            'status' => '{{status}}',
            'external_message_id' => '{{external_message_id}}',
            'error_message' => '{{error_message}}',
        ],
    ],
];

echo "=== Preparando requisição para N8N ===" . PHP_EOL;
echo "Payload: " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo PHP_EOL;

echo "=== Enviando requisição... ===" . PHP_EOL;
$resp = ai_groups_http_post_json($targetUrl, $payload, [
    'X-Concierge-Token: ' . $token,
    'X-Store-Id: ' . $tenantId,
]);

echo "=== Resultado ===" . PHP_EOL;
echo "OK: " . ($resp['ok'] ? 'Sim' : 'Não') . PHP_EOL;
echo "HTTP Code: " . ($resp['http_code'] ?? 0) . PHP_EOL;
echo "Erro: " . ($resp['error'] ?? '') . PHP_EOL;
echo "Raw Response: " . var_export($resp['raw'] ?? '', true) . PHP_EOL;
echo "JSON Decoded: " . var_export($resp['json'] ?? [], true) . PHP_EOL;
