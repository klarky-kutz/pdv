<?php
/**
 * API: api/concierge/resumo_conversa.php
 * Lê e salva o resumo da conversa WhatsApp de um cliente (Moda IA).
 *
 * GET:
 *   - phone      (string, ex: 5511999999999) — ou
 *   - remote_jid (string, ex: 5511999999999@s.whatsapp.net) — ou
 *   - order_id   (int)
 *
 * POST:
 *   - summary    (string, obrigatório) — texto do resumo
 *   - phone      (string) — ou
 *   - remote_jid (string) — ou
 *   - order_id   (int)
 *
 * O resumo é persistido em dois locais:
 *   1. Campo `notes` do pedido ativo/informado (lido primeiro por build_conversation_summary)
 *   2. Campo `conversation_summary` de ai_chat_profiles (quando a coluna existir)
 */

if (!isset($tid)) {
    exit; // Apenas via webhook.php
}

require_once DIR_HELPER . 'ai_evolution.php';

/* ── helpers locais ──────────────────────────────────────────────────── */

function rc_resolve_phone(string $phoneOrJid): string
{
    return ai_evolution_number_from_jid($phoneOrJid);
}

/**
 * Obtém o pedido ativo (pendente/pago/separando/rota) mais recente do contato.
 */
function rc_fetch_active_order(int $tenantId, string $phone): ?array
{
    if ($phone === '') {
        return null;
    }

    $candidates = ai_evolution_phone_candidates($phone);
    if (empty($candidates)) {
        return null;
    }

    $in = implode(',', array_fill(0, count($candidates), '?'));

    try {
        $stmt = db()->prepare("
            SELECT id, whatsapp_phone, customer_name, status, total_amount,
                   payment_method, notes, created_at, updated_at
            FROM ai_orders
            WHERE tenant_id = ?
              AND whatsapp_phone IN ($in)
            ORDER BY (status IN ('pendente','pago','separando','rota')) DESC,
                     updated_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute(array_merge([$tenantId], $candidates));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Salva resumo em `notes` do pedido informado (ou ativo).
 */
function rc_save_to_order(int $tenantId, int $orderId, string $summary): bool
{
    try {
        $stmt = db()->prepare("
            UPDATE ai_orders
            SET notes = :notes, updated_at = NOW()
            WHERE id = :oid AND tenant_id = :tid
            LIMIT 1
        ");
        $stmt->execute([':notes' => $summary, ':oid' => $orderId, ':tid' => $tenantId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Salva resumo em `ai_chat_profiles.conversation_summary` (se coluna existir).
 */
function rc_save_to_profile(int $tenantId, string $phone, string $summary): void
{
    if ($phone === '') {
        return;
    }

    try {
        // Verificar se a coluna existe (migration opcional)
        $check = db()->query("SHOW COLUMNS FROM ai_chat_profiles LIKE 'conversation_summary'");
        if (!$check || $check->rowCount() === 0) {
            // Tentar criar a coluna automaticamente
            db()->exec("ALTER TABLE ai_chat_profiles ADD COLUMN conversation_summary TEXT DEFAULT NULL AFTER preferences_json");
        }

        $candidates = ai_evolution_phone_candidates($phone);
        if (empty($candidates)) {
            return;
        }

        $in = implode(',', array_fill(0, count($candidates), '?'));
        $stmt = db()->prepare("
            UPDATE ai_chat_profiles
            SET conversation_summary = ?,
                last_interaction = NOW()
            WHERE tenant_id = ? AND whatsapp_phone IN ($in)
            LIMIT 1
        ");
        $stmt->execute(array_merge([$summary, $tenantId], $candidates));
    } catch (Exception $e) {
        // Coluna não existe e não foi possível criar — ignorar silenciosamente
    }
}

/* ── main ────────────────────────────────────────────────────────────── */

try {
    $orderId   = (int)($request->get['order_id']   ?? $request->post['order_id']   ?? 0);
    $phone     = trim((string)($request->get['phone']      ?? $request->post['phone']      ?? ''));
    $remoteJid = trim((string)($request->get['remote_jid'] ?? $request->post['remote_jid'] ?? $request->get['jid'] ?? $request->post['jid'] ?? ''));

    // Normalizar telefone
    $resolvedPhone = '';
    if ($remoteJid !== '') {
        $resolvedPhone = rc_resolve_phone($remoteJid);
    } elseif ($phone !== '') {
        $resolvedPhone = rc_resolve_phone($phone);
    }

    if ($orderId <= 0 && $resolvedPhone === '') {
        // Fallback: Tentar pegar summary do POST se os outros campos estiverem lá mas o sistema não os mapeou
        throw new Exception('Informe phone, remote_jid ou order_id.');
    }

    // ── GET ──────────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $order = null;
        if ($orderId > 0) {
            $stmt = db()->prepare("
                SELECT id, whatsapp_phone, customer_name, status, total_amount,
                       payment_method, notes, created_at, updated_at
                FROM ai_orders
                WHERE tenant_id = :tid AND id = :oid
                LIMIT 1
            ");
            $stmt->execute([':tid' => $tid, ':oid' => $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($order && $resolvedPhone === '') {
                $resolvedPhone = rc_resolve_phone((string)$order['whatsapp_phone']);
            }
        } else {
            $order = rc_fetch_active_order($tid, $resolvedPhone);
        }

        // Perfil do cliente
        $profile = $resolvedPhone !== ''
            ? ai_evolution_get_customer_memory($tid, $resolvedPhone . '@s.whatsapp.net')
            : null;

        // Resumo: prioridade do campo notes do pedido, depois profile, depois gerado
        $summary = '';
        if ($order) {
            $summary = trim((string)($order['notes'] ?? ''));
        }
        if ($summary === '' && is_array($profile)) {
            $summary = trim((string)($profile['conversation_summary'] ?? ''));
        }
        if ($summary === '') {
            $summary = ai_evolution_build_conversation_summary($order, $profile);
        }

        echo json_encode([
            'error'    => false,
            'phone'    => $resolvedPhone,
            'order_id' => $order ? (int)$order['id'] : null,
            'status'   => $order ? (string)$order['status'] : null,
            'summary'  => $summary,
            'profile'  => $profile,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ── POST ─────────────────────────────────────────────────────────────
    $summary = trim((string)($request->post['summary'] ?? $request->get['summary'] ?? ''));
    if ($summary === '') {
        throw new Exception('O campo summary não pode estar vazio.');
    }

    // Limitar tamanho (proteção contra payloads gigantes)
    if (mb_strlen($summary, 'UTF-8') > 4000) {
        $summary = mb_substr($summary, 0, 4000, 'UTF-8');
    }

    // Localizar pedido alvo
    $targetOrderId = $orderId;
    if ($targetOrderId <= 0 && $resolvedPhone !== '') {
        $activeOrder = rc_fetch_active_order($tid, $resolvedPhone);
        $targetOrderId = $activeOrder ? (int)$activeOrder['id'] : 0;
    } else {
        // Validar se o order_id pertence ao tenant
        if ($targetOrderId > 0) {
            $check = db()->prepare("SELECT id FROM ai_orders WHERE id = :oid AND tenant_id = :tid LIMIT 1");
            $check->execute([':oid' => $targetOrderId, ':tid' => $tid]);
            if (!$check->fetchColumn()) {
                throw new Exception('Pedido não encontrado.');
            }
        }
    }

    $savedOrder   = false;
    $savedProfile = false;

    if ($targetOrderId > 0) {
        $savedOrder = rc_save_to_order($tid, $targetOrderId, $summary);
        // Obter telefone do pedido se não tínhamos
        if ($resolvedPhone === '') {
            $stmtPhone = db()->prepare("SELECT whatsapp_phone FROM ai_orders WHERE id = :oid AND tenant_id = :tid LIMIT 1");
            $stmtPhone->execute([':oid' => $targetOrderId, ':tid' => $tid]);
            $rawPhone = (string)($stmtPhone->fetchColumn() ?? '');
            $resolvedPhone = rc_resolve_phone($rawPhone);
        }
    }

    if ($resolvedPhone !== '') {
        rc_save_to_profile($tid, $resolvedPhone, $summary);
        $savedProfile = true;
    }

    if (!$savedOrder && !$savedProfile) {
        throw new Exception('Nenhum pedido ativo ou perfil encontrado para salvar o resumo.');
    }

    echo json_encode([
        'error'        => false,
        'message'      => 'Resumo salvo com sucesso.',
        'phone'        => $resolvedPhone,
        'order_id'     => $targetOrderId > 0 ? $targetOrderId : null,
        'saved_order'  => $savedOrder,
        'saved_profile'=> $savedProfile,
        'summary'      => $summary,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
