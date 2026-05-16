<?php
/**
 * Helper: ai_plan_gate.php
 * Gate de controle de plano para o Módulo Moda IA (Concierge IA)
 * Verifica se o tenant tem acesso ao módulo e respeita os limites de uso.
 */

if (!function_exists('ai_tenant_id')) {
    require_once __DIR__ . '/ai_concierge.php';
}
if (!function_exists('ai_get_token_balance')) {
    require_once __DIR__ . '/ai_tokens.php';
}

// ─────────────────────────────────────────────────────────────────────────────
// GATE PRINCIPAL
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Verifica se o tenant pode usar o módulo Moda IA.
 *
 * Retorna array:
 *   ['allowed' => true,  'plan' => [...], 'usage' => [...]]
 *   ['allowed' => false, 'reason' => 'plan_locked|calls_exceeded|catalog_limit', ...]
 */
function ai_check_plan_gate(int $tenantId = 0): array
{
    if (!$tenantId) {
        $tenantId = ai_tenant_id();
    }

    // BYPASS PARA ADMIN / LOCALHOST (Desenvolvimento)
    $is_admin = (user_group_id() == 1);
    $is_local = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']);
    
    $plan = ai_get_active_plan($tenantId);

    // Plano não encontrado ou módulo bloqueado
    if (!$plan) {
        if ($is_admin || $is_local) {
            return ['allowed' => true, 'plan' => ['name' => 'Desenvolvedor', 'ai_concierge_enabled' => 1, 'ai_catalog_limit' => 0], 'usage' => []];
        }
        return [
            'allowed' => false,
            'reason'  => 'plan_locked',
            'message' => 'Módulo Moda IA não disponível no seu plano atual.',
        ];
    }

    if (!(int)($plan['ai_concierge_enabled'] ?? 0)) {
        if ($is_admin || $is_local) {
            return ['allowed' => true, 'plan' => $plan, 'usage' => []];
        }
        return [
            'allowed' => false,
            'reason'  => 'plan_locked',
            'message' => 'O módulo Moda IA está disponível a partir do Plano Profissional.',
        ];
    }

    $usage = ai_get_usage($tenantId);

    // Verifica limite de chamadas ao webhook
    $callLimit = (int)($plan['ai_webhook_calls'] ?? 0);
    if ($callLimit > 0 && (int)($usage['webhook_calls'] ?? 0) >= $callLimit) {
        // Cota mensal atingida. Verificar se possui tokens extras (FASE T3)
        $tokenBalance = ai_get_token_balance($tenantId);
        if ($tokenBalance > 0) {
            return [
                'allowed'       => true,
                'source'        => 'token',
                'plan'          => $plan,
                'usage'         => $usage,
                'token_balance' => $tokenBalance,
                'message'       => 'Cota do plano atingida. Usando tokens extras.',
            ];
        }

        return [
            'allowed'       => false,
            'reason'        => 'calls_exceeded',
            'message'       => 'Limite mensal de chamadas ao webhook atingido.',
            'used'          => (int)$usage['webhook_calls'],
            'limit'         => $callLimit,
            'token_balance' => 0,
        ];
    }

    return [
        'allowed'       => true,
        'source'        => 'base',
        'plan'          => $plan,
        'usage'         => $usage,
        'token_balance' => ai_get_token_balance($tenantId),
    ];
}

/**
 * Verifica apenas se o módulo está liberado no plano (sem checar contadores).
 * Uso rápido para páginas admin.
 */
function ai_plan_is_enabled(int $tenantId = 0): bool
{
    if (!$tenantId) {
        $tenantId = ai_tenant_id();
    }

    $plan = ai_get_active_plan($tenantId);
    return $plan && (int)($plan['ai_concierge_enabled'] ?? 0) === 1;
}

/**
 * Verifica se o catálogo ainda pode receber novas variantes (SKUs).
 * Retorna ['ok' => bool, 'used' => int, 'limit' => int]
 */
