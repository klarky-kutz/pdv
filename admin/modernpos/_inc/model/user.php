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
| WEBSITE:			http://ITsolution24.com
| ----------------------------------------------------------------------------
*/
class ModelUser extends Model 
{
	public function addUser($data) 
	{
		static $__hasTenantCol = null;
		if ($__hasTenantCol === null) {
			try {
				$st = $this->db->query("SHOW COLUMNS FROM users LIKE 'tenant_id'");
				$__hasTenantCol = $st && $st->rowCount() > 0;
			} catch (Throwable $e) {
				$__hasTenantCol = false;
			}
		}

		$password = password_hash($data['password'], PASSWORD_DEFAULT);
		$status = isset($data['status']) ? (int)$data['status'] : 1;
		$sortOrder = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
		$tenantId = isset($data['tenant_id']) ? (int)$data['tenant_id'] : 0;
		$tenantIdParam = $tenantId > 0 ? $tenantId : null;

		// SaaS: valida grupo no escopo do tenant (global | plano do tenant | tenant)
		try {
			$groupId = isset($data['group_id']) ? (int)$data['group_id'] : 0;
			if ($__hasTenantCol && $tenantId > 0 && $groupId > 0) {
				$this->saasAssertGroupAllowedForTenant($groupId, $tenantId);
			}
		} catch (Throwable $e) {
			throw $e;
		}

		// Inserir usuário (sanando dívida técnica: tenant_id + status)
		if ($__hasTenantCol) {
			$statement = $this->db->prepare(
				"INSERT INTO `users` (username, email, mobile, password, group_id, dob, user_image, status, tenant_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
			);
			$statement->execute([
				$data['username'],
				$data['email'],
				$data['mobile'],
				$password,
				(int)$data['group_id'],
				$data['dob'],
				$data['user_image'],
				$status,
				$tenantIdParam,
				date_time(),
			]);
		} else {
			$statement = $this->db->prepare(
				"INSERT INTO `users` (username, email, mobile, password, group_id, dob, user_image, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
			);
			$statement->execute([
				$data['username'],
				$data['email'],
				$data['mobile'],
				$password,
				(int)$data['group_id'],
				$data['dob'],
				$data['user_image'],
				$status,
				date_time(),
			]);
		}

		$id = $this->db->lastInsertId();

		// Inserir vínculos com loja com status/sort_order explícitos
		if (isset($data['user_store'])) {
			foreach ($data['user_store'] as $store_id) {
				$store_id = (int)$store_id;
				if ($store_id <= 0) continue;
				$statement = $this->db->prepare(
					"INSERT INTO `user_to_store` (`user_id`, `store_id`, `status`, `sort_order`) VALUES (?, ?, ?, ?)"
				);
				$statement->execute([(int)$id, $store_id, $status, $sortOrder]);
			}
		}

		$this->updateStatus($id, $status);
		$this->updateSortOrder($id, $sortOrder);

		return $id;    
	}

	public function updateStatus($id, $status, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();

		$statement = $this->db->prepare("UPDATE `user_to_store` SET `status` = ? WHERE `store_id` = ? AND `user_id` = ?");
		$statement->execute(array((int)$status, $store_id, (int)$id));
	}

	public function updateSortOrder($id, $sort_order, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();

		$statement = $this->db->prepare("UPDATE `user_to_store` SET `sort_order` = ? WHERE `store_id` = ? AND `user_id` = ?");
		$statement->execute(array((int)$sort_order, $store_id, (int)$id));
	}		

