<?php
/**
 * API: Alterar Senha do Usuário (Painel /conta)
 *
 * Regras:
 * - Deve estar logado
 * - Deve pertencer ao tenant atual
 * - Apenas Admin/Owner ou usuário com permissão update_user pode alterar senha
 * - Somente Admin/Owner pode alterar senha de Admin (group_id=1) ou do Owner do tenant
 * - Usuário não-admin não pode alterar senha de usuário com grupo superior ao seu
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

// Permissão para alterar senha (gestão de usuários)
$requesterIsAdmin = (function_exists('user_group_id') && user_group_id() == 1)
    || (function_exists('is_tenant_owner') && is_tenant_owner());

$canChangePassword = $requesterIsAdmin
    || (function_exists('has_permission') && has_permission('access', 'update_user'));

if (!$canChangePassword) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para alterar senha de usuários']);
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
$newPassword = (string)($data['password'] ?? '');
$newPasswordConfirm = (string)($data['password_confirm'] ?? '');

if ($targetUserId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'id inválido']);
    exit;
}

if ($newPassword === '' || strlen($newPassword) < 6) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'A senha deve ter no mínimo 6 caracteres']);
    exit;
}

if ($newPassword !== $newPasswordConfirm) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'As senhas não coincidem']);
    exit;
}

if (function_exists('checkPasswordStrongness')) {
    $chk = checkPasswordStrongness($newPassword);
    if ($chk !== 'ok') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => (string)$chk]);
        exit;
    }
}

try {
    // Garantir que usuário pertence ao tenant
    $stUser = db()->prepare('SELECT id, tenant_id, group_id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stUser->execute([(int)$targetUserId, (int)$tenantId]);
    $target = $stUser->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
        exit;
    }

    // Identificar owner do tenant
    $ownerId = 0;
    try {
        $stOwner = db()->prepare('SELECT owner_user_id FROM tenants WHERE tenant_id = ? LIMIT 1');
        $stOwner->execute([(int)$tenantId]);
        $ownerId = (int)$stOwner->fetchColumn();
    } catch (Throwable $e) {
        $ownerId = 0;
    }

    $targetIsOwner = ($ownerId > 0 && (int)$ownerId === (int)$targetUserId);
    $targetIsAdminGroup = ((int)($target['group_id'] ?? 0) === 1);

    if (($targetIsOwner || $targetIsAdminGroup) && !$requesterIsAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Somente Administrador pode alterar a senha de contas do grupo Administrador']);
        exit;
    }

    // Regra: usuário não-admin não pode alterar senha de usuário com grupo superior ao seu
    if (!$requesterIsAdmin) {
        $requesterGroupId = function_exists('user_group_id') ? (int)user_group_id() : 0;

        $extractAccessKeys = function ($serializedOrArray) {
            $arr = [];
            if (is_array($serializedOrArray)) {
                $arr = $serializedOrArray;
            } elseif (is_string($serializedOrArray) && $serializedOrArray !== '') {
                $tmp = null;
                if (function_exists('valid_unserialize')) {
                    $tmp = valid_unserialize($serializedOrArray);
                } else {
                    $tmp = @unserialize($serializedOrArray);
                }
                if (is_array($tmp)) {
                    $arr = $tmp;
                }
            }

            $keys = [];
            if (isset($arr['access']) && is_array($arr['access'])) {
                foreach ($arr['access'] as $k => $v) {
                    if ($v) {
                        $keys[] = (string)$k;
                    }
                }
            }
            return $keys;
        };

        $loadGroupAccessKeys = function (int $gid) use ($extractAccessKeys) {
            if ($gid <= 0) return [];
            try {
                $st = db()->prepare('SELECT permission FROM user_group WHERE group_id = ? LIMIT 1');
                $st->execute([(int)$gid]);
                $perm = (string)$st->fetchColumn();
                return $extractAccessKeys($perm);
            } catch (Throwable $e) {
                return [];
            }
        };

        $requesterSet = [];
        foreach ($loadGroupAccessKeys($requesterGroupId) as $k) {
            $requesterSet[$k] = true;
        }

        $targetGroupId = (int)($target['group_id'] ?? 0);

        // Fallback seguro: se não conseguiu ler permissões do solicitante, só permite alterar senha de usuários do mesmo grupo
        if ($requesterGroupId > 0 && empty($requesterSet) && (int)$targetGroupId !== (int)$requesterGroupId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Você não pode alterar senha de usuários com grupo superior ao seu']);
            exit;
        }

        foreach ($loadGroupAccessKeys($targetGroupId) as $k) {
            if (!isset($requesterSet[$k])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não pode alterar senha de usuários com grupo superior ao seu']);
                exit;
            }
        }
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Atualizar senha (e limpar reset codes)
    $stUpd = db()->prepare('UPDATE users SET password = ?, pass_reset_code = ?, reset_code_time = ? WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stUpd->execute([$hash, '', null, (int)$targetUserId, (int)$tenantId]);

    echo json_encode([
        'success' => true,
        'message' => 'Senha alterada com sucesso',
        'id' => (int)$targetUserId,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao alterar senha: ' . $e->getMessage()]);
}
