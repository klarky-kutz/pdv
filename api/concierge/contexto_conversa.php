<?php
/**
 * API: api/concierge/contexto_conversa.php
 * Consulta contexto completo da conversa (pedido, resumo, perfil e status IA).
 *
 * GET/POST:
 *   - order_id   (opcional)
 *   - phone      (opcional)
 *   - remote_jid (opcional)
 *   - include_items (opcional, 1|0)
 */

if (!isset($tid)) {
    exit; // Apenas via webhook.php
}

require_once DIR_HELPER . 'ai_evolution.php';

function concierge_fetch_order_context_by_id(int $tenantId, int $orderId): ?array
{
    $stmt = db()->prepare("
        SELECT id, whatsapp_phone, customer_name, status, total_amount, payment_method, payment_ref, payment_link, paid_at, notes, created_at, updated_at
        FROM ai_orders
        WHERE tenant_id = :tid
          AND id = :oid
        LIMIT 1
    ");
    $stmt->execute([':tid' => $tenantId, ':oid' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return null;
    }

    $order['id'] = (int)($order['id'] ?? 0);
    $order['total_amount'] = (float)($order['total_amount'] ?? 0);
    $order['items'] = [];

    $itemsStmt = db()->prepare("
        SELECT oi.id, oi.variant_id, oi.model_name, oi.color, oi.size, oi.qty, oi.unit_price, oi.subtotal, v.sku, v.photo_webp
        FROM ai_order_items oi
        LEFT JOIN ai_catalogo_variants v ON v.id = oi.variant_id
        WHERE oi.order_id = :oid
        ORDER BY oi.id ASC
    ");
    $itemsStmt->execute([':oid' => $order['id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$it) {
        $it['id'] = (int)($it['id'] ?? 0);
        $it['variant_id'] = (int)($it['variant_id'] ?? 0);
        $it['qty'] = (int)($it['qty'] ?? 0);
        $it['unit_price'] = (float)($it['unit_price'] ?? 0);
        $it['subtotal'] = (float)($it['subtotal'] ?? 0);
        $it['photo_url'] = ai_evolution_get_image_url($it['photo_webp'] ?? '');
    }
    unset($it);

    $order['items'] = $items;
    return $order;
}

try {
    $orderId = (int)($request->get['order_id'] ?? $request->post['order_id'] ?? 0);
    $phone = trim((string)($request->get['phone'] ?? $request->post['phone'] ?? ''));
    $remoteJid = trim((string)($request->get['remote_jid'] ?? $request->post['remote_jid'] ?? ''));
    $includeItems = (string)($request->get['include_items'] ?? $request->post['include_items'] ?? '1') !== '0';

    if ($orderId <= 0 && $phone === '' && $remoteJid === '') {
        throw new Exception('Informe order_id, phone ou remote_jid.');
    }

    $explicitOrder = null;
    $lookup = $remoteJid !== '' ? $remoteJid : $phone;

    if ($orderId > 0) {
        $explicitOrder = concierge_fetch_order_context_by_id($tid, $orderId);
        if (!$explicitOrder) {
            throw new Exception('Pedido não encontrado para esta loja.');
        }
        if ($lookup === '') {
            $lookup = (string)($explicitOrder['whatsapp_phone'] ?? '');
        }
    }

    $context = $lookup !== '' ? ai_evolution_get_conversation_context($tid, $lookup) : [
        'remote_jid' => '',
        'phone_digits' => '',
        'profile' => null,
        'atendimento' => ['status' => 'Ativo', 'takeover_by_user_id' => 0, 'updated_at' => null],
        'order' => null,
        'summary' => 'Sem resumo de conversa registrado no momento.',
    ];

    if (is_array($explicitOrder)) {
        $context['order'] = $explicitOrder;
        $context['summary'] = ai_evolution_build_conversation_summary($explicitOrder, is_array($context['profile'] ?? null) ? $context['profile'] : null);
    }

    if (!$includeItems && is_array($context['order'] ?? null)) {
        unset($context['order']['items']);
    }

    $orderOut = is_array($context['order'] ?? null) ? $context['order'] : null;
    $pixOut = null;
    if ($orderOut) {
        $pixOut = [
            'status' => (string)($orderOut['status'] ?? ''),
            'payment_method' => (string)($orderOut['payment_method'] ?? ''),
            'payment_ref' => (string)($orderOut['payment_ref'] ?? ''),
            'payment_link' => (string)($orderOut['payment_link'] ?? ''),
            'paid_at' => $orderOut['paid_at'] ?? null,
        ];
    }

    echo json_encode([
        'error' => false,
        'context' => [
            'remote_jid' => (string)($context['remote_jid'] ?? ''),
            'phone' => (string)($context['phone_digits'] ?? ''),
            'conversation_summary' => (string)($context['summary'] ?? ''),
            'profile' => $context['profile'] ?? null,
            'ai_activation' => $context['atendimento'] ?? null,
            'order' => $orderOut,
            'pix' => $pixOut,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
