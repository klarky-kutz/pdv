<?php
/**
 * API: api/concierge/buscar_produto.php
 * Busca produtos no catálogo IA para o n8n.
 * 
 * GET:
 *   q        = string (busca no nome, tags, cores, tamanhos)
 *   loja_id  = int
 *   tamanho  = string (filtro opcional)
 *   cor      = string (filtro opcional)
 */

if (!isset($tid)) {
    exit; // Apenas via webhook.php
}

require_once DIR_HELPER . 'ai_evolution.php';

try {
    $query = trim((string)(
        $request->post['query']
        ?? $request->get['query']
        ?? $request->post['q']
        ?? $request->get['q']
        ?? ''
    ));

    // Normalização Obrigatória (Glossário de Termos)
    if ($query !== '') {
        $queryNormalized = ai_evolution_normalize_query_term($query);
        if ($queryNormalized !== $query) {
            error_log("buscar_produto: query original '{$query}' normalizada para '{$queryNormalized}'");
            $query = $queryNormalized;
        }
    }
    
    // Truncar query longa (Melhoria 4)
    if (mb_strlen($query, 'UTF-8') > 300) {
        error_log('buscar_produto: query >300 chars. Verificar extrator N8N.');
        $query = mb_substr($query, 0, 300, 'UTF-8');
    }
    
    if (mb_strlen($query, 'UTF-8') < 2) {
        echo json_encode([
            'success'            => true,
            'found'              => false,
            'results'            => [],
            'catalog_snapshot'   => ai_evolution_catalog_snapshot($tid),
            'suggestion_context' => 'Query muito curta.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $limit = min((int)($request->post['limit'] ?? $request->get['limit'] ?? 5), 15);

    // Tentar extrair parâmetros estruturados (Melhoria de Busca Estruturada)
    $color = trim((string)($request->post['color'] ?? $request->get['color'] ?? '')) ?: null;
    $size  = trim((string)($request->post['size']  ?? $request->get['size']  ?? '')) ?: null;
    $tags  = trim((string)($request->post['tags']  ?? $request->get['tags']  ?? '')) ?: null;
    $sku   = trim((string)($request->post['sku']   ?? $request->get['sku']   ?? '')) ?: null;

    // Se vierem parâmetros estruturados ou a query for detectada como SKU
    if ($color || $size || $tags || $sku) {
        $searchResult = ai_evolution_search_structured($tid, $query, $color, $size, $tags, $sku, $limit);
        $results = $searchResult['results'];
    } else {
        // Fallback para busca legada que faz o parser da query
        $searchResult = ai_evolution_search_legacy_query($tid, $query, $limit);
        $results = $searchResult['results'];
    }

    // Log de buscas sem resultado (Melhoria 3)
    if (count($results) === 0) {
        $tokens = ai_evolution_tokenize_query($query);
        $colorTerms = ai_evolution_extract_color_terms($query, $tokens);
        ai_evolution_log_search_miss(
            $tid,
            $query,
            $tokens,
            $colorTerms,
            (string)($request->post['phone'] ?? $request->get['phone'] ?? '')
        );
    }

    echo json_encode([
        'success'          => true,
        'found'            => count($results) > 0,
        'search_level'     => $searchResult['search_level'] ?? 'legacy',
        'results'          => $results,
        'catalog_snapshot' => ai_evolution_catalog_snapshot($tid),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
