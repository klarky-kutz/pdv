<?php
ob_start();
session_start();
include __DIR__.'/_init.php';

require_once DIR_HELPER . 'ai_concierge.php';

$tenantId = ai_tenant_id();
$newNumber = '5527988852332';
echo "Atualizando ai_whatsapp_number para tenant $tenantId para: $newNumber<br>";
ai_save_setting('ai_whatsapp_number', $newNumber, $tenantId);
echo "Feito! Verificando o valor agora:<br>";
$currentValue = ai_get_setting('ai_whatsapp_number', '', $tenantId);
echo "Valor atual de ai_whatsapp_number: " . var_export($currentValue, true);
?>