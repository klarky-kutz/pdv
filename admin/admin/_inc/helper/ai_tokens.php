<?php
/**
 * Helper: ai_tokens.php
 * Sistema de Créditos (Tokens) e Log de Demanda — Módulo Moda IA
 */

if (!function_exists('ai_get_active_plan')) {
    require_once __DIR__ . '/ai_concierge.php';
}

/**
 * Retorna o saldo atual de tokens extras do tenant (tenants.ai_extra_tokens).
 */
function ai_get_token_balance(int $tid): int
{
    try {
        $stmt = db()->prepare("SELECT ai_extra_tokens FROM tenants WHERE tenant_id = :tid LIMIT 1");
        $stmt->execute([':tid' => $tid]);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Lógica central de consumo de chamadas:
 * 1. Verifica se cota-base tem saldo → decrementa ai_usage_log.base_calls_used
 * 2. Se cota-base esgotada, verifica tokens extras (tenants.ai_extra_tokens)
 * 3. Se tokens disponíveis → decrementa ai_extra_tokens + incrementa tokens_consumed
 * 4. Se ambos esgotados → retorna ['allowed'=>false, 'reason'=>'calls_exceeded']
 * 
 * @return array ['allowed' => bool, 'source' => 'base|token', 'balance' => int]
 */
function ai_consume_call(int $tid): array
{
    $ym = date('Y-m');
    $plan = ai_get_active_plan($tid);
    $usage = ai_get_usage($tid, $ym);

    $callLimit = (int)($plan['ai_webhook_calls'] ?? 0);
    $usedCalls = (int)($usage['webhook_calls'] ?? 0);

    // 1. Verificar cota-base do plano
    // Se o limite for 0 (ilimitado), sempre permite via base
    if ($callLimit === 0 || $usedCalls < $callLimit) {
        // Incrementa base_calls_used na ai_usage_log
        db()->prepare("
            INSERT INTO ai_usage_log (tenant_id, `year_month`, webhook_calls, base_calls_used)
            VALUES (:tid, :ym, 1, 1)
            ON DUPLICATE KEY UPDATE 
                webhook_calls = webhook_calls + 1,
                base_calls_used = base_calls_used + 1
        ")->execute([':tid' => $tid, ':ym' => $ym]);

        return [
            'allowed' => true,
            'source'  => 'base',
            'balance' => ($callLimit > 0) ? ($callLimit - ($usedCalls + 1)) : 999999
        ];
    }

    // 2. Cota-base esgotada, verificar tokens extras
    $tokenBalance = ai_get_token_balance($tid);
    if ($tokenBalance > 0) {
        // Decrementa ai_extra_tokens no tenant
        db()->prepare("UPDATE tenants SET ai_extra_tokens = ai_extra_tokens - 1 WHERE tenant_id = :tid")
            ->execute([':tid' => $tid]);

        // Incrementa tokens_consumed na ai_usage_log
        db()->prepare("
            INSERT INTO ai_usage_log (tenant_id, `year_month`, webhook_calls, tokens_consumed)
            VALUES (:tid, :ym, 1, 1)
            ON DUPLICATE KEY UPDATE 
                webhook_calls = webhook_calls + 1,
                tokens_consumed = tokens_consumed + 1
        ")->execute([':tid' => $tid, ':ym' => $ym]);

        return [
            'allowed' => true,
            'source'  => 'token',
            'balance' => $tokenBalance - 1
        ];
    }

    // 3. Ambos esgotados
    return [
        'allowed' => false,
        'reason'  => 'calls_exceeded',
        'balance' => 0
    ];
}

/**
 * Adiciona tokens ao saldo do tenant e atualiza status da compra.
 */
function ai_add_tokens(int $tid, int $qty, int $purchaseId): bool
{
    try {
        db()->beginTransaction();

        // 1. Atualiza saldo do tenant
        $stmt = db()->prepare("UPDATE tenants SET ai_extra_tokens = ai_extra_tokens + :qty WHERE tenant_id = :tid");
        $stmt->execute([':qty' => $qty, ':tid' => $tid]);

        // 2. Atualiza status da compra
        $stmt2 = db()->prepare("UPDATE ai_token_purchases SET status = 'paid', paid_at = NOW() WHERE purchase_id = :pid AND tenant_id = :tid");
        $stmt2->execute([':pid' => $purchaseId, ':tid' => $tid]);

        db()->commit();
        return true;
    } catch (Exception $e) {
        db()->rollBack();
        return false;
    }
}

/**
 * Retorna pacotes de tokens ativos ordenados.
 */
function ai_get_token_packages(): array
{
    try {
        return db()->query("SELECT * FROM ai_token_packages WHERE is_active = 1 ORDER BY sort_order ASC")
                   ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Histórico de compras de tokens do tenant.
 */
function ai_get_purchase_history(int $tid, int $limit = 20): array
{
    try {
        $stmt = db()->prepare("
            SELECT tp.*, pk.name as package_name 
            FROM ai_token_purchases tp
            LEFT JOIN ai_token_packages pk ON tp.package_id = pk.package_id
            WHERE tp.tenant_id = :tid 
            ORDER BY tp.created_at DESC 
            LIMIT :lim
        ");
        $stmt->bindValue(':tid', $tid, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Ranking de demanda por produto.
 */
function ai_get_demand_ranking(int $tid, string $period = 'month', int $limit = 10): array
{
    $where = "WHERE tenant_id = :tid";
    $params = [':tid' => $tid];

    if ($period === 'today') {
        $where .= " AND DATE(created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $where .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($period === 'month') {
        $where .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }

    try {
        $sql = "
            SELECT model_id, COUNT(*) as count 
            FROM ai_demand_log 
            $where 
            GROUP BY model_id 
            ORDER BY count DESC 
            LIMIT :lim
        ";
        $stmt = db()->prepare($sql);
        $stmt->bindValue(':tid', $tid, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Hidratar com nomes dos modelos
        foreach ($ranking as &$item) {
            $m = db()->prepare("SELECT name FROM ai_catalogo_models WHERE id = :id LIMIT 1");
            $m->execute([':id' => $item['model_id']]);
            $item['model_name'] = $m->fetchColumn() ?: 'Produto Removido';
        }
        return $ranking;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Registra demanda granular e incrementa contador no modelo.
 */
function ai_log_demand(int $tid, int $modelId, string $queryText = '', string $source = 'webhook'): void
{
    try {
        // 1. Inserir log granular
        $stmt = db()->prepare("
            INSERT INTO ai_demand_log (tenant_id, model_id, query_text, source, created_at)
            VALUES (:tid, :mid, :q, :src, NOW())
        ");
        $stmt->execute([
            ':tid' => $tid,
            ':mid' => $modelId,
            ':q'   => $queryText,
            ':src' => $source
        ]);

        // 2. Incrementar contador global no modelo (redundância de performance)
        ai_increment_demand($modelId, $tid);
    } catch (Exception $e) {
        // Silencioso em caso de erro de log
    }
}
