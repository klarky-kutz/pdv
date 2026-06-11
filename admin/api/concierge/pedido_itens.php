<?php
/**
 * API: api/concierge/pedido_itens.php
 * Lê e atualiza os itens de um pedido WhatsApp (Moda IA).
 *
 * GET:
 *   - order_id  (int, obrigatório)
 *
 * POST:
 *   - order_id  (int, obrigatório)
 *   - mode      (string): replace | add | remove_item
 *   - items     (json)  : array de { variant_id, qty }
 *                         Para mode=remove_item usar { order_item_id }
 *
 * Modos:
 *   replace    → Remove todos os itens existentes e insere os novos.
 *                Devolve estoque dos itens removidos e reserva para os novos.
 *   add        → Adiciona itens ao pedido (cumulativo). Valida estoque.
 *   remove_item → Remove um item específico pelo order_item_id.
 *                 Devolve o estoque ao catálogo.
 */

if (!isset($tid)) {
    exit; // Apenas via webhook.php
}

require_once DIR_HELPER . 'ai_evolution.php';

/* ── helpers locais ──────────────────────────────────────────────────── */

function pi_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function pi_fetch_order(int $tenantId, int $orderId): ?array
{
    $stmt = db()->prepare("
        SELECT id, whatsapp_phone, customer_name, status, total_amount,
               payment_method, payment_ref, payment_link, paid_at, notes,
               created_at, updated_at
        FROM ai_orders
        WHERE tenant_id = :tid AND id = :oid
        LIMIT 1
    ");
    $stmt->execute([':tid' => $tenantId, ':oid' => $orderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function pi_fetch_items(int $orderId): array
{
    $stmt = db()->prepare("
        SELECT oi.id AS order_item_id,
               oi.variant_id,
               oi.model_name,
               oi.color,
               oi.size,
               oi.qty,
               oi.unit_price,
               oi.subtotal,
               v.sku,
               v.photo_webp,
               v.stock_qty AS stock_disponivel
        FROM ai_order_items oi
        LEFT JOIN ai_catalogo_variants v ON v.id = oi.variant_id
        WHERE oi.order_id = :oid
        ORDER BY oi.id ASC
    ");
    $stmt->execute([':oid' => $orderId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['order_item_id'] = (int)$r['order_item_id'];
        $r['variant_id']    = (int)$r['variant_id'];
        $r['qty']           = (int)$r['qty'];
        $r['unit_price']    = (float)$r['unit_price'];
        $r['subtotal']      = (float)$r['subtotal'];
        $r['stock_disponivel'] = (int)$r['stock_disponivel'];
        $r['photo_url'] = ai_evolution_get_image_url($r['photo_webp'] ?? '');
        unset($r['photo_webp']);
    }
    unset($r);

    return $rows;
}

function pi_recalc_total(int $orderId, int $tenantId): float
{
    $stmt = db()->prepare("
        SELECT COALESCE(SUM(subtotal), 0) FROM ai_order_items WHERE order_id = :oid
    ");
    $stmt->execute([':oid' => $orderId]);
    $total = (float)$stmt->fetchColumn();

    db()->prepare("
        UPDATE ai_orders
        SET total_amount = :total, updated_at = NOW()
        WHERE id = :oid AND tenant_id = :tid
        LIMIT 1
    ")->execute([':total' => $total, ':oid' => $orderId, ':tid' => $tenantId]);

    return $total;
}

/* ── main ────────────────────────────────────────────────────────────── */

try {
    $orderId = (int)($request->get['order_id'] ?? $request->post['order_id'] ?? 0);

    if ($orderId <= 0) {
        throw new Exception('order_id inválido.');
    }

    $order = pi_fetch_order($tid, $orderId);
    if (!$order) {
        throw new Exception('Pedido não encontrado.');
    }

    // ── GET ──────────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $items = pi_fetch_items($orderId);
        pi_json([
            'error'       => false,
            'order_id'    => $orderId,
            'status'      => $order['status'],
            'total_amount'=> (float)$order['total_amount'],
            'items'       => $items,
            'total_itens' => count($items),
        ]);
    }

    // ── POST ─────────────────────────────────────────────────────────────
    $mode     = trim((string)($request->post['mode'] ?? 'replace'));
    $itemsRaw = trim((string)($request->post['items'] ?? ''));

    if (!in_array($mode, ['replace', 'add', 'remove_item'], true)) {
        throw new Exception("mode inválido. Use replace, add ou remove_item.");
    }

    // Pedidos entregues ou cancelados não devem ser alterados pela IA
    $lockedStatuses = ['entregue', 'cancelado'];
    if (in_array(strtolower((string)$order['status']), $lockedStatuses, true)) {
        throw new Exception("Pedido com status '{$order['status']}' não pode ser alterado.");
    }

    // ── mode = remove_item ────────────────────────────────────────────────
    if ($mode === 'remove_item') {
        $itemsData = $itemsRaw !== '' ? json_decode($itemsRaw, true) : [];
        if (!is_array($itemsData) || empty($itemsData)) {
            throw new Exception('items obrigatório para mode=remove_item. Use [{"order_item_id": N}]');
        }

        db()->beginTransaction();
        try {
            foreach ($itemsData as $it) {
                $oiId = (int)($it['order_item_id'] ?? 0);
                if ($oiId <= 0) continue;

                // Buscar item para devolver estoque
                $stmtIt = db()->prepare("
                    SELECT variant_id, qty FROM ai_order_items
                    WHERE id = :oiid AND order_id = :oid
                    LIMIT 1
                ");
                $stmtIt->execute([':oiid' => $oiId, ':oid' => $orderId]);
                $existingItem = $stmtIt->fetch(PDO::FETCH_ASSOC);

                if ($existingItem) {
                    // Devolver estoque ao catálogo
                    db()->prepare("
                        UPDATE ai_catalogo_variants
                        SET stock_qty = stock_qty + :qty
                        WHERE id = :vid AND tenant_id = :tid
                    ")->execute([
                        ':qty' => (int)$existingItem['qty'],
                        ':vid' => (int)$existingItem['variant_id'],
                        ':tid' => $tid,
                    ]);

                    db()->prepare("
                        DELETE FROM ai_order_items WHERE id = :oiid AND order_id = :oid LIMIT 1
                    ")->execute([':oiid' => $oiId, ':oid' => $orderId]);
                }
            }

            $newTotal = pi_recalc_total($orderId, $tid);
            db()->commit();

            pi_json([
                'error'        => false,
                'message'      => 'Itens removidos com sucesso.',
                'order_id'     => $orderId,
                'total_amount' => $newTotal,
                'items'        => pi_fetch_items($orderId),
            ]);
        } catch (Exception $e) {
            db()->rollBack();
            throw $e;
        }
    }

    // ── mode = replace | add ─────────────────────────────────────────────
    $itemsData = $itemsRaw !== '' ? json_decode($itemsRaw, true) : null;
    if (!is_array($itemsData) || empty($itemsData)) {
        throw new Exception('items obrigatório. Ex: [{"variant_id":1,"qty":2}]');
    }

    // Validar e enriquecer itens antes de abrir transação
    $enriched = [];
    foreach ($itemsData as $item) {
        $vid = (int)($item['variant_id'] ?? 0);
        $qty = (int)($item['qty'] ?? 1);

        if ($vid <= 0 || $qty <= 0) {
            throw new Exception("Item inválido: variant_id e qty são obrigatórios e devem ser > 0.");
        }

        $stmt = db()->prepare("
            SELECT v.*, m.name AS model_name
            FROM ai_catalogo_variants v
            INNER JOIN ai_catalogo_models m ON m.id = v.model_id
            WHERE v.id = :vid AND v.tenant_id = :tid AND v.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':vid' => $vid, ':tid' => $tid]);
        $variant = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$variant) {
            throw new Exception("Variante ID={$vid} não encontrada ou inativa.");
        }

        // Para mode=add, verificar estoque disponível diretamente.
        // Para mode=replace, o estoque dos itens antigos será devolvido antes da inserção.
        $neededStock = $qty;
        $currentStock = (int)$variant['stock_qty'];

        if ($mode === 'add' && $currentStock < $neededStock) {
            throw new Exception("Estoque insuficiente para '{$variant['model_name']}' (Cor: {$variant['color']}, Tam: {$variant['size']}). Disponível: {$currentStock}.");
        }

        $price    = (float)$variant['price'];
        $subtotal = round($price * $qty, 2);

        $enriched[] = [
            'variant_id' => $vid,
            'model_name' => (string)$variant['model_name'],
            'color'      => (string)$variant['color'],
            'size'       => (string)$variant['size'],
            'qty'        => $qty,
            'unit_price' => $price,
            'subtotal'   => $subtotal,
            'stock_now'  => $currentStock,
        ];
    }

    db()->beginTransaction();
    try {
        if ($mode === 'replace') {
            // Devolver estoque de todos os itens atuais
            $oldItems = db()->prepare("
                SELECT variant_id, qty FROM ai_order_items WHERE order_id = :oid
            ");
            $oldItems->execute([':oid' => $orderId]);
            foreach ($oldItems->fetchAll(PDO::FETCH_ASSOC) as $old) {
                db()->prepare("
                    UPDATE ai_catalogo_variants
                    SET stock_qty = stock_qty + :qty
                    WHERE id = :vid AND tenant_id = :tid
                ")->execute([
                    ':qty' => (int)$old['qty'],
                    ':vid' => (int)$old['variant_id'],
                    ':tid' => $tid,
                ]);
            }

            // Verificar estoque depois de devolver (para replace)
            foreach ($enriched as $en) {
                $stmtStock = db()->prepare("
                    SELECT stock_qty FROM ai_catalogo_variants WHERE id = :vid AND tenant_id = :tid LIMIT 1
                ");
                $stmtStock->execute([':vid' => $en['variant_id'], ':tid' => $tid]);
                $freshStock = (int)$stmtStock->fetchColumn();
                if ($freshStock < $en['qty']) {
                    db()->rollBack();
                    throw new Exception("Estoque insuficiente para '{$en['model_name']}' (Cor: {$en['color']}, Tam: {$en['size']}). Disponível: {$freshStock}.");
                }
            }

            // Remover todos os itens antigos
            db()->prepare("DELETE FROM ai_order_items WHERE order_id = :oid")
                ->execute([':oid' => $orderId]);
        }

        // Inserir novos itens e reservar estoque
        $stmtIns = db()->prepare("
            INSERT INTO ai_order_items (order_id, variant_id, model_name, color, size, qty, unit_price, subtotal)
            VALUES (:oid, :vid, :mname, :color, :size, :qty, :price, :subtotal)
        ");

        foreach ($enriched as $en) {
            $stmtIns->execute([
                ':oid'     => $orderId,
                ':vid'     => $en['variant_id'],
                ':mname'   => $en['model_name'],
                ':color'   => $en['color'],
                ':size'    => $en['size'],
                ':qty'     => $en['qty'],
                ':price'   => $en['unit_price'],
                ':subtotal'=> $en['subtotal'],
            ]);

            // Reservar estoque
            db()->prepare("
                UPDATE ai_catalogo_variants
                SET stock_qty = stock_qty - :qty
                WHERE id = :vid AND tenant_id = :tid
            ")->execute([
                ':qty' => $en['qty'],
                ':vid' => $en['variant_id'],
                ':tid' => $tid,
            ]);
        }

        $newTotal = pi_recalc_total($orderId, $tid);
        db()->commit();

        $action = $mode === 'replace' ? 'Itens substituídos com sucesso.' : 'Itens adicionados com sucesso.';

        pi_json([
            'error'        => false,
            'message'      => $action,
            'order_id'     => $orderId,
            'total_amount' => $newTotal,
            'items'        => pi_fetch_items($orderId),
        ]);
    } catch (Exception $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
