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
class ModelReport extends Model 
{
	public function getTax($type, $from, $to, $store_id = null) 
	{
		$tax = 0;
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = '$store_id'";
		$where_query .= date_range_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`selling_price`.`{$type}`) as total FROM `selling_info` 
		LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
		WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		if ($invoice) {
			$tax = $invoice['total'];
		}
		return $tax ? $tax : 0;
	}

	public function getInOrExclusiveTax($type, $from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = $store_id AND `selling_item`.`tax_method` = '{$type}'";
		$where_query .= date_range_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`selling_item`.`item_tax`) as total, GROUP_CONCAT(DISTINCT `selling_info`.`invoice_id`) AS invoice_id, GROUP_CONCAT(DISTINCT `selling_info`.`created_at`) AS created_at FROM `selling_info` 
			LEFT JOIN `selling_item` ON (`selling_info`.`invoice_id` = `selling_item`.`invoice_id`) 
			WHERE $where_query GROUP BY `selling_info`.`invoice_id`");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getPurchaseTax($type, $from, $to, $store_id = null) 
	{
		$tax = 0;
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`purchase_info`.`store_id` = '$store_id'";
		$where_query .= date_range_filter2($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`purchase_price`.`{$type}`) as total FROM `purchase_info` 
		LEFT JOIN `purchase_price` ON (`purchase_info`.`invoice_id` = `purchase_price`.`invoice_id`) 
		WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		if ($invoice) {
			$tax = $invoice['total'];
		}
		return $tax ? $tax : 0;
	}

	public function getInOrExclusivePurchaseTax($type, $from, $to, $store_id = null) 
	{
		$tax = 0;
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`purchase_info`.`store_id` = $store_id AND `purchase_item`.`tax_method` = '{$type}'";
		$where_query .= date_range_filter2($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`purchase_item`.`item_tax`) as total, GROUP_CONCAT(DISTINCT `purchase_info`.`invoice_id`) AS invoice_id, GROUP_CONCAT(DISTINCT `purchase_info`.`created_at`) AS created_at FROM `purchase_info` 
			LEFT JOIN `purchase_item` ON (`purchase_info`.`invoice_id` = `purchase_item`.`invoice_id`) 
			WHERE $where_query GROUP BY `purchase_info`.`invoice_id`");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		if ($invoice) {
			$tax = $invoice['total'];
		}
		return $tax;
	}

	public function getSellingPrice($from=null, $to=null, $store_id = null) 
	{
		$total = 0;
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = '$store_id'";
		$where_query .= date_range_filter($from, $to);
		$statement = $this->db->prepare("SELECT `selling_info`.`is_installment`, `selling_price`.`payable_amount` as payable_amount FROM `selling_info` 
			LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
			WHERE $where_query");

		$statement->execute(array());
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $invoice) {
			$total += $invoice['payable_amount'];
		}
		return $total;
	}

	public function getInterestAmount($from=null, $to=null, $store_id = null, $invoice_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = '$store_id' AND `is_installment` = 1";
		if ($invoice_id) {
			$where_query .= " AND `selling_info`.`invoice_id` = '$invoice_id'";
		}
		$where_query .= date_range_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`selling_price`.`interest_amount`) as total FROM `selling_info` 
			LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
			WHERE $where_query");
		$statement->execute(array());
		$rows = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($rows['total']) ? $rows['total'] : 0;
	}

	public function getShippingCharge($from=null, $to=null, $store_id = null, $invoice_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = '$store_id'";
		if ($invoice_id) {
			$where_query .= " AND `selling_info`.`invoice_id` = '$invoice_id'";
		}
		$where_query .= date_range_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`selling_price`.`shipping_amount`) as total FROM `selling_info` 
			LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
			WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getPurchaseShippingCharge($from=null, $to=null, $store_id = null, $invoice_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`purchase_info`.`store_id` = '$store_id'";
		if ($invoice_id) {
			$where_query .= " AND `purchase_info`.`invoice_id` = '$invoice_id'";
		}
		$where_query .= date_range_filter2($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`purchase_price`.`shipping_amount`) as total FROM `purchase_info` 
			LEFT JOIN `purchase_price` ON (`purchase_info`.`invoice_id` = `purchase_price`.`invoice_id`) 
			WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getOthersCharge($from=null, $to=null, $store_id = null, $invoice_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = '$store_id'";
		if ($invoice_id) {
			$where_query .= " AND `selling_info`.`invoice_id` = '$invoice_id'";
		}
		$where_query .= date_range_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`selling_price`.`others_charge`) as total FROM `selling_info` 
			LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
			WHERE $where_query");
		$statement->execute(array());
		$rows = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($rows['total']) ? $rows['total'] : 0;
	}

	public function getPurchaseOthersCharge($from=null, $to=null, $store_id = null, $invoice_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`purchase_info`.`store_id` = '$store_id'";
		if ($invoice_id) {
			$where_query .= " AND `purchase_info`.`invoice_id` = '$invoice_id'";
		}
		$where_query .= date_range_filter2($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`purchase_price`.`others_charge`) as total FROM `purchase_info` 
			LEFT JOIN `purchase_price` ON (`purchase_info`.`invoice_id` = `purchase_price`.`invoice_id`) 
			WHERE $where_query");
		$statement->execute(array());
		$rows = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($rows['total']) ? $rows['total'] : 0;
	}

	public function getPurchasePriceOfSell($from=null, $to=null, $store_id = null) 
	{
		$total = 0;
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = '$store_id'";
		$where_query .= date_range_filter($from, $to);
		$statement = $this->db->prepare("SELECT `selling_info`.`invoice_id`, `selling_info`.`is_installment`, `selling_price`.`interest_percentage` as interest_percentage, `selling_price`.`paid_amount`, `selling_item`.`item_total` selling_total, `selling_item`.`item_purchase_price` as purchase_total FROM `selling_info` 
			LEFT JOIN `selling_item` ON `selling_info`.`invoice_id` = `selling_item`.`invoice_id`
			LEFT JOIN `selling_price` ON `selling_info`.`invoice_id` = `selling_price`.`invoice_id`
			WHERE $where_query");
		$statement->execute(array());
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $invoice) {
			if ($invoice['is_installment']) {
				$paid_amount = $invoice['paid_amount'] - $this->getInterestAmount($from,$to,$store_id,$invoice['invoice_id']);
				$selling_total = $invoice['selling_total'];
				$purchase_total = $invoice['purchase_total'];
				$total += $selling_total ? (($purchase_total/$selling_total)*$paid_amount) : 0;
			} else {
				$paid_amount = $invoice['paid_amount'];
				$selling_total = $invoice['selling_total'];
				$purchase_total = $invoice['purchase_total'];
				$total += $selling_total ? (($purchase_total/$selling_total)*$paid_amount) : 0;
			}
		}
		return $total;
	}

	public function getReceivedAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		return $this->getPaidAmount($from, $to, $store_id) + $this->getAnothrDayDueCollectionAmount($from, $to, $store_id);
	}

	public function getSellReceivedAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		return $this->getPaidAmount($from, $to, $store_id);
	}

	public function getPaidAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = '$store_id'";
		$where_query .= date_range_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`selling_price`.`paid_amount`) as total FROM `selling_info` 
			LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
			WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getDiscountAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = '$store_id'";
		$where_query .= date_range_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`selling_price`.`discount_amount`) as total FROM `selling_info` 
			LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`)
			WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getPurchaseDiscountAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`purchase_info`.`store_id` = '$store_id'";
		$where_query .= date_range_filter2($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`purchase_price`.`discount_amount`) as total FROM `purchase_info` 
			LEFT JOIN `purchase_price` ON (`purchase_info`.`invoice_id` = `purchase_price`.`invoice_id`)
			WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getDueAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = '$store_id'";
		$where_query .= date_range_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`selling_price`.`due`) as due FROM `selling_info` 
			LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`)
			WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['due']) ? $invoice['due'] : 0;
	}

	public function getPurchaseDueAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`purchase_info`.`store_id` = '$store_id'";
		$where_query .= date_range_filter2($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`purchase_price`.`due`) as total FROM `purchase_info` 
			LEFT JOIN `purchase_price` ON (`purchase_info`.`invoice_id` = `purchase_price`.`invoice_id`)
			WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getPurchasePrice($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`purchase_info`.`store_id` = '$store_id'";
		$where_query .= date_range_filter2($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`purchase_price`.`payable_amount`) as total FROM `purchase_info` 
			LEFT JOIN `purchase_price` ON (`purchase_info`.`invoice_id` = `purchase_price`.`invoice_id`) 
			WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getPurchaseTotalPaidAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`purchase_info`.`store_id` = ?";
		$where_query .= date_range_filter2($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`purchase_price`.`paid_amount`) as total FROM `purchase_info` 
			LEFT JOIN `purchase_price` ON (`purchase_info`.`invoice_id` = `purchase_price`.`invoice_id`) 
			WHERE $where_query");
		$statement->execute(array($store_id));
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getSellingReturnAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`returns`.`store_id` = '$store_id'";
		$where_query .= date_range_selling_return_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`returns`.`total_amount`) as total FROM `returns` 
			WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getTaxReturnAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`returns`.`store_id` = '$store_id'";
		$where_query .= date_range_selling_return_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`returns`.`item_tax`) as total, SUM(`returns`.`cgst`) as cgst, SUM(`returns`.`sgst`) as sgst, SUM(`returns`.`igst`) as igst FROM `returns` 
			WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return $invoice && $invoice['cgst'] <= 0 && $invoice['sgst'] <= 0 && $invoice['igst'] <= 0 ? $invoice['total'] : 0;
	}

	public function getGSTReturnAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`returns`.`store_id` = '$store_id'";
		$where_query .= date_range_selling_return_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`returns`.`cgst`) as cgst, SUM(`returns`.`sgst`) as sgst, SUM(`returns`.`igst`) as igst FROM `returns` 
			WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return $invoice ? $invoice['cgst'] + $invoice['sgst'] + $invoice['igst'] : 0;
	}

	public function getPurchaseReturnAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`purchase_returns`.`store_id` = '$store_id'";
		$where_query .= date_range_purchase_return_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`purchase_returns`.`total_amount`) as total FROM `purchase_returns` 
			WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getPurchaseTaxReturnAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`purchase_returns`.`store_id` = '$store_id'";
		$where_query .= date_range_purchase_return_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`purchase_returns`.`item_tax`) as total, SUM(`purchase_returns`.`cgst`) as cgst, SUM(`purchase_returns`.`sgst`) as sgst, SUM(`purchase_returns`.`igst`) as igst FROM `purchase_returns` 
			WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return $invoice && $invoice['cgst'] <= 0 && $invoice['sgst'] <= 0 && $invoice['igst'] <= 0 ? $invoice['total'] : 0;
	}

	public function getPurchaseGSTReturnAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`purchase_returns`.`store_id` = '$store_id'";
		$where_query .= date_range_purchase_return_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`purchase_returns`.`cgst`) as cgst, SUM(`purchase_returns`.`sgst`) as sgst, SUM(`purchase_returns`.`igst`) as igst FROM `purchase_returns` 
			WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return $invoice ? $invoice['cgst'] + $invoice['sgst'] + $invoice['igst'] : 0;
	}

	public function getExpenseAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`purchase_info`.`inv_type` = 'expense' AND `purchase_info`.`store_id` = '$store_id'";
		$where_query .= date_range_filter2($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`purchase_price`.`paid_amount`) as total FROM `purchase_info` 
			LEFT JOIN `purchase_price` ON (`purchase_info`.`invoice_id` = `purchase_price`.`invoice_id`) 
			WHERE $where_query");
		$statement->execute(array());
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getDueCollectionAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`type` = ? AND `store_id` = ?";
		$where_query .= date_range_sell_payments_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`amount`) as total FROM `payments` 
			WHERE $where_query");
		$statement->execute(array('due_paid', $store_id));
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getAnothrDayDueCollectionAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`type` = ? AND `store_id` = ?";
		$where_query .= date_range_sell_payments_reverse_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`amount`) as total FROM `payments` 
			WHERE $where_query");
		$statement->execute(array('due_paid', $store_id));
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getAnothrDayDuePaidAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`type` = ? AND `store_id` = ?";
		$where_query .= date_range_purchase_payments_reverse_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`amount`) as total FROM `purchase_payments` 
			WHERE $where_query");
		$statement->execute(array('due_paid', $store_id));
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getPurchaseDuePaidAmount($from, $to, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`type` IN ('due_paid','transfer') AND `store_id` = ?";
		$where_query .= date_range_purchase_payments_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`amount`) as total FROM `purchase_payments` 
			WHERE $where_query");
		$statement->execute(array($store_id));
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getTopProducts($from, $to, $limit = 3, $store_id = null)
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = '$store_id'";
		$where_query .= date_range_filter($from, $to);
		$statement = $this->db->prepare("SELECT GROUP_CONCAT(DISTINCT `selling_info`.`store_id`) AS store_id, GROUP_CONCAT(DISTINCT `selling_info`.`created_at`) AS created_at, GROUP_CONCAT(DISTINCT `selling_item`.`item_name`) AS item_name, SUM(`selling_item`.`item_quantity`) AS quantity FROM `selling_info` 
			LEFT JOIN `selling_item` ON (`selling_info`.`invoice_id` = `selling_item`.`invoice_id`)
			WHERE $where_query
			GROUP BY `selling_item`.`item_id` ORDER BY `quantity` 
			DESC LIMIT $limit");
		$statement->execute(array());
		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getTopCustomers($from, $to, $limit = 3, $store_id = null)
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = '$store_id'";
		$where_query .= date_range_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`selling_price`.`payable_amount`) AS total, GROUP_CONCAT(DISTINCT `selling_info`.`store_id`) AS store_id, GROUP_CONCAT(DISTINCT `selling_info`.`created_at`) AS created_at, GROUP_CONCAT(DISTINCT `selling_info`.`customer_id`) AS customer_id FROM `selling_info` 
			LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`)
			WHERE $where_query
			GROUP BY `customer_id` ORDER BY `total` 
			DESC LIMIT $limit");
		$statement->execute(array());
		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getTopSuppliers($from, $to, $limit = 3, $store_id = null)
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = '$store_id' AND `selling_item`.`sup_id` IS NOT NULL AND `selling_item`.`sup_id` != '0'";
		$where_query .= date_range_filter($from, $to);
		$statement = $this->db->prepare("SELECT GROUP_CONCAT(DISTINCT `selling_info`.`store_id`) AS store_id, GROUP_CONCAT(DISTINCT `selling_info`.`created_at`) AS created_at, GROUP_CONCAT(DISTINCT `selling_item`.`sup_id`) AS sup_id, SUM(`selling_item`.`item_quantity`) as quantity FROM `selling_info`
			LEFT JOIN `selling_item` ON (`selling_info`.`invoice_id` = `selling_item`.`invoice_id`)
			WHERE $where_query
			GROUP BY `selling_item`.`sup_id` ORDER BY `quantity` 
			DESC LIMIT $limit");
		$statement->execute(array());
		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getTopBrands($from, $to, $limit = 3, $store_id = null)
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = '$store_id' AND `selling_item`.`brand_id` IS NOT NULL AND `selling_item`.`sup_id` != '0'";
		$where_query .= date_range_filter($from, $to);
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
GROUP_CONCAT(DISTINCT `brands`.`brand_name`) AS brand_name,
			GROUP_CONCAT(DISTINCT `selling_item`.`brand_id`) as brand_id, SUM(`selling_item`.`item_quantity`) as quantity FROM `selling_info` 
			LEFT JOIN `selling_item` ON (`selling_info`.`invoice_id` = `selling_item`.`invoice_id`)
			RIGHT JOIN `brands` ON (`brands`.`brand_id` = `selling_item`.`brand_id`)
			WHERE $where_query
			GROUP BY `selling_item`.`brand_id` ORDER BY `quantity` 
			DESC LIMIT $limit");
		$statement->execute(array());
		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}

	public function totalOutOfStock($store_id = null)
	{
		$store_id = $store_id ? $store_id : store_id();
		$statement =  $this->db->prepare("SELECT * FROM `products` 
			LEFT JOIN `product_to_store` p2s ON (`products`.`p_id` = `p2s`.`product_id`) 
			WHERE `p2s`.`store_id` = ? AND `p_type` != 'service' AND (`p2s`.`quantity_in_stock` <= `alert_quantity`) AND `p2s`.`status` = 1");
		$statement->execute(array($store_id));
		return $statement->rowCount();
	}

	public function totalExpired($store_id = null)
	{
		$store_id = $store_id ? $store_id : store_id();
		$statement =  $this->db->prepare("SELECT * FROM `products` LEFT JOIN `product_to_store` p2s ON (`products`.`p_id` = `p2s`.`product_id`) WHERE `p2s`.`store_id` = ? AND `e_date` <= CURDATE() AND `p2s`.`status` = 1");
		$statement->execute(array($store_id));
		return $statement->rowCount();
	}

	public function userTotalInvoiceCount($user_id = null, $from = null, $to = null, $store_id = null)
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = ? AND `selling_info`.`status` = 1 AND `created_by` = $user_id AND `inv_type` = 'sell'";
		$where_query .= date_range_filter($from, $to);
		$statement = $this->db->prepare("SELECT * FROM `selling_info` WHERE $where_query");
		$statement->execute(array($store_id));
		return $statement->rowCount();
	}

	public function getOrderTaxAmountDaywise($year, $month = null, $day = null, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = '$store_id'";
		if ($day) {
		  $where_query .= " AND DAY(`selling_info`.`created_at`) = $day";
		}
		if ($month) {
		  $where_query .= " AND MONTH(`selling_info`.`created_at`) = $month";
		}
		if ($year) {
		  $where_query .= " AND YEAR(`selling_info`.`created_at`) = $year";
		}

		$statement = $this->db->prepare("SELECT SUM(`selling_price`.`order_tax`) as total FROM `selling_info` 
			LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
			WHERE $where_query");
		$statement->execute(array());
		$order_tax = $statement->fetch(PDO::FETCH_ASSOC);

		return $order_tax['total'];
	}

	public function getTotalCashReceivedBy($type, $type_id, $from = null, $to = null, $store_id = null) 
	{
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = ? AND `selling_info`.`status` = 1";
		$where_query .= date_range_filter($from, $to);
		switch ($type) {
			case 'userwise':
				$where_query .= " AND `selling_info`.`inv_type` = 'sell' AND `selling_info`.`created_by` = $type_id";
				$statement = $this->db->prepare("SELECT SUM(`selling_price`.`paid_amount`) as total FROM `selling_info` 
					LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
					WHERE $where_query");
				$statement->execute(array($store_id));
				$invoice = $statement->fetch(PDO::FETCH_ASSOC);
				$total = isset($invoice['total']) ? (float)$invoice['total'] : 0;
				$prev_due_collection = $this->getTotalPrevDueCollectionBy($type_id, $from, $to);
				$total = $total+$prev_due_collection;
				break;
			case 'invoicewise':
				$where_query .= " AND `selling_info`.`inv_type` = 'sell' AND `selling_info`.`invoice_id` = '$type_id'";
				$statement = $this->db->prepare("SELECT SUM(`selling_price`.`paid_amount`) as total FROM `selling_info` 
					LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
					WHERE $where_query");
				$statement->execute(array($store_id));
				$invoice = $statement->fetch(PDO::FETCH_ASSOC);
				$total = isset($invoice['total']) ? (float)$invoice['total'] : 0;
				break;
		}	
		return $total;
	}

	public function getTotalDueAmountBy($type, $type_id, $from = null, $to = null, $store_id = null) 
	{
		$edited_invoice_amount = 0;
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = ? AND `selling_info`.`status` = 1";
		$where_query .= date_range_filter($from, $to);
		switch ($type) {
			case 'invoicewise':
				$where_query .= " AND `selling_info`.`invoice_id` = '$type_id'";
				$statement = $this->db->prepare("SELECT SUM(`selling_price`.`due`) as total FROM `selling_info` 
					LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
					WHERE $where_query");
				$statement->execute(array($store_id));
				$invoice = $statement->fetch(PDO::FETCH_ASSOC);
				$total = isset($invoice['total']) ? (float)$invoice['total'] : 0;
				break;
			case 'userwise':
				$where_query .= " AND `inv_type` = 'sell' AND `selling_info`.`created_by` = $type_id";
				$statement = $this->db->prepare("SELECT SUM(`selling_price`.`due`) as total FROM `selling_info` 
					LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
					WHERE $where_query");
				$statement->execute(array($store_id));
				$invoice = $statement->fetch(PDO::FETCH_ASSOC);
				$total = isset($invoice['total']) ? (float)$invoice['total'] : 0;
				break;
		}	
		return $total + $edited_invoice_amount;
	}

	public function getTotalDueCollectionBy($user_id, $from = null, $to = null) 
	{
		$total = 0;
		$from = $from ? $from : date('Y-m-d');
		$to = $to ? $to : date('Y-m-d');
		$where_query = "`payments`.`type`='due_paid' AND `payments`.`created_by` = ?";
		$where_query .= date_range_sell_payments_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`payments`.`amount`) as total, `created_at` FROM `payments` 
			WHERE $where_query");
		$statement->execute(array($user_id));
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return $invoice['total'];
	}

	public function getTotalPrevDueCollectionBy($user_id, $from = null, $to = null) 
	{
		$total = 0;
		$from = $from ? $from : date('Y-m-d');
		$to = $to ? $to : date('Y-m-d');
		$where_query = "`payments`.`type`='due_paid' AND `payments`.`created_by` = ?";
		$where_query .= date_range_sell_payments_reverse_filter($from, $to);
		$statement = $this->db->prepare("SELECT SUM(`payments`.`amount`) as total, GROUP_CONCAT(DISTINCT `payments`.`created_at`) AS created_at FROM `payments` WHERE {$where_query} GROUP BY `payments`.`amount`");
		$statement->execute(array((int)$user_id));
		$invoice = $statement->fetch(PDO::FETCH_ASSOC);
		return isset($invoice['total']) ? $invoice['total'] : 0;
	}

	public function getTotalTaxAmountBy($type, $type_id, $from = null, $to = null, $store_id = null) 
	{
		$total = 0;
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`inv_type` = 'sell' AND `selling_info`.`store_id` = ? AND `selling_info`.`status` = 1";
		$where_query .= date_range_filter($from, $to);
		switch ($type) {
			case 'invoicewise':
				$where_query .= " AND `selling_info`.`invoice_id` = '$type_id'";
				$statement = $this->db->prepare("SELECT `selling_info`.*, `selling_price`.`order_tax` as order_tax, `selling_price`.`item_tax` as item_tax 
				FROM `selling_info` 
				LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
				WHERE $where_query");
				break;
			case 'userwise':
				$where_query .= " AND `selling_info`.`created_by` = $type_id";
				$statement = $this->db->prepare("SELECT `selling_info`.*, `selling_price`.`order_tax` as order_tax, `selling_price`.`item_tax` as item_tax 
				FROM `selling_info` 
				LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
				WHERE $where_query");
				break;		
		}
		$statement->execute(array($store_id));
		$invoices = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach ($invoices as $inv) {
			$total += $inv['order_tax'] + $inv['item_tax'];
		}
		return $total;
	}

	public function getTotalShippingChargeBy($type, $type_id, $from = null, $to = null, $store_id = null) 
	{
		$total = 0;
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`inv_type` = 'sell' AND `selling_info`.`store_id` = ? AND `selling_info`.`status` = 1";
		$where_query .= date_range_filter($from, $to);
		switch ($type) {
			case 'invoicewise':
				$where_query .= " AND `selling_info`.`invoice_id` = '$type_id'";
				$statement = $this->db->prepare("SELECT `selling_info`.*, `selling_price`.`shipping_amount` as shipping_charge 
				FROM `selling_info` 
				LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
				WHERE $where_query");
				break;
			case 'userwise':
				$where_query .= " AND `selling_info`.`created_by` = $type_id";
				$statement = $this->db->prepare("SELECT `selling_info`.*, `selling_price`.`shipping_amount` as shipping_charge  
				FROM `selling_info` 
				LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
				WHERE $where_query");
				break;		
		}
		$statement->execute(array($store_id));
		$invoices = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach ($invoices as $inv) {
			$total += $inv['shipping_charge'];
		}
		return $total;
	}

	public function getTotalOthersChargeBy($type, $type_id, $from = null, $to = null, $store_id = null) 
	{
		$total = 0;
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`inv_type` = 'sell' AND `selling_info`.`store_id` = ? AND `selling_info`.`status` = 1";
		$where_query .= date_range_filter($from, $to);
		switch ($type) {
			case 'invoicewise':
				$where_query .= " AND `selling_info`.`invoice_id` = '$type_id'";
				$statement = $this->db->prepare("SELECT `selling_info`.*, `selling_price`.`others_charge` as others_charge 
				FROM `selling_info` 
				LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
				WHERE $where_query");
				break;
			case 'userwise':
				$where_query .= " AND `selling_info`.`created_by` = $type_id";
				$statement = $this->db->prepare("SELECT `selling_info`.*, `selling_price`.`others_charge` as others_charge  
				FROM `selling_info` 
				LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
				WHERE $where_query");
				break;		
		}
		$statement->execute(array($store_id));
		$invoices = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach ($invoices as $inv) {
			$total += $inv['others_charge'];
		}
		return $total;
	}

	public function getTotalDiscountAmountBy($type, $type_id, $from = null, $to = null, $store_id = null) 
	{
		$total = 0;
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`inv_type` = 'sell' AND `selling_info`.`store_id` = ? AND `selling_info`.`status` = 1";
		$where_query .= date_range_filter($from, $to);
		switch ($type) {
			case 'invoicewise':
				$where_query .= " AND `selling_info`.`invoice_id` = '$type_id'";
				$statement = $this->db->prepare("SELECT `selling_info`.*, `selling_price`.`discount_amount` as discount_amount 
				FROM `selling_info` 
				LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
				WHERE $where_query");
				break;
			case 'userwise':
				$where_query .= " AND `selling_info`.`created_by` = $type_id";
				$statement = $this->db->prepare("SELECT `selling_info`.*, `selling_price`.`discount_amount` as discount_amount  
				FROM `selling_info` 
				LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
				WHERE $where_query");
				break;	
			case 'itemwise':
				$where_query .= " AND `selling_info`.`invoice_id` = '$type_id'";
				$statement = $this->db->prepare("SELECT `selling_info`.*, `selling_price`.`discount_amount` as discount_amount  
				FROM `selling_info` 
				LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
				WHERE $where_query");
				break;	
		}
		$statement->execute(array($store_id));
		$invoices = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach ($invoices as $inv) {
			$total += $inv['discount_amount'];
		}
		return $total;
	}
	public function getTotalInvoiceAmountBy($type, $type_id, $from = null, $to = null, $store_id = null) 
	{
		$total = 0;
		$store_id = $store_id ? $store_id : store_id();
		$where_query = "`selling_info`.`store_id` = ? AND `selling_info`.`status` = 1";
		$where_query .= date_range_filter($from, $to);
		switch ($type) {
			case 'invoicewise':
				$where_query .= " AND `inv_type` = 'sell' AND `selling_info`.`invoice_id` = '$type_id'";
				$statement = $this->db->prepare("SELECT SUM(`selling_price`.`subtotal`) as total FROM `selling_info` 
					LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
					WHERE $where_query");
				$statement->execute(array($store_id));
				$invoice = $statement->fetch(PDO::FETCH_ASSOC);
				$total = isset($invoice['total']) ? (float)$invoice['total'] : 0;
				break;
			case 'userwise':
				$where_query .= " AND `inv_type` = 'sell' AND `selling_info`.`created_by` = $type_id";
				$statement = $this->db->prepare("SELECT SUM(`selling_price`.`subtotal`) as total FROM `selling_info` 
					LEFT JOIN `selling_price` ON (`selling_info`.`invoice_id` = `selling_price`.`invoice_id`) 
					WHERE $where_query");
				$statement->execute(array($store_id));
				$invoice = $statement->fetch(PDO::FETCH_ASSOC);
				$total = isset($invoice['total']) ? (float)$invoice['total'] : 0;
				break;
		}	
		return $total;
	}

	/**
	 * Resumo consolidado por meio de pagamento para uma ou mais lojas.
	 * Retorna totais por método, por loja×método e agregados gerais.
	 */
	public function getPaymentSummaryByStores($from = null, $to = null, $store_ids = array())
	{
		if (empty($store_ids)) {
			return array(
				'by_method' => array(),
				'by_store_and_method' => array(),
				'totals' => array(
					'grand_total'   => 0.0,
					'transactions'  => 0,
					'ticket_medio'  => 0.0,
				),
			);
		}

		// Garante IDs inteiros
		$store_ids = array_map('intval', $store_ids);
		$store_ids_in = implode("','", $store_ids);

		$where_query = "`selling_info`.`store_id` IN ('{$store_ids_in}') AND `selling_info`.`status` = 1 AND `selling_info`.`inv_type` = 'sell'";
		$where_query .= date_range_filter($from, $to);

		$sql = "
			SELECT
				`selling_info`.`store_id`,
				`selling_info`.`pmethod_id`,
				`pmethods`.`name`      AS pmethod_name,
				`pmethods`.`code_name` AS pmethod_code,
				SUM(`selling_price`.`paid_amount`)          AS total,
				COUNT(DISTINCT `selling_info`.`invoice_id`) AS transactions
			FROM `selling_info`
			LEFT JOIN `selling_price`
				ON `selling_info`.`invoice_id` = `selling_price`.`invoice_id`
			LEFT JOIN `pmethods`
				ON `selling_info`.`pmethod_id` = `pmethods`.`pmethod_id`
			WHERE {$where_query}
			GROUP BY `selling_info`.`store_id`, `selling_info`.`pmethod_id`
		";

		$statement = $this->db->prepare($sql);
		$statement->execute(array());
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

		$by_method = array();
		$by_store_and_method = array();
		$grand_total = 0.0;
		$grand_transactions = 0;

		foreach ($rows as $row) {
			$store_id   = (int)$row['store_id'];
			$pmethod_id = (int)$row['pmethod_id'];
			$total      = (float)$row['total'];
			$tx         = (int)$row['transactions'];

			// Ignora vendas sem método de pagamento definido
			if (!$pmethod_id) {
				continue;
			}

			if (!isset($by_method[$pmethod_id])) {
				$by_method[$pmethod_id] = array(
					'pmethod_id'    => $pmethod_id,
					'name'          => $row['pmethod_name'],
					'code_name'     => $row['pmethod_code'],
					'total'         => 0.0,
					'transactions'  => 0,
					'ticket_medio'  => 0.0,
					'percent'       => 0.0,
				);
			}

			$by_method[$pmethod_id]['total']        += $total;
			$by_method[$pmethod_id]['transactions'] += $tx;

			if (!isset($by_store_and_method[$store_id])) {
				$by_store_and_method[$store_id] = array();
			}
			if (!isset($by_store_and_method[$store_id][$pmethod_id])) {
				$by_store_and_method[$store_id][$pmethod_id] = array(
					'total'        => 0.0,
					'transactions' => 0,
				);
			}

			$by_store_and_method[$store_id][$pmethod_id]['total']        += $total;
			$by_store_and_method[$store_id][$pmethod_id]['transactions'] += $tx;

			$grand_total       += $total;
			$grand_transactions += $tx;
		}

		// Calcula ticket médio e participação percentual por método
		foreach ($by_method as $pm_id => &$m) {
			$m['ticket_medio'] = $m['transactions'] > 0
				? ($m['total'] / $m['transactions'])
				: 0.0;
			$m['percent'] = $grand_total > 0
				? ($m['total'] / $grand_total)
				: 0.0;
		}
		unset($m);

		$totals = array(
			'grand_total'   => $grand_total,
			'transactions'  => $grand_transactions,
			'ticket_medio'  => $grand_transactions > 0 ? ($grand_total / $grand_transactions) : 0.0,
		);

		return array(
			'by_method'          => $by_method,
			'by_store_and_method'=> $by_store_and_method,
			'totals'             => $totals,
		);
	}

	/**
	 * Retorna uma série diária (dia × meio de pagamento) para uma ou mais lojas.
	 * Útil para gráficos de tendência no painel de conta.
	 */
	public function getPaymentTrendByStores($from = null, $to = null, $store_ids = array(), $pmethod_ids = array())
	{
		if (empty($store_ids)) {
			return array();
		}

		$store_ids = array_map('intval', $store_ids);
		$store_ids_in = implode(',', $store_ids);

		$where = "`si`.`store_id` IN ({$store_ids_in}) AND `si`.`status` = 1 AND `si`.`inv_type` = 'sell'";

		if ($from) {
			$from_date = date('Y-m-d', strtotime($from));
			$where .= " AND DATE(`si`.`created_at`) >= '{$from_date}'";
		}
		if ($to) {
			$to_date = date('Y-m-d', strtotime($to));
			$where .= " AND DATE(`si`.`created_at`) <= '{$to_date}'";
		}

		if (!empty($pmethod_ids)) {
			$pmethod_ids = array_map('intval', $pmethod_ids);
			$pmethod_ids_in = implode(',', $pmethod_ids);
			$where .= " AND `si`.`pmethod_id` IN ({$pmethod_ids_in})";
		}

		$sql = "
			SELECT
				DATE(`si`.`created_at`) AS day,
				`si`.`pmethod_id`       AS pmethod_id,
				SUM(`sp`.`paid_amount`) AS total,
				COUNT(DISTINCT `si`.`invoice_id`) AS transactions
			FROM `selling_info` `si`
			LEFT JOIN `selling_price` `sp`
				ON `si`.`invoice_id` = `sp`.`invoice_id`
			WHERE {$where}
			GROUP BY DATE(`si`.`created_at`), `si`.`pmethod_id`
			ORDER BY DATE(`si`.`created_at`) ASC
		";

		$statement = $this->db->prepare($sql);
		$statement->execute();
		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}
}
