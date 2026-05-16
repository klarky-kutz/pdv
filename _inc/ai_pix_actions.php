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

function mia_money(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

try {
    $tid = ai_tenant_id();
    $action = (string)($request->post['action'] ?? $request->get['action'] ?? '');

    if ($action === 'list') {
        $filter = (string)($request->get['filter'] ?? 'pendentes');
        $search = trim((string)($request->get['search'] ?? ''));

        $stats = [
            'pendentes' => 0,
            'confirmados' => 0,
            'cancelados' => 0,
            'pendentes_valor' => 0.0,
        ];
        try {
            $st = db()->prepare("
                SELECT
                    SUM(status = 'pendente') AS pendentes,
                    SUM(status IN ('pago','separando','rota','entregue')) AS confirmados,
                    SUM(status = 'cancelado') AS cancelados,
                    COALESCE(SUM(CASE WHEN status = 'pendente' THEN total_amount ELSE 0 END), 0) AS pendentes_valor
                FROM ai_orders
                WHERE tenant_id = :tid AND payment_method = 'pix'
            ");
            $st->execute([':tid' => $tid]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats['pendentes'] = (int)($row['pendentes'] ?? 0);
            $stats['confirmados'] = (int)($row['confirmados'] ?? 0);
            $stats['cancelados'] = (int)($row['cancelados'] ?? 0);
            $stats['pendentes_valor'] = (float)($row['pendentes_valor'] ?? 0);
        } catch (Exception $e) {
        }

        $where = ['tenant_id = :tid', "payment_method = 'pix'"];
        $params = [':tid' => $tid];

        if ($filter === 'pendentes') $where[] = "status = 'pendente'";
        if ($filter === 'confirmados') $where[] = "status IN ('pago','separando','rota','entregue')";
        if ($filter === 'cancelados') $where[] = "status = 'cancelado'";

        if ($search !== '') {
            $where[] = "(customer_name LIKE :s1 OR whatsapp_phone LIKE :s2 OR id = :sid)";
            $params[':s1'] = '%' . $search . '%';
            $params[':s2'] = '%' . $search . '%';
            $params[':sid'] = (int)$search;
        }

        $stmt = db()->prepare("
            SELECT o.id,
                   COALESCE(
                     (SELECT cp.name FROM ai_chat_profiles cp
                      WHERE cp.tenant_id = o.tenant_id
                        AND (cp.whatsapp_phone = o.whatsapp_phone
                          OR cp.whatsapp_phone = CONCAT('+', o.whatsapp_phone)
                          OR cp.whatsapp_phone = CONCAT(o.whatsapp_phone, '@s.whatsapp.net')
                          OR cp.whatsapp_phone = REPLACE(o.whatsapp_phone, '@s.whatsapp.net', '')
                          OR REPLACE(cp.whatsapp_phone, '+', '') = REPLACE(o.whatsapp_phone, '+', ''))
                      LIMIT 1),
                     o.customer_name
                   ) AS customer_name,
                   o.whatsapp_phone, o.status, o.total_amount, o.payment_ref, o.paid_at, o.created_at
            FROM ai_orders o
            WHERE " . implode(' AND ', $where) . "
            ORDER BY o.id DESC
            LIMIT 200
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html = '';
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $name = trim((string)($r['customer_name'] ?? 'Cliente'));
            $phone = preg_replace('/\D+/', '', (string)($r['whatsapp_phone'] ?? ''));
            $total = mia_money((float)($r['total_amount'] ?? 0));
            $status = (string)($r['status'] ?? '');

            $badge = $status === 'pendente'
                ? '<span class="badge badge-pend"><i class="fa fa-clock-o"></i> Pendente</span>'
                : ($status === 'cancelado'
                    ? '<span class="badge badge-rej"><i class="fa fa-times-circle"></i> Cancelado</span>'
                    : '<span class="badge badge-auto"><i class="fa fa-check-circle"></i> Confirmado</span>');

            $actions = $status === 'pendente'
                ? '<button class="btn btn-success btn-sm" onclick="confirmarPix(' . $id . ')"><i class="fa fa-check"></i> Confirmar</button>'
                    . '<button class="btn btn-danger btn-sm" onclick="cancelarPix(' . $id . ')"><i class="fa fa-times"></i> Cancelar</button>'
                : '<button class="btn btn-secondary btn-sm" onclick="abrirPedido(' . $id . ')"><i class="fa fa-external-link"></i> Ver Pedido</button>';

            $html .= '<tr>'
                . '<td><div class="proof-container" style="display:flex;align-items:center;gap:10px">'
                . '<div class="proof-thumb" onclick="abrirValidar(' . $id . ')"><i class="fa fa-file-image-o"></i></div>';
            
            if (!empty($r['payment_ref']) && strpos($r['payment_ref'], 'storage/') === 0) {
                $html .= '<button class="btn btn-danger btn-sm" style="padding:2px 6px!important" onclick="deleteSingleProof(' . $id . ')" title="Apagar apenas este comprovante"><i class="fa fa-trash"></i></button>';
            }
            
            $html .= '</div></td>'
                . '<td><div class="ord-id">#' . $id . '</div><div class="ord-client"><i class="fa fa-user"></i> ' . htmlspecialchars($name) . '</div><div class="ord-client"><i class="fa fa-whatsapp" style="color:#25d366"></i> ' . htmlspecialchars($phone) . '</div></td>'
                . '<td><div class="val-num">' . htmlspecialchars($total) . '</div><div class="val-sub">' . htmlspecialchars($status) . '</div></td>'
                . '<td>' . date('d/m/Y H:i', strtotime($r['created_at'])) . '</td>'
                . '<td>' . $badge . '</td>'
                . '<td style="text-align:right;white-space:nowrap">' . $actions . '</td>'
                . '</tr>';
        }
        if ($html === '') {
            $html = '<tr><td colspan="4" style="text-align:center;padding:40px 16px;color:#9ca3af">Nenhum registro.</td></tr>';
        }

        mia_json(['error' => false, 'rows_html' => $html, 'count' => count($rows), 'stats' => $stats]);
    }

    if ($action === 'confirm') {
        $orderId = (int)($request->post['order_id'] ?? 0);
        if (!$orderId) throw new Exception('order_id inválido.');

        $stmt = db()->prepare("
            UPDATE ai_orders
            SET status = 'pago', paid_at = NOW(), updated_at = NOW(), moved_by_ia = 0
            WHERE tenant_id = :tid AND id = :id AND status = 'pendente'
            LIMIT 1
        ");
        $stmt->execute([':tid' => $tid, ':id' => $orderId]);
        if ($stmt->rowCount() <= 0) throw new Exception('Não foi possível confirmar (pedido não pendente).');
        // Notificar cliente via WhatsApp sobre confirmação do Pix
        ai_notify_customer_status_change($tid, $orderId, 'pago');
        mia_json(['error' => false, 'order_id' => $orderId]);
    }

    if ($action === 'cancel') {
        $orderId = (int)($request->post['order_id'] ?? 0);
        if (!$orderId) throw new Exception('order_id inválido.');

        $stmt = db()->prepare("
            UPDATE ai_orders
            SET status = 'cancelado', updated_at = NOW(), moved_by_ia = 0
            WHERE tenant_id = :tid AND id = :id
            LIMIT 1
        ");
        $stmt->execute([':tid' => $tid, ':id' => $orderId]);
        if ($stmt->rowCount() <= 0) throw new Exception('Não foi possível cancelar.');
        mia_json(['error' => false, 'order_id' => $orderId]);
    }

    if ($action === 'delete_single_proof') {
        $orderId = (int)($request->post['order_id'] ?? 0);
        if (!$orderId) throw new Exception('ID do pedido inválido.');

        $stmt = db()->prepare("SELECT id, payment_ref FROM ai_orders WHERE tenant_id = :tid AND id = :id LIMIT 1");
        $stmt->execute([':tid' => $tid, ':id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) throw new Exception('Pedido não encontrado.');
        
        $deleted = false;
        if (!empty($order['payment_ref']) && strpos($order['payment_ref'], 'storage/') === 0) {
            $filePath = ROOT . '/' . $order['payment_ref'];
            if (file_exists($filePath) && is_file($filePath)) {
                @unlink($filePath);
                $deleted = true;
            }
            // Limpa a referência no banco mesmo se o arquivo não existir fisicamente
            $upd = db()->prepare("UPDATE ai_orders SET payment_ref = '' WHERE id = :id");
            $upd->execute([':id' => $orderId]);
        }

        mia_json([
            'error' => false, 
            'message' => $deleted ? 'Arquivo de comprovante excluído com sucesso.' : 'Referência removida (arquivo não encontrado).'
        ]);
    }

    if ($action === 'delete_cancelled_proofs') {
        // Busca todos os pedidos cancelados que tenham referência de arquivo
        // No Moda IA, os comprovantes costumam ser enviados via WhatsApp (Evolution API)
        // e salvos em storage/webhook_media/ ou similares se configurado.
        
        // Vamos buscar pedidos cancelados desta loja
        $stmt = db()->prepare("
            SELECT id, payment_ref 
            FROM ai_orders 
            WHERE tenant_id = :tid AND status = 'cancelado' AND payment_ref LIKE 'storage/%'
        ");
        $stmt->execute([':tid' => $tid]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $deletedCount = 0;
        foreach ($orders as $order) {
            $filePath = ROOT . '/' . $order['payment_ref'];
            if (file_exists($filePath) && is_file($filePath)) {
                if (@unlink($filePath)) {
                    // Limpa a referência no banco após deletar o arquivo
                    $upd = db()->prepare("UPDATE ai_orders SET payment_ref = '' WHERE id = :id");
                    $upd->execute([':id' => $order['id']]);
                    $deletedCount++;
                }
            } else {
                // Se o arquivo não existe fisicamente mas tem ref, limpa a ref
                $upd = db()->prepare("UPDATE ai_orders SET payment_ref = '' WHERE id = :id");
                $upd->execute([':id' => $order['id']]);
            }
        }
        
        mia_json([
            'error' => false, 
            'message' => "Processo concluído. {$deletedCount} comprovante(s) físico(s) removido(s)."
        ]);
    }

    throw new Exception('Ação inválida.');
} catch (Exception $e) {
    mia_json(['error' => true, 'message' => $e->getMessage()], 422);
}
