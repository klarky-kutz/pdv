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

    // Aceita URL enviada direto do formulário (sem precisar salvar antes)
    $target_url = trim((string)($request->post['webhook_url'] ?? ''));
    if ($target_url === '') {
        $target_url = ai_get_setting('ai_webhook_target_url', '', $tid);
    }

    if (empty($target_url)) {
        throw new Exception('URL do Webhook de destino não configurada.');
    }

    $payload = [
        'event' => 'test_webhook',
        'timestamp' => date('Y-m-d H:i:s'),
        'store_id' => $tid,
        'message' => 'Este é um webhook de teste enviado do ModernPOS Moda IA.',
        'data' => [
            'sofia_name' => ai_get_setting('ai_name', 'Sofia', $tid),
            'personality' => ai_get_setting('ai_personality', '', $tid)
        ]
    ];

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
        'msg' => 'Webhook de teste enviado!',
        'http_code' => $http_code,
        'response' => mb_substr($response, 0, 500)
    ]);

} catch (\Throwable $e) {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(422);
    echo json_encode(['errorMsg' => $e->getMessage()]);
}
