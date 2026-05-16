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
class ModelStore extends Model 
{
	public function addStore($data) 
	{
		// Resolve tenant_id se disponível
		$tenant_id = isset($data['tenant_id']) ? (int)$data['tenant_id'] : 0;
		if ($tenant_id <= 0 && isset($_SESSION['tenant_id']) && $_SESSION['tenant_id']) {
			$tenant_id = (int)$_SESSION['tenant_id'];
		}
		if ($tenant_id <= 0 && function_exists('user_id')) {
			$uid = (int)user_id();
			if ($uid > 0) {
				$st = $this->db->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
				$st->execute(array($uid));
				$tmp = $st->fetchColumn();
				if ($tmp) {
					$tenant_id = (int)$tmp;
				}
			}
		}

		if ($tenant_id > 0) {
			$statement = $this->db->prepare("INSERT INTO `stores` (name, code_name, mobile, email, country, vat_reg_no, zip_code, cashier_id, address, logo, favicon, sound_effect, status, sort_order, receipt_printer, remote_printing, auto_print, tenant_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
			$statement->execute(array($data['name'], $data['code_name'], $data['mobile'], $data['email'], $data['country'], $data['vat_reg_no'], $data['zip_code'], (int)$data['cashier_id'], $data['address'], $data['logo'], $data['favicon'], $data['sound_effect'], (int)$data['status'], (int)$data['sort_order'], $data['receipt_printer'], $data['remote_printing'], (int)$data['auto_print'], $tenant_id, date_time()));
		} else {
			$statement = $this->db->prepare("INSERT INTO `stores` (name, code_name, mobile, email, country, vat_reg_no, zip_code, cashier_id, address, logo, favicon, sound_effect, status, sort_order, receipt_printer, remote_printing, auto_print, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
			$statement->execute(array($data['name'], $data['code_name'], $data['mobile'], $data['email'], $data['country'], $data['vat_reg_no'], $data['zip_code'], (int)$data['cashier_id'], $data['address'], $data['logo'], $data['favicon'], $data['sound_effect'], (int)$data['status'], (int)$data['sort_order'], $data['receipt_printer'], $data['remote_printing'], (int)$data['auto_print'], date_time()));
		}

		$store_id = $this->db->lastInsertId();
		
		// Create sample data for new store (templates, category, supplier, brand, bank account)
		error_log('[SAMPLE_DATA_DEBUG] Checking if create_sample_data_for_store exists...');
		if (function_exists('create_sample_data_for_store')) {
			error_log('[SAMPLE_DATA_DEBUG] Function exists, calling for store ID: ' . $store_id);
			try {
				$result = create_sample_data_for_store($store_id);
				error_log('[SAMPLE_DATA_DEBUG] Function returned: ' . ($result ? 'true' : 'false'));
				error_log('Sample data created successfully for store ID: ' . $store_id);
			} catch (Throwable $e) {
				error_log('[SAMPLE_DATA_DEBUG] Throwable caught: ' . $e->getMessage());
				error_log('[SAMPLE_DATA_DEBUG] Stack trace: ' . $e->getTraceAsString());
				error_log('Failed to create sample data for store ID ' . $store_id . ': ' . $e->getMessage());
			}
		} else {
			error_log('[SAMPLE_DATA_DEBUG] Function create_sample_data_for_store does NOT exist!');
		}
		
		return $store_id;
	}

	public function editStore($store_id, $data) 
	{
    	$statement = $this->db->prepare("UPDATE `stores` SET `name` = ?, `code_name` = ?, `mobile` = ?, `email` = ?, `country` = ?, `vat_reg_no` = ?,  `zip_code` = ?, `cashier_id` = ?, `address` = ?, `sound_effect` = ?, `status` = ?, `sort_order` = ?, `receipt_printer` = ?, `remote_printing` = ?, `auto_print` = ?, `deposit_account_id` = ? WHERE `store_id` = ? ");
    	$statement->execute(array($data['name'], $data['code_name'], $data['mobile'], $data['email'], $data['country'], $data['vat_reg_no'], $data['zip_code'], $data['cashier_id'], $data['address'], $data['sound_effect'], $data['status'], $data['sort_order'], $data['receipt_printer'], $data['remote_printing'], $data['auto_print'], (int) $data['deposit_account_id'], $store_id));


    	$statement = $this->db->prepare("UPDATE `customers` SET `customer_address` = ?,`customer_city` = ?, `customer_state` = ?, `customer_country` = ?");
    	$statement->execute(array($data['address'], $data['preference']['business_state'], $data['preference']['business_state'], $data['country']));

    	return $store_id;
	}

	public function editPreference($store_id, $preference = array())
	{
		if (empty($preference)) {
			$preference = array();
		}
		
		// Update timezone in _init.php
		$timezone = $preference['timezone'];
		$index_path = ROOT . '/_init.php';
		chmod($index_path, 0777);
		if (is_writable($index_path) === false) {
			throw new Exception(trans('timezone_not_change_in_init.php'));
			return false;
		} else {
			$file = $index_path;
			$filecontent = "$" . "timezone = '". $timezone ."';";
			$fileArray = array(3 => $filecontent);
			replace_lines($file, $fileArray);
			chmod($index_path, 0644);
		}

		$statement = $this->db->prepare("UPDATE `stores` SET `preference` = ? WHERE `store_id` = ?");
    	$statement->execute(array(serialize($preference), $store_id));

    	return $store_id;
	}

	public function deleteStore($store_id) 
	{
    	$statement = $this->db->prepare("DELETE FROM `stores` WHERE `store_id` = ? LIMIT 1");
    	$statement->execute(array($store_id));
        return $store_id;
	}

	public function getStore($store_id) 
	{
		$statement = $this->db->prepare("SELECT * FROM `stores` WHERE `store_id` = ?");
	  	$statement->execute(array($store_id));

	  	return $statement->fetch(PDO::FETCH_ASSOC);
	}

	public function getStoreIDs($data = array()) 
	{
		$ids = array();
		foreach ($this->getStores($ids) as $store) {
			$ids[] = $store['store_id'];
 		}
 		return $ids;
	}

	public function getStores($data = array()) 
	{
		$sql = "SELECT * FROM `stores` WHERE 1=1";

		// SaaS: filtra por tenant_id quando disponível
		$tenant_id = 0;
		if (isset($_SESSION['tenant_id']) && $_SESSION['tenant_id']) {
			$tenant_id = (int)$_SESSION['tenant_id'];
		} else {
			// Fallback: busca tenant_id diretamente do usuário logado
			if (function_exists('user_id')) {
				$uid = (int)user_id();
				if ($uid > 0) {
					$st = $this->db->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
					$st->execute(array($uid));
					$tmp = $st->fetchColumn();
					if ($tmp) {
						$tenant_id = (int)$tmp;
					}
				}
			}
		}
		if ($tenant_id > 0) {
			$sql .= " AND `tenant_id` = " . (int)$tenant_id;
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
			$sql .= " ORDER BY `store_id`";
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

	public function getStoreIdByCode($code_name)
	{
		$statement = $this->db->prepare("SELECT `store_id` FROM `stores` WHERE `code_name` = ?");
		$statement->execute(array($code_name));
		$row = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($row['store_id']) ? $row['store_id'] : null;
	}

	public function total() 
	{
		$statement = $this->db->prepare("SELECT * FROM `stores` WHERE `status` = ?");
		$statement->execute(array(1));

		return $statement->rowCount();
	}

	public function getCashiers($store_id) 
	{
		// Obter tenant_id do usuário atual para filtrar apenas usuários do mesmo tenant
		$tenant_id = 0;
		if (isset($_SESSION['tenant_id']) && $_SESSION['tenant_id']) {
			$tenant_id = (int)$_SESSION['tenant_id'];
		} else {
			// Fallback: busca tenant_id diretamente do usuário logado
			if (function_exists('user_id')) {
				$uid = (int)user_id();
				if ($uid > 0) {
					$st = $this->db->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
					$st->execute(array($uid));
					$tmp = $st->fetchColumn();
					if ($tmp) {
						$tenant_id = (int)$tmp;
					}
				}
			}
		}

		// Se tem tenant_id, lista todos os usuários do mesmo tenant (independente do grupo)
		if ($tenant_id > 0) {
			$statement = $this->db->prepare("
				SELECT DISTINCT u.*, ug.name as group_name 
				FROM `users` u
				LEFT JOIN `user_group` ug ON (u.`group_id` = ug.`group_id`) 
				WHERE u.`tenant_id` = ? 
				AND u.`status` = 1
				ORDER BY u.`username` ASC
			");
			$statement->execute(array($tenant_id));
		} else {
			// Modo single-tenant (legado): mantém comportamento original filtrando por store e grupo
			$statement = $this->db->prepare("
				SELECT u.*, ug.name as group_name 
				FROM `users` u
				LEFT JOIN `user_to_store` u2s ON (u.`id` = u2s.`user_id`) 
				LEFT JOIN `user_group` ug ON (u.`group_id` = ug.`group_id`) 
				WHERE u2s.`store_id` = ? 
				AND u.`status` = 1
				ORDER BY u.`username` ASC
			");
			$statement->execute(array($store_id));
		}

		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getSalesmans($store_id) 
	{
		$statement = $this->db->prepare("SELECT * FROM `users`
		LEFT JOIN `user_to_store` as u2s ON (`users`.`id` = `u2s`.`user_id`) 
		LEFT JOIN `user_group` as ug ON (`users`.`group_id` = `ug`.`group_id`) 
		 WHERE `store_id` = ? AND `ug`.`slug` = ?");
	  	$statement->execute(array($store_id, 'salesman'));
	  	return $statement->fetchAll(PDO::FETCH_ASSOC);
	}
}