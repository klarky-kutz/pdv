<?php
/**
 * Endpoint de apoio para recuperação de tags por categoria.
 * Uso exclusivo via webhook.php (action=tags_categoria).
 */

require_once __DIR__ . '/helpers/ai_evolution_tags_helper.php';

/**
 * Processa a action tags_categoria e retorna tags reais do catálogo por categoria.
 *
 * @param PDO $pdo Conexão PDO ativa.
 * @param int $loja_id Identificador da loja já autenticada no webhook.
 * @param array $input Estrutura de entrada com chaves get/post.
 * @return void
 * @example
 * handle_tags_categoria(db(), 300, ['get' => $_GET, 'post' => $_POST]);
 */
function handle_tags_categoria(PDO $pdo, int $loja_id, array $input): void
{
    try {
        $get = (array)($input['get'] ?? []);
        $post = (array)($input['post'] ?? []);

        $rawCategoria = (string)($post['q'] ?? $get['q'] ?? '');
        $categoria = trim(strip_tags($rawCategoria));
        if ($categoria !== '') {
            $categoria = mb_substr($categoria, 0, 100, 'UTF-8');
        } else {
            $categoria = null;
        }

        $rawLimit = $post['limit'] ?? $get['limit'] ?? 60;
        $limit = (int)$rawLimit;
        if ($limit <= 0) {
            $limit = 60;
        }
        $limit = max(1, min($limit, 100));

        $data = get_tags_by_categoria($pdo, $loja_id, $categoria, $limit);
        $hasTags = ((int)($data['total_tags'] ?? 0) > 0);

        echo json_encode([
            'success' => $hasTags,
            'categoria' => $categoria,
            'total_tags' => (int)($data['total_tags'] ?? 0),
            'tags' => (array)($data['tags'] ?? []),
            'tags_csv' => (string)($data['tags_csv'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } catch (Exception $e) {
        error_log('handle_tags_categoria: ' . $e->getMessage());
        http_response_code(422);
        echo json_encode([
            'error' => true,
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
