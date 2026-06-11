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
class ModelCurrency extends Model 
{
	public function addCurrency($data) 
	{
	   	$statement = $this->db->prepare("INSERT INTO `currency` (title, code, symbol_left, symbol_right, decimal_place, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    	$statement->execute(array($data['title'], $data['code'], $data['symbol_left'], $data['symbol_right'], $data['decimal_place'], date_time()));

    	$currency_id = $this->db->lastInsertId();

    	if (isset($data['currency_store'])) {
			foreach ($data['currency_store'] as $store_id) {
				$statement = $this->db->prepare("INSERT INTO `currency_to_store` SET `currency_id` = ?, `store_id` = ?");
				$statement->execute(array((int)$currency_id, (int)$store_id));
			}
		}

		$this->updateStatus($currency_id, $data['status']);
		$this->updateSortOrder($currency_id, $data['sort_order']);

    	return $currency_id;     
	}

	public function updateStatus($currency_id, $status, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();

		$statement = $this->db->prepare("UPDATE `currency_to_store` SET `status` = ? WHERE `store_id` = ? AND `currency_id` = ?");
		$statement->execute(array((int)$status, $store_id, (int)$currency_id));
	}

	public function updateSortOrder($currency_id, $sort_order, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();

		$statement = $this->db->prepare("UPDATE `currency_to_store` SET `sort_order` = ? WHERE `store_id` = ? AND `currency_id` = ?");
		$statement->execute(array((int)$sort_order, $store_id, (int)$currency_id));
	}

	public function editCurrency($currency_id, $data) 
	{ 
    	$statement = $this->db->prepare("UPDATE `currency` SET `title` = ?, `code` = ?, `symbol_left` = ?, `symbol_right` = ?, `decimal_place` = ? WHERE `currency_id` = ? ");
    	$statement->execute(array($data['title'], $data['code'], $data['symbol_left'], $data['symbol_right'], $data['decimal_place'], $currency_id));

    	// Delete store data balongs to the currency
    	$statement = $this->db->prepare("DELETE FROM `currency_to_store` WHERE `currency_id` = ?");
    	$statement->execute(array($currency_id));
		
		// Insert currency into store
    	if (isset($data['currency_store'])) {
			foreach ($data['currency_store'] as $store_id) {
				$statement = $this->db->prepare("INSERT INTO `currency_to_store` SET `currency_id` = ?, `store_id` = ?");
				$statement->execute(array((int)$currency_id, (int)$store_id));
			}
		}

		$this->updateStatus($currency_id, $data['status']);
		$this->updateSortOrder($currency_id, $data['sort_order']);

    	return $currency_id;
	}

	public function deleteCurrency($currency_id) 
	{    	
    	$statement = $this->db->prepare("DELETE FROM `currency` WHERE `currency_id` = ? LIMIT 1");
    	$statement->execute(array($currency_id));

    	$statement = $this->db->prepare("DELETE FROM `currency_to_store` WHERE `currency_id` = ?");
    	$statement->execute(array($currency_id));	

        return $currency_id;
	}

	public function getCurrency($currency_id, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();

	    $statement = $this->db->prepare("SELECT `currency`.*, `c2s`.`status`, `c2s`.`sort_order` 
	    	FROM `currency` 
	    	LEFT JOIN `currency_to_store` as c2s ON (`currency`.`currency_id` = `c2s`.`currency_id`)  
	    	WHERE `c2s`.`store_id` = ? AND `currency`.`currency_id` = ?");
	    $statement->execute(array($store_id, $currency_id));
	    $currency = $statement->fetch(PDO::FETCH_ASSOC);
		if (!$currency) {
	    	return array();
	    }
		
	    // Fetch stores related to currency
	    $statement = $this->db->prepare("SELECT `store_id` FROM `currency_to_store` WHERE `currency_id` = ?");
	    $statement->execute(array($currency_id));
	    $all_stores = $statement->fetchAll(PDO::FETCH_ASSOC);
	    $stores = array();
	    foreach ($all_stores as $store) {
	    	$stores[] = $store['store_id'];
	    }

	    $currency['stores'] = $stores;

	    return $currency;

	}

	public function getCurrencies($data = array(), $store_id = null) {

		$store_id = $store_id ? $store_id : store_id();

		$sql = "SELECT `currency`.`currency_id`,
GROUP_CONCAT(DISTINCT `currency`.`title`) AS title,
GROUP_CONCAT(DISTINCT `currency`.`code`) AS code,
GROUP_CONCAT(DISTINCT `currency`.`symbol_left`) AS symbol_left,
GROUP_CONCAT(DISTINCT `currency`.`symbol_right`) AS symbol_right,
GROUP_CONCAT(DISTINCT `currency`.`decimal_place`) AS decimal_place,
GROUP_CONCAT(DISTINCT `currency`.`value`) AS value,
GROUP_CONCAT(DISTINCT `currency`.`created_at`) AS created_at,
GROUP_CONCAT(DISTINCT `c2s`.`ca2s_id`) AS ca2s_id,
GROUP_CONCAT(DISTINCT `c2s`.`currency_id`) AS currency_id,
GROUP_CONCAT(DISTINCT `c2s`.`store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `c2s`.`status`) AS status,
GROUP_CONCAT(DISTINCT `c2s`.`sort_order`) AS sort_order
		FROM `currency` LEFT JOIN `currency_to_store` c2s ON (`currency`.`currency_id` = `c2s`.`currency_id`) WHERE `c2s`.`store_id` = ? AND `c2s`.`status` = ?";

		if (isset($data['filter_name'])) {
			$sql .= " AND `title` LIKE '" . $data['filter_name'] . "%'";
		}

		$sql .= " GROUP BY `currency`.`currency_id`";

		$sort_data = array(
			'title'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY title";
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
}