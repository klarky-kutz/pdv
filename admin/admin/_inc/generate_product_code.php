<?php
/**
 * Endpoint simples para gerar código de produto sequencial.
 *
 * Regra:
 * - Busca o MAIOR p_code numérico que começa com o prefixo (default: 7898)
 * - Retorna (maior + 1)
 *
 * Importante:
 * - Não usa mais a tabela product_code_sequence (evita 500 caso a tabela não exista).
 * - Não calcula dígito verificador; é somente +1 conforme solicitado.
 */

session_start();
include("../_init.php");

header('Content-Type: application/json');

if (!is_loggedin()) {
    http_response_code(401);
    echo json_encode(array(
        'success' => false,
        'error' => 'Não autorizado. Faça login para continuar.',
    ));
    exit;
}

try {
    if (user_group_id() != 1 && !has_permission('access', 'create_product')) {
        throw new Exception(trans('error_create_permission'));
    }

    $prefix = isset($request->post['prefix']) ? (string)$request->post['prefix'] : '7898';
    $prefix = preg_replace('/\D+/', '', $prefix);
    if ($prefix === '') {
        $prefix = '7898';
    }

    // Padrão: 13 dígitos (EAN13-like), mas sem check digit
    $target_len = 13;
    if (strlen($prefix) > $target_len) {
        $target_len = strlen($prefix);
    }

    // Pega o maior código numérico com esse prefixo
    $like = $prefix . '%';
    $stmt = db()->prepare("SELECT MAX(CAST(`p_code` AS UNSIGNED)) AS max_code FROM `products` WHERE `p_code` REGEXP '^[0-9]+$' AND `p_code` LIKE ?");
    $stmt->execute(array($like));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $max_code = isset($row['max_code']) ? (int)$row['max_code'] : 0;

    if ($max_code <= 0) {
        // começa em prefix + zeros, depois +1
        $base = $prefix . str_repeat('0', max(0, $target_len - strlen($prefix)));
        $next = (int)$base + 1;
    } else {
        $next = $max_code + 1;
    }

    // Garante que não repete código já existente (evita colisões em casos raros)
    $tries = 0;
    while ($tries < 50) {
        $tries++;
        $check = db()->prepare("SELECT 1 FROM `products` WHERE `p_code` = ? LIMIT 1");
        $check->execute(array((string)$next));
        if (!$check->fetchColumn()) {
            break;
        }
        $next++;
    }

    echo json_encode(array(
        'success' => true,
        'code' => (string)$next,
        'prefix' => $prefix,
        'max_code' => (string)$max_code,
    ));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage(),
    ));
}
