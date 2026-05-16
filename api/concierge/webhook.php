<?php
/**
 * API: api/concierge/webhook.php
 * Roteador principal para as chamadas do n8n (Moda IA).
 * 
 * GET/POST:
 *   loja_id = int
 *   action  = string
 * Header:
 *   X-Concierge-Token: {token}
 */
ob_start();
session_start();
include('../../_init.php');

// Suporte robusto para JSON body (n8n/Agentes IA), mesmo quando o content-type vem inconsistente.
$rawInput = file_get_contents('php://input');
$decodedInput = null;
if ($rawInput !== '' && $rawInput !== false) {
    $trimmedInput = ltrim($rawInput);
    $looksLikeJson = $trimmedInput !== '' && ($trimmedInput[0] === '{' || $trimmedInput[0] === '[');
    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');

    if (stripos($contentType, 'application/json') !== false || $looksLikeJson) {
        $tmp = json_decode($rawInput, true);
        if (is_array($tmp)) {
            $decodedInput = $tmp;
        }
    }
}

if (is_array($decodedInput)) {
    foreach ($decodedInput as $key => $value) {
        // Se já existe no post mas está vazio, sobrescreve com o valor real do JSON.
        $current = $request->post[$key] ?? null;
        $isCurrentEmptyString = is_string($current) && trim($current) === '';
        $isCurrentNull = $current === null;

        if ($isCurrentNull || $isCurrentEmptyString) {
            $request->post[$key] = $value;
        }
    }
}

// Libera o lock de sessão imediatamente, pois chamadas da API são stateless.
if (session_id()) {
    session_write_close();
}

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_plan_gate.php';
require_once DIR_HELPER . 'ai_evolution.php';
require_once DIR_HELPER . 'ai_groups_helper.php';

header('Content-Type: application/json; charset=UTF-8');

function concierge_extract_auth_token(): string
{
    $token = trim((string)($_SERVER['HTTP_X_CONCIERGE_TOKEN'] ?? ''));

    if ($token === '') {
        $token = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    }

    if ($token === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $name => $value) {
            $n = strtolower((string)$name);
            if ($n === 'x-concierge-token' || $n === 'authorization') {
                $token = trim((string)$value);
                break;
            }
        }
    }

    if (stripos($token, 'Bearer ') === 0) {
        $token = trim(substr($token, 7));
    }

    if ($token === '') {
        $token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
    }

    return $token;
}

