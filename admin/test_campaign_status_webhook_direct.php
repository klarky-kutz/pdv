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

echo "=== Obtendo token da loja ===" . PHP_EOL;
$stmt = db()->prepare("SELECT ai_webhook_token FROM stores WHERE store_id = ? LIMIT 1");
$stmt->execute([$tenantId]);
$token = (string)$stmt->fetchColumn();
echo "Token: " . $token . PHP_EOL;
echo PHP_EOL;

$url = rtrim((string)ROOT_URL, '/') . '/api/concierge/campaign_status_webhook.php?loja_id=' . $tenantId;
echo "=== Testando endpoint: $url ===" . PHP_EOL;
echo PHP_EOL;

$payload = [
    'tenant_id' => $tenantId,
    'campaign_id' => $campaignId,
    'group_id' => $groupId,
    'status' => 'sent',
    'external_message_id' => 'test_external_msg_' . time(),
    'error_message' => '',
];

echo "=== Payload ===" . PHP_EOL;
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo PHP_EOL;

echo "=== Enviando requisição POST ===" . PHP_EOL;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'X-Concierge-Token: ' . $token,
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
if (stripos($url, 'https://') === 0) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
}

$raw = curl_exec($ch);
$err = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== Resultado ===" . PHP_EOL;
echo "HTTP Code: " . $httpCode . PHP_EOL;
echo "Curl Error: " . $err . PHP_EOL;
echo "Raw Response (length: " . strlen($raw) . "):" . PHP_EOL;
echo "--- BEGIN RAW RESPONSE ---" . PHP_EOL;
echo $raw . PHP_EOL;
echo "--- END RAW RESPONSE ---" . PHP_EOL;
echo PHP_EOL;

$decoded = json_decode($raw, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✅ JSON VÁLIDO!" . PHP_EOL;
    echo "Decoded Response:" . PHP_EOL;
    echo var_export($decoded, true) . PHP_EOL;
} else {
    echo "❌ JSON INVÁLIDO!" . PHP_EOL;
    echo "Erro: " . json_last_error_msg() . PHP_EOL;
    echo "Erro Code: " . json_last_error() . PHP_EOL;
    echo PHP_EOL;
    echo "Analisando bytes da resposta..." . PHP_EOL;
    for ($i = 0; $i < min(20, strlen($raw)); $i++) {
        $char = $raw[$i];
        $ord = ord($char);
        echo "  Byte $i: '$char' (ASCII: $ord)" . PHP_EOL;
    }
}
