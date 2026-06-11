<?php
/**
 * API: Atualizar Usuário (Painel /conta)
 *
 * Regras SaaS:
 * - Apenas Owner do tenant (ou superadmin)
 * - Só pode atualizar usuários do tenant atual
 * - Valida lojas pertencem ao tenant
 * - Não aplica quota em update/toggle de status (quota é baseada em total de usuários e deve ser aplicada apenas na criação)
 * - Não permite desativar o owner do tenant
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

// Permissão para editar usuários:
// - Administrador global (group_id=1) OU Owner do tenant
// - OU usuário com permissão update_user (RBAC)
$requesterIsAdmin = (function_exists('user_group_id') && user_group_id() == 1)
    || (function_exists('is_tenant_owner') && is_tenant_owner());

$canUpdateUsers = $requesterIsAdmin
    || (function_exists('has_permission') && has_permission('access', 'update_user'));

if (!$canUpdateUsers) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para editar usuários']);
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

$id = (int)($data['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'id inválido']);
    exit;
}

$username = trim((string)($data['username'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$mobile = trim((string)($data['mobile'] ?? ''));
$dob = trim((string)($data['dob'] ?? ''));
$groupId = (int)($data['group_id'] ?? 0);
$status = isset($data['status']) ? (int)$data['status'] : 1;
$sortOrder = isset($data['sort_order']) ? (int)($data['sort_order'] ?? 0) : 0;
$storeId = (int)($data['store_id'] ?? 0);
$storeIds = $storeId > 0 ? [$storeId] : [];

// Normalizações
$emailNorm = strtolower($email);
$mobileDigits = preg_replace('/\D+/', '', $mobile);

if ($username === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Nome é obrigatório']);
    exit;
}

if ($email === '' && $mobileDigits === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'E-mail ou celular é obrigatório']);
    exit;
}

if ($emailNorm !== '') {
    $isValidEmail = function_exists('validateEmail') ? validateEmail($emailNorm) : (filter_var($emailNorm, FILTER_VALIDATE_EMAIL) !== false);
    if (!$isValidEmail) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'E-mail inválido']);
        exit;
    }
}

if ($mobileDigits !== '') {
    if (strlen($mobileDigits) < 10 || strlen($mobileDigits) > 11) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Telefone inválido. Informe DDD + número (10 ou 11 dígitos).']);
        exit;
    }
}

if ($groupId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Selecione um grupo de permissão']);
    exit;
}

if ($storeId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Selecione uma loja']);
    exit;
}

try {
    // Garantir que usuário pertence ao tenant
    $stUser = db()->prepare('SELECT id, tenant_id, group_id, status, dob, user_image FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stUser->execute([(int)$id, (int)$tenantId]);
    $current = $stUser->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
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
    } catch (Exception $e) {
        $ownerId = 0;
    }

    $targetIsOwner = ($ownerId > 0 && $ownerId === (int)$id);
    $targetIsAdminGroup = ((int)($current['group_id'] ?? 0) === 1);

    // Regra: somente Administradores (Owner do tenant ou group_id=1) podem alterar contas Admin/Owner
    if (($targetIsOwner || $targetIsAdminGroup) && !$requesterIsAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Apenas Administradores podem alterar contas do grupo Administrador']);
        exit;
    }

    // Painel /conta: grupo Admin (group_id=1) não é atribuível (mesmo para owner)
    if ((int)$groupId === 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'O grupo Administrador não pode ser atribuído pelo Painel da Conta']);
        exit;
    }

    // Regra: usuário não-admin não pode editar usuários com grupo superior ao seu,
    // nem atribuir um grupo superior ao seu.
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

        // Permissões do usuário atual (set)
        $requesterSet = [];
        foreach ($loadGroupAccessKeys($requesterGroupId) as $k) {
            $requesterSet[$k] = true;
        }

        // Fallback seguro: se não conseguimos ler as permissões do solicitante,
        // permite apenas manter o MESMO grupo (não consegue editar/atribuir grupos diferentes).
        $targetCurrentGroupId = (int)($current['group_id'] ?? 0);
        if ($requesterGroupId > 0 && empty($requesterSet)) {
            if ((int)$targetCurrentGroupId !== (int)$requesterGroupId || (int)$groupId !== (int)$requesterGroupId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não pode alterar usuários com grupo superior ao seu']);
                exit;
            }
        }

        // Grupo atual do usuário alvo
        foreach ($loadGroupAccessKeys($targetCurrentGroupId) as $k) {
            if (!isset($requesterSet[$k])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não pode alterar usuários com grupo superior ao seu']);
                exit;
            }
        }

        // Novo grupo solicitado
        foreach ($loadGroupAccessKeys((int)$groupId) as $k) {
            if (!isset($requesterSet[$k])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Você não pode atribuir um grupo superior ao seu']);
                exit;
            }
        }
    }

    // Não permitir desativar owner
    if ($targetIsOwner && (int)$status === 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'O proprietário da conta não pode ser desativado']);
        exit;
    }

    // Não permitir alterar grupo do owner
    $currentGroupId = (int)($current['group_id'] ?? 0);
    if ($targetIsOwner && (int)$groupId !== $currentGroupId) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'O proprietário da conta não pode ter seu grupo alterado']);
        exit;
    }

    // Unicidade dentro do tenant (nome/e-mail/telefone)
    $nameNorm = strtolower($username);

    $st = db()->prepare('SELECT id FROM users WHERE tenant_id = ? AND LOWER(username) = ? AND id != ? LIMIT 1');
    $st->execute([(int)$tenantId, $nameNorm, (int)$id]);
    if ($st->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Já existe um usuário com este nome nesta conta']);
        exit;
    }

    if ($emailNorm !== '') {
        $st = db()->prepare('SELECT id FROM users WHERE tenant_id = ? AND LOWER(email) = ? AND id != ? LIMIT 1');
        $st->execute([(int)$tenantId, $emailNorm, (int)$id]);
        if ($st->fetchColumn()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Já existe um usuário com este e-mail nesta conta']);
            exit;
        }
    }

    if ($mobileDigits !== '') {
        $st = db()->prepare("SELECT id FROM users WHERE tenant_id = ? AND id != ? AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mobile,'(',''),')',''),'-',''),' ',''),'+','') = ? LIMIT 1");
        $st->execute([(int)$tenantId, (int)$id, $mobileDigits]);
        if ($st->fetchColumn()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Já existe um usuário com este telefone nesta conta']);
            exit;
        }
    }

    // Validar stores do tenant
    if (class_exists('SaasLimitsBridge')) {
        if (!SaasLimitsBridge::validateStoresBelongToTenant(db(), (int)$tenantId, $storeIds)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Selecione apenas lojas da sua conta (tenant).']);
            exit;
        }

        // IMPORTANTE:
        // Não validar quota aqui. O limite é baseado em total de usuários criados no tenant,
        // então ativar/desativar não deve bloquear o update.
    }

    $user_image = (string)($current['user_image'] ?? '');
    $dobFinal = $dob !== '' ? $dob : (string)($current['dob'] ?? '');
    if ($dobFinal === '') {
        $dobFinal = date('Y-m-d', strtotime('-20 years'));
    }

    $user_model = registry()->get('loader')->model('user');

    $payload = [
        'username' => $username,
        'email' => $emailNorm,
        'mobile' => $mobileDigits,
        'group_id' => $groupId,
        'dob' => $dobFinal,
        'user_image' => $user_image,
        'status' => $status,
        'sort_order' => $sortOrder,
        'tenant_id' => (int)$tenantId,
        'user_store' => $storeIds,
    ];

    $user_model->editUser((int)$id, $payload);

    echo json_encode([
        'success' => true,
        'message' => 'Usuário atualizado com sucesso',
        'id' => (int)$id,
    ]);

} catch (Exception $e) {
    try {
        if (db() && method_exists(db(), 'inTransaction') && db()->inTransaction()) {
            db()->rollBack();
        }
    } catch (Exception $e2) {
        // ignore
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao atualizar usuário: ' . $e->getMessage()]);
}
