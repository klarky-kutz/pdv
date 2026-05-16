<?php
/**
 * AJAX: _inc/ai_token_packages_list.php
 * Retorna lista de pacotes de tokens ativos.
 */
ob_start();
session_start();
include('../_init.php');

header('Content-Type: application/json; charset=UTF-8');

require_once DIR_HELPER . 'ai_tokens.php';

try {
    $packages = ai_get_token_packages();
    echo json_encode([
        'success' => true,
        'packages' => $packages
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
