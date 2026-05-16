<?php
include __DIR__ . '/_init.php';

$tenantId = 347;
$query = 'vestido inspiração farm';

$stStore = db()->prepare("SELECT ai_webhook_token FROM stores WHERE store_id = :tid LIMIT 1");
$stStore->execute([':tid' => $tenantId]);
$token = (string)$stStore->fetchColumn();

if ($token === '') {
    echo "TOKEN_NOT_FOUND\n";
    exit(1);
}

$url = 'http://localhost/modernpos/api/concierge/webhook.php?action=buscar_produto&loja_id=' . $tenantId;
$payload = ['query' => $query, 'limit' => 5];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Concierge-Token: ' . $token,
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$raw = curl_exec($ch);
$err = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$successCount = is_string($raw) ? substr_count($raw, '"success"') : 0;
$resultsCount = is_string($raw) ? substr_count($raw, '"results"') : 0;

echo json_encode([
    'http_code' => $code,
    'curl_error' => $err,
    'raw_length' => is_string($raw) ? strlen($raw) : 0,
    'success_occurrences' => $successCount,
    'results_occurrences' => $resultsCount,
    'raw_response' => is_string($raw) ? $raw : null,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
