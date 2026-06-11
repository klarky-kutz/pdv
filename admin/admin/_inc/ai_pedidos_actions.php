<?php
ob_start();
session_start();
include('../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_evolution.php';

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
    if (ob_get_level() > 0) ob_clean();
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mia_time_ago(?string $datetime): string
{
    if (!$datetime) return '';
    $ts = strtotime($datetime);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60) return 'agora';
    if ($diff < 3600) return 'há ' . floor($diff / 60) . 'min';
    if ($diff < 86400) return 'há ' . floor($diff / 3600) . 'h';
    return 'há ' . floor($diff / 86400) . 'd';
}

function mia_money(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function mia_phone_digits(string $raw): string
{
    return preg_replace('/\D+/', '', $raw);
}

function mia_remote_jid_from_phone(string $raw): string
{
    $digits = mia_phone_digits($raw);
    if ($digits === '') return '';
    return $digits . '@s.whatsapp.net';
}

function mia_build_order_card(array $o, array $atendimentoMap): string
{
    $id = (int)($o['id'] ?? 0);
    $status = (string)($o['status'] ?? '');
    $isPago = $status === 'pago';
    $isPend = $status === 'pendente';

    $phone = (string)($o['whatsapp_phone'] ?? '');
    $digits = mia_phone_digits($phone);
    $nicePhone = $digits !== '' ? $digits : $phone;

    $remoteJid = mia_remote_jid_from_phone($phone);
    $att = $remoteJid !== '' ? ($atendimentoMap[$remoteJid] ?? null) : null;
    $attStatus = is_array($att) ? strtolower((string)($att['status'] ?? 'ativo')) : 'ativo';

    $iaIsActive = $attStatus === 'ativo';
    $iaBadge = $iaIsActive
        ? '<span class="ia-status active"><i class="fa fa-magic"></i> IA Ativa</span>'
        : '<span class="ia-status human"><i class="fa fa-user"></i> Humano</span>';

    $ago = mia_time_ago((string)($o['created_at'] ?? ''));

    $items = $o['items'] ?? [];
    $first = is_array($items) && !empty($items) ? $items[0] : null;
    $itemName = is_array($first) ? (string)($first['model_name'] ?? '') : '';
    $itemVar = is_array($first)
        ? trim((string)($first['color'] ?? '') . ' · ' . (string)($first['size'] ?? '') . ' · ' . (int)($first['qty'] ?? 1) . 'x')
        : '';

    $pixBadge = $isPago
        ? '<span class="pix-badge confirmed"><i class="fa fa-check"></i> Pix Confirmado</span>'
        : ($isPend ? '<span class="pix-badge pending"><i class="fa fa-qrcode"></i> Pix Pendente</span>' : '<span class="pix-badge waiting"><i class="fa fa-magic"></i> Em andamento</span>');

    $total = (float)($o['total_amount'] ?? 0);

    return '
      <div class="card" data-order-id="' . $id . '" onclick="abrirDrawer(' . $id . ')">
        <div class="card-top">
          ' . $iaBadge . '
          <span class="card-time"><i class="fa fa-clock-o"></i> ' . htmlspecialchars($ago) . '</span>
        </div>
        <div class="card-client">' . htmlspecialchars((string)($o['profile_name'] ?: ($o['customer_name'] ?: 'Cliente'))) . '</div>
        <div class="card-phone" style="cursor:pointer;color:#25d366;font-weight:600" onclick="event.stopPropagation();abrirWhatsApp(\'' . htmlspecialchars(addslashes($nicePhone)) . '\')"><i class="fa fa-whatsapp"></i> ' . htmlspecialchars($nicePhone) . '</div>
        <div class="card-items">
          <div class="card-item-row">
            <div class="card-item-thumb"><i class="fa fa-camera"></i></div>
            <div><div class="card-item-name">' . htmlspecialchars($itemName ?: 'Itens do pedido') . '</div><div class="card-item-var">' . htmlspecialchars($itemVar) . '</div></div>
          </div>
        </div>
        <div class="card-total"><span class="card-price">' . htmlspecialchars(mia_money($total)) . '</span>' . $pixBadge . '</div>
      </div>';
}

function mia_period_to_range(string $period, string $customDate): array
{
    $period = strtolower(trim($period));
    $today = new DateTime('today');

    if ($period === 'ontem') {
        $d = (clone $today)->modify('-1 day');
        return [$d->format('Y-m-d'), $d->format('Y-m-d')];
    }
    if ($period === 'semana') {
        $d1 = (clone $today)->modify('-6 days');
        return [$d1->format('Y-m-d'), $today->format('Y-m-d')];
    }
    if ($period === 'mes') {
        $d1 = (clone $today)->modify('-29 days');
        return [$d1->format('Y-m-d'), $today->format('Y-m-d')];
    }
    if ($period === 'custom' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customDate)) {
        return [$customDate, $customDate];
    }
    return [$today->format('Y-m-d'), $today->format('Y-m-d')];
}

