<?php
/**
 * API: api/concierge/evolution_webhook.php
 * Webhook de entrada da Evolution API (v2.x) para o ModernPOS.
 *
 * Fluxo:
 *  1) Recebe evento da Evolution
 *  2) Valida loja_id + token por loja (multi-tenant)
 *  3) Normaliza payload (Inbound)
 *  4) Consulta memória/perfil + catálogo + status de atendimento (Consulta)
 *  5) Encaminha contexto ao n8n
 *  6) Se houver resposta do n8n, envia /message/sendText ou /message/sendMedia (Outbound)
 */
ob_start();
session_start();
include('../../_init.php');

// Libera o lock de sessão imediatamente, pois webhooks são stateless.
// Isso evita deadlocks se o n8n chamar outro script PHP no mesmo servidor.
if (session_id()) {
    session_write_close();
}

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_plan_gate.php';
require_once DIR_HELPER . 'ai_evolution.php';

header('Content-Type: application/json; charset=UTF-8');

function evolution_webhook_response(array $payload, int $statusCode = 200): void
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function evolution_debug_log(int $tid, string $message, array $context = []): void
{
    $path = rtrim((string)ROOT, "/\\") . '/storage/webhook_debug.txt';
    $ts = date('Y-m-d H:i:s');
    $line = $ts . ' | tid=' . $tid . ' | ' . $message;
    if (!empty($context)) {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{"json_encode_error":"' . json_last_error_msg() . '"}';
        }
        $line .= ' | ' . $json;
    }
    $line .= "\n";
    @file_put_contents($path, $line, FILE_APPEND);
}

function evolution_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return [];
    }
    return $decoded;
}

function evolution_header_token(): string
{
    $candidate = '';

    // Prioridade para headers customizados
    if (!empty($_SERVER['HTTP_X_CONCIERGE_TOKEN'])) {
        $candidate = (string)$_SERVER['HTTP_X_CONCIERGE_TOKEN'];
    }
    
    if ($candidate === '' && !empty($_SERVER['HTTP_X_EVOLUTION_STORE_TOKEN'])) {
        $candidate = (string)$_SERVER['HTTP_X_EVOLUTION_STORE_TOKEN'];
    }
    
    if ($candidate === '' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $candidate = (string)$_SERVER['HTTP_AUTHORIZATION'];
    }
    
    if ($candidate === '' && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $candidate = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    
    if ($candidate === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $name => $value) {
            $nameLower = strtolower($name);
            if ($nameLower === 'authorization') {
                $candidate = (string)$value;
                break;
            } elseif ($nameLower === 'x-concierge-token') {
                $candidate = (string)$value;
                break;
            }
        }
    }

    if ($candidate === '' && !empty($_GET['token'])) {
        $candidate = (string)$_GET['token'];
    }
    
    $candidate = trim($candidate);
    if (stripos($candidate, 'Bearer ') === 0) {
        $candidate = trim(substr($candidate, 7));
    }
    return $candidate;
}

function evolution_post_json(string $url, array $payload, array $headers = []): array
{
    $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonBody === false) {
        $jsonBody = '{}';
    }

    if ($url === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'error' => 'targetUrl vazio',
            'raw' => '',
            'json' => [],
            'curl_errno' => 0,
            'curl_error' => 'targetUrl vazio',
            'curl_info' => [],
        ];
    }

    $ch = curl_init($url);
    $mergedHeaders = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $mergedHeaders);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    if (stripos($url, 'https://') === 0) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    $raw = curl_exec($ch);
    $curlErrNo = curl_errno($ch);
    $err = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $info = curl_getinfo($ch);
    curl_close($ch);

    $json = [];
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $json = $decoded;
        }
    }

    return [
        'ok' => $curlErrNo === 0 && $err === '' && $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'error' => $err,
        'raw' => is_string($raw) ? $raw : '',
        'json' => $json,
        'curl_errno' => $curlErrNo,
        'curl_error' => $err,
        'curl_info' => is_array($info) ? $info : [],
    ];
}