function ai_check_catalog_limit(int $tenantId = 0): array
{
    if (!$tenantId) {
        $tenantId = ai_tenant_id();
    }

    $plan  = ai_get_active_plan($tenantId);
    $limit = (int)($plan['ai_catalog_limit'] ?? 0);
    $used  = ai_count_catalog($tenantId);

    if ($limit > 0 && $used >= $limit) {
        return [
            'ok'      => false,
            'used'    => $used,
            'limit'   => $limit,
            'message' => "Limite de {$limit} SKUs atingido no catálogo. Faça upgrade para continuar cadastrando.",
        ];
    }

    return ['ok' => true, 'used' => $used, 'limit' => $limit];
}

/**
 * Retorna o percentual de uso do catálogo (0–100).
 * Retorna 0 quando limite é ilimitado.
 */
function ai_catalog_usage_pct(int $tenantId = 0): float
{
    $check = ai_check_catalog_limit($tenantId);
    if (!$check['limit']) {
        return 0.0;
    }
    return round(($check['used'] / $check['limit']) * 100, 1);
}

/**
 * Retorna o percentual de uso das chamadas ao webhook no mês atual.
 * Retorna 0 quando limite é ilimitado.
 */
function ai_webhook_usage_pct(int $tenantId = 0): float
{
    if (!$tenantId) {
        $tenantId = ai_tenant_id();
    }
    $plan  = ai_get_active_plan($tenantId);
    $limit = (int)($plan['ai_webhook_calls'] ?? 0);
    if (!$limit) {
        return 0.0;
    }
    $usage = ai_get_usage($tenantId);
    return round(((int)$usage['webhook_calls'] / $limit) * 100, 1);
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS DE UI (usados nas páginas admin)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Renderiza o overlay de bloqueio de plano (HTML inline).
 * Chamar dentro da <section class="content"> quando ai_plan_is_enabled() === false.
 */
function ai_render_plan_locked_overlay(): void
{
    ?>
    <div class="mia-plan-locked" style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:400px;text-align:center;padding:60px 20px;">
      <div style="background:linear-gradient(135deg,#4c1d95,#7c3aed);width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;box-shadow:0 4px 20px rgba(109,40,217,.4);">
        <i class="fa fa-lock" style="font-size:32px;color:#fff;"></i>
      </div>
      <h3 style="font-size:20px;font-weight:700;color:#1f2937;margin-bottom:8px;">Recurso Exclusivo PRO</h3>
      <p style="font-size:14px;color:#6b7280;max-width:400px;margin:0 auto 6px;">O módulo <strong>Moda IA</strong> está disponível a partir do <strong>Plano Profissional</strong>.</p>
      <p style="font-size:13px;color:#9ca3af;max-width:400px;margin:0 auto 24px;">Automatize atendimentos no WhatsApp, gerencie pedidos com Kanban e valide pagamentos Pix — tudo com inteligência artificial.</p>
      <ul style="list-style:none;padding:0;margin:0 auto 28px;text-align:left;display:inline-block;">
        <li style="font-size:14px;color:#374151;margin-bottom:8px;"><i class="fa fa-check" style="color:#059669;margin-right:8px;"></i>Catálogo IA integrado ao WhatsApp</li>
        <li style="font-size:14px;color:#374151;margin-bottom:8px;"><i class="fa fa-check" style="color:#059669;margin-right:8px;"></i>Kanban de entregas com automação</li>
        <li style="font-size:14px;color:#374151;margin-bottom:8px;"><i class="fa fa-check" style="color:#059669;margin-right:8px;"></i>Validação automática de Pix</li>
        <li style="font-size:14px;color:#374151;margin-bottom:0;"><i class="fa fa-check" style="color:#059669;margin-right:8px;"></i>Memória de perfil de cada cliente</li>
      </ul>
      <a href="account_plans.php" class="btn" style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;font-size:15px;font-weight:700;padding:12px 28px;border-radius:2px;text-decoration:none;box-shadow:0 2px 8px rgba(217,119,6,.4);">
        <i class="fa fa-arrow-up"></i> Ver Planos PRO
      </a>
    </div>
    <?php
}

/**
 * Verifica se o módulo de Grupos IA está liberado no plano.
 * Prioriza ai_groups_enabled e mantém fallback de compatibilidade.
 */
function ai_groups_plan_is_enabled(int $tenantId = 0): bool
{
    if (!$tenantId) {
        $tenantId = ai_tenant_id();
    }

    $plan = ai_get_active_plan($tenantId);
    if (!$plan) {
        return false;
    }

    if (array_key_exists('ai_groups_enabled', $plan)) {
        return (int)($plan['ai_groups_enabled'] ?? 0) === 1;
    }

    return (int)($plan['ai_concierge_enabled'] ?? 0) === 1;
}

/**
 * Renderiza o overlay de bloqueio de plano para Grupos IA.
 */
function ai_render_groups_plan_locked_overlay(): void
{
    ?>
    <div class="mia-plan-locked" style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:400px;text-align:center;padding:60px 20px;">
      <div style="background:linear-gradient(135deg,#1e1b4b,#3730a3);width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;box-shadow:0 4px 20px rgba(30,27,75,.4);">
        <i class="fa fa-users" style="font-size:32px;color:#fff;"></i>
      </div>
      <h3 style="font-size:20px;font-weight:700;color:#1f2937;margin-bottom:8px;">Automação de Grupos IA</h3>
      <p style="font-size:14px;color:#6b7280;max-width:400px;margin:0 auto 6px;">O módulo de <strong>Automação de Grupos</strong> está disponível a partir do <strong>Plano Enterprise</strong>.</p>
      <p style="font-size:13px;color:#9ca3af;max-width:400px;margin:0 auto 24px;">Escale suas vendas disparando novidades e promoções automaticamente em todos os seus grupos de WhatsApp.</p>
      <ul style="list-style:none;padding:0;margin:0 auto 28px;text-align:left;display:inline-block;">
        <li style="font-size:14px;color:#374151;margin-bottom:8px;"><i class="fa fa-check" style="color:#059669;margin-right:8px;"></i>Disparos automáticos em massa</li>
        <li style="font-size:14px;color:#374151;margin-bottom:8px;"><i class="fa fa-check" style="color:#059669;margin-right:8px;"></i>Agendamento inteligente por categorias</li>
        <li style="font-size:14px;color:#374151;margin-bottom:8px;"><i class="fa fa-check" style="color:#059669;margin-right:8px;"></i>Limite diário de segurança (anti-ban)</li>
        <li style="font-size:14px;color:#374151;margin-bottom:0;"><i class="fa fa-check" style="color:#059669;margin-right:8px;"></i>Relatórios de entrega e engajamento</li>
      </ul>
      <a href="account_plans.php" class="btn" style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#7c3aed,#4c1d95);color:#fff;font-size:15px;font-weight:700;padding:12px 28px;border-radius:2px;text-decoration:none;box-shadow:0 2px 8px rgba(124,58,237,.4);">
        <i class="fa fa-arrow-up"></i> Ver Planos Enterprise
      </a>
    </div>
    <?php
}

/**
 * Verifica se o limite de chamadas IA foi atingido.
 */
function ai_is_calls_limit_reached(int $tenantId = 0): bool
{
    if (!$tenantId) {
        $tenantId = ai_tenant_id();
    }
    
    // BYPASS PARA ADMIN / LOCALHOST
    if (user_group_id() == 1 || in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        return false;
    }

    $plan = ai_get_active_plan($tenantId);
    if (!$plan) return true;

    $usage = ai_get_usage($tenantId);
    $callLimit = (int)($plan['ai_webhook_calls'] ?? 0);
    
    if ($callLimit > 0 && (int)($usage['webhook_calls'] ?? 0) >= $callLimit) {
        // Se o limite foi atingido, verifica se tem tokens extras
        $tokenBalance = ai_get_token_balance($tenantId);
        return $tokenBalance <= 0;
    }
    
    return false;
}

/**
 * Renderiza o banner de aviso de limite de chamadas atingido.
 */
function ai_render_calls_limit_banner(): string
{
    if (!ai_is_calls_limit_reached()) return '';

    $videoUrl = ROOT_URL . 'storage/concierge/Imagens/Lock.webm';
    
    return '
    <div class="ai-limit-banner" style="display:flex;align-items:center;background:linear-gradient(90deg, #fef2f2 0%, #fff 100%);border:1px solid #fee2e2;border-radius:4px;padding:12px 20px;margin-bottom:20px;animation:mia-slideInLeft 0.5s ease-out;position:relative;overflow:hidden;box-shadow:0 4px 12px rgba(220,38,38,0.08);">
        <div style="flex-shrink:0;width:60px;height:60px;margin-right:15px;display:flex;align-items:center;justify-content:center;">
            <video autoplay loop muted playsinline style="width:100%;height:100%;object-fit:contain;">
                <source src="' . $videoUrl . '" type="video/webm">
            </video>
        </div>
        <div style="flex-grow:1;">
            <div style="font-size:15px;font-weight:700;color:#991b1b;margin-bottom:2px;">Sua IA está desativada!</div>
            <div style="font-size:13px;color:#7f1d1d;opacity:0.9;">O limite mensal de atendimentos do seu plano foi atingido. Compre créditos para reativar agora.</div>
        </div>
        <div style="flex-shrink:0;margin-left:20px;">
            <button onclick="abrirUpgrade()" class="btn" style="background:#dc2626!important;color:#fff!important;border:none!important;padding:8px 20px!important;font-weight:700!important;border-radius:4px!important;box-shadow:0 2px 6px rgba(220,38,38,0.3)!important;cursor:pointer;">
                <i class="fa fa-bolt"></i> Reativar IA
            </button>
        </div>
        <style>
            @keyframes mia-slideInLeft {
                from { transform: translateX(-100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        </style>
    </div>';
}
function ai_render_usage_warning(int $tenantId = 0): string
{
    $catCheck = ai_check_catalog_limit($tenantId ?: ai_tenant_id());
    if ($catCheck['limit'] <= 0) {
        return '';
    }

    $pct = ($catCheck['used'] / $catCheck['limit']) * 100;

    if ($pct < 80) {
        return '';
    }

    $isCritical = $pct >= 95;
    $bgColor     = $isCritical ? '#fee2e2' : '#fef3c7';
    $borderColor = $isCritical ? '#dc2626' : '#f59e0b';
    $textColor   = $isCritical ? '#7f1d1d' : '#78350f';
    $message     = $isCritical
        ? "Atenção: você atingiu {$pct}% do limite de SKUs no catálogo ({$catCheck['used']}/{$catCheck['limit']}). Novos cadastros serão bloqueados ao atingir 100%."
        : "Você usou {$pct}% do limite de SKUs no catálogo ({$catCheck['used']}/{$catCheck['limit']}). Considere fazer upgrade.";

    return '<div style="background:' . $bgColor . ';border-left:4px solid ' . $borderColor . ';color:' . $textColor . ';padding:10px 15px;margin-bottom:14px;font-size:13px;border-radius:0 2px 2px 0;display:flex;align-items:center;gap:8px;">'
        . '<i class="fa fa-exclamation-triangle" style="font-size:15px;flex-shrink:0;"></i>'
        . '<span>' . htmlspecialchars($message) . '</span>'
        . '<a href="account_plans.php" style="margin-left:auto;font-size:12px;font-weight:700;color:inherit;text-decoration:underline;white-space:nowrap;">Fazer Upgrade ↑</a>'
        . '</div>';
}
