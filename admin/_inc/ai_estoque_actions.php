<?php
ob_start();
session_start();
include('../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';

header('Content-Type: application/json; charset=UTF-8');

if (!is_loggedin()) {
    http_response_code(401);
    echo json_encode(['error' => true, 'message' => 'Não logado.']);
    exit;
}

if (user_group_id() != 1 && !has_permission('access', 'access_concierge_ia')) {
    http_response_code(403);
    echo json_encode(['error' => true, 'message' => 'Sem permissão para acessar o módulo Moda IA.']);
    exit;
}

function mia_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mia_money(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

try {
    $tid = ai_tenant_id();
    $action = (string)($request->post['action'] ?? $request->get['action'] ?? '');

    if ($action === 'list') {
        $statusFilter = (string)($request->get['status'] ?? $request->post['status'] ?? '');
        $search = trim((string)($request->get['search'] ?? $request->post['search'] ?? ''));
        $page = max(1, (int)($request->get['page'] ?? 1));
        $limit = (int)($request->get['limit'] ?? 20);

        $status = null;
        if ($statusFilter === 'zerado') $status = 'zerado';
        if ($statusFilter === 'critico') $status = 'critico';
        if ($statusFilter === 'ok') $status = 'ok';

        $summary = ai_get_stock_summary();
        
        // Obter todas as variantes filtradas para paginação manual (já que o helper não pagina)
        $allVariants = ai_get_stock_variants([
            'status' => $status,
            'search' => $search,
        ]);

        $totalFiltered = count($allVariants);
        $offset = 0;
        $pagedVariants = $allVariants;

        if ($limit > 0) {
            $offset = ($page - 1) * $limit;
            $pagedVariants = array_slice($allVariants, $offset, $limit);
        }

        $totalSkus = (int)($summary['total_skus'] ?? 0);
        $zerados = (int)($summary['zerados'] ?? 0);
        $criticos = (int)($summary['criticos'] ?? 0);
        $valorTotal = (float)($summary['valor_total'] ?? 0);

        $html = '';
        foreach ($pagedVariants as $v) {
            $qty = (int)($v['stock_qty'] ?? 0);
            $rowCls = $qty === 0 ? 'row-zero' : ($qty <= 3 ? 'row-crit' : '');
            $qtyCls = $qty === 0 ? 'zero' : ($qty < 3 ? 'crit' : '');

            $badge = $qty === 0
                ? '<span class="badge badge-zero"><i class="fa fa-times-circle"></i> Zerado</span>'
                : ($qty < 3
                    ? '<span class="badge badge-crit"><i class="fa fa-exclamation-triangle"></i> Crítico</span>'
                    : ($qty < 5
                        ? '<span class="badge badge-low"><i class="fa fa-exclamation-circle"></i> Baixo</span>'
                        : '<span class="badge badge-ok"><i class="fa fa-check-circle"></i> OK</span>'));

            $demandPct = (float)($v['demand_pct'] ?? 0);
            $demandPct = max(0, min(100, $demandPct));

            $skuDisplay = !empty($v['sku']) ? htmlspecialchars((string)$v['sku']) : '#ID-' . (int)$v['id'];

            $html .= '<tr class="' . $rowCls . '">'
                . '<td>'
                . '<div class="prod-name">' . htmlspecialchars((string)($v['model_name'] ?? '')) . '</div>'
                . '<div class="prod-id">' . $skuDisplay . '</div>'
                . '</td>'
                . '<td><div class="color-cell"><span class="swatch-lg" style="background:' . htmlspecialchars((string)($v['color_hex'] ?: '#999')) . '"></span><span class="color-name">' . htmlspecialchars((string)($v['color'] ?? '')) . '</span></div></td>'
                . '<td><span class="size-pill">' . htmlspecialchars((string)($v['size'] ?? '')) . '</span></td>'
                . '<td><div class="demand-num">' . round($demandPct) . '%</div><div class="demand-sub">solicitações IA</div><div class="demand-bar"><div class="demand-fill" style="width:' . round($demandPct) . '%"></div></div></td>'
                . '<td style="font-weight:700;color:#374151">' . htmlspecialchars(mia_money((float)($v['price'] ?? 0))) . '</td>'
                . '<td><div class="qty-ctrl">'
                . '<button class="btn-icon" onclick="ajustarQty(this,-1)"><i class="fa fa-minus"></i></button>'
                . '<input class="qty-val ' . $qtyCls . '" type="number" value="' . $qty . '" min="0" onchange="salvarQty(this)" data-variant-id="' . (int)$v['id'] . '">'
                . '<button class="btn-icon" onclick="ajustarQty(this,1)"><i class="fa fa-plus"></i></button>'
                . '</div></td>'
                . '<td>' . $badge . '</td>'
                . '</tr>';
        }

        if ($html === '') {
            $html = '<tr><td colspan="7" style="text-align:center;padding:40px 16px;color:#9ca3af">Nenhuma variante encontrada.</td></tr>';
        }

        mia_json([
            'error' => false,
            'summary' => [
                'total_skus' => $totalSkus,
                'zerados' => $zerados,
                'criticos' => $criticos,
                'valor_total' => $valorTotal,
            ],
            'rows_html' => $html,
            'total_filtered' => $totalFiltered,
            'count' => count($pagedVariants),
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    if ($action === 'update_qty') {
        $variantId = (int)($request->post['variant_id'] ?? 0);
        $qty = (int)($request->post['qty'] ?? 0);
        if (!$variantId || $qty < 0) {
            throw new Exception('Parâmetros inválidos.');
        }

        $stmt = db()->prepare("
            UPDATE ai_catalogo_variants
            SET stock_qty = :q
            WHERE id = :id AND tenant_id = :tid
            LIMIT 1
        ");
        $stmt->execute([':q' => $qty, ':id' => $variantId, ':tid' => $tid]);
        if ($stmt->rowCount() <= 0) {
            throw new Exception('Não foi possível atualizar o estoque.');
        }

        mia_json(['error' => false, 'variant_id' => $variantId, 'qty' => $qty]);
    }

    throw new Exception('Ação inválida.');
} catch (Exception $e) {
    mia_json(['error' => true, 'message' => $e->getMessage()], 422);
}

