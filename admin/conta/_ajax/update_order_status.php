<?php
/**
 * API: Atualizar Status do Pedido
 * 
 * POST /conta/_ajax/update_order_status.php
 * Body (JSON):
 * {
 *   "order_id": 123,
 *   "payment_intent_id": "pi_xxx",
 *   "status": "paid"
 * }
 * 
 * Chamado pelo checkout transparente Stripe após pagamento bem-sucedido.
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

$initPath = dirname(__DIR__) . '/../_init.php';
if (!file_exists($initPath)) {
    echo json_encode(['success' => false, 'message' => 'Sistema não configurado corretamente.']);
    exit;
}

require_once $initPath;

if (!function_exists('user_id') || !user_id()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

// Lê JSON
$raw = file_get_contents('php://input');
$input = [];
if ($raw) {
    $tmp = json_decode($raw, true);
    if (is_array($tmp)) {
        $input = $tmp;
    }
}

$orderId = isset($input['order_id']) ? (int)$input['order_id'] : 0;
$paymentIntentId = isset($input['payment_intent_id']) ? trim((string)$input['payment_intent_id']) : '';
$status = isset($input['status']) ? strtolower(trim((string)$input['status'])) : '';

// Validação básica
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'order_id inválido.']);
    exit;
}

$validStatuses = ['paid', 'pending', 'failed', 'cancelled', 'refunded'];
if (!in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Status inválido.']);
    exit;
}

try {
    $pdo = db();
    
    // Resolve tenant do usuário
    $tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    if ($tenantId <= 0) {
        $userId = (int)user_id();
        $stmtUser = $pdo->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
        $stmtUser->execute([$userId]);
        $tenantId = (int)$stmtUser->fetchColumn();
    }
    
    if ($tenantId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Tenant não identificado.']);
        exit;
    }
    
    // Busca o pedido
    $stmtOrder = $pdo->prepare("SELECT order_id, tenant_id, status, transaction_id FROM saas_orders WHERE order_id = :order_id LIMIT 1");
    $stmtOrder->execute([':order_id' => $orderId]);
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
        exit;
    }
    
    // Verifica se o pedido pertence ao tenant do usuário
    if ((int)$order['tenant_id'] !== $tenantId) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado a este pedido.']);
        exit;
    }
    
    // Se o pedido já está pago, não atualiza
    if ($order['status'] === 'paid') {
        echo json_encode(['success' => true, 'message' => 'Pedido já está pago.', 'already_paid' => true]);
        exit;
    }
    
    // Verifica se o PaymentIntent corresponde (se informado)
    if ($paymentIntentId !== '' && !empty($order['transaction_id'])) {
        if ($order['transaction_id'] !== $paymentIntentId) {
            // Log para auditoria, mas não bloqueia
            error_log("[update_order_status] PaymentIntent mismatch: expected {$order['transaction_id']}, got {$paymentIntentId}");
        }
    }
    
    // Atualiza o status
    $updateFields = ['status = :status', 'updated_at = NOW()'];
    $updateParams = [':status' => $status, ':order_id' => $orderId];
    
    if ($status === 'paid') {
        $updateFields[] = 'paid_at = NOW()';
    }
    
    // Se temos um payment_intent_id e o transaction_id está vazio, salvamos
    if ($paymentIntentId !== '' && empty($order['transaction_id'])) {
        $updateFields[] = 'transaction_id = :transaction_id';
        $updateParams[':transaction_id'] = $paymentIntentId;
    }
    
    $sql = "UPDATE saas_orders SET " . implode(', ', $updateFields) . " WHERE order_id = :order_id";
    $stmtUpdate = $pdo->prepare($sql);
    $stmtUpdate->execute($updateParams);
    
    // Se o pagamento foi confirmado, atualiza o tenant para "active" se necessário
    if ($status === 'paid') {
        try {
            $stmtTenant = $pdo->prepare("UPDATE tenants SET status = 'active', updated_at = NOW() WHERE tenant_id = :tenant_id AND status != 'active'");
            $stmtTenant->execute([':tenant_id' => $tenantId]);
        } catch (Throwable $e) {
            // Não bloqueia se falhar
            error_log("[update_order_status] Falha ao ativar tenant: " . $e->getMessage());
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso.', 'status' => $status]);
    exit;
    
} catch (Throwable $e) {
    error_log("[update_order_status] Erro: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status: ' . $e->getMessage()]);
    exit;
}
