<?php
/**
 * API: Detalhes de um Pedido (saas_orders)
 *
 * GET /conta/_ajax/order_details.php?order_id=123
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

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'order_id inválido.']);
    exit;
}

try {
    $pdo = db();

    $tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    if ($tenantId <= 0) {
        $uid = (int)user_id();
        $stU = $pdo->prepare('SELECT tenant_id FROM users WHERE id = ? LIMIT 1');
        $stU->execute([$uid]);
        $tenantId = (int)$stU->fetchColumn();
    }

    if ($tenantId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Tenant não identificado.']);
        exit;
    }

    $sql = "
      SELECT
        o.order_id,
        o.tenant_id,
        o.plan_id,
        o.reference_no,
        o.amount,
        o.payment_method,
        o.status,
        o.transaction_id,
        o.proof_file,
        o.created_at,
        o.updated_at,
        o.due_date,
        o.paid_at,
        p.name AS plan_name
      FROM saas_orders o
      LEFT JOIN plans p ON p.plan_id = o.plan_id
      WHERE o.order_id = :order_id
      LIMIT 1
    ";

    $st = $pdo->prepare($sql);
    $st->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
        exit;
    }

    if ((int)$row['tenant_id'] !== $tenantId) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado para este pedido.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'order' => [
            'order_id' => (int)$row['order_id'],
            'plan_id' => (int)$row['plan_id'],
            'plan_name' => $row['plan_name'] ?? null,
            'reference_no' => $row['reference_no'] ?? null,
            'amount' => isset($row['amount']) ? (float)$row['amount'] : 0.0,
            'payment_method' => strtolower((string)($row['payment_method'] ?? '')),
            'status' => strtolower((string)($row['status'] ?? 'pending')),
            'transaction_id' => $row['transaction_id'] ?? null,
            'proof_file' => $row['proof_file'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'due_date' => $row['due_date'] ?? null,
            'paid_at' => $row['paid_at'] ?? null,
        ],
    ]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    exit;
}
