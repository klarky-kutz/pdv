<?php
/**
 * AJAX: ai_demanda_ranking.php
 * Retorna ranking de peças mais desejadas (maior demand_count).
 *
 * GET:
 *   limit = int (padrão 10, máx 50)
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

try {
    $tid   = ai_tenant_id();
    $limit = min((int)($request->get['limit'] ?? 10), 500);
    if ($limit < 1) {
        $limit = 10;
    }

    $stmt = db()->prepare("
        SELECT m.id, m.name, m.sku, m.category_id, m.main_color, m.main_price, m.description, m.tags, m.cover_webp, m.demand_count, m.is_active,
               COUNT(v.id) AS variant_count,
               COALESCE(SUM(v.stock_qty), 0) AS total_stock,
               COALESCE(MIN(v.price), 0)     AS min_price
        FROM   ai_catalogo_models m
        LEFT JOIN ai_catalogo_variants v ON v.model_id = m.id AND v.tenant_id = m.tenant_id
        WHERE  m.tenant_id = :tid
        GROUP BY m.id
        ORDER BY m.demand_count DESC, m.updated_at DESC
        LIMIT  :lim
    ");
    $stmt->bindValue(':tid', $tid, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Adicionar URL da foto de capa
    foreach ($models as &$m) {
        $m['cover_url'] = $m['cover_webp']
            ? ROOT_URL . 'storage/' . ltrim(str_replace('\\', '/', $m['cover_webp']), '/')
            : null;
        $m['demand_count'] = (int)$m['demand_count'];
    }
    unset($m);

    echo json_encode([
        'ranking' => $models,
        'total'   => count($models),
    ]);

} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['errorMsg' => $e->getMessage()]);
}
