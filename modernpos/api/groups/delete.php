<?php
/**
 * API: Excluir Grupo Customizado
 *
 * Regras:
 * - Apenas Owner do tenant
 * - Só pode excluir grupos do próprio tenant (tenant_id = tenant atual)
 * - Não pode excluir grupos do sistema (tenant_id IS NULL)
 * - Não pode excluir grupo com usuários vinculados
 */

// PRIMEIRO: Iniciar buffer para capturar qualquer output
ob_start();

// Suprimir TODOS os erros de exibição
error_reporting(0);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Iniciar sessão com supressão de warnings
@session_start();

// Carregar _init.php com supressão de warnings
@require_once dirname(__DIR__, 2) . '/_init.php';

// Limpar TODO o output que possa ter sido gerado
ob_end_clean();

// Agora sim, enviar header JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Verificar se está logado
if (!$user->isLogged()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// Verificar se é Administrador (Owner)
if (!function_exists('is_tenant_owner') || !is_tenant_owner()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Apenas Administradores podem excluir grupos']);
    exit;
}

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

// Receber dados (JSON)
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit;
}

global $db;

$groupId = (int)($data['group_id'] ?? 0);
$reassignToGroupId = (int)($data['reassign_to_group_id'] ?? 0);

if ($groupId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'group_id inválido']);
    exit;
}

if ($groupId === 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Grupo Admin não pode ser excluído']);
    exit;
}

// Pegar tenant_id do usuário
$userId = user_id();
$userData = get_the_user($userId);
$tenantId = $userData['tenant_id'] ?? null;

if (!$tenantId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tenant não identificado']);
    exit;
}

try {
    // Buscar grupo
    $stmt = db()->prepare("SELECT group_id, tenant_id, name FROM user_group WHERE group_id = ? LIMIT 1");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Grupo não encontrado']);
        exit;
    }

    // Bloquear grupos do sistema
    if ($group['tenant_id'] === null) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Grupo do sistema não pode ser excluído']);
        exit;
    }

    // Garantir que pertence ao tenant
    if ((int)$group['tenant_id'] !== (int)$tenantId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Grupo não pertence ao tenant atual']);
        exit;
    }

    // Validação: não pode excluir grupo com usuários vinculados
    $stmtUsers = db()->prepare("SELECT COUNT(*) FROM users WHERE group_id = ? AND tenant_id = ?");
    $stmtUsers->execute([$groupId, $tenantId]);
    $totalUsers = (int)$stmtUsers->fetchColumn();

    if ($totalUsers > 0) {
        // Se foi informado grupo destino, realocar e excluir em uma única operação
        if ($reassignToGroupId > 0 && $reassignToGroupId !== $groupId) {
            // Validar grupo destino
            if ($reassignToGroupId === 1) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Não é permitido realocar usuários para o grupo Admin']);
                exit;
            }

            $stmtTarget = db()->prepare("SELECT group_id, tenant_id FROM user_group WHERE group_id = ? LIMIT 1");
            $stmtTarget->execute([$reassignToGroupId]);
            $target = $stmtTarget->fetch(PDO::FETCH_ASSOC);

            if (!$target) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Grupo de destino não encontrado']);
                exit;
            }

            // Permitir realocar para grupo do tenant atual ou grupo do sistema
            if (!($target['tenant_id'] === null || (int)$target['tenant_id'] === (int)$tenantId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Grupo de destino não pertence ao tenant atual']);
                exit;
            }

            db()->beginTransaction();

            // Realocar usuários
            $stmtMove = db()->prepare("UPDATE users SET group_id = ? WHERE tenant_id = ? AND group_id = ?");
            $stmtMove->execute([$reassignToGroupId, $tenantId, $groupId]);
            $moved = (int)$stmtMove->rowCount();

            // Excluir o grupo
            $stmtDel = db()->prepare("DELETE FROM user_group WHERE group_id = ? AND tenant_id = ?");
            $stmtDel->execute([$groupId, $tenantId]);

            db()->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Usuários realocados e grupo excluído com sucesso',
                'group_id' => $groupId,
                'reassigned_to_group_id' => $reassignToGroupId,
                'moved_users' => $moved,
            ]);
            exit;
        }

        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'Este grupo possui usuários. Realocar usuários para outro grupo antes de excluir.',
            'code' => 'GROUP_HAS_USERS',
            'group_id' => $groupId,
            'total_users' => $totalUsers,
        ]);
        exit;
    }

    // Excluir (sem usuários)
    $stmtDel = db()->prepare("DELETE FROM user_group WHERE group_id = ? AND tenant_id = ?");
    $stmtDel->execute([$groupId, $tenantId]);

    echo json_encode([
        'success' => true,
        'message' => 'Grupo excluído com sucesso',
        'group_id' => $groupId,
    ]);

} catch (Exception $e) {
    try {
        if (db() && method_exists(db(), 'inTransaction') && db()->inTransaction()) {
            db()->rollBack();
        }
    } catch (Exception $e2) {
        // ignore rollback errors
    }

    error_log('Erro ao excluir grupo: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao excluir grupo: ' . $e->getMessage()]);
}
