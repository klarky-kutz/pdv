<?php
/*
| ----------------------------------------------------------------------------
| PRODUCT NAME: 	Modern POS - Point of Sale with Stock Management System
| ----------------------------------------------------------------------------
| AUTHOR:			ITsolution24.com
| ----------------------------------------------------------------------------
| EMAIL:			itsolution24bd@gmail.com
| ----------------------------------------------------------------------------
| COPYRIGHT:		RESERVED BY ITsolution24.com
| ----------------------------------------------------------------------------
|| WEBSITE:			http://ITsolution24.com
|| ----------------------------------------------------------------------------
*/
class ModelUsergroup extends Model 
{
	protected function usergroupHasTenantColumns(): bool
	{
		static $has = null;
		if ($has !== null) return (bool)$has;

		try {
			$st1 = $this->db->query("SHOW COLUMNS FROM user_group LIKE 'tenant_id'");
			$st2 = $this->db->query("SHOW COLUMNS FROM user_group LIKE 'tenant_scope'");
			$has = ($st1 && $st1->rowCount() > 0) && ($st2 && $st2->rowCount() > 0);
		} catch (Throwable $e) {
			$has = false;
		}

		return (bool)$has;
	}

	protected function resolveTenantId(): int
	{
		static $tenantId = null;
		if ($tenantId !== null) return (int)$tenantId;

		$tid = 0;
		if (isset($_SESSION['tenant_id'])) {
			$tid = (int)$_SESSION['tenant_id'];
		}

		if ($tid <= 0 && function_exists('user_id')) {
			try {
				$uid = (int)user_id();
				if ($uid > 0) {
					$st = $this->db->prepare('SELECT tenant_id FROM users WHERE id = ? LIMIT 1');
					$st->execute([$uid]);
					$tmp = $st->fetchColumn();
					$tid = $tmp ? (int)$tmp : 0;
				}
			} catch (Throwable $e) {
				$tid = 0;
			}
		}

		$tenantId = $tid;
		return (int)$tenantId;
	}

	protected function resolvePlanId(int $tenantId): int
	{
		static $planCache = [];
		$tenantId = (int)$tenantId;
		if ($tenantId <= 0) return 0;
		if (isset($planCache[$tenantId])) return (int)$planCache[$tenantId];

		try {
			$st = $this->db->prepare('SELECT plan_id FROM tenants WHERE tenant_id = ? LIMIT 1');
			$st->execute([$tenantId]);
			$pid = (int)$st->fetchColumn();
			$planCache[$tenantId] = $pid;
			return $pid;
		} catch (Throwable $e) {
			$planCache[$tenantId] = 0;
			return 0;
		}
	}

	public function addUsergroup($data) 
	{
		$permission = serialize(array());

		// Multi-tenant: cria grupos sempre no escopo do tenant (tenant_id = tenant atual)
		if ($this->usergroupHasTenantColumns()) {
			$tenantId = 0;
			if (isset($data['tenant_id'])) {
				$tenantId = (int)$data['tenant_id'];
			}
			if ($tenantId <= 0) {
				$tenantId = $this->resolveTenantId();
			}

			// Se não conseguimos resolver tenant_id, cria como grupo do sistema (compat)
			$tenantParam = $tenantId > 0 ? $tenantId : null;

			$statement = $this->db->prepare("INSERT INTO `user_group` (tenant_id, name, slug, permission) VALUES (?, ?, ?, ?)");
			$statement->execute([$tenantParam, $data['name'], $data['slug'], $permission]);
			return $this->db->lastInsertId();
		}

		// Legacy
		$statement = $this->db->prepare("INSERT INTO `user_group` (name, slug, permission) VALUES (?, ?, ?)");
		$statement->execute([$data['name'], $data['slug'], $permission]);
		return $this->db->lastInsertId();
	}

	public function editUsergroup($group_id, $data, $permission) 
	{    	
		$statement = $this->db->prepare("UPDATE `user_group` SET name = ?, slug = ?, `permission` = ? WHERE `group_id` = ?");
		$statement->execute([$data['name'], $data['slug'], serialize($permission), (int)$group_id]);
		return $group_id;
	}

	public function deleteUsergroup($group_id) 
	{    	
    	$statement = $this->db->prepare("DELETE FROM `user_group` WHERE `group_id` = ? LIMIT 1");
    	$statement->execute(array($group_id));
        return $group_id;
	}

	public function getUsergroup($group_id) 
	{
		$group_id = (int)$group_id;

		if ($this->usergroupHasTenantColumns()) {
			$tenantId = $this->resolveTenantId();
			if ($tenantId > 0) {
				$planId = $this->resolvePlanId($tenantId);
				if ($planId > 0) {
					$statement = $this->db->prepare("SELECT * FROM `user_group` WHERE `group_id` = ? AND `tenant_scope` IN (0, ?, ?)");
					$statement->execute([$group_id, $tenantId, $planId]);
					return $statement->fetch(PDO::FETCH_ASSOC);
				}

				$statement = $this->db->prepare("SELECT * FROM `user_group` WHERE `group_id` = ? AND `tenant_scope` IN (0, ?)");
				$statement->execute([$group_id, $tenantId]);
				return $statement->fetch(PDO::FETCH_ASSOC);
			}

			// Se não sabemos tenant, mostra apenas grupos do sistema
			$statement = $this->db->prepare("SELECT * FROM `user_group` WHERE `group_id` = ? AND `tenant_scope` = 0");
			$statement->execute([$group_id]);
			return $statement->fetch(PDO::FETCH_ASSOC);
		}

		$statement = $this->db->prepare("SELECT * FROM `user_group` WHERE `group_id` = ?");
		$statement->execute([$group_id]);
		return $statement->fetch(PDO::FETCH_ASSOC);
	}

	public function getUsergroups($data = array()) 
	{
		$sql = "SELECT * FROM `user_group` WHERE 1=1";

		// Multi-tenant: mostra grupos do sistema (tenant_scope=0) + grupos do tenant atual
		// + grupos vinculados ao plano (tenant_scope = plan_id)
		if ($this->usergroupHasTenantColumns()) {
			$tenantId = $this->resolveTenantId();
			if ($tenantId > 0) {
				$planId = $this->resolvePlanId($tenantId);
				if ($planId > 0) {
					$sql .= " AND `tenant_scope` IN (0, " . (int)$tenantId . ", " . (int)$planId . ")";
				} else {
					$sql .= " AND `tenant_scope` IN (0, " . (int)$tenantId . ")";
				}
			} else {
				// tenant desconhecido => restringe a grupos do sistema
				$sql .= " AND `tenant_scope` = 0";
			}
		}

		if (isset($data['filter_name'])) {
			$sql .= " AND `name` LIKE '" . $data['filter_name'] . "%'";
		}

		$sort_data = array(
			'name'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY name";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$statement = $this->db->prepare($sql);
		$statement->execute();
		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}

	public function totalUser($group_id, $store_id = null)
	{
		$store_id = $store_id ? $store_id : store_id();

		$statement = $this->db->prepare("SELECT * FROM `users`
			LEFT JOIN `user_to_store` u2s ON (`users`.`id` = `u2s`.`user_id`) WHERE `store_id` = ? AND `group_id` = ?");
		$statement->execute(array($store_id, $group_id));
		return $statement->rowCount();

	}
}