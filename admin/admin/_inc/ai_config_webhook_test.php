<?php
/**
 * AJAX: ai_config_webhook_test.php
 * Envia um webhook de teste para a URL configurada.
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
    $tid = ai_tenant_id();
    $webhookType = strtolower(trim((string)($request->post['webhook_type'] ?? 'conversation')));
    if (!in_array($webhookType, ['conversation', 'campaign', 'status', 'suggestions'], true)) {
        $webhookType = 'conversation';
    }

    // Aceita URL enviada direto do formulário (sem precisar salvar antes)
    $target_url = trim((string)($request->post['webhook_url'] ?? ''));
    if ($target_url === '') {
        if ($webhookType === 'campaign') {
            $settingKey = 'ai_groups_dispatch_webhook_url';
        } elseif ($webhookType === 'status') {
            $settingKey = 'ai_status_dispatch_webhook_url';
        } elseif ($webhookType === 'suggestions') {
            $settingKey = 'ai_groups_suggestions_webhook_url';
        } else {
            $settingKey = 'ai_webhook_conversation_url';
        }
        $target_url = ai_get_setting($settingKey, '', $tid);
        if ($target_url === '' && $webhookType === 'conversation') {
            $target_url = ai_get_setting('ai_webhook_target_url', '', $tid);
        }
    }

    if (empty($target_url)) {
        throw new Exception('URL do Webhook de destino não configurada.');
    }

    if ($webhookType === 'suggestions') {
        $payload = [
            'event' => 'test_suggestions',
            'webhook_type' => $webhookType,
            'timestamp' => date('Y-m-d H:i:s'),
            'tenant_id' => $tid,
            'message' => 'Este é um webhook de teste para sugestões de campanhas IA.',
            'products' => [
                [
                    'id' => 999,
                    'nome' => 'Produto Teste - Camisa Polo',
                    'preco' => 99.90,
                    'descricao' => 'Camisa polo de algodão premium, confortável e elegante.',
                    'categoria' => [
                        'id' => 1,
                        'nome' => 'Camisetas'
                    ],
                    'tamanhos' => ['P', 'M', 'G', 'GG'],
                    'cores' => ['Preto', 'Branco', 'Azul'],
                    'sku' => 'CMP-999-1',
                    'media_urls' => [
                        'https://via.placeholder.com/400x400?text=Produto+Teste'
                    ]
                ]
            ]
        ];
    } else {
        $payload = [
            'event' => 'test_webhook',
            'webhook_type' => $webhookType,
            'timestamp' => date('Y-m-d H:i:s'),
            'store_id' => $tid,
            'message' => 'Este é um webhook de teste enviado do ModernPOS Moda IA.',
            'data' => [
                'sofia_name' => ai_get_setting('ai_name', 'Sofia', $tid),
                'personality' => ai_get_setting('ai_personality', '', $tid)
            ]
        ];
    }

    $ch = curl_init($target_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-ModernPOS-Test: true'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("Erro de conexão: $error");
    }

    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode([
        'msg' => 'Webhook de teste enviado (' . $webhookType . ')!',
        'http_code' => $http_code,
        'response' => mb_substr($response, 0, 500),
        'webhook_type' => $webhookType,
    ]);

} catch (\Throwable $e) {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(422);
    echo json_encode(['errorMsg' => $e->getMessage()]);
}
