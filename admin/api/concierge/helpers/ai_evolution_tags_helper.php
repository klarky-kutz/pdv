<?php
/**
 * Helper de tags para o endpoint tags_categoria.
 */

/**
 * Normaliza uma string de tags em array único e limpo.
 *
 * @param string $raw String bruta de tags (separadas por vírgula, ponto-e-vírgula, barra vertical, quebra de linha ou espaço).
 * @return array Array de tags normalizadas (lowercase, trim, sem duplicatas e sem vazias).
 * @example
 * normalize_tags_string('Colado, Justo, Malha');
 */
function normalize_tags_string(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }

    $raw = mb_strtolower($raw, 'UTF-8');
    $raw = str_replace([';', '|', "\r\n", "\n", "\r"], ',', $raw);

    if (strpos($raw, ',') !== false) {
        $parts = explode(',', $raw);
    } else {
        $parts = preg_split('/\s+/u', $raw);
        if (!is_array($parts)) {
            $parts = [];
        }
    }

    $tags = [];
    foreach ($parts as $part) {
        $tag = trim((string)$part);
        $tag = preg_replace('/\s+/u', ' ', $tag);
        if (!is_string($tag) || $tag === '') {
            continue;
        }
        $tags[] = $tag;
    }

    return array_values(array_unique($tags));
}

/**
 * Monta CSV de tags para uso no n8n/IA extratora.
 *
 * @param array $tags Lista de tags normalizadas.
 * @return string CSV no formato "tag1, tag2, tag3".
 * @example
 * build_tags_csv(['colado', 'justo', 'malha']);
 */
function build_tags_csv(array $tags): string
{
    $clean = [];
    foreach ($tags as $tag) {
        $value = trim((string)$tag);
        if ($value === '') {
            continue;
        }
        $clean[] = $value;
    }
    return implode(', ', array_values(array_unique($clean)));
}

/**
 * Retorna tags mais frequentes por categoria ou de todo o catálogo se a categoria for omitida.
 * Estratégia otimizada: filtra modelos elegíveis no SQL e calcula frequência das tags em PHP.
 *
 * @param PDO $pdo Conexão PDO ativa do projeto.
 * @param int $loja_id ID da loja (tenant).
 * @param string|null $categoria Nome da categoria (opcional).
 * @param int $limit Limite final de tags retornadas.
 * @return array Estrutura com tags, tags_csv e total_tags.
 * @example
 * get_tags_by_categoria(db(), 300, 'Vestidos', 60);
 */
function get_tags_by_categoria(PDO $pdo, int $loja_id, ?string $categoria = null, int $limit = 60): array
{
    $limit = max(1, min((int)$limit, 100));
    $categoria = $categoria !== null ? trim($categoria) : null;

    if ($loja_id <= 0) {
        return [
            'tags' => [],
            'tags_csv' => '',
            'total_tags' => 0,
        ];
    }

    $rows = [];
    $sqlBase = "
        SELECT m.id, m.tags
        FROM ai_catalogo_models m
        LEFT JOIN categorys c
                ON c.category_id = m.category_id
        LEFT JOIN category_to_store c2s
                ON c2s.ccategory_id = c.category_id
               AND c2s.store_id = :store_id
               AND c2s.status = 1
        WHERE m.tenant_id = :tenant_id
          AND m.is_active = 1
          AND m.tags IS NOT NULL
          AND TRIM(m.tags) <> ''
          AND %s
          AND EXISTS (
              SELECT 1
              FROM ai_catalogo_variants v
              WHERE v.model_id = m.id
                AND v.tenant_id = :variant_tenant_id
                AND v.is_active = 1
                AND v.stock_qty > 0
          )
    ";

    try {
        if ($categoria === null || $categoria === '') {
            $sqlNoCat = sprintf($sqlBase, '1=1');
            $stmt = $pdo->prepare($sqlNoCat);
            $stmt->bindValue(':store_id', $loja_id, PDO::PARAM_INT);
            $stmt->bindValue(':tenant_id', $loja_id, PDO::PARAM_INT);
            $stmt->bindValue(':variant_tenant_id', $loja_id, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sqlExact = sprintf($sqlBase, 'LOWER(c.category_name) = LOWER(:categoria_exact)');
            $stmt = $pdo->prepare($sqlExact);
            $stmt->bindValue(':store_id', $loja_id, PDO::PARAM_INT);
            $stmt->bindValue(':tenant_id', $loja_id, PDO::PARAM_INT);
            $stmt->bindValue(':variant_tenant_id', $loja_id, PDO::PARAM_INT);
            $stmt->bindValue(':categoria_exact', $categoria, PDO::PARAM_STR);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                $sqlLike = sprintf($sqlBase, 'LOWER(c.category_name) LIKE :categoria_like');
                $stmtLike = $pdo->prepare($sqlLike);
                $stmtLike->bindValue(':store_id', $loja_id, PDO::PARAM_INT);
                $stmtLike->bindValue(':tenant_id', $loja_id, PDO::PARAM_INT);
                $stmtLike->bindValue(':variant_tenant_id', $loja_id, PDO::PARAM_INT);
                $stmtLike->bindValue(':categoria_like', '%' . mb_strtolower($categoria, 'UTF-8') . '%', PDO::PARAM_STR);
                $stmtLike->execute();
                $rows = $stmtLike->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        error_log('get_tags_by_categoria: ' . $e->getMessage());
        return [
            'tags' => [],
            'tags_csv' => '',
            'total_tags' => 0,
        ];
    }

    if (empty($rows)) {
        return [
            'tags' => [],
            'tags_csv' => '',
            'total_tags' => 0,
        ];
    }

    $frequency = [];
    foreach ($rows as $row) {
        $rawTags = (string)($row['tags'] ?? '');
        if ($rawTags === '') {
            continue;
        }
        $modelTags = normalize_tags_string($rawTags);
        if (empty($modelTags)) {
            continue;
        }
        $modelTags = array_values(array_unique($modelTags));
        foreach ($modelTags as $tag) {
            $frequency[$tag] = (int)($frequency[$tag] ?? 0) + 1;
        }
    }

    if (empty($frequency)) {
        return [
            'tags' => [],
            'tags_csv' => '',
            'total_tags' => 0,
        ];
    }

    uksort($frequency, static function ($a, $b) use ($frequency): int {
        $freqA = (int)$frequency[$a];
        $freqB = (int)$frequency[$b];
        if ($freqA === $freqB) {
            return strcasecmp((string)$a, (string)$b);
        }
        return ($freqA > $freqB) ? -1 : 1;
    });

    $tags = array_slice(array_keys($frequency), 0, $limit);

    return [
        'tags' => $tags,
        'tags_csv' => build_tags_csv($tags),
        'total_tags' => count($tags),
    ];
}
