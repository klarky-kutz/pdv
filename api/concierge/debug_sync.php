<?php
ob_start();
session_start();
include('../../_init.php');
require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_evolution.php';

header('Content-Type: application/json; charset=UTF-8');

if (!is_loggedin()) {
    echo json_encode(['error' => true, 'message' => 'Não logado']);
    exit;
}

$tenantId = ai_tenant_id();
$connection = ai_evolution_get_connection($tenantId);

$debug = [
    'tenant_id' => $tenantId,
    'instance_name' => $connection['instance_name'],
    'base_url' => $connection['base_url'],
    'has_token' => !empty($connection['global_token']),
    'webhook_url' => $connection['webhook_inbound_url'],
];

// Testar a conexão com a Evolution
$instance = $connection['instance_name'];
$baseUrl = $connection['base_url'];
$token = $connection['global_token'];

if ($instance && $baseUrl && $token) {
    $path = '/instance/connectionState/' . rawurlencode($instance);
    $resp = ai_evolution_http_request('GET', $baseUrl . $path, $token);
    $debug['evolution_test'] = [
        'path' => $path,
        'http_code' => $resp['http_code'],
        'ok' => $resp['ok'],
        'json' => $resp['json'],
        'error' => $resp['error']
    ];
} else {
    $debug['evolution_test'] = 'Configuração incompleta';
}

echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
