<?php
/**
 * ai_checkout_pix_status.php — Polling público de status do pedido PIX
 * Aceita POST com order_id, retorna JSON {success, status}.
 * Sem autenticação — segurança baseada no order_id numérico opaco.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . $sql_details['host'] . ';port=' . ($sql_details['port'] ?? '3306') . ';dbname=' . $sql_details['db'] . ';charset=utf8mb4',
        $sql_details['user'],
        $sql_details['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'db_error']);
    exit;
}

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_order']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT status FROM ai_orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $orderId]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'not_found']);
        exit;
    }

    echo json_encode(['success' => true, 'status' => $row['status']]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'query_error']);
}
