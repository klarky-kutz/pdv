<?php
/**
 * API: Criar Usuário (Painel /conta)
 *
 * Regras SaaS:
 * - Apenas Owner do tenant (ou superadmin)
 * - Usuário criado sempre pertence ao tenant atual
 * - Valida limite de usuários do plano (max_users) antes de criar usuário ATIVO
 * - Valida lojas pertencem ao tenant
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

// Permissão para criar usuários:
// - Somente Administrador global (group_id=1) OU Owner do tenant
$requesterIsAdmin = (function_exists('user_group_id') && user_group_id() == 1)
    || (function_exists('is_tenant_owner') && is_tenant_owner());

if (!$requesterIsAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Somente Administrador pode adicionar novos usuários']);
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

// Validar campos
$username = trim((string)($data['username'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$mobile = trim((string)($data['mobile'] ?? ''));
$dob = trim((string)($data['dob'] ?? ''));
$groupId = (int)($data['group_id'] ?? 0);
$status = isset($data['status']) ? (int)$data['status'] : 1;
$sortOrder = isset($data['sort_order']) ? (int)($data['sort_order'] ?? 0) : 0;
$password = (string)($data['password'] ?? '');
$passwordConfirm = (string)($data['password_confirm'] ?? '');

// Normalizações
$emailNorm = strtolower($email);
$mobileDigits = preg_replace('/\D+/', '', $mobile);

// Lojas vinculadas (múltiplas)
if (isset($data['store_ids']) && is_array($data['store_ids'])) {
    $storeIds = array_filter(array_map('intval', $data['store_ids']), fn($id) => $id > 0);
} else {
    // Fallback para compatibilidade com store_id único
    $storeId = (int)($data['store_id'] ?? 0);
    $storeIds = $storeId > 0 ? [$storeId] : [];
}

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
    // BR: 10 ou 11 dígitos (com DDD)
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

// Painel /conta: grupo Admin (group_id=1) não é atribuível (mesmo para owner)
if ((int)$groupId === 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'O grupo Administrador não pode ser atribuído pelo Painel da Conta']);
    exit;
}

// Regra: usuário não-admin não pode criar usuários em grupos com permissões superiores ao seu.
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

    // Fallback seguro: se não conseguimos ler as permissões do solicitante,
    // permite apenas atribuir o MESMO grupo do solicitante.
    if ($requesterGroupId > 0 && empty($requesterSet) && (int)$groupId !== (int)$requesterGroupId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Você não pode atribuir um grupo superior ao seu']);
        exit;
    }

    $targetKeys = $loadGroupAccessKeys((int)$groupId);
    foreach ($targetKeys as $k) {
        if (!isset($requesterSet[$k])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Você não pode atribuir um grupo superior ao seu']);
            exit;
        }
    }
}

if (empty($storeIds)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Selecione pelo menos uma loja']);
    exit;
}

if ($password === '' || strlen($password) < 6) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Senha deve ter no mínimo 6 caracteres']);
    exit;
}

if ($password !== $passwordConfirm) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'As senhas não coincidem']);
    exit;
}

// Password strongness (se disponível)
if (function_exists('checkPasswordStrongness')) {
    $chk = checkPasswordStrongness($password);
    if ($chk !== 'ok') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => (string)$chk]);
        exit;
    }
}

// DOB default
if ($dob === '') {
    $dob = date('Y-m-d', strtotime('-20 years'));
}

try {
    // Unicidade dentro do tenant (nome/e-mail/telefone)
    $nameNorm = strtolower($username);

    $st = db()->prepare('SELECT id FROM users WHERE tenant_id = ? AND LOWER(username) = ? LIMIT 1');
    $st->execute([(int)$tenantId, $nameNorm]);
    if ($st->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Já existe um usuário com este nome nesta conta']);
        exit;
    }

    if ($emailNorm !== '') {
        $st = db()->prepare('SELECT id FROM users WHERE tenant_id = ? AND LOWER(email) = ? LIMIT 1');
        $st->execute([(int)$tenantId, $emailNorm]);
        if ($st->fetchColumn()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Já existe um usuário com este e-mail nesta conta']);
            exit;
        }
    }

    if ($mobileDigits !== '') {
        // Normaliza comparação (remove máscara)
        $st = db()->prepare("SELECT id FROM users WHERE tenant_id = ? AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mobile,'(',''),')',''),'-',''),' ',''),'+','') = ? LIMIT 1");
        $st->execute([(int)$tenantId, $mobileDigits]);
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

        // Limite de usuários
        // Requisito: usuário desativado também conta no limite (status não influencia).
        $check = SaasLimitsBridge::canCreateUser(db(), (int)$tenantId);
        if (is_array($check) && isset($check[0]) && !$check[0]) {
            $limits = SaasLimitsBridge::getPlanLimits(db(), (int)$tenantId);
            $maxUsers = (int)($limits['max_users'] ?? 0);

            $usedUsers = method_exists('SaasLimitsBridge', 'countTenantUsersTotal')
                ? SaasLimitsBridge::countTenantUsersTotal(db(), (int)$tenantId)
                : SaasLimitsBridge::countUsedUsers(db(), (int)$tenantId);

            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error' => (string)($check[1] ?? 'Limite de usuários atingido. Faça upgrade.'),
                'code' => 'LIMIT_REACHED',
                'limit_type' => 'users',
                'used' => (int)$usedUsers,
                'max' => (int)$maxUsers,
            ]);
            exit;
        }
    }

    // Model
    $user_model = registry()->get('loader')->model('user');

    $payload = [
        'username' => $username,
        'email' => $emailNorm,
        'mobile' => $mobileDigits,
        'password' => $password,
        'password1' => $passwordConfirm,
        'group_id' => $groupId,
        'dob' => $dob,
        'user_image' => '',
        'status' => $status,
        'sort_order' => $sortOrder,
        'tenant_id' => (int)$tenantId,
        'user_store' => $storeIds,
    ];

    $newId = $user_model->addUser($payload);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Usuário criado com sucesso',
        'id' => (int)$newId,
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
    echo json_encode(['success' => false, 'error' => 'Erro ao criar usuário: ' . $e->getMessage()]);
}
