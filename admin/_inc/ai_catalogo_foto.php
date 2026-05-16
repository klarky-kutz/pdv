<?php
/**
 * AJAX: ai_catalogo_foto.php
 * Upload de foto para o Catálogo IA — converte para WebP automaticamente.
 *
 * POST (multipart/form-data):
 *   type      = 'capa' | 'variante'
 *   model_id  = int
 *   variant_id = int (somente quando type='variante')
 *   foto      = $_FILES['foto']
 */
ob_start();
session_start();
include('../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_image_webp.php';
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
    $type      = $request->post['type'] ?? 'capa';        // 'capa' | 'variante'
    $modelId   = (int)($request->post['model_id'] ?? 0);
    $variantId = (int)($request->post['variant_id'] ?? 0);
    $tid       = ai_tenant_id();

    if (!$modelId) {
        throw new Exception('model_id inválido.');
    }

    // Verificar que o modelo pertence ao tenant
    $check = db()->prepare('SELECT id FROM ai_catalogo_models WHERE id = :mid AND tenant_id = :tid');
    $check->execute([':mid' => $modelId, ':tid' => $tid]);
    if (!$check->fetch()) {
        throw new Exception('Modelo não encontrado.');
    }

    // Verificar arquivo
    if (empty($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('Nenhum arquivo enviado.');
    }

    // Verificar se a biblioteca GD está habilitada (necessária para conversão WebP)
    if (!extension_loaded('gd')) {
        throw new Exception('A biblioteca PHP GD não está habilitada no servidor XAMPP. Para corrigir: 1. Abra o painel do XAMPP; 2. Clique em Config > PHP (php.ini); 3. Procure por ";extension=gd" e remova o ";" (ponto e vírgula); 4. Salve e reinicie o Apache.');
    }

    $oldPathRel = '';
    if ($type === 'capa') {
        $oldCover = db()->prepare('SELECT cover_webp FROM ai_catalogo_models WHERE id = :id AND tenant_id = :tid');
        $oldCover->execute([':id' => $modelId, ':tid' => $tid]);
        $oldPathRel = (string)$oldCover->fetchColumn();
        $filename = 'produto_' . $modelId . '_capa_' . time();
    } else {
        if (!$variantId) {
            throw new Exception('variant_id inválido para upload de variante.');
        }
        // Verificar posse da variante e recuperar foto anterior para cálculo de delta.
        $checkVar = db()->prepare(
            'SELECT id, photo_webp FROM ai_catalogo_variants WHERE id = :vid AND model_id = :mid AND tenant_id = :tid'
        );
        $checkVar->execute([':vid' => $variantId, ':mid' => $modelId, ':tid' => $tid]);
        $variant = $checkVar->fetch(PDO::FETCH_ASSOC);
        if (!$variant) {
            throw new Exception('Variante não encontrada.');
        }
        $oldPathRel = (string)($variant['photo_webp'] ?? '');
        $filename = 'produto_' . $modelId . '_variante_' . $variantId . '_' . time();
    }

    $oldAbsolute = $oldPathRel ? ai_storage_absolute_path($oldPathRel) : '';
    $replaceBytes = ($oldAbsolute && is_file($oldAbsolute)) ? (int)filesize($oldAbsolute) : 0;
    $incomingBytes = (int)($_FILES['foto']['size'] ?? 0);
    $storageCheck = ai_check_storage_capacity($incomingBytes, $tid, $replaceBytes);
    if (!$storageCheck['ok']) {
        throw new Exception($storageCheck['message']);
    }

    // Diretório de destino — mesmo padrão do FileManager:
    // storage/products/{tenant_id}/MODA AI
    $destDir = ai_get_catalog_media_dir($tid);
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
        throw new Exception('Não foi possível criar a pasta MODA AI no FileManager.');
    }
    if (!is_writable($destDir)) {
        throw new Exception('A pasta MODA AI não tem permissão de escrita.');
    }

    // Fazer upload e converter para WebP
    $result = ai_save_upload_webp($_FILES['foto'], $destDir, $filename, 82, 8192);
    if (!$result['ok']) {
        throw new Exception($result['error'] ?? 'Falha ao processar imagem.');
    }

    // Caminho relativo ao DIR_STORAGE (para salvar no banco)
    $relativePath = ai_storage_relative_path($result['path']);

    if ($type === 'capa') {
        // Apagar capa anterior
        if ($oldPathRel) {
            ai_delete_webp(ai_storage_absolute_path($oldPathRel));
        }

        db()->prepare(
            'UPDATE ai_catalogo_models SET cover_webp = :path WHERE id = :id AND tenant_id = :tid'
        )->execute([':path' => $relativePath, ':id' => $modelId, ':tid' => $tid]);
    } else {
        // Apagar foto anterior da variante
        if ($oldPathRel) {
            ai_delete_webp(ai_storage_absolute_path($oldPathRel));
        }

        db()->prepare(
            'UPDATE ai_catalogo_variants SET photo_webp = :path WHERE id = :id AND tenant_id = :tid'
        )->execute([':path' => $relativePath, ':id' => $variantId, ':tid' => $tid]);
    }
    // Atualizar log mensal aproximado (fonte oficial de uso continua sendo filesystem).
    $newBytes = (int)round(($result['kb'] ?? 0) * 1024);
    $deltaBytes = max(0, $newBytes - $replaceBytes);
    if ($deltaBytes > 0) {
        ai_add_storage_usage($deltaBytes / (1024 * 1024), $tid);
    }

    echo json_encode([
        'msg'  => 'Foto enviada com sucesso.',
        'url'  => $result['url'],
        'path' => $relativePath,
        'kb'   => $result['kb'],
    ]);

} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['errorMsg' => $e->getMessage()]);
}
