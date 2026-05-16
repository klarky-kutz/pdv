<?php
session_start();
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../saas/includes/SaasLimitsBridge.php';

$pdo = db();
$tenantId = 1;

echo "=== TESTE DE CONTAGEM DE USUARIOS ===\n\n";

// Query direta
$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ?');
$stmt->execute([$tenantId]);
$directCount = (int)$stmt->fetchColumn();
echo "Query direta (COUNT users WHERE tenant_id=1): " . $directCount . "\n";

// Via SaasLimitsBridge
$bridgeCount = SaasLimitsBridge::countTenantUsersTotal($pdo, $tenantId);
echo "SaasLimitsBridge::countTenantUsersTotal(1): " . $bridgeCount . "\n";

// Limites do plano
$limits = SaasLimitsBridge::getPlanLimits($pdo, $tenantId);
echo "\nLimites do plano:\n";
print_r($limits);

// Sessao
echo "\n\$_SESSION['tenant_id']: " . (isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : 'NAO DEFINIDO') . "\n";
