<?php
/**
 * API: Listar histórico de login (Painel /conta)
 *
 * Regras:
 * - Logado
 * - Usuário alvo deve pertencer ao tenant atual
 * - Admin/Owner pode ver de qualquer usuário do tenant
 * - Não-admin pode ver apenas o próprio histórico
 */

ob_start();
error_reporting(0);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
@session_start();
@require_once dirname(__DIR__, 2) . '/_init.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

if (!$user->isLogged()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$targetUserId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
if ($limit <= 0) $limit = 50;
if ($limit > 200) $limit = 200;

if ($targetUserId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'id inválido']);
    exit;
}

$requesterId = function_exists('user_id') ? (int)user_id() : 0;
$requesterIsAdmin = (function_exists('user_group_id') && user_group_id() == 1)
    || (function_exists('is_tenant_owner') && is_tenant_owner());

// Carregar SaasLimitsBridge
if (!class_exists('SaasLimitsBridge')) {
    $saasLimitsPath = dirname(__DIR__, 2) . '/../saas/includes/SaasLimitsBridge.php';
    if (file_exists($saasLimitsPath)) {
        require_once $saasLimitsPath;
    }
}

// Resolver tenant
$tenantId = 0;
try {
    $sessionTid = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    if (class_exists('SaasLimitsBridge')) {
        $tenantId = SaasLimitsBridge::resolveTenantId(db(), $requesterId, $sessionTid > 0 ? $sessionTid : null);
    } else {
        $tenantId = $sessionTid;
    }
} catch (Throwable $e) {
    $tenantId = 0;
}

if ($tenantId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tenant não identificado']);
    exit;
}

// Não-admin só pode ver o próprio histórico
if (!$requesterIsAdmin && $targetUserId !== $requesterId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para ver o histórico de login deste usuário']);
    exit;
}

try {
    // Garantir que usuário alvo pertence ao tenant
    $st = db()->prepare('SELECT id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
    $st->execute([(int)$targetUserId, (int)$tenantId]);
    if (!$st->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
        exit;
    }

    // Buscar logs
    $st = db()->prepare('SELECT id, user_id, username, ip, created_at FROM login_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . (int)$limit);
    $st->execute([(int)$targetUserId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $logs = [];
    foreach ($rows as $r) {
        $logs[] = [
            'id' => (int)($r['id'] ?? 0),
            'user_id' => (int)($r['user_id'] ?? 0),
            'username' => (string)($r['username'] ?? ''),
            'ip' => (string)($r['ip'] ?? ''),
            'created_at' => (string)($r['created_at'] ?? ''),
        ];
    }

    echo json_encode([
        'success' => true,
        'user_id' => (int)$targetUserId,
        'count' => count($logs),
        'logs' => $logs,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar histórico de login: ' . $e->getMessage()]);
}
