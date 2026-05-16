<?php
/**
 * AJAX: ai_catalogo_listar.php
 * Retorna HTML das linhas da tabela do catálogo com base em filtros e busca.
 */
ob_start();
session_start();
include('../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';

header('Content-Type: application/json; charset=UTF-8');

if (!is_loggedin()) {
    http_response_code(401);
    echo json_encode(['errorMsg' => 'Não logado']);
    exit;
}

try {
    $tid    = ai_tenant_id();
    $filter = $request->get['filter'] ?? 'todos';
    $search = trim($request->get['search'] ?? '');
    $limit  = (int)($request->get['limit'] ?? 20);
    $page   = max(1, (int)($request->get['page'] ?? 1));

    $where = ["m.tenant_id = :tid"];
    $params = [':tid' => $tid];
    
    // Ordem padrão: mais recentes primeiro
    $orderBy = "m.updated_at DESC";

    if ($search !== '') {
        $where[] = "(LOWER(m.name) LIKE :s1 OR LOWER(m.tags) LIKE :s2 OR LOWER(m.description) LIKE :s3)";
        // Para o WHERE, usamos "contém" em todos para não ignorar resultados válidos
        $params[':s1'] = "%" . strtolower($search) . "%";
        $params[':s2'] = "%" . strtolower($search) . "%";
        $params[':s3'] = "%" . strtolower($search) . "%";
        
        // Priorizar resultados que COMEÇAM com o termo no nome
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
        SELECT m.*,
               c.category_name,
               COUNT(v.id) AS variant_count,
               COALESCE(SUM(v.stock_qty), 0) AS total_stock,
               COALESCE(MIN(v.price), 0) AS min_price
        FROM ai_catalogo_models m
        LEFT JOIN categorys c ON c.category_id = m.category_id
        LEFT JOIN ai_catalogo_variants v ON v.model_id = m.id AND v.tenant_id = m.tenant_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY m.id
        ORDER BY $orderBy
    ";

    // Contagem total para paginação
    $totalFiltered = 0;
    $countSql = "SELECT COUNT(DISTINCT m.id) FROM ai_catalogo_models m 
                 LEFT JOIN categorys c ON c.category_id = m.category_id
                 LEFT JOIN ai_catalogo_variants v ON v.model_id = m.id AND v.tenant_id = m.tenant_id
                 WHERE " . implode(' AND ', $where);
    $countStmt = db()->prepare($countSql);
    foreach ($params as $k => $v) { $countStmt->bindValue($k, $v); }
    $countStmt->execute();
    $totalFiltered = (int)$countStmt->fetchColumn();

    // Adicionar LIMIT e OFFSET
    if ($limit > 0) {
        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT $limit OFFSET $offset";
    }

    $stmt = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Bulk-load de variantes (evita N+1 queries) ────────────────────────────
    $variantsByModel = [];
    if (!empty($models)) {
        $modelIds   = array_column($models, 'id');
        $inPlaceholders = implode(',', array_fill(0, count($modelIds), '?'));
        $vStmt = db()->prepare(
            "SELECT * FROM ai_catalogo_variants
             WHERE model_id IN ($inPlaceholders) AND tenant_id = ?
             ORDER BY color, size"
        );
        $vStmt->execute(array_merge(array_map('intval', $modelIds), [$tid]));
        foreach ($vStmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
            $variantsByModel[(int)$v['model_id']][] = $v;
        }
    }

    // ── Stats globais (não filtrados) para atualizar os info-boxes ────────────
    $statsStmt = db()->prepare("
        SELECT
            COALESCE(SUM(is_active = 1), 0)  AS active_count,
            COALESCE(SUM(is_active = 0), 0)  AS inactive_count,
            COUNT(*)                          AS total_count
        FROM ai_catalogo_models WHERE tenant_id = :tid
    ");
    $statsStmt->execute([':tid' => $tid]);
    $overallStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    $zeroStmt = db()->prepare("
        SELECT COUNT(DISTINCT model_id)
        FROM ai_catalogo_variants
        WHERE tenant_id = :tid AND stock_qty <= 0
    ");
    $zeroStmt->execute([':tid' => $tid]);
    $zeroStock = (int)$zeroStmt->fetchColumn();

    $callsStmt = db()->prepare("
        SELECT COALESCE(webhook_calls, 0)
        FROM ai_usage_log WHERE tenant_id = :tid AND `year_month` = :ym LIMIT 1
    ");
    $callsStmt->execute([':tid' => $tid, ':ym' => date('Y-m')]);
    $aiCalls = (int)($callsStmt->fetchColumn() ?: 0);

    // Pegar max demand para as barras de progresso
    $maxDemand = 1;
    foreach($models as $m) if($m['demand_count'] > $maxDemand) $maxDemand = $m['demand_count'];

    $html = '';
    foreach ($models as $_m) {
        $mid = (int)$_m['id'];
        $cover_url = $_m['cover_webp'] ? ROOT_URL . 'storage/' . ltrim(str_replace('\\', '/', $_m['cover_webp']), '/') : null;
        
        $isActive = (int)$_m['is_active'];
        $demand = (int)$_m['demand_count'];
        $demandPct = min(100, round($demand / $maxDemand * 100));
        $isHot = $demand > 0 && $demandPct >= 60;
        
        // Usar variantes já carregadas em bulk
        $variants = $variantsByModel[$mid] ?? [];
        $colors = array_unique(array_filter(array_column($variants, 'color')));
        
        $swatchesHtml = '';
        $shownHex = [];
        $uniqueVariantsByColor = [];
        
        // Agrupar variantes por cor para mostrar o status de estoque corretamente no swatch
        foreach ($variants as $_v) {
            $hex = $_v['color_hex'] ?: '#999';
            if (!isset($uniqueVariantsByColor[$hex])) {
                $uniqueVariantsByColor[$hex] = [
                    'color' => $_v['color'],
                    'stock' => 0
                ];
            }
            $uniqueVariantsByColor[$hex]['stock'] += (int)$_v['stock_qty'];
        }

        $count = 0;
        foreach ($uniqueVariantsByColor as $hex => $data) {
            if ($count >= 6) break;
            $count++;
            
            $isOutOfStock = $data['stock'] <= 0;
            $swatchClass = $isOutOfStock ? 'swatch out-of-stock' : 'swatch';
            $stockTitle = $isOutOfStock ? ' (Sem estoque)' : '';
            $swatchesHtml .= '<span class="'.$swatchClass.'" style="background:'.htmlspecialchars($hex).'" title="'.htmlspecialchars($data['color'] ?? '').$stockTitle.'"></span>';
        }

        $skuHtml = htmlspecialchars($_m['sku'] ?: 'Sem SKU');
        $catHtml = !empty($_m['category_name'])
            ? '<span class="pcat">'.htmlspecialchars($_m['category_name']).'</span>'
            : '<span class="pcat">Sem categoria</span>';

        $html .= '
        <tr data-id="'.$mid.'">
          <td class="td-foto">
            <div class="thumb">
              '.($cover_url ? '<img src="'.htmlspecialchars($cover_url).'" style="width:100%;height:100%;object-fit:cover;">' : '<i class="fa fa-image"></i>').'
            </div>
          </td>
          <td class="td-produto">
            <div class="pname">'.htmlspecialchars($_m['name']).'</div>
            <div style="font-size:11px;color:#9ca3af;margin-bottom:4px;">'.$skuHtml.'</div>
            '.$catHtml.'
          </td>
          <td class="td-variantes">
            <div class="swatches">'.$swatchesHtml.'</div>
            <div class="vinfo">'.count($colors).' '.(count($colors)==1?'cor':'cores').' · '.(int)$_m['variant_count'].' variantes</div>
          </td>
          <td class="td-demand">
            <div class="demand-num'.($isHot?' hot':'').'">'.$demand.'</div>
            <div class="demand-sub">Solicitações IA</div>
            <div class="demand-bar"><div class="demand-fill'.($isHot?' hot':'').'" style="width:'.$demandPct.'%"></div></div>
          </td>
          <td class="td-status">
            <div style="display:flex;align-items:center;gap:7px">
              <div class="toggle-track'.($isActive?' on':'').'" data-id="'.$mid.'" onclick="toggleStatus(this)"><div class="toggle-thumb"></div></div>
              <span class="status-lbl'.($isActive?' on':'').'">'.($isActive?'Ativo':'Inativo').'</span>
            </div>
          </td>
          <td class="td-acoes">
            <button class="btn btn-secondary btn-sm" onclick="editarModelo('.$mid.')"><i class="fa fa-pencil"></i> Editar</button>
            <button class="btn btn-danger btn-sm" onclick="deletarModelo('.$mid.', \''.htmlspecialchars(addslashes($_m['name'])).'\')"><i class="fa fa-trash-o"></i></button>
          </td>
        </tr>';
    }

    if (empty($models)) {
        $html = '<tr><td colspan="6" style="text-align:center;padding:60px 20px;color:#9ca3af">
            <i class="fa fa-search" style="font-size:32px;margin-bottom:12px;display:block;opacity:0.5"></i>
            <div style="font-weight:600;font-size:14px">Nenhum resultado encontrado</div>
            <div style="font-size:12px">Tente ajustar sua busca ou filtros.</div>
        </td></tr>';
    }

    echo json_encode([
        'html'           => $html,
        'total'          => $totalFiltered,
        'active_count'   => (int)($overallStats['active_count'] ?? 0),
        'inactive_count' => (int)($overallStats['inactive_count'] ?? 0),
        'zero_stock'     => $zeroStock,
        'ai_calls'       => $aiCalls,
        'page'           => $page,
        'limit'          => $limit,
        'total_filtered' => $totalFiltered
    ]);

} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['errorMsg' => $e->getMessage()]);
}
