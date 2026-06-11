<?php
/**
 * API: api/concierge/kanban_card.php
 * Atualiza status ou deleta pedidos via API (Moda IA).
 * 
 * POST:
 *   order_id = int
 *   status   = string (pendente, pago, separando, rota, entregue, cancelado)
 *   delete   = boolean (opcional, se true deleta o pedido)
 */

if (!isset($tid)) {
    exit; // Apenas via webhook.php
}

try {
    $orderId = (int)($request->post['order_id'] ?? $request->get['order_id'] ?? 0);
    $status  = trim($request->post['status'] ?? $request->get['status'] ?? '');
    $delete  = (bool)($request->post['delete'] ?? $request->get['delete'] ?? false);

    if (!$orderId) {
        throw new Exception('order_id não informado.');
    }

    // Verificar se o pedido pertence ao tenant
    $stmt = db()->prepare("SELECT id FROM ai_orders WHERE id = :id AND tenant_id = :tid LIMIT 1");
    $stmt->execute([':id' => $orderId, ':tid' => $tid]);
    $orderExists = $stmt->fetchColumn();

    if (!$orderExists) {
        throw new Exception('Pedido não encontrado ou não pertence a esta loja.');
    }

    if ($delete) {
        // Deletar pedido e itens
        db()->beginTransaction();
        try {
            // Deletar itens primeiro (FK opcional, mas boa prática)
            db()->prepare("DELETE FROM ai_order_items WHERE order_id = :oid")->execute([':oid' => $orderId]);
            // Deletar pedido
            db()->prepare("DELETE FROM ai_orders WHERE id = :oid AND tenant_id = :tid LIMIT 1")
                ->execute([':oid' => $orderId, ':tid' => $tid]);
            
            db()->commit();
            echo json_encode([
                'error' => false,
                'message' => "Pedido #{$orderId} deletado com sucesso.",
                'order_id' => $orderId,
                'deleted' => true
            ]);
            exit;
        } catch (Exception $e) {
            db()->rollBack();
            throw $e;
        }
    }

    if ($status !== '') {
        $allowed = ['pendente', 'pago', 'separando', 'rota', 'entregue', 'cancelado'];
        if (!in_array($status, $allowed)) {
            throw new Exception("Status '{$status}' inválido. Use: " . implode(', ', $allowed));
        }

        $ok = ai_update_order_status($orderId, $status, true, $tid);
        if (!$ok) {
            throw new Exception('Não foi possível atualizar o status do pedido.');
        }
        if ($status === 'pago' && function_exists('ai_notify_store_payment_confirmed')) {
            ai_notify_store_payment_confirmed($tid, $orderId);
        }

        echo json_encode([
            'error' => false,
            'message' => "Status do pedido #{$orderId} atualizado para '{$status}'.",
            'order_id' => $orderId,
            'new_status' => $status
        ]);
        exit;
    }

    throw new Exception('Nenhuma ação (status ou delete) informada.');

} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
