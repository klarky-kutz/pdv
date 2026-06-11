<?php
/**
 * API: Criar Grupo Customizado
 * 
 * Endpoint para Owners criarem grupos RBAC customizados
 * Apenas permissões presentes nas capabilities do plano podem ser selecionadas
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
    echo json_encode(['success' => false, 'error' => 'Apenas Administradores podem criar grupos']);
    exit;
}

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

// Receber dados
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit;
}

// Validar campos obrigatórios
$name = trim($data['name'] ?? '');
$slug = trim($data['slug'] ?? '');
$permissions = $data['permissions'] ?? [];

if (empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nome do grupo é obrigatório']);
    exit;
}

// Gerar slug se não fornecido
if (empty($slug)) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
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
    // Buscar capabilities do plano
    $saasLimitsPath = dirname(__DIR__, 2) . '/../saas/includes/SaasLimitsBridge.php';
    if (!file_exists($saasLimitsPath)) {
        throw new Exception('SaasLimitsBridge não encontrado');
    }
    require_once $saasLimitsPath;
    $planCapabilities = SaasLimitsBridge::getPlanFeatures(db(), $tenantId);
    $isPermissive = in_array('*', $planCapabilities, true);

    // =========================================================
    // Limite de grupos = max_users do plano (solicitado)
    // =========================================================
    $planLimits = SaasLimitsBridge::getPlanLimits(db(), $tenantId);
    $maxUsers = (int)($planLimits['max_users'] ?? 0);

    if ($maxUsers > 0) {
        $stmtCount = db()->prepare("SELECT COUNT(*) FROM user_group WHERE tenant_id = ?");
        $stmtCount->execute([$tenantId]);
        $groupsCount = (int)$stmtCount->fetchColumn();

        if ($groupsCount >= $maxUsers) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error' => "Limite de grupos atingido ({$groupsCount}/{$maxUsers}). Faça upgrade do plano.",
                'code' => 'LIMIT_REACHED',
                'limit_type' => 'groups',
                'used' => $groupsCount,
                'max' => $maxUsers,
            ]);
            exit;
        }
    }
    
    // Validar que todas as permissões selecionadas estão no plano
    if (!$isPermissive && !empty($permissions)) {
        foreach ($permissions as $permKey => $permValue) {
            if (is_array($permValue)) {
                foreach ($permValue as $subKey => $subValue) {
                    if ($subValue && !in_array($subKey, $planCapabilities, true)) {
                        http_response_code(403);
                        echo json_encode([
                            'success' => false, 
                            'error' => "Permissão '$subKey' não está disponível no seu plano"
                        ]);
                        exit;
                    }
                }
            }
        }
    }
    
    // Verificar se slug já existe para este tenant
    $stmt = db()->prepare("SELECT group_id FROM user_group WHERE slug = ? AND (tenant_id = ? OR tenant_id IS NULL)");
    $stmt->execute([$slug, $tenantId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Já existe um grupo com este identificador']);
        exit;
    }
    
    // Serializar permissões
    $permissionsSerialized = serialize($permissions);
    
    // Inserir grupo
    $stmt = db()->prepare("
        INSERT INTO user_group (tenant_id, name, slug, permission, status, sort_order)
        VALUES (?, ?, ?, ?, 1, 0)
    ");
    $stmt->execute([$tenantId, $name, $slug, $permissionsSerialized]);
    
    $groupId = db()->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Grupo criado com sucesso',
        'group_id' => $groupId,
        'group' => [
            'group_id' => $groupId,
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $slug,
            'permissions' => $permissions
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Erro ao criar grupo: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao criar grupo: ' . $e->getMessage()]);
}
