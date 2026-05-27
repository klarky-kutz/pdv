<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/_inc/helper/ai_groups_helper.php';

$tenantId = 347;
$campaignId = 21;

echo "=== Obtendo token da loja ===" . PHP_EOL;
$stmt = db()->prepare("SELECT ai_webhook_token FROM stores WHERE store_id = ? LIMIT 1");
$stmt->execute([$tenantId]);
$token = (string)$stmt->fetchColumn();
echo "Token: " . $token . PHP_EOL;

echo "=== Preparando curl para o endpoint send_now ===" . PHP_EOL;
$url = ROOT_URL . '/api/concierge/campaigns.php?action=send_now';
$payload = [
    'campaign_id' => $campaignId,
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'X-Concierge-Token: ' . $token,
    'X-Store-Id: ' . $tenantId,
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
if (stripos($url, 'https://') === 0) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
}

echo "Executando curl..." . PHP_EOL;
$raw = curl_exec($ch);
$err = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== Resultado ===" . PHP_EOL;
echo "HTTP Code: " . $httpCode . PHP_EOL;
if ($err) {
    echo "Curl Error: " . $err . PHP_EOL;
}
echo "Raw Response: " . $raw . PHP_EOL;
$decoded = json_decode($raw, true);
if ($decoded) {
    echo "Decoded Response: " . var_export($decoded, true) . PHP_EOL;
}
