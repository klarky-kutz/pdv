<?php
/**
 * AJAX: ai_catalogo_variante.php
 * Cria, atualiza ou exclui uma Variante do Catálogo IA.
 *
 * POST:
 *   action    = 'salvar' | 'deletar' | 'update_stock'
 *   id        = int (0 = nova)
 *   model_id  = int
 *   color     = string
 *   color_hex = string (#rrggbb)
 *   size      = string
 *   price     = float
 *   stock_qty = int
 *   sku       = string
 *   is_active = 0|1
 */
ob_start();
session_start();
include('../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_plan_gate.php';

header('Content-Type: application/json; charset=UTF-8');

if (!is_loggedin()) {
    http_response_code(401);
    echo json_encode(['errorMsg' => trans('error_login')]);
    exit;
}

if (user_group_id() != 1 && !has_permission('access', 'access_concierge_ia')) {
    http_response_code(403);
    echo json_encode(['errorMsg' => 'Sem permissão para acessar o módulo Moda IA.']);
    exit;
}

try {
    $action = $request->post['action'] ?? $request->get['action'] ?? '';
    $tid    = ai_tenant_id();

    // ── LISTAR ───────────────────────────────────────────────────────────────
    if ($action === 'listar') {
        $modelId = (int)($request->get['model_id'] ?? 0);
        if (!$modelId) {
            throw new Exception('model_id inválido.');
        }

        $stmt = db()->prepare(
            'SELECT * FROM ai_catalogo_variants WHERE model_id = :mid AND tenant_id = :tid ORDER BY color, size'
        );
        $stmt->execute([':mid' => $modelId, ':tid' => $tid]);
        $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['variantes' => $variants]);
        exit;
    }

    // ── DELETAR ───────────────────────────────────────────────────────────────
    if ($action === 'deletar') {
        $id = (int)($request->post['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID inválido.');
        }

        $stmt = db()->prepare(
            'SELECT id, photo_webp FROM ai_catalogo_variants WHERE id = :id AND tenant_id = :tid'
        );
        $stmt->execute([':id' => $id, ':tid' => $tid]);
        $variant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$variant) {
            throw new Exception('Variante não encontrada.');
        }

        if ($variant['photo_webp']) {
            require_once DIR_HELPER . 'ai_image_webp.php';
            ai_delete_webp(DIR_STORAGE . $variant['photo_webp']);
        }

        db()->prepare('DELETE FROM ai_catalogo_variants WHERE id = :id AND tenant_id = :tid')
           ->execute([':id' => $id, ':tid' => $tid]);

        echo json_encode(['msg' => 'Variante excluída.']);
        exit;
    }

    // ── UPDATE STOCK (edição inline na tela de estoque) ───────────────────────
    if ($action === 'update_stock') {
        $id  = (int)($request->post['id'] ?? 0);
        $qty = (int)($request->post['stock_qty'] ?? 0);
        if (!$id) {
            throw new Exception('ID inválido.');
        }
        if ($qty < 0) {
            throw new Exception('Quantidade não pode ser negativa.');
        }

        $stmt = db()->prepare(
            'UPDATE ai_catalogo_variants SET stock_qty = :qty WHERE id = :id AND tenant_id = :tid'
        );
        $stmt->execute([':qty' => $qty, ':id' => $id, ':tid' => $tid]);
        if (!$stmt->rowCount()) {
            throw new Exception('Variante não encontrada.');
        }

        echo json_encode(['msg' => 'Estoque atualizado.', 'stock_qty' => $qty]);
        exit;
    }

    // ── SALVAR ────────────────────────────────────────────────────────────────
    if ($action === 'salvar') {
        $id       = (int)($request->post['id'] ?? 0);
        $modelId  = (int)($request->post['model_id'] ?? 0);
        $color    = trim($request->post['color'] ?? '');
        $colorNormalized = mb_strtolower(trim((string)($request->post['color_normalized'] ?? '')), 'UTF-8') ?: null;
        $colorHex = trim($request->post['color_hex'] ?? '');
        $size     = trim($request->post['size'] ?? '');
        $price    = (float)($request->post['price'] ?? 0);
        $stockQty = (int)($request->post['stock_qty'] ?? 0);
        $sku      = trim($request->post['sku'] ?? '');
        $isActive = isset($request->post['is_active']) ? (int)$request->post['is_active'] : 1;

        // Validações básicas
        if (!$modelId) {
            throw new Exception('Modelo inválido.');
        }
        if ($price < 0) {
            throw new Exception('Preço inválido.');
        }
        if ($stockQty < 0) {
            throw new Exception('Estoque inválido.');
        }

        // Verificar que o modelo pertence ao tenant
        $check = db()->prepare('SELECT id FROM ai_catalogo_models WHERE id = :mid AND tenant_id = :tid');
        $check->execute([':mid' => $modelId, ':tid' => $tid]);
        if (!$check->fetch()) {
            throw new Exception('Modelo não encontrado.');
        }

        // Validar hex de cor
        if ($colorHex && !preg_match('/^#[0-9a-fA-F]{6}$/', $colorHex)) {
            $colorHex = '';
        }

        if (empty($sku)) {
            throw new Exception('O SKU da variante é obrigatório.');
        }

        require_once DIR_HELPER . 'ai_evolution.php';

        if ($id) {
            // Atualizar variante existente
            $stmt = db()->prepare('SELECT id FROM ai_catalogo_variants WHERE id = :id AND tenant_id = :tid');
            $stmt->execute([':id' => $id, ':tid' => $tid]);
            if (!$stmt->fetch()) {
                throw new Exception('Variante não encontrada.');
            }

            db()->prepare(
                'UPDATE ai_catalogo_variants
                 SET color = :color, color_normalized = :color_norm, color_hex = :hex, size = :size,
                     price = :price, stock_qty = :qty, sku = :sku, is_active = :active
                 WHERE id = :id AND tenant_id = :tid'
            )->execute([
                ':color'      => $color,
                ':color_norm' => $colorNormalized,
                ':hex'        => $colorHex,
                ':size'       => $size,
                ':price'      => $price,
                ':qty'        => $stockQty,
                ':sku'        => $sku,
                ':active'     => $isActive,
                ':id'         => $id,
                ':tid'        => $tid,
            ]);

            require_once DIR_HELPER . 'ai_evolution.php';
            ai_evolution_invalidate_snapshot_cache($tid);
            
            if (ob_get_level() > 0) ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['msg' => 'Variante atualizada.', 'id' => $id]);
        } else {
            // Criar nova variante
            $gate = ai_check_plan_gate($tid);
            if (!$gate['allowed']) {
                throw new Exception($gate['message']);
            }

            $insert = db()->prepare(
                'INSERT INTO ai_catalogo_variants
                   (model_id, tenant_id, color, color_normalized, color_hex, size, price, stock_qty, sku, is_active)
                 VALUES (:mid, :tid, :color, :color_norm, :hex, :size, :price, :qty, :sku, :active)'
            );
            $insert->execute([
                ':mid'        => $modelId,
                ':tid'        => $tid,
                ':color'      => $color,
                ':color_norm' => $colorNormalized,
                ':hex'        => $colorHex,
                ':size'       => $size,
                ':price'      => $price,
                ':qty'        => $stockQty,
                ':sku'        => $sku,
                ':active'     => $isActive,
            ]);
            $newId = (int)db()->lastInsertId();

            require_once DIR_HELPER . 'ai_evolution.php';
            ai_evolution_invalidate_snapshot_cache($tid);
            
            if (ob_get_level() > 0) ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['msg' => 'Variante adicionada.', 'id' => $newId]);
        }
        exit;
    }

    throw new Exception('Ação inválida.');

} catch (Exception $e) {
    if (ob_get_level() > 0) ob_clean();
    http_response_code(422);
    echo json_encode(['errorMsg' => $e->getMessage()]);
}
