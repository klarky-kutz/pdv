<?php
/**
 * API: Buscar Grupo por ID
 *
 * Usado na modal premium de edição de grupo para marcar checkboxes já habilitadas.
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

// Mantém regra atual: apenas administrador (owner) pode gerenciar grupos
if (!function_exists('is_tenant_owner') || !is_tenant_owner()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Apenas Administradores podem acessar grupos']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$groupId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($groupId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'id inválido']);
    exit;
}

$userId = user_id();
$userData = get_the_user($userId);
$tenantId = $userData['tenant_id'] ?? null;

if (!$tenantId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tenant não identificado']);
    exit;
}

try {
    $st = db()->prepare('SELECT group_id, tenant_id, name, slug, permission FROM user_group WHERE group_id = ? LIMIT 1');
    $st->execute([(int)$groupId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Grupo não encontrado']);
        exit;
    }

    // Bloquear grupos do sistema (não editáveis no painel /conta)
    if ($row['tenant_id'] === null) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Grupo do sistema não pode ser editado']);
        exit;
    }

    if ((int)$row['tenant_id'] !== (int)$tenantId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Grupo não pertence ao tenant atual']);
        exit;
    }

    $permissions = [];
    if (isset($row['permission']) && $row['permission'] !== '') {
        $tmp = @unserialize($row['permission']);
        if (is_array($tmp)) {
            $permissions = $tmp;
        }
    }

    echo json_encode([
        'success' => true,
        'group' => [
            'group_id' => (int)$row['group_id'],
            'tenant_id' => (int)$row['tenant_id'],
            'name' => (string)($row['name'] ?? ''),
            'slug' => (string)($row['slug'] ?? ''),
            'permissions' => $permissions,
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar grupo: ' . $e->getMessage()]);
}
