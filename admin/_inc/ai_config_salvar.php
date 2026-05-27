<?php
/**
 * AJAX: ai_config_salvar.php
 * Salva as configurações do módulo Moda IA.
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
    
    // Lista de campos permitidos para salvar na tabela ai_settings
    $allowed_keys = [
        'ai_enabled',
        'ai_whatsapp_provider',
        'ai_instance_url',
        'ai_instance_name',
        'ai_whatsapp_number',
        'ai_whatsapp_number_2',
        'ai_notify_store_primary_enabled',
        'ai_notify_store_secondary_enabled',
        'ai_api_key',
        'ai_webhook_target_url',
        'ai_webhook_conversation_url',
        'ai_groups_dispatch_webhook_url',
        'ai_status_dispatch_webhook_url',
        'ai_groups_suggestions_webhook_url',
        'ai_name',
        'ai_personality',
        'ai_greeting',
        'ai_offline_msg',
        'ai_response_delay',
        'ai_max_products',
        'ai_max_photos',
        'ai_limit_concurrent',
        'ai_limit_products',
        'ai_limit_retries',
        'ai_limit_timeout',
        'ai_send_high_res',
        'ai_remember_history',
        'ai_suggest_complementary',
        'ai_24h_mode',
        'ai_schedule_json',
        'ai_language',
        'ai_pix_keys_json',
        'ai_payment_provider',
        'ai_mp_access_token_enc',
        'ai_asaas_api_key_enc',
        'ai_stripe_secret_enc',
        'ai_pix_validity',
        'ai_pix_retry',
        'ai_notify_new_order',
        'ai_notify_payment_confirmed',
        'ai_notify_stock_critical',
        'ai_weekly_report',
        // Notificações WhatsApp ao Cliente
        'ai_notify_customer_enabled',
        'ai_notify_stage_separando',
        'ai_notify_stage_rota',
        'ai_notify_stage_entregue',
        'ai_notify_stage_pago',
        'ai_notify_msg_separando',
        'ai_notify_msg_rota',
        'ai_notify_msg_entregue',
        'ai_notify_msg_pago',
        // Checkout PIX Transparente
        'ai_checkout_nome_empresa',
        'ai_checkout_titular',
        'ai_checkout_cidade',
        'ai_checkout_whatsapp',
        'ai_checkout_minutos',
        'ai_checkout_cor_acento',
        'ai_checkout_msg_pago',
    ];

    foreach ($allowed_keys as $key) {
        if (isset($request->post[$key])) {
            ai_save_setting($key, $request->post[$key], $tid);
        }
    }

    echo json_encode(['msg' => 'Configurações salvas com sucesso!']);

} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['errorMsg' => $e->getMessage()]);
}
