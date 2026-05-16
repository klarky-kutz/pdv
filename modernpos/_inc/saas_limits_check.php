<?php
/**
 * SaaS Limits Check
 * Helper para verificar limites do plano antes de criar recursos
 * 
 * @package ModernPOS
 * @subpackage SaaS
 */

// Evitar acesso direto
if (!defined('ROOT')) {
    die('Acesso negado');
}

/**
 * Obtém o tenant_id do usuário logado
 * @return int
 */
function get_tenant_id_from_session() {
    // Primeiro tenta da sessão
    if (isset($_SESSION['tenant_id']) && $_SESSION['tenant_id'] > 0) {
        return (int)$_SESSION['tenant_id'];
    }
    
    // Fallback: buscar do usuário
    if (function_exists('user_id') && user_id() > 0) {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([user_id()]);
        $tenantId = (int)$stmt->fetchColumn();
        
        // Armazena na sessão para próximas consultas
        if ($tenantId > 0) {
            $_SESSION['tenant_id'] = $tenantId;
        }
        
        return $tenantId;
    }
    
    return 0;
}

/**
 * Obtém os limites do plano do tenant
 * @return array|null
 */
function get_tenant_plan_limits() {
    $tenantId = get_tenant_id_from_session();
    
    if ($tenantId <= 0) {
        return null; // Não é tenant SaaS
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT 
                p.max_products,
                p.products_unlimited,
                p.clients_limit,
                p.max_users,
                p.max_stores,
                p.name as plan_name
            FROM tenants t
            JOIN plans p ON p.plan_id = t.plan_id
            WHERE t.tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        $limits = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $limits ?: null;
    } catch (Exception $e) {
        error_log('[SaaS Limits] Erro ao buscar limites: ' . $e->getMessage());
        return null;
    }
}

/**
 * Obtém as store_ids do tenant
 * @return array
 */
function get_tenant_store_ids() {
    $tenantId = get_tenant_id_from_session();
    
    if ($tenantId <= 0) {
        return [];
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT store_id FROM stores WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log('[SaaS Limits] Erro ao buscar stores: ' . $e->getMessage());
        return [];
    }
}

/**
 * Conta o uso atual do tenant (produtos, clientes, usuários)
 * @return array
 */
function get_tenant_usage() {
    $storeIds = get_tenant_store_ids();
    $tenantId = get_tenant_id_from_session();
    
    $usage = [
        'products' => 0,
        'customers' => 0,
        'users' => 0,
        'stores' => count($storeIds)
    ];
    
    if (empty($storeIds)) {
        return $usage;
    }
    
    try {
        $pdo = db();
        $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
        
        // Contar produtos (distintos, ativos)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT product_id) 
            FROM product_to_store 
            WHERE store_id IN ($placeholders) AND status = 1
        ");
        $stmt->execute($storeIds);
        $usage['products'] = (int)$stmt->fetchColumn();
        
        // Contar clientes (distintos)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT customer_id) 
            FROM customer_to_store 
            WHERE store_id IN ($placeholders)
        ");
        $stmt->execute($storeIds);
        $usage['customers'] = (int)$stmt->fetchColumn();
        
        // Contar usuários do tenant
        if ($tenantId > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
            $usage['users'] = (int)$stmt->fetchColumn();
        }
        
    } catch (Exception $e) {
        error_log('[SaaS Limits] Erro ao contar uso: ' . $e->getMessage());
    }
    
    return $usage;
}

/**
 * Verifica se pode criar novo produto
 * @return bool
 */
function can_create_product() {
    $limits = get_tenant_plan_limits();
    
    // Se não é SaaS ou não encontrou limites, permite
    if (!$limits) {
        return true;
    }
    
    // Se produtos são ilimitados, permite
    if (!empty($limits['products_unlimited'])) {
        return true;
    }
    
    // Verifica limite
    $maxProducts = (int)($limits['max_products'] ?? 0);
    if ($maxProducts <= 0) {
        return true; // Sem limite definido = ilimitado
    }
    
    $usage = get_tenant_usage();
    return $usage['products'] < $maxProducts;
}

/**
 * Verifica se pode criar novo cliente
 * @return bool
 */
function can_create_customer() {
    $limits = get_tenant_plan_limits();
    
    // Se não é SaaS ou não encontrou limites, permite
    if (!$limits) {
        return true;
    }
    
    // Verifica limite de clientes
    $maxClients = (int)($limits['clients_limit'] ?? 0);
    if ($maxClients <= 0) {
        return true; // Sem limite definido = ilimitado
    }
    
    $usage = get_tenant_usage();
    return $usage['customers'] < $maxClients;
}

/**
 * Obtém informações completas de limite para exibição na UI
 * @param string $type 'products' ou 'customers'
 * @return array
 */
function get_limit_info($type) {
    $limits = get_tenant_plan_limits();
    $usage = get_tenant_usage();
    
    $info = [
        'is_saas' => ($limits !== null),
        'current' => 0,
        'limit' => 0,
        'unlimited' => true,
        'can_create' => true,
        'percentage' => 0,
        'plan_name' => $limits['plan_name'] ?? 'N/A'
    ];
    
    if (!$limits) {
        return $info;
    }
    
    switch ($type) {
        case 'products':
            $info['current'] = $usage['products'];
            $info['limit'] = (int)($limits['max_products'] ?? 0);
            $info['unlimited'] = !empty($limits['products_unlimited']) || $info['limit'] <= 0;
            $info['can_create'] = can_create_product();
            break;
            
        case 'customers':
            $info['current'] = $usage['customers'];
            $info['limit'] = (int)($limits['clients_limit'] ?? 0);
            $info['unlimited'] = $info['limit'] <= 0;
            $info['can_create'] = can_create_customer();
            break;
            
        case 'users':
            $info['current'] = $usage['users'];
            $info['limit'] = (int)($limits['max_users'] ?? 0);
            $info['unlimited'] = $info['limit'] <= 0;
            $info['can_create'] = $info['unlimited'] || $usage['users'] < $info['limit'];
            break;
            
        case 'stores':
            $info['current'] = $usage['stores'];
            $info['limit'] = (int)($limits['max_stores'] ?? 0);
            $info['unlimited'] = $info['limit'] <= 0;
            $info['can_create'] = $info['unlimited'] || $usage['stores'] < $info['limit'];
            break;
    }
    
    // Calcular porcentagem de uso
    if (!$info['unlimited'] && $info['limit'] > 0) {
        $info['percentage'] = round(($info['current'] / $info['limit']) * 100, 1);
    }
    
    return $info;
}