	public function editUser($id, $data) 
	{    	
		static $__hasTenantCol = null;
		if ($__hasTenantCol === null) {
			try {
				$st = $this->db->query("SHOW COLUMNS FROM users LIKE 'tenant_id'");
				$__hasTenantCol = $st && $st->rowCount() > 0;
			} catch (Throwable $e) {
				$__hasTenantCol = false;
			}
		}

		$status = isset($data['status']) ? (int)$data['status'] : 1;
		$sortOrder = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
		$tenantId = isset($data['tenant_id']) ? (int)$data['tenant_id'] : 0;
		$tenantIdParam = $tenantId > 0 ? $tenantId : null;

		// SaaS: valida grupo no escopo do tenant (global | plano do tenant | tenant)
		try {
			$groupId = isset($data['group_id']) ? (int)$data['group_id'] : 0;
			$effectiveTenantId = $tenantId;
			if ($__hasTenantCol && $effectiveTenantId <= 0) {
				$st = $this->db->prepare('SELECT tenant_id FROM users WHERE id = ? LIMIT 1');
				$st->execute([(int)$id]);
				$effectiveTenantId = (int)$st->fetchColumn();
			}
			if ($__hasTenantCol && $effectiveTenantId > 0 && $groupId > 0) {
				$this->saasAssertGroupAllowedForTenant($groupId, $effectiveTenantId);
			}
		} catch (Throwable $e) {
			throw $e;
		}

		if ($__hasTenantCol) {
			$statement = $this->db->prepare(
				"UPDATE `users` SET `username` = ?, `email` = ?, `mobile` = ?, `group_id` = ?, `dob` = ?, `user_image` = ?, `status` = ?, `tenant_id` = COALESCE(?, `tenant_id`) WHERE `id` = ?"
			);
			$statement->execute([
				$data['username'],
				$data['email'],
				$data['mobile'],
				(int)$data['group_id'],
				$data['dob'],
				$data['user_image'],
				$status,
				$tenantIdParam,
				$id,
			]);
		} else {
			$statement = $this->db->prepare(
				"UPDATE `users` SET `username` = ?, `email` = ?, `mobile` = ?, `group_id` = ?, `dob` = ?, `user_image` = ?, `status` = ? WHERE `id` = ?"
			);
			$statement->execute([
				$data['username'],
				$data['email'],
				$data['mobile'],
				(int)$data['group_id'],
				$data['dob'],
				$data['user_image'],
				$status,
				$id,
			]);
		}

		// Delete store data belongs to the user
		$statement = $this->db->prepare("DELETE FROM `user_to_store` WHERE `user_id` = ?");
		$statement->execute([(int)$id]);
		
		// Insert user into store (status/sort_order explícitos)
		if (isset($data['user_store'])) {
			foreach ($data['user_store'] as $store_id) {
				$store_id = (int)$store_id;
				if ($store_id <= 0) continue;
				$statement = $this->db->prepare(
					"INSERT INTO `user_to_store` (`user_id`, `store_id`, `status`, `sort_order`) VALUES (?, ?, ?, ?)"
				);
				$statement->execute([(int)$id, $store_id, $status, $sortOrder]);
			}
		}

		$this->updateStatus($id, $status);
		$this->updateSortOrder($id, $sortOrder);

		return $id;
	}

	private function saasGetTenantPlanId(int $tenantId): int
	{
		try {
			$st = $this->db->prepare('SELECT plan_id FROM tenants WHERE tenant_id = ? LIMIT 1');
			$st->execute([(int)$tenantId]);
			return (int)$st->fetchColumn();
		} catch (Throwable $e) {
			return 0;
		}
	}

	private function saasGetGroupRow(int $groupId): ?array
	{
		try {
			$st = $this->db->prepare('SELECT * FROM user_group WHERE group_id = ? LIMIT 1');
			$st->execute([(int)$groupId]);
			$row = $st->fetch(PDO::FETCH_ASSOC);
			return $row ?: null;
		} catch (Throwable $e) {
			return null;
		}
	}

	private function saasAssertGroupAllowedForTenant(int $groupId, int $tenantId): void
	{
		$group = $this->saasGetGroupRow($groupId);
		if (!$group) {
			throw new Exception('Grupo selecionado não é válido.');
		}

		$tenantPlanId = $this->saasGetTenantPlanId($tenantId);

		$groupTenantId = isset($group['tenant_id']) ? (int)$group['tenant_id'] : 0;
		$groupTenantScope = isset($group['tenant_scope']) ? (int)$group['tenant_scope'] : 0;
		$groupPlanId = isset($group['plan_id']) ? (int)$group['plan_id'] : 0;

		$isGlobal = ($groupTenantId <= 0 && $groupTenantScope <= 0 && $groupPlanId <= 0);
		$isTenant = ($groupTenantId > 0 && $groupTenantId === (int)$tenantId);
		$isPlan = ($groupTenantId <= 0 && $tenantPlanId > 0) && (
			($groupPlanId > 0 && $groupPlanId === $tenantPlanId) ||
			($groupTenantScope > 0 && $groupTenantScope === $tenantPlanId)
		);

		if (!$isGlobal && !$isTenant && !$isPlan) {
			throw new Exception('Grupo selecionado não é permitido para este tenant.');
		}
	}

