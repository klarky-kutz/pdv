<?php
/**
 * API: Lista de Planos Disponíveis
 * 
 * Retorna todos os planos ativos do banco de dados com suas features e preços.
 * 
 * GET /conta/_ajax/plans_list.php
 * 
 * Response:
 * {
 *   "success": true,
 *   "plans": [...],
 *   "current_plan_id": 1,
 *   "tenant_info": {...}
 * }
 */

header('Content-Type: application/json; charset=utf-8');

// Inicializa sessão e carrega configurações
session_start();

// Caminho para o _init.php do modernpos
$initPath = dirname(__DIR__) . '/../_init.php';
if (!file_exists($initPath)) {
    echo json_encode(['success' => false, 'message' => 'Sistema não configurado corretamente.']);
    exit;
}

require_once $initPath;

// Verifica se o usuário está logado
if (!function_exists('user_id') || !user_id()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

try {
    $pdo = db();
    
    // Obtém o tenant_id do usuário logado
    $tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    
    if ($tenantId <= 0) {
        // Tenta descobrir pelo usuário
        $userId = user_id();
        $stmtUser = $pdo->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
        $stmtUser->execute([$userId]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $tenantId = $userRow ? (int)$userRow['tenant_id'] : 0;
    }
    
    if ($tenantId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Tenant não identificado.']);
        exit;
    }
    
    // Busca informações do tenant (plano atual, status, etc.)
    // Usa apenas colunas que existem na tabela original
    $stmtTenant = $pdo->prepare("
        SELECT 
            t.tenant_id,
            t.company_name,
            t.plan_id,
            t.subscription_status,
            t.trial_ends_at
        FROM tenants t
        WHERE t.tenant_id = ?
        LIMIT 1
    ");
    $stmtTenant->execute([$tenantId]);
    $tenantInfo = $stmtTenant->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenantInfo) {
        echo json_encode(['success' => false, 'message' => 'Tenant não encontrado.']);
        exit;
    }
    
    $currentPlanId = (int)($tenantInfo['plan_id'] ?? 0);
    
    // Verifica se as novas colunas existem na tabela plans
    $hasNewColumns = false;
    try {
        $checkCol = $pdo->query("SELECT description_short FROM plans LIMIT 1");
        $hasNewColumns = true;
    } catch (PDOException $e) {
        $hasNewColumns = false;
    }
    
    // Busca todos os planos ativos
    if ($hasNewColumns) {
        $sqlPlans = "
            SELECT 
                p.plan_id,
                p.name,
                p.tagline,
                p.description_short,
                p.description_long,
                p.features_list,
                p.price_monthly,
                p.price_yearly,
                p.max_stores,
                p.max_users,
                p.max_products,
                p.storage_mb,
                p.whatsapp_accounts,
                p.priority_support,
                p.products_unlimited,
                p.clients_limit,
                p.is_featured,
                p.badge_text,
                p.sort_order,
                p.trial_days,
                p.is_active
            FROM plans p
            WHERE p.is_active = 1
            ORDER BY p.sort_order ASC, p.price_monthly ASC
        ";
    } else {
        // Fallback: sem as novas colunas
        $sqlPlans = "
            SELECT 
                p.plan_id,
                p.name,
                p.tagline,
                NULL as description_short,
                NULL as description_long,
                NULL as features_list,
                p.price_monthly,
                NULL as price_yearly,
                p.max_stores,
                p.max_users,
                p.max_products,
                p.storage_mb,
                p.whatsapp_accounts,
                p.priority_support,
                p.products_unlimited,
                p.clients_limit,
                0 as is_featured,
                NULL as badge_text,
                0 as sort_order,
                0 as trial_days,
                p.is_active
            FROM plans p
            WHERE p.is_active = 1
            ORDER BY p.price_monthly ASC
        ";
    }
    
    $stmtPlans = $pdo->prepare($sqlPlans);
    $stmtPlans->execute();
    $plansRaw = $stmtPlans->fetchAll(PDO::FETCH_ASSOC);
    
    // Processa os planos
    $plans = [];
    foreach ($plansRaw as $plan) {
        // Decodifica features_list se existir
        $featuresList = null;
        if (!empty($plan['features_list'])) {
            $featuresList = json_decode($plan['features_list'], true);
        }
        
        // Se não tiver features_list, gera um padrão baseado nos campos
        if (!$featuresList) {
            $featuresList = [
                'highlights' => [],
                'included' => [],
                'not_included' => [],
                'limits' => [
                    'stores' => (int)$plan['max_stores'],
                    'users' => (int)$plan['max_users'],
                    'products' => $plan['products_unlimited'] ? -1 : (int)$plan['max_products'],
                    'clients' => (int)$plan['clients_limit'],
                ]
            ];
            
            // Gera highlights automáticos
            if ((int)$plan['max_stores'] === 1) {
                $featuresList['highlights'][] = '1 loja';
            } else {
                $featuresList['highlights'][] = 'Até ' . $plan['max_stores'] . ' lojas';
            }
            
            if ($plan['products_unlimited']) {
                $featuresList['highlights'][] = 'Produtos ilimitados';
            } else {
                $featuresList['highlights'][] = 'Até ' . $plan['max_products'] . ' produtos';
            }
            
            if ($plan['priority_support']) {
                $featuresList['highlights'][] = 'Suporte prioritário';
            }
            
            if ((int)$plan['whatsapp_accounts'] > 0) {
                $featuresList['highlights'][] = 'WhatsApp integrado';
            }
        }
        
        // Calcula se é upgrade, downgrade ou plano atual
        $priceCompare = 'same';
        if ($currentPlanId > 0) {
            // Busca preço do plano atual para comparar
            $stmtCurrentPrice = $pdo->prepare("SELECT price_monthly FROM plans WHERE plan_id = ?");
            $stmtCurrentPrice->execute([$currentPlanId]);
            $currentPrice = (float)$stmtCurrentPrice->fetchColumn();
            
            if ((float)$plan['price_monthly'] > $currentPrice) {
                $priceCompare = 'upgrade';
            } elseif ((float)$plan['price_monthly'] < $currentPrice) {
                $priceCompare = 'downgrade';
            }
        }
        
        $plans[] = [
            'plan_id' => (int)$plan['plan_id'],
            'name' => $plan['name'],
            'tagline' => $plan['tagline'],
            'description_short' => $plan['description_short'],
            'description_long' => $plan['description_long'],
            'features_list' => $featuresList,
            'price_monthly' => (float)$plan['price_monthly'],
            'price_yearly' => $plan['price_yearly'] ? (float)$plan['price_yearly'] : null,
            'yearly_discount_percent' => $plan['price_yearly'] && $plan['price_monthly'] > 0 
                ? round((1 - ($plan['price_yearly'] / ($plan['price_monthly'] * 12))) * 100)
                : 0,
            'limits' => [
                'stores' => (int)$plan['max_stores'],
                'users' => (int)$plan['max_users'],
                'products' => $plan['products_unlimited'] ? -1 : (int)$plan['max_products'],
                'storage_mb' => (int)$plan['storage_mb'],
                'whatsapp' => (int)$plan['whatsapp_accounts'],
                'clients' => (int)$plan['clients_limit'],
            ],
            'priority_support' => (bool)$plan['priority_support'],
            'is_featured' => (bool)$plan['is_featured'],
            'badge_text' => $plan['badge_text'],
            'trial_days' => (int)$plan['trial_days'],
            'is_current' => ($plan['plan_id'] == $currentPlanId),
            'price_compare' => ($plan['plan_id'] == $currentPlanId) ? 'current' : $priceCompare,
        ];
    }
    
    // Verifica se está em período de trial
    $isInTrial = false;
    $trialDaysRemaining = 0;
    if (!empty($tenantInfo['trial_ends_at'])) {
        $trialEnds = new DateTime($tenantInfo['trial_ends_at']);
        $now = new DateTime();
        if ($trialEnds > $now) {
            $isInTrial = true;
            $trialDaysRemaining = $now->diff($trialEnds)->days;
        }
    }
    
    // Monta resposta
    $response = [
        'success' => true,
        'plans' => $plans,
        'current_plan_id' => $currentPlanId,
        'tenant_info' => [
            'tenant_id' => $tenantId,
            'company_name' => $tenantInfo['company_name'],
            'subscription_status' => $tenantInfo['subscription_status'] ?? 'unknown',
            'is_in_trial' => $isInTrial,
            'trial_days_remaining' => $trialDaysRemaining,
            'trial_ends_at' => $tenantInfo['trial_ends_at'],
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar planos: ' . $e->getMessage()
    ]);
}