try {
    $tid = ai_tenant_id();
    $action = (string)($request->post['action'] ?? $request->get['action'] ?? '');

    if ($action === 'badge') {
        $pending = ai_count_pending_orders();
        mia_json(['error' => false, 'pending' => $pending]);
    }

    if ($action === 'list') {
        $period = (string)($request->get['period'] ?? $request->post['period'] ?? 'hoje');
        $customDate = (string)($request->get['date'] ?? $request->post['date'] ?? '');
        $search = trim((string)($request->get['search'] ?? $request->post['search'] ?? ''));

        [$from, $to] = mia_period_to_range($period, $customDate);

        // Se o período for "hoje" e não houver busca, queremos ver TODOS os pedidos ativos (não entregues)
        // independentemente da data, para que pedidos de ontem não sumam do Kanban.
        $ignoreDateForActive = ($period === 'hoje' && $search === '');

        $ordersByStatus = ai_get_orders_by_status([
            'date_from' => $ignoreDateForActive ? null : $from,
            'date_to'   => $to, // To sempre limitado ao dia de hoje no modo "hoje"
            'search'    => $search,
        ]);

        // Se ignoramos a data para ativos, precisamos filtrar manualmente os 'entregues' 
        // para mostrar apenas os de hoje, conforme a regra do Kanban.
        if ($ignoreDateForActive && isset($ordersByStatus['entregue'])) {
            $ordersByStatus['entregue'] = array_values(array_filter($ordersByStatus['entregue'], function($o) use ($from) {
                return date('Y-m-d', strtotime($o['created_at'])) === $from;
            }));
        }

        $pedido = array_merge($ordersByStatus['pendente'] ?? [], $ordersByStatus['pago'] ?? []);
        $separacao = $ordersByStatus['separando'] ?? [];
        $rota = $ordersByStatus['rota'] ?? [];
        $entregue = $ordersByStatus['entregue'] ?? [];

        $all = array_merge($pedido, $separacao, $rota, $entregue);
        $remoteJids = [];
        foreach ($all as $o) {
            $jid = mia_remote_jid_from_phone((string)($o['whatsapp_phone'] ?? ''));
            if ($jid !== '') $remoteJids[] = $jid;
        }
        $atendimentoMap = ai_evolution_get_atendimento_map($tid, array_values(array_unique($remoteJids)));

        $pedidoHtml = '';
        foreach ($pedido as $o) $pedidoHtml .= mia_build_order_card($o, $atendimentoMap);
        if ($pedidoHtml === '') $pedidoHtml = '<div style="padding:14px;color:#9ca3af;font-size:12px;text-align:center">Nenhum pedido neste período.</div>';

        $sepHtml = '';
        foreach ($separacao as $o) $sepHtml .= mia_build_order_card($o, $atendimentoMap);
        if ($sepHtml === '') $sepHtml = '<div style="padding:14px;color:#9ca3af;font-size:12px;text-align:center">Nenhum pedido em separação.</div>';

        $rotaHtml = '';
        foreach ($rota as $o) $rotaHtml .= mia_build_order_card($o, $atendimentoMap);
        if ($rotaHtml === '') $rotaHtml = '<div style="padding:14px;color:#9ca3af;font-size:12px;text-align:center">Nenhum pedido em rota.</div>';

        $entHtml = '';
        foreach ($entregue as $o) $entHtml .= mia_build_order_card($o, $atendimentoMap);
        if ($entHtml === '') $entHtml = '<div style="padding:14px;color:#9ca3af;font-size:12px;text-align:center">Nenhum pedido entregue.</div>';

        $entreguesHoje = ai_get_orders_by_status([
            'status' => 'entregue',
            'date_from' => (new DateTime('today'))->format('Y-m-d'),
            'date_to' => (new DateTime('today'))->format('Y-m-d'),
        ]);
        $entreguesHojeCount = count($entreguesHoje['entregue'] ?? []);

        mia_json([
            'error' => false,
            'from' => $from,
            'to' => $to,
            'pedido_html' => $pedidoHtml,
            'separacao_html' => $sepHtml,
            'rota_html' => $rotaHtml,
            'entregue_html' => $entHtml,
            'counts' => [
                'pedido' => count($pedido),
                'separacao' => count($separacao),
                'rota' => count($rota),
                'entregue' => count($entregue),
                'pendente_pix' => count($ordersByStatus['pendente'] ?? []),
                'entregue_hoje' => $entreguesHojeCount,
            ],
        ]);
    }

    if ($action === 'get_order') {
        $orderId = (int)($request->get['order_id'] ?? $request->post['order_id'] ?? 0);
        if (!$orderId) {
            throw new Exception('order_id inválido.');
        }
        $order = ai_get_order($orderId, $tid);
        if (!$order) {
            throw new Exception('Pedido não encontrado.');
        }

        $remoteJid = mia_remote_jid_from_phone((string)$order['whatsapp_phone']);
        $atendimento = $remoteJid !== '' ? ai_evolution_get_atendimento_status($tid, $remoteJid) : ['status' => 'Ativo'];
        $profile = ai_evolution_get_customer_memory($tid, (string)$order['whatsapp_phone']);
        $summary = ai_evolution_build_conversation_summary($order, $profile);

        $items = $order['items'] ?? [];
        foreach ($items as &$it) {
            if (!empty($it['photo_webp'])) {
                $it['photo_url'] = rtrim(ROOT_URL, '/') . '/storage/' . ltrim(str_replace('\\', '/', $it['photo_webp']), '/');
            } else {
                $it['photo_url'] = '';
            }
        }
        unset($it);

        mia_json([
            'error' => false,
            'order' => $order,
            'items' => $items,
            'profile' => $profile,
            'atendimento' => $atendimento,
            'summary' => $summary,
            'remote_jid' => $remoteJid,
        ]);
    }

    if ($action === 'update_status') {
        $orderId = (int)($request->post['order_id'] ?? 0);
        $status = (string)($request->post['status'] ?? '');
        if (!$orderId || $status === '') {
            throw new Exception('Parâmetros inválidos.');
        }
        $ok = ai_update_order_status($orderId, $status, false, $tid);
        if (!$ok) {
            throw new Exception('Não foi possível atualizar o status.');
        }
        // Notificar cliente via WhatsApp (exceto primeiro estágio e cancelamento)
        if (!in_array($status, ['pendente', 'cancelado'], true)) {
            ai_notify_customer_status_change($tid, $orderId, $status);
        }
        if ($status === 'pago') {
            ai_notify_store_payment_confirmed($tid, $orderId);
        }
        mia_json(['error' => false, 'message' => 'Status atualizado.', 'order_id' => $orderId, 'status' => $status]);
    }

    if ($action === 'toggle_atendimento') {
        $remoteJid = (string)($request->post['remote_jid'] ?? '');
        $phone = (string)($request->post['phone'] ?? '');
        $status = (string)($request->post['status'] ?? '');

        if ($remoteJid === '' && $phone !== '') {
            $remoteJid = mia_remote_jid_from_phone($phone);
        }
        if ($remoteJid === '' || ($status !== 'Ativo' && $status !== 'Manual')) {
            throw new Exception('Parâmetros inválidos.');
        }

        $uid = function_exists('user_id') ? (int)user_id() : 0;
        ai_evolution_set_atendimento_status($tid, $remoteJid, $status, $uid);
        mia_json(['error' => false, 'remote_jid' => $remoteJid, 'status' => $status]);
    }

    if ($action === 'search') {
        $q = trim((string)($request->get['q'] ?? $request->post['q'] ?? ''));
        $filter = trim((string)($request->get['filter'] ?? $request->post['filter'] ?? 'todos'));

        if ($q === '' && $filter === 'todos') {
            mia_json(['error' => false, 'count' => 0, 'html' => '']);
        }

        $status = null;
        if ($filter === 'pedido') $status = null;
        if ($filter === 'separacao') $status = 'separando';
        if ($filter === 'rota') $status = 'rota';
        if ($filter === 'entregue') $status = 'entregue';

        $ordersByStatus = ai_get_orders_by_status([
            'status' => $status,
            'search' => $q,
        ]);

        $list = [];
        foreach ($ordersByStatus as $arr) {
            if (is_array($arr)) $list = array_merge($list, $arr);
        }
        if ($filter === 'pedido') {
            $list = array_values(array_filter($list, function ($o) {
                $st = (string)($o['status'] ?? '');
                return $st === 'pendente' || $st === 'pago';
            }));
        }

        $remoteJids = [];
        foreach ($list as $o) {
            $jid = mia_remote_jid_from_phone((string)($o['whatsapp_phone'] ?? ''));
            if ($jid !== '') $remoteJids[] = $jid;
        }
        $atendimentoMap = ai_evolution_get_atendimento_map($tid, array_values(array_unique($remoteJids)));

        $html = '';
        foreach ($list as $o) {
            $orderId = (int)$o['id'];
            
            // Buscar nome no perfil se disponível para evitar exibir nomes genéricos de pedidos antigos
            $stmtProf = db()->prepare("SELECT name FROM ai_chat_profiles WHERE tenant_id = :tid AND whatsapp_phone = :phone LIMIT 1");
            $stmtProf->execute([':tid' => $tid, ':phone' => (string)($o['whatsapp_phone'] ?? '')]);
            $profName = $stmtProf->fetchColumn();
            
            $name = (string)($profName ?: ($o['customer_name'] ?: 'Cliente'));
            $phone = mia_phone_digits((string)($o['whatsapp_phone'] ?? ''));
            $total = mia_money((float)($o['total_amount'] ?? 0));
            $st = (string)($o['status'] ?? '');
            $stLabel = $st === 'pendente' ? 'Pedido' : ($st === 'pago' ? 'Pago' : ($st === 'separando' ? 'Separação' : ($st === 'rota' ? 'Rota' : ($st === 'entregue' ? 'Entregue' : $st))));

            $remoteJid = mia_remote_jid_from_phone((string)($o['whatsapp_phone'] ?? ''));
            $att = $remoteJid !== '' ? ($atendimentoMap[$remoteJid] ?? null) : null;
            $attStatus = is_array($att) ? strtolower((string)($att['status'] ?? 'ativo')) : 'ativo';
            $isHuman = $attStatus !== 'ativo';

            $badge = $isHuman
                ? '<span class="pix-badge confirmed"><i class="fa fa-user"></i> Humano</span>'
                : '<span class="pix-badge pending"><i class="fa fa-magic"></i> IA</span>';

            $html .= '<div class="srch-rc" onclick="fecharBusca();abrirDrawer(' . $orderId . ')">'
                . '<div class="srch-rc-av" style="background:linear-gradient(135deg,#a78bfa,#7c3aed)">' . htmlspecialchars(mb_substr($name, 0, 1)) . '</div>'
                . '<div class="srch-rc-info"><div class="srch-rc-name">' . htmlspecialchars($name) . ' <span style="font-size:10px;font-weight:600;color:#9ca3af">#' . $orderId . '</span></div>'
                . '<div class="srch-rc-det"><span><i class="fa fa-whatsapp" style="color:#25d366"></i> ' . htmlspecialchars($phone) . '</span><span style="color:#d1d5db">·</span><span>' . htmlspecialchars($stLabel) . '</span></div></div>'
                . '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><span class="srch-rc-price">' . htmlspecialchars($total) . '</span>' . $badge . '</div>'
                . '</div>';
        }

        mia_json(['error' => false, 'count' => count($list), 'html' => $html]);
    }

    if ($action === 'delete_order') {
        $orderId = (int)($request->post['order_id'] ?? 0);
        if (!$orderId) {
            throw new Exception('order_id inválido.');
        }
        // Verificar propriedade antes de deletar
        $chk = db()->prepare("SELECT id FROM ai_orders WHERE id = :id AND tenant_id = :tid LIMIT 1");
        $chk->execute([':id' => $orderId, ':tid' => $tid]);
        if (!$chk->fetchColumn()) {
            throw new Exception('Pedido não encontrado ou sem permissão.');
        }
        db()->prepare("DELETE FROM ai_order_items WHERE order_id = :oid")
            ->execute([':oid' => $orderId]);
        db()->prepare("DELETE FROM ai_orders WHERE id = :id AND tenant_id = :tid")
            ->execute([':id' => $orderId, ':tid' => $tid]);
        mia_json(['error' => false, 'message' => 'Pedido excluído.', 'order_id' => $orderId]);
    }

    throw new Exception('Ação inválida.');
} catch (Exception $e) {
    mia_json(['error' => true, 'message' => $e->getMessage()], 422);
}
