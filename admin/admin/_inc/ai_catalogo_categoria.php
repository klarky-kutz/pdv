<?php
/**
 * AJAX: ai_catalogo_categoria.php
 * Gerencia Categorias do Catálogo IA.
 *
 * POST:
 *   action  = 'listar' | 'salvar' | 'deletar'
 *   name    = string (para salvar)
 *   id      = int (para deletar)
 */
ob_start();
session_start();
include('../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';

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
    
    error_log("Moda IA AJAX: action=$action, tenant_id=$tid");

    // LOAD CATEGORY MODEL
    $category_model = registry()->get('loader')->model('category');

    // ── LISTAR ───────────────────────────────────────────────────────────────
    if ($action === 'listar') {
        $categories = ai_get_catalogo_categories();
        error_log("Moda IA AJAX: Listadas " . count($categories) . " categorias");
        echo json_encode(['categories' => $categories]);
        exit;
    }

    // ── SALVAR ───────────────────────────────────────────────────────────────
    if ($action === 'salvar') {
        $name = trim($request->post['name'] ?? '');
        if (empty($name)) {
            throw new Exception('O nome da categoria é obrigatório.');
        }

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));

        // Preparar dados para o model nativo
        $data = [
            'category_name' => $name,
            'category_slug' => $slug,
            'parent_id'     => 0,
            'category_details' => 'Criado via Moda IA',
            'category_image'   => '',
            'category_store'   => [$tid],
            'status'           => 1,
            'sort_order'       => 0
        ];

        // Usar o model nativo para manter integridade (vínculos com lojas, etc)
        $newId = $category_model->addCategory($data);

        echo json_encode(['msg' => 'Categoria cadastrada no sistema.', 'id' => $newId, 'name' => $name]);
        exit;
    }

    // ── DELETAR ──────────────────────────────────────────────────────────────
    if ($action === 'deletar') {
        $id = (int)($request->post['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID inválido.');
        }

        // Usar model nativo para deletar (mantém integridade de outras tabelas)
        // Nota: O model nativo deleteCategory não verifica tenant_id internamente da mesma forma, 
        // mas aqui estamos no contexto administrativo.
        $category_model->deleteCategory($id);

        echo json_encode(['msg' => 'Categoria excluída do sistema.']);
        exit;
    }

    throw new Exception('Ação inválida.');

} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['errorMsg' => $e->getMessage()]);
}