try {
    $payload = evolution_read_json_body();
    $tid = (int)($_GET['loja_id'] ?? $_POST['loja_id'] ?? ($payload['loja_id'] ?? 0));

    evolution_debug_log($tid, 'webhook_received', [
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? '',
    ]);
    if ($tid <= 0) {
        throw new Exception('loja_id não informado no webhook da Evolution.');
    }

    $receivedToken = evolution_header_token();
    $storedToken = ai_evolution_store_token($tid);
    
    // Validação de token: aceita o token específico da loja ou o token global do SaaS
    $globalConfig = ai_evolution_global_config();
    $globalToken = $globalConfig['global_token'] ?? '';
    
    $isStoreToken = ($receivedToken !== '' && hash_equals($storedToken, $receivedToken));
    $isGlobalToken = ($receivedToken !== '' && $globalToken !== '' && hash_equals($globalToken, $receivedToken));

    evolution_debug_log($tid, 'token_check', [
        'received_len' => strlen($receivedToken),
        'stored_len' => strlen((string)$storedToken),
        'is_store_token' => $isStoreToken,
        'is_global_token' => $isGlobalToken
    ]);

    if (!$isStoreToken && !$isGlobalToken) {
        evolution_webhook_response([
            'error' => true,
            'message' => 'Token inválido para esta loja.',
        ], 401);
    }

    $gate = ai_check_plan_gate($tid);
    if (!$gate['allowed']) {
        evolution_webhook_response([
            'error' => true,
            'message' => $gate['message'] ?? 'Módulo IA indisponível no plano atual.',
        ], 403);
    }

    $connection = ai_evolution_get_connection($tid);
    $configuredInstance = trim((string)$connection['instance_name']);

    $eventName = ai_evolution_extract_event_name($payload);
    $instanceFromPayload = ai_evolution_extract_instance_name($payload);
    $remoteJidRaw = ai_evolution_extract_remote_jid($payload);
    
    // Resolve LIDs estranhos para números de telefone se disponível
    $remoteJid = ai_evolution_resolve_lid_to_phone($remoteJidRaw, $payload);
    
    $pushName = ai_evolution_extract_push_name($payload);
    $isFromMe = ai_evolution_is_from_me($payload);
    $messageType = ai_evolution_detect_message_type($payload);
    $messageText = ai_evolution_extract_message_text($payload);

    if ($configuredInstance !== '' && $instanceFromPayload !== '' && strcasecmp($configuredInstance, $instanceFromPayload) !== 0) {
        ai_evolution_log_webhook($tid, [
            'instance_name' => $instanceFromPayload,
            'event_name' => $eventName,
            'remote_jid' => $remoteJid,
            'push_name' => $pushName,
            'message_type' => $messageType,
            'status' => 'Erro',
            'error_message' => 'Instância recebida não pertence à loja informada.',
            'payload' => $payload,
            'response' => [],
        ]);

        evolution_webhook_response([
            'error' => true,
            'message' => 'Instância inválida para esta loja.',
        ], 422);
    }

    // Atualiza status de conexão para refletir eventos CONNECTION_UPDATE.
    if (strtoupper($eventName) === 'CONNECTION_UPDATE') {
        $state = (string)(
            ai_evolution_array_path($payload, ['data', 'state'])
            ?? ai_evolution_array_path($payload, ['state'])
            ?? ''
        );
        $updateData = [];
        if ($state !== '') {
            $updateData['ai_evolution_status'] = ai_evolution_status_label($state);
        }
        // Extract and save instance owner number
        $ownerNumber = ai_evolution_extract_instance_number($payload);
        if ($ownerNumber !== '') {
            $updateData['ai_whatsapp_number'] = $ownerNumber;
        }
        if (!empty($updateData)) {
            ai_evolution_save_connection($updateData, $tid);
        }
    }

    $isInboundMessage = strtoupper($eventName) === 'MESSAGES_UPSERT' || $remoteJid !== '' || $messageText !== '';
    if (!$isInboundMessage) {
        ai_evolution_log_webhook($tid, [
            'instance_name' => $instanceFromPayload ?: $configuredInstance,
            'event_name' => $eventName,
            'remote_jid' => $remoteJid,
            'push_name' => $pushName,
            'message_type' => $messageType,
            'status' => 'Sucesso',
            'payload' => $payload,
            'response' => ['processed' => false, 'reason' => 'evento_sem_mensagem'],
        ]);

        evolution_webhook_response([
            'error' => false,
            'processed' => false,
            'reason' => 'Evento recebido sem mensagem de cliente.',
        ]);
    }

    $aiEnabled = (string)ai_get_setting('ai_enabled', '1', $tid);
    if ($aiEnabled !== '1') {
        ai_evolution_log_webhook($tid, [
            'instance_name' => $instanceFromPayload ?: $configuredInstance,
            'event_name' => $eventName,
            'remote_jid' => $remoteJid,
            'push_name' => $pushName,
            'message_type' => $messageType,
            'status' => 'Ignorado',
            'error_message' => 'IA desativada.',
            'payload' => $payload,
            'response' => ['processed' => false, 'reason' => 'ia_desativada'],
        ]);

        evolution_webhook_response([
            'error' => false,
            'processed' => false,
            'reason' => 'IA desativada.',
        ]);
    }

    if ($isFromMe) {
        ai_evolution_log_webhook($tid, [
            'instance_name' => $instanceFromPayload ?: $configuredInstance,
            'event_name' => $eventName,
            'remote_jid' => $remoteJid,
            'push_name' => $pushName,
            'message_type' => $messageType,
            'status' => 'Ignorado',
            'error_message' => 'Mensagem de saída da própria instância.',
            'payload' => $payload,
            'response' => ['processed' => false, 'reason' => 'mensagem_de_saida'],
        ]);

        evolution_webhook_response([
            'error' => false,
            'processed' => false,
            'reason' => 'Mensagem de saída ignorada.',
        ]);
    }

    if (!ai_is_within_schedule($tid)) {
        $offlineMsg = trim((string)ai_get_setting('ai_offline_msg', '', $tid));
        if ($offlineMsg !== '' && $remoteJid !== '') {
            $vars = [
                '{{nome_loja}}' => ai_get_store_name($tid),
                '{{nome_ia}}' => (string)ai_get_setting('ai_name', 'Sofia', $tid),
                '{{horario}}' => date('H:i'),
                '{{cidade}}' => '',
            ];
            $text = trim(ai_apply_template_vars($offlineMsg, $vars));
            if ($text !== '') {
                ai_evolution_send_text($tid, ai_evolution_number_from_jid($remoteJid), $text);
            }
        }

        ai_evolution_log_webhook($tid, [
            'instance_name' => $instanceFromPayload ?: $configuredInstance,
            'event_name' => $eventName,
            'remote_jid' => $remoteJid,
            'push_name' => $pushName,
            'message_type' => $messageType,
            'status' => 'Ignorado',
            'error_message' => 'Fora do horário de atendimento.',
            'payload' => $payload,
            'response' => ['processed' => false, 'reason' => 'fora_do_horario'],
        ]);

        evolution_webhook_response([
            'error' => false,
            'processed' => false,
            'reason' => 'Fora do horário de atendimento.',
        ]);
    }

    $conversationContext = ai_evolution_get_conversation_context($tid, $remoteJid);
    $atendimento = is_array($conversationContext['atendimento'] ?? null)
        ? $conversationContext['atendimento']
        : ai_evolution_get_atendimento_status($tid, $remoteJid);
    $customerMemory = is_array($conversationContext['profile'] ?? null)
        ? $conversationContext['profile']
        : ai_evolution_get_customer_memory($tid, $remoteJid);
    $orderContext = is_array($conversationContext['order'] ?? null) ? $conversationContext['order'] : null;
    $conversationSummary = trim((string)($conversationContext['summary'] ?? ''));
    if ($conversationSummary === '') {
        $conversationSummary = ai_evolution_build_conversation_summary($orderContext, $customerMemory);
    }
    $atendimentoStatusCurrent = (string)($atendimento['status'] ?? 'Ativo');
    $atendimentoStatusNext = strtolower($atendimentoStatusCurrent) === 'manual' ? 'Ativo' : 'Manual';
    // Busca de catálogo removida do webhook — a IA no N8N realiza a busca via
    // Tool (action=buscar_produto) permitindo contextos complexos e multi-critério.

    // ── URL base das ferramentas para o N8N usar como Tools ──────────────────
    $apiBaseUrl = rtrim(ROOT_URL, '/') . '/api/concierge/webhook.php?loja_id=' . $tid . '&action=';

    $normalized = [
        'loja_id' => $tid,
        'token' => $storedToken,
        'instance_name' => $instanceFromPayload ?: $configuredInstance,
        'event' => $eventName,
        'received_at' => date('c'),
        'config' => [
            'ai_name' => (string)ai_get_setting('ai_name', 'Sofia', $tid),
            'ai_personality' => (string)ai_get_setting('ai_personality', '', $tid),
            'ai_greeting' => (string)ai_get_setting('ai_greeting', '', $tid),
            'ai_offline_msg' => (string)ai_get_setting('ai_offline_msg', '', $tid),
            'language' => (string)ai_get_setting('ai_language', 'pt-BR', $tid),
            'max_products' => (int)ai_get_setting('ai_max_products', 3, $tid),
            'max_photos' => (int)ai_get_setting('ai_max_photos', 2, $tid),
            'send_high_res' => (string)ai_get_setting('ai_send_high_res', '1', $tid) === '1',
            'remember_history' => (string)ai_get_setting('ai_remember_history', '1', $tid) === '1',
            'suggest_complementary' => (string)ai_get_setting('ai_suggest_complementary', '1', $tid) === '1',
        ],
        'inbound' => [
            'remoteJid' => $remoteJid,
            'fromMe' => $isFromMe,
            'pushName' => ($isFromMe && $pushName === 'Você') ? '' : $pushName,
            'messageType' => $messageType,
            'messageText' => $messageText,
            'payload' => $payload,
        ],
        'consulta' => [
            'ia_customer_memory' => $customerMemory,
            'ia_status_atendimento' => $atendimento,
            'ia_status_toggle_context' => [
                'remote_jid' => $remoteJid,
                'current_status' => $atendimentoStatusCurrent,
                'next_status' => $atendimentoStatusNext,
                'toggle_endpoint' => $apiBaseUrl . 'conversa_ia_status',
                'toggle_method' => 'POST',
                'toggle_payload_template' => [
                    'remote_jid' => $remoteJid,
                    'status' => $atendimentoStatusNext,
                ],
            ],
            'ia_conversation_summary' => $conversationSummary,
            'ia_order_context' => $orderContext,
            'ia_pix_context' => is_array($orderContext) ? [
                'status' => (string)($orderContext['status'] ?? ''),
                'payment_method' => (string)($orderContext['payment_method'] ?? ''),
                'payment_ref' => (string)($orderContext['payment_ref'] ?? ''),
                'payment_link' => (string)($orderContext['payment_link'] ?? ''),
                'paid_at' => $orderContext['paid_at'] ?? null,
            ] : null,
            // Nota: busca de produtos, resumo e itens do pedido são obtidos
            // diretamente pelo N8N via Tools (ver api_tools abaixo).
        ],
        'api_tools' => [
            'buscar_produto'       => $apiBaseUrl . 'buscar_produto',
            'perfil_cliente'       => $apiBaseUrl . 'perfil_cliente',
            'criar_pedido'         => $apiBaseUrl . 'criar_pedido',
            'pedido_itens'         => $apiBaseUrl . 'pedido_itens',
            'pedido_itens_update'  => $apiBaseUrl . 'pedido_itens_update',
            'pix_status'           => $apiBaseUrl . 'pix_status',
            'pix_confirmacao'      => $apiBaseUrl . 'pix_confirmacao',
            'confirmar_pagamento'  => $apiBaseUrl . 'confirmar_pagamento',
            'status_atendimento'   => $apiBaseUrl . 'status_atendimento',
            'conversa_ia_status'   => $apiBaseUrl . 'conversa_ia_status',
            'contexto_conversa'    => $apiBaseUrl . 'contexto_conversa',
            'conversa_contexto'    => $apiBaseUrl . 'conversa_contexto',
            'resumo_conversa'      => $apiBaseUrl . 'resumo_conversa',
            'conversa_resumo'      => $apiBaseUrl . 'conversa_resumo',
            'kanban_card'          => $apiBaseUrl . 'kanban_card',
            'kanban_mover'         => $apiBaseUrl . 'kanban_mover',
        ],
    ];

    // Se o status for 'Manual', verificar se há pedido ativo para este contato.
    // Sem pedido ativo, o modo Manual fica "preso" pois não aparece no Kanban —
    // auto-resetar para 'Ativo' apenas se o status manual for antigo (ex: > 2h)
    // ou se o lojista explicitamente habilitou o reset. 
    // Por enquanto, vamos remover o auto-reset agressivo que quebra a Pausa manual.
    /*
    if (strtolower($atendimentoStatusCurrent) === 'manual') {
        if (!ai_evolution_has_active_order($tid, $remoteJid)) {
            ai_evolution_set_atendimento_status($tid, $remoteJid, 'Ativo');
            $atendimentoStatusCurrent   = 'Ativo';
            $atendimento['status']      = 'Ativo';
            $atendimentoStatusNext      = 'Manual';
            // Atualizar o contexto no payload já montado
            $normalized['consulta']['ia_status_atendimento']['status']                     = 'Ativo';
            $normalized['consulta']['ia_status_toggle_context']['current_status']          = 'Ativo';
            $normalized['consulta']['ia_status_toggle_context']['next_status']             = 'Manual';
            $normalized['consulta']['ia_status_toggle_context']['toggle_payload_template']['status'] = 'Manual';
        }
    }
    */

    // ── ATUALIZAÇÃO DE PERFIL E CONTADOR DE MENSAGENS ────────────────────────
    $isGroup = (strpos((string)$remoteJid, '@g.us') !== false);

    if ($remoteJid !== '' && !$isGroup) {
        $phoneForProfile = ai_evolution_number_from_jid($remoteJid);
        
        // Se a mensagem for "fromMe" (enviada pela IA), o pushName costuma vir como "Você".
        // Não devemos atualizar o nome do cliente baseado em mensagens de saída.
        $nameToUpdate = $isFromMe ? '' : ($pushName ?: '');

        $stmtProfile = db()->prepare("
            INSERT INTO ai_chat_profiles (tenant_id, whatsapp_phone, name, total_interactions, last_interaction, created_at)
            VALUES (:tid, :phone, :name, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                name = IF(name IS NULL OR name = '', :name3, name),
                total_interactions = total_interactions + 1,
                last_interaction = NOW()
        ");
        $stmtProfile->execute([
            ':tid'   => $tid,
            ':phone' => $phoneForProfile,
            ':name'  => $nameToUpdate,
            ':name3' => $nameToUpdate,
        ]);
    }

    if (strtolower((string)$atendimento['status']) !== 'ativo') {
        ai_evolution_log_webhook($tid, [
            'instance_name' => $normalized['instance_name'],
            'event_name' => $eventName,
            'remote_jid' => $remoteJid,
            'push_name' => $pushName,
            'message_type' => $messageType,
            'status' => 'Ignorado',
            'error_message' => 'Atendimento em modo manual pelo lojista.',
            'payload' => $payload,
            'response' => ['normalized' => $normalized],
        ]);

        evolution_webhook_response([
            'error' => false,
            'processed' => false,
            'reason' => 'Atendimento manual ativo.',
            'normalized' => $normalized,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EARLY ACK: libera o worker da Evolution API imediatamente.
    // PHP continuará executando (n8n + outbound + log) em background,
    // sem travar a fila de mensagens em picos de múltiplos lojistas.
    // ─────────────────────────────────────────────────────────────────────────
    ignore_user_abort(true);
    while (ob_get_level() > 0) ob_end_clean();
    $ackBody = json_encode(['ok' => true, 'processing' => true], JSON_UNESCAPED_UNICODE);
    http_response_code(200);
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Length: ' . strlen($ackBody));
    header('Connection: close');
    echo $ackBody;
    flush();
    if (function_exists('fastcgi_finish_request')) {
        // PHP-FPM: fecha a conexão com o cliente instantaneamente
        fastcgi_finish_request();
    }
    // → A partir daqui a Evolution já recebeu 200 e encerrou sua espera.
    //   O processo PHP segue rodando para chamar o n8n, enviar outbound e logar.

    $n8nResult = null;
    $targetUrl = trim((string)$connection['webhook_target_url']);
    evolution_debug_log($tid, 'n8n_target', [
        'targetUrl' => $targetUrl,
    ]);
    if ($targetUrl !== '') {
        $normalizedPreview = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($normalizedPreview === false) {
            $normalizedPreview = '{"json_encode_error":"' . json_last_error_msg() . '"}';
        }
        evolution_debug_log($tid, 'n8n_request_start', [
            'url' => $targetUrl,
            'payload_keys' => array_keys($normalized),
            'payload_preview' => substr($normalizedPreview, 0, 2000),
        ]);
        $n8nResult = evolution_post_json($targetUrl, $normalized, [
            'X-Concierge-Token: ' . $storedToken,
            'X-Store-Id: ' . $tid,
        ]);
        evolution_debug_log($tid, 'n8n_request_end', [
            'ok' => (bool)($n8nResult['ok'] ?? false),
            'http_code' => (int)($n8nResult['http_code'] ?? 0),
            'curl_errno' => (int)($n8nResult['curl_errno'] ?? 0),
            'curl_error' => (string)($n8nResult['curl_error'] ?? ''),
            'curl_info' => $n8nResult['curl_info'] ?? [],
            'raw_preview' => substr((string)($n8nResult['raw'] ?? ''), 0, 1500),
        ]);
    } else {
        evolution_debug_log($tid, 'n8n_target_empty');
    }

    $replyPayload = is_array($n8nResult['json'] ?? null) ? $n8nResult['json'] : [];
    $replyType = strtolower((string)($replyPayload['replyType'] ?? $replyPayload['reply_type'] ?? ''));
    $replyText = trim((string)($replyPayload['text'] ?? $replyPayload['reply_text'] ?? ''));
    $replyMediaUrl = trim((string)($replyPayload['media'] ?? $replyPayload['media_url'] ?? ''));
    $replyMimeType = trim((string)($replyPayload['mimetype'] ?? 'image/jpeg'));
    $replyFileName = trim((string)($replyPayload['fileName'] ?? $replyPayload['filename'] ?? 'imagem.jpg'));
    $replyCaption = trim((string)($replyPayload['caption'] ?? $replyText));

    if ($replyType === '' && $replyMediaUrl !== '') {
        $replyType = 'media';
    } elseif ($replyType === '' && $replyText !== '') {
        $replyType = 'text';
    }

    $outboundResult = null;
    if ($replyType !== '' && $remoteJid !== '' && $connection['base_url'] !== '' && $connection['global_token'] !== '' && $normalized['instance_name'] !== '') {
        $destinationNumber = ai_evolution_number_from_jid($remoteJid);

        if ($replyType === 'text' && $replyText !== '') {
            $outboundResult = ai_evolution_http_request(
                'POST',
                $connection['base_url'] . '/message/sendText/' . rawurlencode($normalized['instance_name']),
                $connection['global_token'],
                [
                    'number' => $destinationNumber,
                    'text' => $replyText,
                ]
            );
        } elseif ($replyType === 'media' && $replyMediaUrl !== '') {
            $mediaType = strtolower((string)($replyPayload['mediatype'] ?? 'image'));
            $outboundResult = ai_evolution_http_request(
                'POST',
                $connection['base_url'] . '/message/sendMedia/' . rawurlencode($normalized['instance_name']),
                $connection['global_token'],
                [
                    'number' => $destinationNumber,
                    'mediatype' => $mediaType,
                    'mimetype' => $replyMimeType,
                    'caption' => $replyCaption,
                    'media' => $replyMediaUrl,
                    'fileName' => $replyFileName,
                ]
            );
        }
    }

    $status = 'Sucesso';
    $errorMessage = '';
    if ($targetUrl !== '' && is_array($n8nResult) && !$n8nResult['ok']) {
        $status = 'Erro';
        $errorMessage = 'Falha ao consultar n8n (HTTP ' . (int)$n8nResult['http_code'] . ').';
    }
    if (is_array($outboundResult) && !$outboundResult['ok']) {
        $status = 'Erro';
        $errorMessage = 'Falha ao enviar resposta pela Evolution (HTTP ' . (int)$outboundResult['http_code'] . ').';
    }

    ai_evolution_log_webhook($tid, [
        'instance_name' => $normalized['instance_name'],
        'event_name' => $eventName,
        'remote_jid' => $remoteJid,
        'push_name' => $pushName,
        'message_type' => $messageType,
        'status' => $status,
        'error_message' => $errorMessage,
        'payload' => $payload,
        'response' => [
            'normalized' => $normalized,
            'n8n' => $n8nResult,
            'outbound' => $outboundResult,
        ],
    ]);

    // Conexão com a Evolution já foi fechada pelo early ACK acima.
    // O log foi registrado; encerramos o processo silenciosamente.
    exit;
} catch (\Throwable $e) {
    evolution_webhook_response([
        'error' => true,
        'message' => $e->getMessage(),
    ], 422);
}
