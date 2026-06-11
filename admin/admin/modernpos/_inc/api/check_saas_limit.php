<?php
/**
 * API: Verificar Limite SaaS
 * GET /modernpos/_inc/api/check_saas_limit.php?type=products
 * 
 * @package ModernPOS
 * @subpackage SaaS API
 */

session_start();

// Carregar init
$initPath = realpath(__DIR__ . '/../../_init.php');
if (!file_exists($initPath)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Sistema não configurado']);
    exit;
}
include($initPath);

// Carregar helper de limites
include(__DIR__ . '/../saas_limits_check.php');

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Verificar autenticação
if (!function_exists('is_loggedin') || !is_loggedin()) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// Obter tipo
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

if (!in_array($type, ['products', 'customers', 'users', 'stores'])) {
    echo json_encode(['success' => false, 'error' => 'Tipo inválido. Use: products, customers, users, stores']);
    exit;
}

// Obter informações de limite
$info = get_limit_info($type);

// Retornar resposta
echo json_encode([
    'success' => true,
    'type' => $type,
    'is_saas' => $info['is_saas'],
    'can_create' => $info['can_create'],
    'current' => $info['current'],
    'limit' => $info['limit'],
    'unlimited' => $info['unlimited'],
    'percentage' => $info['percentage'],
    'plan_name' => $info['plan_name']
]);
