<?php
function is_loggedin()
{
    global $user;
    return $user->isLogged();
}

function is_admin()
{
    global $user;
    return user_group_id() == 1;
}

function user($field)
{
    return get_the_user(user_id(), $field);
}

function user_id()
{
    global $user;
    return $user->getId();
}

function user_group_id()
{
    global $user;
    return $user->getGroupId();
}

function get_users()
{
    $model = registry()->get('loader')->model('user');
    return $model->getUsers();
}

function get_the_user($id, $field = null)
{
    $model = registry()->get('loader')->model('user');
    $user = $model->getUser($id);
    if ($field && isset($user[$field])) {
        return $user[$field];
    } elseif ($field) {
        return null; // it's equivalent to return;
    }
    return $user;
}

function count_user_store($id = false)
{
    global $user;
    $id = $id ? $id : user_id();
    return $user->countBelongsStore($id);
}

function total_user_today($store_id = null)
{
    $user_model = registry()->get('loader')->model('user');
    return $user_model->totalToday($store_id);
}

function total_user($from = null, $to = null, $store_id = null)
{
    $user_model = registry()->get('loader')->model('user');
    return $user_model->total($from, $to, $store_id);
}

function get_user_due($id, $store_id = null, $index = 'due_amount')
{
    $user_model = registry()->get('loader')->model('user');
    return $user_model->getDueAmount($id, $store_id, $index);
}

function recent_users($limit)
{
    $user_model = registry()->get('loader')->model('user');
    return $user_model->getRecentUsers($limit);
}

function user_total_purchase_amount($id)
{
    $user_model = registry()->get('loader')->model('user');
    return $user_model->getTotalpurchaseAmount($id);
}

function user_total_invoice($id = null)
{
    $user_model = registry()->get('loader')->model('user');
    return $user_model->getTotalInvoiceNumber($id);
}

function best_user($field)
{
    $user_model = registry()->get('loader')->model('user');
    return $user_model->getBestUser($field);
}

function get_best_user_purchase_amount()
{
    $user_model = registry()->get('loader')->model('user');
    return $user_model->getBestUserTotalpurchaseAmount();
}

function user_avatar($sex)
{
    $user_model = registry()->get('loader')->model('user');
    return $user_model->getAvatar($sex);
}

/**
 * Verifica se o usuário é Owner do tenant
 * 
 * @param int|null $user_id ID do usuário (null = usuário atual)
 * @return bool True se é owner, False caso contrário
 */
function is_tenant_owner($user_id = null)
{
    if ($user_id === null) {
        $user_id = function_exists('user_id') ? user_id() : 0;
    }
    
    if ($user_id <= 0) {
        return false;
    }
    
    try {
        $stmt = db()->prepare("SELECT is_owner, tenant_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return false;
        }
        
        // Owner = is_owner = 1 OU user_id = tenant_id
        return (bool)$result['is_owner'] || ($user_id == $result['tenant_id']);
    } catch (Throwable $e) {
        // Fallback: verificar apenas se user_id = tenant_id
        $userData = get_the_user($user_id);
        return $userData && isset($userData['tenant_id']) && $user_id == $userData['tenant_id'];
    }
}

function has_permission($type, $param)
{
    global $user;

    // Cache DESABILITADO temporariamente para debug
    // static $decisionCache = array();
    static $tenantId = null;
    static $featuresSet = null;
    static $isPermissive = null;

    // $cacheKey = (string)$type . ':' . (string)$param;
    // if (array_key_exists($cacheKey, $decisionCache)) {
    //     return (bool)$decisionCache[$cacheKey];
    // }

    // =======================================================
    // REGRA 1: Admin Global (group_id = 1) tem acesso TOTAL
    // =======================================================
    if (function_exists('user_group_id') && user_group_id() == 1) {
        return true;
    }
    
    // =======================================================
    // REGRA 2: Owner do Tenant herda TODAS as capabilities do plano
    // =======================================================
    if (function_exists('is_tenant_owner') && is_tenant_owner()) {
        // Owner não precisa verificar RBAC, apenas se o plano permite
        try {
            if (!class_exists('SaasLimitsBridge')) {
                $saasLimitsPath = defined('ROOT') ? (ROOT . '/../saas/includes/SaasLimitsBridge.php') : null;
                if ($saasLimitsPath && file_exists($saasLimitsPath)) {
                    require_once $saasLimitsPath;
                }
            }
            
            if (class_exists('SaasLimitsBridge') && function_exists('db')) {
                if ($tenantId === null) {
                    $sessionTid = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
                    $uid = function_exists('user_id') ? (int)user_id() : 0;
                    $tenantId = SaasLimitsBridge::resolveTenantId(db(), $uid, $sessionTid > 0 ? $sessionTid : null);
                }
                
                if ((int)$tenantId > 0) {
                    if ($featuresSet === null || $isPermissive === null) {
                        $features = SaasLimitsBridge::getPlanFeatures(db(), (int)$tenantId);
                        $isPermissive = in_array('*', $features, true);
                        $featuresSet = $isPermissive ? array('*' => true) : array_fill_keys($features, true);
                    }
                    
                    // Owner: se plano permite, owner tem acesso
                    if ($isPermissive || isset($featuresSet[(string)$param])) {
                        return true;
                    }
                }
            }
        } catch (Throwable $e) {
            // Owner com erro: permissivo (fail-open)
            return true;
        }
        
        // Owner mas permissão não está no plano
        return false;
    }

    // 1) RBAC (legado)
    $allowedByRbac = $user->hasPermission($type, $param);
    if (!$allowedByRbac) {
        // $decisionCache[$cacheKey] = false;
        return false;
    }

    // 2) Feature gating (SaaS) - compat: se bridge/coluna não existir => permissivo
    try {
        if (!class_exists('SaasLimitsBridge')) {
            $saasLimitsPath = defined('ROOT') ? (ROOT . '/../saas/includes/SaasLimitsBridge.php') : null;
            if ($saasLimitsPath && file_exists($saasLimitsPath)) {
                require_once $saasLimitsPath;
            }
        }

        if (!class_exists('SaasLimitsBridge') || !function_exists('db')) {
            // $decisionCache[$cacheKey] = (bool)$allowedByRbac;
            return (bool)$allowedByRbac;
        }

        if ($tenantId === null) {
            $sessionTid = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
            $uid = function_exists('user_id') ? (int)user_id() : 0;
            $tenantId = SaasLimitsBridge::resolveTenantId(db(), $uid, $sessionTid > 0 ? $sessionTid : null);
        }

        // Sem tenant => modo legacy/single-tenant
        if ((int)$tenantId <= 0) {
            // $decisionCache[$cacheKey] = (bool)$allowedByRbac;
            return (bool)$allowedByRbac;
        }

        if ($featuresSet === null || $isPermissive === null) {
            $features = SaasLimitsBridge::getPlanFeatures(db(), (int)$tenantId);
            $isPermissive = in_array('*', $features, true);
            $featuresSet = $isPermissive ? array('*' => true) : array_fill_keys($features, true);
        }

        if ($isPermissive) {
            // $decisionCache[$cacheKey] = true;
            return true;
        }

        // Regra: permission key precisa estar liberada no plano
        $allowedByPlan = isset($featuresSet[(string)$param]);
        // $decisionCache[$cacheKey] = (bool)$allowedByPlan;
        return (bool)$allowedByPlan;
    } catch (Throwable $e) {
        // Fail-open para não derrubar o POS em caso de erro de schema/DB.
        // $decisionCache[$cacheKey] = (bool)$allowedByRbac;
        return (bool)$allowedByRbac;
    }
}
