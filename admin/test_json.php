<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();
session_start();
include('_init.php');

require_once DIR_HELPER . 'ai_groups_helper.php';

ob_end_clean();
header('Content-Type: application/json; charset=UTF-8');

$testResponse = ai_groups_response(false, 'Teste OK', ['test' => 'value']);
$json = json_encode($testResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

echo $json;
exit;
