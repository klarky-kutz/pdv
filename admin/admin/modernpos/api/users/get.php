<?php
/**
 * API: Obter Usuário (Painel /conta)
 *
 * Retorna dados do usuário (somente do tenant atual) para preencher a modal.
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

// Permissão para visualizar usuários:
// - Administrador global (group_id=1) OU Owner do tenant
// - OU usuário com permissão read_user / update_user (RBAC)
$requesterIsAdmin = (function_exists('user_group_id') && user_group_id() == 1)
    || (function_exists('is_tenant_owner') && is_tenant_owner());

$canReadUsers = $requesterIsAdmin
    || (function_exists('has_permission') && has_permission('access', 'read_user'))
    || (function_exists('has_permission') && has_permission('access', 'update_user'));

if (!$canReadUsers) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para acessar usuários']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'id inválido']);
    exit;
}

// Carregar bridge
if (!class_exists('SaasLimitsBridge')) {
    $saasLimitsPath = dirname(__DIR__, 2) . '/../saas/includes/SaasLimitsBridge.php';
    if (file_exists($saasLimitsPath)) {
        require_once $saasLimitsPath;
    }
}

$tenantId = 0;
try {
    $sessionTid = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    $uid = function_exists('user_id') ? (int)user_id() : 0;
    if (class_exists('SaasLimitsBridge')) {
        $tenantId = SaasLimitsBridge::resolveTenantId(db(), $uid, $sessionTid > 0 ? $sessionTid : null);
    }
} catch (Throwable $e) {
    $tenantId = 0;
}

if ((int)$tenantId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tenant não identificado']);
    exit;
}

try {
    $st = db()->prepare('SELECT id, username, email, mobile, group_id, status, dob, user_image, tenant_id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
    $st->execute([(int)$id, (int)$tenantId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    // Regra: somente Administradores (Owner do tenant ou group_id=1) podem acessar usuários Admin/Owner
    $ownerId = 0;
    try {
        $stOwner = db()->prepare('SELECT owner_user_id FROM tenants WHERE tenant_id = ? LIMIT 1');
        $stOwner->execute([(int)$tenantId]);
        $ownerId = (int)$stOwner->fetchColumn();
    } catch (Throwable $e) {
        $ownerId = 0;
    }

    $targetIsOwner = ($ownerId > 0 && (int)$ownerId === (int)$id);
    $targetIsAdminGroup = ((int)($row['group_id'] ?? 0) === 1);

    if (($targetIsOwner || $targetIsAdminGroup) && !$requesterIsAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Apenas Administradores podem acessar usuários do grupo Administrador']);
        exit;
    }

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
        exit;
    }

    // Stores vinculadas (podem ser múltiplas; modal atual usa 1)
    $stStores = db()->prepare('SELECT store_id, status, sort_order FROM user_to_store WHERE user_id = ? ORDER BY store_id ASC');
    $stStores->execute([(int)$id]);
    $links = $stStores->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $storeIds = [];
    $firstStoreId = 0;
    $sortOrder = 0;

    foreach ($links as $i => $lnk) {
        $sid = (int)($lnk['store_id'] ?? 0);
        if ($sid <= 0) continue;
        $storeIds[] = $sid;
        if ($firstStoreId <= 0) {
            $firstStoreId = $sid;
            $sortOrder = (int)($lnk['sort_order'] ?? 0);
        }
    }

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => (int)$row['id'],
            'username' => (string)($row['username'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'mobile' => (string)($row['mobile'] ?? ''),
            'group_id' => (int)($row['group_id'] ?? 0),
            'status' => (int)($row['status'] ?? 0),
            'dob' => (string)($row['dob'] ?? ''),
            'sort_order' => (int)$sortOrder,
            'store_id' => (int)$firstStoreId,
            'store_ids' => $storeIds,
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar usuário: ' . $e->getMessage()]);
}
