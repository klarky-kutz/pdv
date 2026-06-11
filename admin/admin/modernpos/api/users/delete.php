<?php
/**
 * API: Excluir Usuário (Painel /conta)
 *
 * Implementação SaaS (hard delete):
 * - Apenas Admin (group_id=1) ou Owner do tenant
 * - Somente usuários do tenant atual
 * - Não permite excluir o Owner do tenant
 * - Remove vínculos (user_to_store) e remove o usuário (users)
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

// Somente Admin/Owner
$requesterIsAdmin = (function_exists('user_group_id') && user_group_id() == 1)
    || (function_exists('is_tenant_owner') && is_tenant_owner());

if (!$requesterIsAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Somente Administrador pode excluir usuários']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit;
}

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

$targetUserId = (int)($data['id'] ?? 0);
if ($targetUserId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'id inválido']);
    exit;
}

try {
    // Garantir que o usuário pertence ao tenant
    $stUser = db()->prepare('SELECT id, tenant_id, status FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stUser->execute([(int)$targetUserId, (int)$tenantId]);
    $target = $stUser->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
        exit;
    }

    // Não permitir excluir owner do tenant
    $ownerId = 0;
    try {
        $stOwner = db()->prepare('SELECT owner_user_id FROM tenants WHERE tenant_id = ? LIMIT 1');
        $stOwner->execute([(int)$tenantId]);
        $ownerId = (int)$stOwner->fetchColumn();
    } catch (Throwable $e) {
        $ownerId = 0;
    }

    if ($ownerId > 0 && (int)$ownerId === (int)$targetUserId) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'O proprietário da conta não pode ser excluído']);
        exit;
    }

    // Hard delete (transação)
    $pdo = db();
    if (method_exists($pdo, 'beginTransaction')) {
        $pdo->beginTransaction();
    }

    try {
        // Remover vínculos primeiro
        $stLinks = $pdo->prepare('DELETE FROM user_to_store WHERE user_id = ?');
        $stLinks->execute([(int)$targetUserId]);

        // Opcional: limpar logs (se tabela existir). Não falhar se não existir.
        try {
            $stLogs = $pdo->prepare('DELETE FROM login_logs WHERE user_id = ?');
            $stLogs->execute([(int)$targetUserId]);
        } catch (Throwable $e) {
            // ignore
        }

        // Remover usuário (garantindo tenant)
        $stDel = $pdo->prepare('DELETE FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stDel->execute([(int)$targetUserId, (int)$tenantId]);

        // Se não removeu, trata como erro (evita "sucesso" em caso de race)
        if (method_exists($stDel, 'rowCount') && (int)$stDel->rowCount() <= 0) {
            throw new Exception('Usuário não encontrado para exclusão');
        }

        if (method_exists($pdo, 'commit')) {
            $pdo->commit();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Usuário excluído com sucesso',
            'id' => (int)$targetUserId,
        ]);
    } catch (Throwable $e) {
        if (method_exists($pdo, 'rollBack')) {
            try { $pdo->rollBack(); } catch (Throwable $e2) { /* ignore */ }
        }
        throw $e;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao excluir usuário: ' . $e->getMessage()]);
}
