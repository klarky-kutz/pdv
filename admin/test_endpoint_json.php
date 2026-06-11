<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$url = 'http://localhost/modernpos/test_json.php';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
if (stripos($url, 'https://') === 0) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
}

echo "=== Testando endpoint: $url ===\n";
$raw = curl_exec($ch);
$err = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Curl Error: $err\n";
echo "Raw Response: " . var_export($raw, true) . "\n";

$decoded = json_decode($raw, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✅ JSON válido!\n";
    echo "Decoded: " . var_export($decoded, true) . "\n";
} else {
    echo "❌ JSON inválido!\n";
    echo "Erro: " . json_last_error_msg() . "\n";
}
