<?php
/**
 * AJAX: ai_catalogo_salvar.php
 * Cria ou atualiza um Modelo no Catálogo IA.
 *
 * POST:
 *   action  = 'salvar' | 'deletar' | 'toggle_status'
 *   id      = int (0 = novo)
 *   name    = string
 *   description = string
 *   tags    = string (csv)
 *   is_active = 0|1
 */
ob_start();
session_start();
include('../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_plan_gate.php';

header('Content-Type: application/json; charset=UTF-8');

// Autenticação
if (!is_loggedin()) {
    http_response_code(401);
    echo json_encode(['errorMsg' => trans('error_login')]);
    exit;
}

// Permissão
if (user_group_id() != 1 && !has_permission('access', 'access_concierge_ia')) {
    http_response_code(403);
    echo json_encode(['errorMsg' => 'Sem permissão para acessar o módulo Moda IA.']);
    exit;
}

try {
    $action = $request->post['action'] ?? '';
    $tid    = ai_tenant_id();

    // ── DELETAR ──────────────────────────────────────────────────────────────
    if ($action === 'deletar') {
        $id = (int)($request->post['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID inválido.');
        }

        // Verificar posse (tenant)
        $stmt = db()->prepare('SELECT id, cover_webp FROM ai_catalogo_models WHERE id = :id AND tenant_id = :tid');
        $stmt->execute([':id' => $id, ':tid' => $tid]);
        $model = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$model) {
            throw new Exception('Modelo não encontrado.');
        }

        // Excluir fotos das variantes
        require_once DIR_HELPER . 'ai_image_webp.php';
        $vars = db()->prepare('SELECT photo_webp FROM ai_catalogo_variants WHERE model_id = :mid AND tenant_id = :tid');
        $vars->execute([':mid' => $id, ':tid' => $tid]);
        foreach ($vars->fetchAll(PDO::FETCH_COLUMN) as $webp) {
            if ($webp) {
                ai_delete_webp(DIR_STORAGE . $webp);
            }
        }

        // Excluir capa
        if ($model['cover_webp']) {
            ai_delete_webp(DIR_STORAGE . $model['cover_webp']);
        }

        // Excluir variantes e modelo
        $delVars = db()->prepare('DELETE FROM ai_catalogo_variants WHERE model_id = :mid AND tenant_id = :tid');
        $delVars->execute([':mid' => $id, ':tid' => $tid]);
        
        $delModel = db()->prepare('DELETE FROM ai_catalogo_models WHERE id = :id AND tenant_id = :tid');
        $delModel->execute([':id' => $id, ':tid' => $tid]);

        echo json_encode(['msg' => 'Modelo removido do Catálogo IA.']);
        exit;
    }

    // ── TOGGLE STATUS ─────────────────────────────────────────────────────────
    if ($action === 'toggle_status') {
        $id = (int)($request->post['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID inválido.');
        }

        $stmt = db()->prepare('SELECT id, is_active FROM ai_catalogo_models WHERE id = :id AND tenant_id = :tid');
        $stmt->execute([':id' => $id, ':tid' => $tid]);
        $model = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$model) {
            throw new Exception('Modelo não encontrado.');
        }

        $newStatus = $model['is_active'] ? 0 : 1;
        $updStatus = db()->prepare('UPDATE ai_catalogo_models SET is_active = :s WHERE id = :id AND tenant_id = :tid');
        $updStatus->execute([':s' => $newStatus, ':id' => $id, ':tid' => $tid]);

        echo json_encode([
            'msg'       => $newStatus ? 'Produto ativado.' : 'Produto desativado.',
            'is_active' => $newStatus,
        ]);
        exit;
    }

    // ── SALVAR (criar ou atualizar) ───────────────────────────────────────────
    if ($action === 'salvar') {
        $id      = (int)($request->post['id'] ?? 0);
        $name    = trim($request->post['name'] ?? '');
        $sku     = trim($request->post['sku'] ?? '');
        $catId   = (int)($request->post['category_id'] ?? 0);
        $desc    = trim($request->post['description'] ?? '');
        $tags    = trim($request->post['tags'] ?? '');

        if (!$name || !$sku) {
            throw new Exception('Nome e SKU são obrigatórios.');
        }

        // Os campos main_price e main_color agora são derivados das variantes ou deixados como fallback
        $mainPrice = (float)($request->post['main_price'] ?? 0.00);
        $mainColor = trim($request->post['main_color'] ?? '');
        $mainStock = (int)($request->post['main_stock'] ?? 0);

        if ($id) {
            // Atualizar existente
            $stmt = db()->prepare('SELECT id FROM ai_catalogo_models WHERE id = :id AND tenant_id = :tid');
            $stmt->execute([':id' => $id, ':tid' => $tid]);
            if (!$stmt->fetch()) {
                throw new Exception('Modelo não encontrado para edição.');
            }

            $stmt = db()->prepare("
                UPDATE ai_catalogo_models 
                SET name = :name, sku = :sku, category_id = :cat, description = :desc, tags = :tags, main_price = :price, main_color = :color, updated_at = NOW()
                WHERE id = :id AND tenant_id = :tid
            ");
            $stmt->execute([
                ':name'  => $name,
                ':sku'   => $sku,
                ':cat'   => $catId ?: null,
                ':desc'  => $desc,
                ':tags'  => $tags,
                ':price' => $mainPrice,
                ':color' => $mainColor,
                ':id'    => $id,
                ':tid'   => $tid
            ]);
            $newId = $id;
        } else {
            // Criar novo — verificar limite do plano
            $gate = ai_check_plan_gate($tid);
            if (!$gate['allowed']) {
                throw new Exception($gate['message']);
            }

            $catLimit = ai_check_catalog_limit($tid);
            if (!$catLimit['ok']) {
                throw new Exception($catLimit['message']);
            }

            $stmt = db()->prepare("
                INSERT INTO ai_catalogo_models 
                (tenant_id, name, sku, category_id, main_price, main_color, description, tags, is_active, demand_count)
                VALUES (:tid, :name, :sku, :cat, :price, :color, :desc, :tags, 1, 0)
            ");
            $stmt->execute([
                ':tid'   => $tid,
                ':name'  => $name,
                ':sku'   => $sku,
                ':cat'   => $catId ?: null,
                ':price' => $mainPrice,
                ':color' => $mainColor,
                ':desc'  => $desc, 
                ':tags'  => $tags
            ]);
            $newId = (int)db()->lastInsertId();

            // Se forneceu cor, estoque ou preço, cria a primeira variante automaticamente
            if (!empty($mainColor) || $mainStock > 0 || $mainPrice > 0) {
                $mainColorNormalized = mb_strtolower(trim((string)$mainColor), 'UTF-8') ?: null;
                $insVar = db()->prepare(
                    'INSERT INTO ai_catalogo_variants (model_id, tenant_id, color, color_normalized, color_hex, price, stock_qty, sku, is_active)
                     VALUES (:mid, :tid, :color, :color_norm, :hex, :price, :qty, :sku, 1)'
                );
                $insVar->execute([
                    ':mid'        => $newId,
                    ':tid'        => $tid,
                    ':color'      => $mainColor ?: 'Padrão',
                    ':color_norm' => $mainColorNormalized,
                    ':hex'        => '#cccccc',
                    ':price'      => $mainPrice,
                    ':qty'        => $mainStock,
                    ':sku'        => $sku . '-1' // Primeira variante agora segue o padrão SKU-PAI-1
                ]);
            }
        }

        // PROCESSAR FOTO SE ENVIADA JUNTO
        if (isset($_FILES['foto']) && (int)($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            require_once DIR_HELPER . 'ai_image_webp.php';
            $incomingBytes = (int)($_FILES['foto']['size'] ?? 0);

            // Buscar capa anterior para permitir substituição sem bloquear indevidamente o storage.
            $oldCoverStmt = db()->prepare('SELECT cover_webp FROM ai_catalogo_models WHERE id = :id AND tenant_id = :tid');
            $oldCoverStmt->execute([':id' => $newId, ':tid' => $tid]);
            $oldCover = (string)$oldCoverStmt->fetchColumn();
            $oldCoverPath = $oldCover ? ai_storage_absolute_path($oldCover) : '';
            $replaceBytes = ($oldCoverPath && is_file($oldCoverPath)) ? (int)filesize($oldCoverPath) : 0;

            $storageCheck = ai_check_storage_capacity($incomingBytes, $tid, $replaceBytes);
            if (!$storageCheck['ok']) {
                throw new Exception($storageCheck['message']);
            }

            $destDir = ai_get_catalog_media_dir($tid);
            if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
                throw new Exception('Não foi possível criar a pasta MODA AI no FileManager.');
            }
            if (!is_writable($destDir)) {
                throw new Exception('A pasta MODA AI não tem permissão de escrita.');
            }

            $filename = 'produto_' . $newId . '_capa_' . time();
            $res = ai_save_upload_webp($_FILES['foto'], $destDir, $filename, 82, 8192);
            if (!$res['ok']) {
                throw new Exception($res['error'] ?? 'Falha ao processar foto de capa.');
            }
            $relativePath = ai_storage_relative_path($res['path']);
            $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

            // Excluir capa anterior para evitar lixo de storage
            if (!empty($oldCover)) {
                ai_delete_webp(ai_storage_absolute_path($oldCover));
            }

            // Atualizar no banco com escopo do tenant
            db()->prepare('UPDATE ai_catalogo_models SET cover_webp = :p WHERE id = :id AND tenant_id = :tid')
               ->execute([':p' => $relativePath, ':id' => $newId, ':tid' => $tid]);

            // Mantém o log mensal aproximado (fonte oficial de uso continua sendo filesystem).
            $deltaBytes = max(0, (int)round(($res['kb'] ?? 0) * 1024) - $replaceBytes);
            if ($deltaBytes > 0) {
                ai_add_storage_usage($deltaBytes / (1024 * 1024), $tid);
            }
        }

        $model = db()->prepare('SELECT * FROM ai_catalogo_models WHERE id = :id AND tenant_id = :tid');
        $model->execute([':id' => $newId, ':tid' => $tid]);
        $modelData = $model->fetch(PDO::FETCH_ASSOC);

        require_once DIR_HELPER . 'ai_evolution.php';
        ai_evolution_invalidate_snapshot_cache($tid);

        if (ob_get_level() > 0) ob_clean();
        echo json_encode([
            'msg' => $id ? 'Modelo atualizado com sucesso.' : 'Produto adicionado com sucesso.', 
            'id' => $newId, 
            'cover_url' => $modelData['cover_webp'] ? ROOT_URL . 'storage/' . $modelData['cover_webp'] : null
        ]);
        exit;
    }

    throw new Exception('Ação inválida.');

} catch (Exception $e) {
    if (ob_get_level() > 0) ob_clean();
    http_response_code(422);
    echo json_encode(['errorMsg' => $e->getMessage()]);
}
