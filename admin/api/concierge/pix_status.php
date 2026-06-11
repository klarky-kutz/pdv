<?php
/**
 * API: api/concierge/pix_status.php
 * Consulta ou atualiza status de pagamento PIX de um pedido.
 *
 * GET:
 *   - order_id
 *
 * POST:
 *   - order_id
 *   - status (pago|cancelado|pendente)
 *   - payment_ref (opcional)
 */

if (!isset($tid)) {
    exit; // Apenas via webhook.php
}

try {
    $orderId = (int)($request->post['order_id'] ?? $request->get['order_id'] ?? 0);
    $statusRaw = trim((string)($request->post['status'] ?? $request->get['status'] ?? ''));
    $paymentRef = trim((string)($request->post['payment_ref'] ?? $request->get['payment_ref'] ?? ''));

    if ($orderId <= 0) {
        throw new Exception('order_id inválido.');
    }

    $stmt = db()->prepare("
        SELECT id, status, payment_method, payment_ref, payment_link, paid_at, total_amount, updated_at
        FROM ai_orders
        WHERE tenant_id = :tid
          AND id = :oid
        LIMIT 1
    ");
    $stmt->execute([':tid' => $tid, ':oid' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        throw new Exception('Pedido não encontrado.');
    }

    $paymentMethod = strtolower((string)($order['payment_method'] ?? ''));
    if ($paymentMethod !== 'pix') {
        throw new Exception('Este pedido não usa pagamento PIX.');
    }

    $isUpdate = $_SERVER['REQUEST_METHOD'] === 'POST' || $statusRaw !== '' || $paymentRef !== '';
    if ($isUpdate) {
        if ($statusRaw === '') {
            $statusRaw = 'pago';
        }

        $normalized = strtolower($statusRaw);
        if (in_array($normalized, ['pago', 'confirmado', 'confirmar', 'approved', 'approve'], true)) {
            $newStatus = 'pago';
        } elseif (in_array($normalized, ['cancelado', 'cancelar', 'rejeitado', 'rejeitar', 'reprovado', 'reject'], true)) {
            $newStatus = 'cancelado';
        } elseif (in_array($normalized, ['pendente', 'pending', 'aguardando'], true)) {
            $newStatus = 'pendente';
        } else {
            throw new Exception('Status PIX inválido. Use pago, cancelado ou pendente.');
        }

        $sql = "
            UPDATE ai_orders
            SET status = :status,
                moved_by_ia = 1,
                updated_at = NOW()";
        $params = [
            ':status' => $newStatus,
            ':tid' => $tid,
            ':oid' => $orderId,
        ];

        if ($newStatus === 'pago') {
            $sql .= ", paid_at = NOW()";
        } else {
            $sql .= ", paid_at = NULL";
        }

        if ($paymentRef !== '') {
            $sql .= ", payment_ref = :pref";
            $params[':pref'] = $paymentRef;
        }

        $sql .= " WHERE tenant_id = :tid AND id = :oid LIMIT 1";
        db()->prepare($sql)->execute($params);

        $stmt->execute([':tid' => $tid, ':oid' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: $order;
    }

    echo json_encode([
        'error' => false,
        'order_id' => (int)$orderId,
        'status' => (string)($order['status'] ?? ''),
        'payment_method' => (string)($order['payment_method'] ?? ''),
        'payment_ref' => (string)($order['payment_ref'] ?? ''),
        'payment_link' => (string)($order['payment_link'] ?? ''),
        'paid_at' => $order['paid_at'] ?? null,
        'total_amount' => (float)($order['total_amount'] ?? 0),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