	public function deleteUser($id) 
	{
    	$statement = $this->db->prepare("DELETE FROM `users` WHERE `id` = ? LIMIT 1");
    	$statement->execute(array($id));
        return $id;
	}

	public function getUser($id, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();

		$statement = $this->db->prepare("SELECT `users`.*, `ug`.`slug` as `group_name`, `ug`.`sort_order` FROM `users`
			LEFT JOIN `user_to_store` as u2s ON (`users`.`id` = `u2s`.`user_id`)  
			LEFT JOIN `user_group` as ug ON (`users`.`group_id` = `ug`.`group_id`)  
	    	WHERE `u2s`.`store_id` = ? AND `users`.`id` = ?");
	  	$statement->execute(array($store_id, $id));
		$user = $statement->fetch(PDO::FETCH_ASSOC);
		if (!$user) {
	    	return array();
	    }
		
		// Fetch stores related to users
	    $statement = $this->db->prepare("SELECT `store_id` FROM `user_to_store` WHERE `user_id` = ?");
	    $statement->execute(array($id));
	    $all_stores = $statement->fetchAll(PDO::FETCH_ASSOC);
	    $stores = array();
	    foreach ($all_stores as $store) {
	    	$stores[] = $store['store_id'];
	    }

	    $user['stores'] = $stores;

	    return $user;
	}

	public function getUsers($data = array(), $store_id = null) {

		$store_id = $store_id ? $store_id : store_id();

		$sql = "SELECT `users`.`id`,
GROUP_CONCAT(DISTINCT `users`.`group_id`) AS group_id,
GROUP_CONCAT(DISTINCT `users`.`username`) AS username,
GROUP_CONCAT(DISTINCT `users`.`email`) AS email,
GROUP_CONCAT(DISTINCT `users`.`mobile`) AS mobile,
GROUP_CONCAT(DISTINCT `users`.`dob`) AS dob,
GROUP_CONCAT(DISTINCT `users`.`sex`) AS sex,
GROUP_CONCAT(DISTINCT `users`.`password`) AS password,
GROUP_CONCAT(DISTINCT `users`.`pass_reset_code`) AS pass_reset_code,
GROUP_CONCAT(DISTINCT `users`.`reset_code_time`) AS reset_code_time,
GROUP_CONCAT(DISTINCT `users`.`login_try`) AS login_try,
GROUP_CONCAT(DISTINCT `users`.`last_login`) AS last_login,
GROUP_CONCAT(DISTINCT `users`.`ip`) AS ip,
GROUP_CONCAT(DISTINCT `users`.`address`) AS address,
GROUP_CONCAT(DISTINCT `users`.`preference`) AS preference,
GROUP_CONCAT(DISTINCT `users`.`user_image`) AS user_image,
GROUP_CONCAT(DISTINCT `users`.`created_at`) AS created_at,
GROUP_CONCAT(DISTINCT `users`.`updated_at`) AS updated_at,
GROUP_CONCAT(DISTINCT `u2s`.`u2s_id`) AS u2s_id,
GROUP_CONCAT(DISTINCT `u2s`.`user_id`) AS user_id,
GROUP_CONCAT(DISTINCT `u2s`.`store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `u2s`.`status`) AS status,
GROUP_CONCAT(DISTINCT `u2s`.`sort_order`) AS sort_orde 
		FROM `users` LEFT JOIN `user_to_store` as `u2s` ON (`users`.`id` = `u2s`.`user_id`) 
			WHERE `u2s`.`store_id` = ? AND `u2s`.`status` = ?";

		if (isset($data['filter_name'])) {
			$sql .= " AND `username` LIKE '" . $data['filter_name'] . "%'";
		}

		if (isset($data['filter_email'])) {
			$sql .= " AND `email` LIKE '" . $data['filter_email'] . "%'";
		}

		if (isset($data['filter_mobile'])) {
			$sql .= " AND `mobile` LIKE '" . $data['filter_mobile'] . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$sql .= " AND `status` = '" . (int)$data['filter_status'] . "'";
		}

		$sql .= " GROUP BY `id`";

		$sort_data = array(
			'username'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY `id`";
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
		$statement->execute(array($store_id, 1));
		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getBestUser($field, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$statement = $this->db->prepare("SELECT
GROUP_CONCAT(DISTINCT `selling_info`.`info_id`) AS info_id,
GROUP_CONCAT(DISTINCT `selling_info`.`invoice_id`) AS invoice_id,
GROUP_CONCAT(DISTINCT `selling_info`.`edit_counter`) AS edit_counter,
GROUP_CONCAT(DISTINCT `selling_info`.`inv_type`) AS inv_type,
GROUP_CONCAT(DISTINCT `selling_info`.`store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `selling_info`.`customer_id`) AS customer_id,
GROUP_CONCAT(DISTINCT `selling_info`.`customer_mobile`) AS customer_mobile,
GROUP_CONCAT(DISTINCT `selling_info`.`ref_invoice_id`) AS ref_invoice_id,
GROUP_CONCAT(DISTINCT `selling_info`.`ref_user_id`) AS ref_user_id,
GROUP_CONCAT(DISTINCT `selling_info`.`invoice_note`) AS invoice_note,
GROUP_CONCAT(DISTINCT `selling_info`.`total_items`) AS total_items,
GROUP_CONCAT(DISTINCT `selling_info`.`is_installment`) AS is_installment,
GROUP_CONCAT(DISTINCT `selling_info`.`status`) AS status,
GROUP_CONCAT(DISTINCT `selling_info`.`pmethod_id`) AS pmethod_id,
GROUP_CONCAT(DISTINCT `selling_info`.`payment_status`) AS payment_status,
GROUP_CONCAT(DISTINCT `selling_info`.`checkout_status`) AS checkout_status,
GROUP_CONCAT(DISTINCT `selling_info`.`created_by`) AS created_by,
GROUP_CONCAT(DISTINCT `selling_info`.`created_at`) AS created_at,
GROUP_CONCAT(DISTINCT `selling_info`.`updated_at`) AS updated_at,
GROUP_CONCAT(DISTINCT `selling_price`.`price_id`) AS price_id,
GROUP_CONCAT(DISTINCT `selling_price`.`invoice_id`) AS invoice_id,
GROUP_CONCAT(DISTINCT `selling_price`.`store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `selling_price`.`subtotal`) AS subtotal,
GROUP_CONCAT(DISTINCT `selling_price`.`discount_type`) AS discount_type,
GROUP_CONCAT(DISTINCT `selling_price`.`discount_amount`) AS discount_amount,
GROUP_CONCAT(DISTINCT `selling_price`.`interest_amount`) AS interest_amount,
GROUP_CONCAT(DISTINCT `selling_price`.`interest_percentage`) AS interest_percentage,
GROUP_CONCAT(DISTINCT `selling_price`.`item_tax`) AS item_tax,
GROUP_CONCAT(DISTINCT `selling_price`.`order_tax`) AS order_tax,
GROUP_CONCAT(DISTINCT `selling_price`.`cgst`) AS cgst,
GROUP_CONCAT(DISTINCT `selling_price`.`sgst`) AS sgst,
GROUP_CONCAT(DISTINCT `selling_price`.`igst`) AS igst,
GROUP_CONCAT(DISTINCT `selling_price`.`total_purchase_price`) AS total_purchase_price,
GROUP_CONCAT(DISTINCT `selling_price`.`shipping_type`) AS shipping_type,
GROUP_CONCAT(DISTINCT `selling_price`.`shipping_amount`) AS shipping_amount,
GROUP_CONCAT(DISTINCT `selling_price`.`others_charge`) AS others_charge,
GROUP_CONCAT(DISTINCT `selling_price`.`payable_amount`) AS payable_amount,
GROUP_CONCAT(DISTINCT `selling_price`.`paid_amount`) AS paid_amount,
GROUP_CONCAT(DISTINCT `selling_price`.`due`) AS due,
GROUP_CONCAT(DISTINCT `selling_price`.`due_paid`) AS due_paid,
GROUP_CONCAT(DISTINCT `selling_price`.`return_amount`) AS return_amount,
GROUP_CONCAT(DISTINCT `selling_price`.`balance`) AS balance,
GROUP_CONCAT(DISTINCT `selling_price`.`profit`) AS profit,
GROUP_CONCAT(DISTINCT `selling_price`.`previous_due`) AS previous_due,
GROUP_CONCAT(DISTINCT `selling_price`.`prev_due_paid`) AS prev_due_paid,
GROUP_CONCAT(DISTINCT `users`.`id`) AS id,
GROUP_CONCAT(DISTINCT `users`.`group_id`) AS group_id,
GROUP_CONCAT(DISTINCT `users`.`username`) AS username,
GROUP_CONCAT(DISTINCT `users`.`email`) AS email,
GROUP_CONCAT(DISTINCT `users`.`mobile`) AS mobile,
GROUP_CONCAT(DISTINCT `users`.`dob`) AS dob,
GROUP_CONCAT(DISTINCT `users`.`sex`) AS sex,
GROUP_CONCAT(DISTINCT `users`.`password`) AS password,
GROUP_CONCAT(DISTINCT `users`.`pass_reset_code`) AS pass_reset_code,
GROUP_CONCAT(DISTINCT `users`.`reset_code_time`) AS reset_code_time,
GROUP_CONCAT(DISTINCT `users`.`login_try`) AS login_try,
GROUP_CONCAT(DISTINCT `users`.`last_login`) AS last_login,
GROUP_CONCAT(DISTINCT `users`.`ip`) AS ip,
GROUP_CONCAT(DISTINCT `users`.`address`) AS address,
GROUP_CONCAT(DISTINCT `users`.`preference`) AS preference,
GROUP_CONCAT(DISTINCT `users`.`user_image`) AS user_image,
GROUP_CONCAT(DISTINCT `users`.`created_at`) AS created_at,
GROUP_CONCAT(DISTINCT `users`.`updated_at`) AS updated_at,
SUM(`selling_price`.`payable_amount`) as total 
			FROM `selling_info` 
			LEFT JOIN `users` ON (`selling_info`.`created_by` = `users`.`id`)
			LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
			WHERE `selling_info`.`store_id` = ?
			GROUP BY `selling_info`.`created_by` ORDER BY `total` DESC");
		$statement->execute(array($store_id));
		$user = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($user[$field]) ? $user[$field] : null;
	}

	public function getRecentUsers($limit, $store_id = null)
	{
		$store_id = $store_id ? $store_id : store_id();
		$statement = $this->db->prepare("SELECT users.* FROM `selling_info` 
			LEFT JOIN `users` ON (`selling_info`.`created_by` = `users`.`id`) 
			LEFT JOIN `user_to_store` as u2s ON (`selling_info`.`created_by` = `u2s`.`user_id`)
			where `selling_info`.`store_id` = ? AND `u2s`.`status` = ?
			GROUP BY `selling_info`.`created_by`
			ORDER BY `info_id` DESC 
			LIMIT $limit"
			);
	    $statement->execute(array($store_id, 1));
	    return $statement->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getTotalpurchaseAmount($id, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$statement = $this->db->prepare("SELECT
GROUP_CONCAT(DISTINCT `selling_info`.`info_id`) AS info_id,
GROUP_CONCAT(DISTINCT `selling_info`.`invoice_id`) AS invoice_id,
GROUP_CONCAT(DISTINCT `selling_info`.`edit_counter`) AS edit_counter,
GROUP_CONCAT(DISTINCT `selling_info`.`inv_type`) AS inv_type,
GROUP_CONCAT(DISTINCT `selling_info`.`store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `selling_info`.`customer_id`) AS customer_id,
GROUP_CONCAT(DISTINCT `selling_info`.`customer_mobile`) AS customer_mobile,
GROUP_CONCAT(DISTINCT `selling_info`.`ref_invoice_id`) AS ref_invoice_id,
GROUP_CONCAT(DISTINCT `selling_info`.`ref_user_id`) AS ref_user_id,
GROUP_CONCAT(DISTINCT `selling_info`.`invoice_note`) AS invoice_note,
GROUP_CONCAT(DISTINCT `selling_info`.`total_items`) AS total_items,
GROUP_CONCAT(DISTINCT `selling_info`.`is_installment`) AS is_installment,
GROUP_CONCAT(DISTINCT `selling_info`.`status`) AS status,
GROUP_CONCAT(DISTINCT `selling_info`.`pmethod_id`) AS pmethod_id,
GROUP_CONCAT(DISTINCT `selling_info`.`payment_status`) AS payment_status,
GROUP_CONCAT(DISTINCT `selling_info`.`checkout_status`) AS checkout_status,
GROUP_CONCAT(DISTINCT `selling_info`.`created_by`) AS created_by,
GROUP_CONCAT(DISTINCT `selling_info`.`created_at`) AS created_at,
GROUP_CONCAT(DISTINCT `selling_info`.`updated_at`) AS updated_at,
GROUP_CONCAT(DISTINCT `selling_price`.`price_id`) AS price_id,
GROUP_CONCAT(DISTINCT `selling_price`.`invoice_id`) AS invoice_id,
GROUP_CONCAT(DISTINCT `selling_price`.`store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `selling_price`.`subtotal`) AS subtotal,
GROUP_CONCAT(DISTINCT `selling_price`.`discount_type`) AS discount_type,
GROUP_CONCAT(DISTINCT `selling_price`.`discount_amount`) AS discount_amount,
GROUP_CONCAT(DISTINCT `selling_price`.`interest_amount`) AS interest_amount,
GROUP_CONCAT(DISTINCT `selling_price`.`interest_percentage`) AS interest_percentage,
GROUP_CONCAT(DISTINCT `selling_price`.`item_tax`) AS item_tax,
GROUP_CONCAT(DISTINCT `selling_price`.`order_tax`) AS order_tax,
GROUP_CONCAT(DISTINCT `selling_price`.`cgst`) AS cgst,
GROUP_CONCAT(DISTINCT `selling_price`.`sgst`) AS sgst,
GROUP_CONCAT(DISTINCT `selling_price`.`igst`) AS igst,
GROUP_CONCAT(DISTINCT `selling_price`.`total_purchase_price`) AS total_purchase_price,
GROUP_CONCAT(DISTINCT `selling_price`.`shipping_type`) AS shipping_type,
GROUP_CONCAT(DISTINCT `selling_price`.`shipping_amount`) AS shipping_amount,
GROUP_CONCAT(DISTINCT `selling_price`.`others_charge`) AS others_charge,
GROUP_CONCAT(DISTINCT `selling_price`.`payable_amount`) AS payable_amount,
GROUP_CONCAT(DISTINCT `selling_price`.`paid_amount`) AS paid_amount,
GROUP_CONCAT(DISTINCT `selling_price`.`due`) AS due,
GROUP_CONCAT(DISTINCT `selling_price`.`due_paid`) AS due_paid,
GROUP_CONCAT(DISTINCT `selling_price`.`return_amount`) AS return_amount,
GROUP_CONCAT(DISTINCT `selling_price`.`balance`) AS balance,
GROUP_CONCAT(DISTINCT `selling_price`.`profit`) AS profit,
GROUP_CONCAT(DISTINCT `selling_price`.`previous_due`) AS previous_due,
GROUP_CONCAT(DISTINCT `selling_price`.`prev_due_paid`) AS prev_due_paid,
GROUP_CONCAT(DISTINCT `users`.`id`) AS id,
GROUP_CONCAT(DISTINCT `users`.`group_id`) AS group_id,
GROUP_CONCAT(DISTINCT `users`.`username`) AS username,
GROUP_CONCAT(DISTINCT `users`.`email`) AS email,
GROUP_CONCAT(DISTINCT `users`.`mobile`) AS mobile,
GROUP_CONCAT(DISTINCT `users`.`dob`) AS dob,
GROUP_CONCAT(DISTINCT `users`.`sex`) AS sex,
GROUP_CONCAT(DISTINCT `users`.`password`) AS password,
GROUP_CONCAT(DISTINCT `users`.`pass_reset_code`) AS pass_reset_code,
GROUP_CONCAT(DISTINCT `users`.`reset_code_time`) AS reset_code_time,
GROUP_CONCAT(DISTINCT `users`.`login_try`) AS login_try,
GROUP_CONCAT(DISTINCT `users`.`last_login`) AS last_login,
GROUP_CONCAT(DISTINCT `users`.`ip`) AS ip,
GROUP_CONCAT(DISTINCT `users`.`address`) AS address,
GROUP_CONCAT(DISTINCT `users`.`preference`) AS preference,
GROUP_CONCAT(DISTINCT `users`.`user_image`) AS user_image,
GROUP_CONCAT(DISTINCT `users`.`created_at`) AS created_at,
GROUP_CONCAT(DISTINCT `users`.`updated_at`) AS updated_at,
SUM(`selling_price`.`payable_amount`) as total FROM `selling_info` 
			LEFT JOIN `users` ON (`selling_info`.`created_by` = `users`.`id`)
			LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`)
			where `users`.`id` = ? AND `selling_info`.`store_id` = ? 
			ORDER BY `total` DESC");
		$statement->execute(array($id, $store_id));
		$user = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($user['total']) ? $user['total'] : '0';
	}

	public function getTotalInvoiceNumber($id = null, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		if ($id) {
			$statement = $this->db->prepare("SELECT * FROM `selling_info` 
				WHERE `created_by` = ? AND `store_id` = ?");
			$statement->execute(array($id, store_id()));
		}
		else {
			$statement = $this->db->prepare("SELECT * FROM `selling_info` WHERE `store_id` = ?");
			$statement->execute(array($store_id));
		}
		return $statement->rowCount();
	}

	public function getBelongsStore($id)
	{
		$statement = $this->db->prepare("SELECT * FROM `user_to_store` WHERE `user_id` = ?");
		$statement->execute(array($id));
		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}

	public function totalToday($store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`u2s`.`store_id` = '$store_id'";
		$from = date('Y-m-d');
		$to = date('Y-m-d');
		if (($from && ($to == false)) || ($from == $to)) {
			$day = date('d', strtotime($from));
			$month = date('m', strtotime($from));
			$year = date('Y', strtotime($from));
			$where_query .= " AND DAY(`users`.`created_at`) = $day";
			$where_query .= " AND MONTH(`users`.`created_at`) = $month";
			$where_query .= " AND YEAR(`users`.`created_at`) = $year";
		} else {
			$from = date('Y-m-d H:i:s', strtotime($from.' '. '00:00:00')); 
			$to = date('Y-m-d H:i:s', strtotime($to.' '. '23:59:59'));
			$where_query .= " AND users.created_at >= '{$from}' AND users.created_at <= '{$to}'";
		}

		$statement = $this->db->prepare("SELECT * FROM `users` LEFT JOIN `user_to_store` u2s ON (`users`.`id` = `u2s`.`user_id`) WHERE $where_query");
		$statement->execute(array());
		return $statement->rowCount();
	}

	public function total($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`u2s`.`store_id` = '$store_id'";
		if ($from) {
			$from = $from ? $from : date('Y-m-d');
			$to = $to ? $to : date('Y-m-d');
			if (($from && ($to == false)) || ($from == $to)) {
				$day = date('d', strtotime($from));
				$month = date('m', strtotime($from));
				$year = date('Y', strtotime($from));
				$where_query .= " AND DAY(`users`.`created_at`) = $day";
				$where_query .= " AND MONTH(`users`.`created_at`) = $month";
				$where_query .= " AND YEAR(`users`.`created_at`) = $year";
			} else {
				$from = date('Y-m-d H:i:s', strtotime($from.' '. '00:00:00')); 
				$to = date('Y-m-d H:i:s', strtotime($to.' '. '23:59:59'));
				$where_query .= " AND users.created_at >= '{$from}' AND users.created_at <= '{$to}'";
			}
		}
		$statement = $this->db->prepare("SELECT * FROM `users` LEFT JOIN `user_to_store` u2s ON (`users`.`id` = `u2s`.`user_id`) WHERE $where_query");
		$statement->execute(array());
		return $statement->rowCount();
	}

	public function getAvatar($sex)
	{
		switch ($sex) {
			case 1:
				$avatar = 'avatar';
				break;
			case 2:
				$avatar = 'avatar-female';
				break;
			default:
				$avatar = 'avatar-others';
				break;
		}
		return $avatar;
	}
}