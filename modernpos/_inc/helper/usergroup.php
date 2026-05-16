<?php
function get_usergroups() 
{
	$model = registry()->get('loader')->model('usergroup');
	return $model->getUsergroups();
}

/**
 * Retorna grupos disponíveis para o tenant atual.
 *
 * Regras:
 * - Se não houver tenant (modo legacy) => retorna todos os grupos
 * - Se houver tenant => retorna:
 *   1) Grupos globais
 *   2) Grupos do plano do tenant (tenant_id NULL e tenant_scope = plan_id)
 *   3) Grupos do tenant (tenant_id = tenant_id)
 */
function get_usergroups_for_tenant()
{
	if (!function_exists('db')) {
		return get_usergroups();
	}

	$pdo = db();

	// Resolver tenant_id (best effort)
	$tenantId = 0;
	try {
		@session_start();
		$sessionTid = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
		$uid = function_exists('user_id') ? (int)user_id() : 0;

		if (!class_exists('SaasLimitsBridge')) {
			$saasLimitsPath = defined('ROOT') ? (ROOT . '/../saas/includes/SaasLimitsBridge.php') : null;
			if ($saasLimitsPath && file_exists($saasLimitsPath)) {
				require_once $saasLimitsPath;
			}
		}

		if (class_exists('SaasLimitsBridge')) {
			$tenantId = (int)SaasLimitsBridge::resolveTenantId($pdo, $uid, $sessionTid > 0 ? $sessionTid : null);
		} else {
			$tenantId = (int)$sessionTid;
		}
	} catch (Throwable $e) {
		$tenantId = 0;
	}

	if ($tenantId <= 0) {
		return get_usergroups();
	}

	// Descobrir plan_id do tenant
	$planId = 0;
	try {
		$st = $pdo->prepare('SELECT plan_id FROM tenants WHERE tenant_id = ? LIMIT 1');
		$st->execute([(int)$tenantId]);
		$planId = (int)$st->fetchColumn();
	} catch (Throwable $e) {
		$planId = 0;
	}

	// Verificar se a tabela user_group tem colunas de multi-tenant
	$hasTenantIdCol = false;
	$hasTenantScopeCol = false;
	try {
		$st = $pdo->query("SHOW COLUMNS FROM user_group LIKE 'tenant_id'");
		$hasTenantIdCol = $st && $st->rowCount() > 0;
		$st = $pdo->query("SHOW COLUMNS FROM user_group LIKE 'tenant_scope'");
		$hasTenantScopeCol = $st && $st->rowCount() > 0;
	} catch (Throwable $e) {
		$hasTenantIdCol = false;
		$hasTenantScopeCol = false;
	}

	if (!$hasTenantIdCol) {
		return get_usergroups();
	}

	// Query de grupos permitidos
	// - global: tenant_id IS NULL e (tenant_scope IS NULL ou tenant_scope=0)
	// - plano: tenant_id IS NULL e tenant_scope = plan_id
	// - tenant: tenant_id = tenant_id
	$sql = 'SELECT * FROM user_group WHERE '
		. '(tenant_id IS NULL ';
	if ($hasTenantScopeCol) {
		$sql .= ' AND (tenant_scope IS NULL OR tenant_scope = 0)';
	}
	$sql .= ') '
		. 'OR (tenant_id IS NULL ';
	if ($hasTenantScopeCol) {
		$sql .= ' AND tenant_scope = :plan_id';
	} else {
		$sql .= ' AND 1=0';
	}
	$sql .= ') '
		. 'OR (tenant_id = :tenant_id) '
		. 'ORDER BY name ASC';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([
		':tenant_id' => (int)$tenantId,
		':plan_id' => (int)$planId,
	]);

	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_usergroup_user_count($group_id) 
{
	$model = registry()->get('loader')->model('usergroup');
	return $model->totalUser($group_id);
}

function get_the_usergroup($id, $field = null) 
{
	$model = registry()->get('loader')->model('usergroup');
	$usergroup = $model->getUsergroup($id);
	if ($field && isset($usergroup[$field])) {
		return $usergroup[$field];
	} elseif ($field) {
		return null; // it's equivalent to return;
	}
	return $usergroup;
}