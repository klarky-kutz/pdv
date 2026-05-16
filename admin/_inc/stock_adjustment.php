<?php 
ob_start();
session_start();
include ("../_init.php");

// Check, if your logged in or not
// If user is not logged in then return an alert message
if (!is_loggedin()) {
	header('HTTP/1.1 422 Unprocessable Entity');
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode(array('errorMsg' => trans('error_login')));
	exit();
}

// Check, if user has reading permission or not
// If user have not reading permission return an alert message
if (user_group_id() != 1 && !has_permission('access', 'read_stock')) {
	header('HTTP/1.1 422 Unprocessable Entity');
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode(array('errorMsg' => trans('error_read_permission')));
	exit();
}

// Update
if($request->server['REQUEST_METHOD'] == 'POST' && isset($request->request['action_type']) && $request->request['action_type'] == 'UPDATE')
{
	try {


		// Check update permission
		if (user_group_id() != 1 && !has_permission('access', 'update_stock')) {
		  throw new Exception(trans('error_update_permission'));
		}

		$store_id = store_id();

		if (!empty($request->post['selected'])) {
			$p_ids = $request->post['selected'];
		} else {
			$p_id = $request->post['p_id'];
			$p_ids = explode(',', $p_id);
		}

		foreach ($p_ids as $id) {
			$product = get_the_product($id);
			if (!$product) {
				continue;
			}

			$qty = (int) $request->post['qty'];
			if ($qty <= 0) {
				// throw new Exception(trans('error_quantity'));
				continue;
			}

			$Hooks->do_action('Before_Update_Stock', $request);

			$statement = db()->prepare("SELECT * FROM `purchase_item` WHERE `item_id` = ? AND `store_id` = ?");
			$statement->execute(array($id, $store_id));
			$purchase_item = $statement->fetch(PDO::FETCH_ASSOC);
			if (!$purchase_item) {
				// throw new Exception(trans('error_you_need_to_purchase_the_items_before_stock_adjustment'));
				$statement = db()->prepare("INSERT INTO `purchase_item` (invoice_id, store_id, item_id, category_id, brand_id, item_name, item_purchase_price, item_selling_price, item_quantity, status, item_total, item_tax, tax_method, tax, gst, cgst, sgst, igst) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
	        	$statement->execute(array(uniqid(), $store_id, $id, (int)$product['category_id'], (int)$product['brand_id'], $product['p_name'], $product['purchase_price'], $product['sell_price'], $qty, 'active', 0, 0, $product['tax_method'], NULL, NULL, 0, 0, 0));
			} else {
				$statement = db()->prepare("UPDATE `purchase_item` SET `item_quantity` = `item_quantity` + $qty, status = 'active' WHERE `item_id` = ? AND `store_id` = ? ORDER BY `id` DESC");
	        	$statement->execute(array($id, $store_id));
			}

	        
	        $statement = db()->prepare("UPDATE `product_to_store` SET `quantity_in_stock` = `quantity_in_stock` + $qty WHERE `product_id` = ? AND `store_id` = ?");
	        $result = $statement->execute(array($id, $store_id));

			$Hooks->do_action('After_Update_Stock', $result);
		}

		header('Content-Type: application/json');
		echo json_encode(array('msg' => trans('text_update_success'), 'id' => $id));
		exit();

	} catch(Exception $e) { 

		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}

// View
if (!empty($request->get['p_id']) && isset($request->get['action_type']) && $request->get['action_type'] == 'VIEW') {
	$p_id = $request->get['p_id'];
	$product = get_the_product($p_id);
	if (!$product) {
		die(trans('error_product_not_found'));
	}
	$Hooks->do_action('Before_Showing_Stock_Adjustment_Form', $product);
	include 'template/stock_adjustment_form.php';
	$Hooks->do_action('After_Showing_Stock_Adjustment_Form', $product);
	exit();
}
