<?php
/**
 * API: api/concierge/confirmar_pagamento.php
 * Confirma o pagamento de um pedido originado via WhatsApp (Moda IA).
 * 
 * POST:
 *   order_id   = int
 *   payment_ref = string (ID de referência do gateway)
 *   loja_id    = int
 */

if (!isset($tid)) {
    exit; // Apenas via webhook.php
}

try {
    $orderId    = (int)($request->post['order_id'] ?? 0);
    $paymentRef = trim($request->post['payment_ref'] ?? '');

    if (!$orderId) {
        throw new Exception('ID do pedido não informado.');
    }

    // Buscar pedido
    $stmt = db()->prepare("
        SELECT * FROM ai_orders 
        WHERE id = :id AND tenant_id = :tid 
        LIMIT 1
    ");
    $stmt->execute([':id' => $orderId, ':tid' => $tid]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Pedido não encontrado.');
    }

    if ($order['status'] !== 'pendente') {
        throw new Exception("Pedido já processado (Status: {$order['status']}).");
    }

    // Atualizar status
    $stmtUpdate = db()->prepare("
        UPDATE ai_orders 
        SET    status = 'pago', 
               payment_ref = :ref, 
               paid_at = NOW(), 
               updated_at = NOW(),
               moved_by_ia = 1
        WHERE  id = :id AND tenant_id = :tid
    ");
    $stmtUpdate->execute([':ref' => $paymentRef, ':id' => $orderId, ':tid' => $tid]);

    // Notificar cliente via WhatsApp
    require_once DIR_HELPER . 'ai_evolution.php';
    ai_notify_customer_status_change($tid, $orderId, 'pago');

    echo json_encode([
        'error'    => false,
        'message'  => 'Pagamento confirmado com sucesso.',
        'order_id' => $orderId,
        'status'   => 'pago',
    ]);

} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
