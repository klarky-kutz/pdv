<?php
/**
 * AJAX: ai_catalogo_exportar.php
 * Exporta CSV do catálogo filtrado.
 */
ob_start();
session_start();
include('../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';

if (!is_loggedin()) {
    exit('Não logado');
}

try {
    $tid    = ai_tenant_id();
    $filter = $request->get['filter'] ?? 'todos';
    $search = trim($request->get['search'] ?? '');

    $where = ["m.tenant_id = :tid"];
    $params = [':tid' => $tid];
    
    $orderBy = "m.updated_at DESC";

    if ($search !== '') {
        $where[] = "(LOWER(m.name) LIKE :s1 OR LOWER(m.tags) LIKE :s2 OR LOWER(m.description) LIKE :s3)";
        $params[':s1'] = "%" . strtolower($search) . "%";
        $params[':s2'] = "%" . strtolower($search) . "%";
        $params[':s3'] = "%" . strtolower($search) . "%";

        // Priorizar resultados que COMEÇAM com o termo
        $searchLower = strtolower($search);
        $orderBy = "CASE 
            WHEN LOWER(m.name) = '$searchLower' THEN 1
            WHEN LOWER(m.name) LIKE '$searchLower%' THEN 2 
            WHEN LOWER(m.name) LIKE '%$searchLower%' THEN 3 
            ELSE 4 
        END, m.name ASC";
    }

    if ($filter === 'ativos') {
        $where[] = "m.is_active = 1";
    } elseif ($filter === 'inativos') {
        $where[] = "m.is_active = 0";
    } elseif ($filter === 'zerados') {
        $where[] = "EXISTS (SELECT 1 FROM ai_catalogo_variants v2 WHERE v2.model_id = m.id AND v2.stock_qty <= 0)";
    }

    if ($filter === 'quentes' && $search === '') {
        $orderBy = "m.demand_count DESC, m.updated_at DESC";
    }

    $sql = "
        SELECT m.id, m.name, m.description, m.tags, m.is_active, m.demand_count,
               COUNT(v.id) AS variant_count,
               COALESCE(SUM(v.stock_qty), 0) AS total_stock,
               COALESCE(MIN(v.price), 0) AS min_price
        FROM ai_catalogo_models m
        LEFT JOIN ai_catalogo_variants v ON v.model_id = m.id AND v.tenant_id = m.tenant_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY m.id
        ORDER BY $orderBy
    ";

    $stmt = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="catalogo_ia_'.date('Ymd_His').'.csv"');
    
    // Bom para UTF-8 no Excel
    echo "\xEF\xBB\xBF";
    
    $f = fopen('php://output', 'w');
    fputcsv($f, ['ID', 'Nome', 'Descricao', 'Tags', 'Status', 'Desejos', 'Variantes', 'Estoque Total', 'Preco Minimo'], ';');

    foreach ($models as $m) {
        fputcsv($f, [
            $m['id'],
            $m['name'],
            $m['description'],
            $m['tags'],
            $m['is_active'] ? 'Ativo' : 'Inativo',
            $m['demand_count'],
            $m['variant_count'],
            $m['total_stock'],
            number_format($m['min_price'], 2, ',', '.')
        ], ';');
    }
    fclose($f);
    exit;

} catch (Exception $e) {
    exit($e->getMessage());
}