try {
    $tid    = (int)($request->get['loja_id'] ?? $request->post['loja_id'] ?? 0);
    $action = $request->get['action'] ?? $request->post['action'] ?? '';
    $token  = concierge_extract_auth_token();

    if (!$tid) {
        throw new Exception('loja_id não informado.');
    }

    // Validar token da loja
    $stmt = db()->prepare('SELECT ai_webhook_token FROM stores WHERE store_id = :tid');
    $stmt->execute([':tid' => $tid]);
    $storedToken = $stmt->fetchColumn();

    if (!$storedToken || !hash_equals($storedToken, $token)) {
        http_response_code(401);
        throw new Exception('Token de autenticação inválido.');
    }

    // Verificar se o plano da loja permite AI e consumir chamada (FASE T4)
    $consume = ai_consume_call($tid);
    if (!$consume['allowed']) {
        http_response_code(402); // Payment Required
        echo json_encode([
            'error'   => true, 
            'blocked' => true,
            'reason'  => $consume['reason'],
            'message' => 'Limite de chamadas IA atingido. Adquira mais tokens ou faça upgrade.'
        ]);
        exit;
    }

    // Roteamento
    switch ($action) {
        case 'buscar_produto': {
            // Ler parâmetros estruturados (podem vir via POST ou GET)
            $q     = trim((string)($request->post['q']     ?? $request->get['q']     ?? '')) ?: null;
            $color = trim((string)($request->post['color'] ?? $request->get['color'] ?? '')) ?: null;
            $size  = trim((string)($request->post['size']  ?? $request->get['size']  ?? '')) ?: null;
            $tags  = trim((string)($request->post['tags']  ?? $request->get['tags']  ?? '')) ?: null;
            $sku   = trim((string)($request->post['sku']   ?? $request->get['sku']   ?? '')) ?: null;
            $limit = max(1, min((int)($request->post['limit'] ?? $request->get['limit'] ?? 5), 10));

            // Compatibilidade retroativa: se vier 'query' (formato antigo), usar como q
            $legacyQuery = trim((string)($request->post['query'] ?? $request->get['query'] ?? ''));
            if ($q === null && $legacyQuery !== '') {
                $q = $legacyQuery;
            }

            // Se vier apenas texto livre (q/query), usar parser legado para separar q/tags/cor/tamanho.
            // Isso preserva expansão de tags via glossário e evita falhas em consultas compostas (ex.: "vestido farm").
            if ($color || $size || $tags || $sku) {
                $searchResult = ai_evolution_search_structured($tid, $q, $color, $size, $tags, $sku, $limit);
            } else {
                $searchResult = ai_evolution_search_legacy_query($tid, (string)($q ?? ''), $limit);
            }

            // Log de miss para relatório de demanda reprimida
            if (!$searchResult['found']) {
                $phone = (string)($request->post['phone'] ?? $request->get['phone'] ?? '');
                ai_evolution_log_search_miss(
                    $tid,
                    implode(' | ', array_filter([$q, $color, $size, $tags, $sku])),
                    array_filter(['q'=>$q,'color'=>$color,'size'=>$size,'tags'=>$tags,'sku'=>$sku]),
                    [],
                    $phone
                );
            }

            // Invalidação de cache se necessário (snapshot agora conta variantes)
            // snapshot cache ttl é curto, mas se quiser forçar:
            // ai_evolution_invalidate_snapshot_cache($tid);


            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($searchResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        case 'buscar_tags':
        case 'tags_categoria':
            require_once __DIR__ . '/webhook_tags_categoria.php';
            handle_tags_categoria(db(), $tid, ['get' => $request->get, 'post' => $request->post]);
            break;
        case 'perfil_cliente':
            require 'perfil_cliente.php';
            break;
        case 'criar_pedido':
            require 'criar_pedido.php';
            break;
        case 'confirmar_pagamento':
            require 'confirmar_pagamento.php';
            ai_evolution_invalidate_snapshot_cache($tid);
            break;
        case 'contexto_conversa':
        case 'conversa_contexto':
            require 'contexto_conversa.php';
            break;
        case 'status_atendimento':
        case 'conversa_ia_status':
            require 'status_atendimento.php';
            break;
        case 'pix_status':
        case 'pix_confirmacao':
            require 'pix_status.php';
            break;
        case 'pedido_itens':
        case 'pedido_itens_update':
            require 'pedido_itens.php';
            ai_evolution_invalidate_snapshot_cache($tid);
            break;
        case 'resumo_conversa':
        case 'conversa_resumo':
            require 'resumo_conversa.php';
            break;
        case 'kanban_card':
        case 'kanban_mover':
            require 'kanban_card.php';
            break;
        case 'payment_config':
            $provider = (string)ai_get_setting('ai_payment_provider', 'mercadopago', $tid);
            $pixKeys = (string)ai_get_setting('ai_pix_keys_json', '[]', $tid);
            $decoded = json_decode($pixKeys, true);
            if (!is_array($decoded)) $decoded = [];
            echo json_encode([
                'error' => false,
                'provider' => $provider,
                'pix_keys' => $decoded,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        case 'groups_list':
            if (!ai_groups_plan_is_enabled($tid)) {
                throw new Exception('Módulo de grupos indisponível no plano.');
            }
            $includeInactive = (int)($request->post['include_inactive'] ?? $request->get['include_inactive'] ?? 0);
            $groups = ai_get_concierge_groups($tid, $includeInactive !== 1);
            echo json_encode([
                'error' => false,
                'message' => 'OK',
                'data' => [
                    'groups' => $groups,
                    'stats' => ai_get_groups_stats($tid),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        case 'campaign_create':
            if (!ai_groups_plan_is_enabled($tid)) {
                throw new Exception('Módulo de grupos indisponível no plano.');
            }
            $groupIds = $request->post['group_ids'] ?? $request->get['group_ids'] ?? [];
            if (!is_array($groupIds)) {
                $tmp = json_decode((string)$groupIds, true);
                $groupIds = is_array($tmp) ? $tmp : [];
            }
            $payload = [
                'title' => trim((string)($request->post['title'] ?? $request->get['title'] ?? 'Campanha IA')),
                'content' => trim((string)($request->post['content'] ?? $request->get['content'] ?? '')),
                'status' => trim((string)($request->post['status'] ?? $request->get['status'] ?? 'draft')),
                'product_id' => (int)($request->post['product_id'] ?? $request->get['product_id'] ?? 0),
                'media_url' => trim((string)($request->post['media_url'] ?? $request->get['media_url'] ?? '')),
                'scheduled_at' => trim((string)($request->post['scheduled_at'] ?? $request->get['scheduled_at'] ?? '')),
                'group_ids' => $groupIds,
                'created_by' => 0,
            ];
            $campaignId = ai_create_concierge_campaign($tid, $payload);
            if ($campaignId <= 0) {
                throw new Exception('Falha ao criar campanha.');
            }
            $sanitizedGroups = ai_groups_sanitize_group_ids($tid, $groupIds);
            if (!empty($sanitizedGroups)) {
                ai_upsert_campaign_broadcast_targets($tid, $campaignId, $sanitizedGroups);
            }
            if ($payload['scheduled_at'] !== '') {
                ai_schedule_concierge_campaign($tid, $campaignId, $payload['scheduled_at'], 0);
            }
            if ((int)($request->post['send_now'] ?? $request->get['send_now'] ?? 0) === 1) {
                ai_dispatch_concierge_campaign_now($tid, $campaignId, 0);
            }
            echo json_encode([
                'error' => false,
                'message' => 'Campanha criada.',
                'data' => ['campaign' => ai_get_concierge_campaign($tid, $campaignId)],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        case 'campaign_send_now':
            if (!ai_groups_plan_is_enabled($tid)) {
                throw new Exception('Módulo de grupos indisponível no plano.');
            }
            $campaignId = (int)($request->post['campaign_id'] ?? $request->get['campaign_id'] ?? 0);
            if ($campaignId <= 0) {
                throw new Exception('campaign_id não informado.');
            }
            $targets = ai_get_campaign_targets($tid, $campaignId);
            $limitCheck = ai_check_groups_broadcast_limit($tid, max(1, count($targets)));
            if (empty($limitCheck['ok'])) {
                http_response_code(402);
                echo json_encode([
                    'error' => true,
                    'message' => (string)($limitCheck['message'] ?? 'Limite mensal atingido.'),
                    'data' => ['limit' => $limitCheck],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            if (!ai_dispatch_concierge_campaign_now($tid, $campaignId, 0)) {
                throw new Exception('Falha ao disparar campanha.');
            }
            echo json_encode([
                'error' => false,
                'message' => 'Disparo solicitado.',
                'data' => ['campaign' => ai_get_concierge_campaign($tid, $campaignId)],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        case 'campaign_status_update':
            if (!ai_groups_plan_is_enabled($tid)) {
                throw new Exception('Módulo de grupos indisponível no plano.');
            }
            $campaignId = (int)($request->post['campaign_id'] ?? $request->get['campaign_id'] ?? 0);
            $groupId = (int)($request->post['group_id'] ?? $request->get['group_id'] ?? 0);
            $status = trim((string)($request->post['status'] ?? $request->get['status'] ?? 'pending'));
            $externalMessageId = trim((string)($request->post['external_message_id'] ?? $request->get['external_message_id'] ?? ''));
            $errorMessage = trim((string)($request->post['error_message'] ?? $request->get['error_message'] ?? ''));
            if ($campaignId <= 0 || $groupId <= 0) {
                throw new Exception('campaign_id/group_id obrigatórios.');
            }
            if (!ai_mark_broadcast_status($tid, $campaignId, $groupId, $status, $externalMessageId, $errorMessage)) {
                throw new Exception('Falha ao persistir status.');
            }
            echo json_encode([
                'error' => false,
                'message' => 'Status atualizado.',
                'data' => ['summary' => ai_get_campaign_delivery_summary($tid, $campaignId)],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        case 'gerar_pagamento':
            $orderId = (int)($request->post['order_id'] ?? $request->get['order_id'] ?? 0);
            if (!$orderId) {
                throw new Exception('order_id não informado.');
            }
            $st = db()->prepare("SELECT id, payment_method, payment_ref, payment_link FROM ai_orders WHERE tenant_id = :tid AND id = :id LIMIT 1");
            $st->execute([':tid' => $tid, ':id' => $orderId]);
            $o = $st->fetch(PDO::FETCH_ASSOC);
            if (!$o) {
                throw new Exception('Pedido não encontrado.');
            }
            $pm = (string)($o['payment_method'] ?? 'pix');
            $pref = (string)($o['payment_ref'] ?? '');
            $plink = (string)($o['payment_link'] ?? '');
            if ($pref === '' || $plink === '') {
                $pref = strtoupper($pm) . '-' . $orderId . '-' . substr(bin2hex(random_bytes(6)), 0, 12);
                $plink = rtrim(ROOT_URL, '/') . '/admin/concierge_pix.php?order_id=' . $orderId;
                db()->prepare("UPDATE ai_orders SET payment_ref = :pref, payment_link = :plink, updated_at = NOW() WHERE tenant_id = :tid AND id = :id LIMIT 1")
                    ->execute([':pref' => $pref, ':plink' => $plink, ':tid' => $tid, ':id' => $orderId]);
            }
            echo json_encode([
                'error' => false,
                'order_id' => $orderId,
                'payment_method' => $pm,
                'payment_ref' => $pref,
                'payment_link' => $plink,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        default:
            throw new Exception("Ação '{$action}' não implementada.");
    }

} catch (Exception $e) {
    if (http_response_code() === 200) {
        http_response_code(422);
    }
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
