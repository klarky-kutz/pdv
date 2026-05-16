<?php
include ("../_init.php");

// Product Images
if($request->server['REQUEST_METHOD'] == 'GET' AND $request->get['type'] == 'PRODUCTIMAGES') 
{
	try {
		$p_id = $request->get['p_id'];
		$images = get_product_images($p_id);
	    header('Content-Type: application/json');
	    echo json_encode(array('msg' => trans('text_success'), 'images' => $images));
	    exit();

	  } catch (Exception $e) { 
	    
	    header('HTTP/1.1 422 Unprocessable Entity');
	    header('Content-Type: application/json; charset=UTF-8');
	    echo json_encode(array('errorMsg' => $e->getMessage()));
	    exit();
	  }
}

// Banner Images
if($request->server['REQUEST_METHOD'] == 'GET' AND $request->get['type'] == 'BANNERIMAGES') 
{
	try {
		$id = $request->get['id'];
		$images = get_banner_images($id);
	    header('Content-Type: application/json');
	    echo json_encode(array('msg' => trans('text_banner_images'), 'images' => $images));
	    exit();

	  } catch (Exception $e) { 
	    
	    header('HTTP/1.1 422 Unprocessable Entity');
	    header('Content-Type: application/json; charset=UTF-8');
	    echo json_encode(array('errorMsg' => $e->getMessage()));
	    exit();
	  }
}

// Quotation info
if($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'QUOTATIONINFO') 
{
	try {
		$ref_no = $request->post['ref_no'];
		$quotation_model = registry()->get('loader')->model('quotation');
		$quotation = $quotation_model->getQuotationInfo($ref_no);
		$quotation_items = $quotation_model->getQuotationItems($ref_no);
		$quotation['items'] = $quotation_items;
		header('Content-Type: application/json');
		echo json_encode(array('msg' => trans('text_success'), 'quotation' => $quotation));
		exit();

	} catch (Exception $e) { 

		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}

// Gift Card Info - Get balance for payment
if($request->server['REQUEST_METHOD'] == 'GET' AND $request->get['type'] == 'GIFTCARDINFO') 
{
	try {
		$card_no = isset($request->get['card_no']) ? $request->get['card_no'] : '';
		$customer_id = isset($request->get['customer_id']) ? (int)$request->get['customer_id'] : 0;
		
		if (empty($card_no)) {
			throw new Exception('Número do cartão não informado');
		}
		
		// Buscar gift card válido
		$statement = db()->prepare("
			SELECT g.*, c.customer_name 
			FROM `gift_cards` g 
			LEFT JOIN `customers` c ON g.customer_id = c.customer_id
			WHERE g.`card_no` = ? 
			AND g.`customer_id` = ?
			AND g.`expiry` > NOW()
			LIMIT 1
		");
		$statement->execute(array($card_no, $customer_id));
		$giftcard = $statement->fetch(PDO::FETCH_ASSOC);
		
		if (!$giftcard) {
			throw new Exception('Cartão não encontrado, expirado ou não pertence a este cliente');
		}
		
		header('Content-Type: application/json');
		echo json_encode(array(
			'success' => true,
			'card_no' => $giftcard['card_no'],
			'balance' => (float)$giftcard['balance'],
			'value' => (float)$giftcard['value'],
			'expiry' => $giftcard['expiry'],
			'customer_name' => $giftcard['customer_name']
		));
		exit();

	} catch (Exception $e) { 
		
		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}

// Update POS tempalte content
if($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'UPDATEPOSTEMPALTECONTENT')
{
	try {

		if (DEMO || (user_group_id() != 1 && !has_permission('access', 'receipt_template'))) {
	      throw new Exception(trans('error_update_permission'));
	    }

		$template_id = isset($request->post['template_id']) ? (int)$request->post['template_id'] : 0;
		$content = isset($request->post['content']) ? $request->post['content'] : '';
		if (!$template_id) {
			throw new Exception('Invalid template id');
		}

		// Security: ensure template belongs to current store
		$check = db()->prepare(
			"SELECT t.template_name\n"
			. "FROM `pos_templates` t\n"
. "INNER JOIN `pos_template_to_store` pt2s ON (t.template_id = pt2s.ttemplate_id)\n"
			. "WHERE pt2s.store_id = ? AND t.template_id = ?\n"
			. "LIMIT 1"
		);
		$check->execute(array((int)store_id(), $template_id));
		$row = $check->fetch(PDO::FETCH_ASSOC);
		if (!$row) {
			throw new Exception('Template not found');
		}

		// Non-admin users can only save custom copies
		$tmeta = postemplate_parse_custom_marker($row['template_name']);
		$is_custom_for_store = (
			$tmeta['is_custom'] && (int)$tmeta['store_id'] === (int)store_id()
		) || postemplate_is_legacy_custom_template_name($row['template_name'], store_id());
		if (user_group_id() != 1 && !$is_custom_for_store) {
			throw new Exception('Permission denied');
		}

		$statement = db()->prepare("UPDATE `pos_templates` SET `template_content` = ? WHERE `template_id` = ?");
		$statement->execute(array($content, $template_id));

		header('Content-Type: application/json');
		echo json_encode(array('msg' => trans('text_template_content_update_success')));
		exit();

	} catch (Exception $e) { 

		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
	exit();
	}
}

// Update POS tempalte CSS
if($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'UPDATEPOSTEMPALTECSS') 
{
	try {
	    
	    if (DEMO || (user_group_id() != 1 && !has_permission('access', 'receipt_template'))) {
	      throw new Exception(trans('error_update_permission'));
	    }
	    
		$template_id = isset($request->post['template_id']) ? (int)$request->post['template_id'] : 0;
		$content = isset($request->post['content']) ? $request->post['content'] : '';
		if (!$template_id) {
			throw new Exception('Invalid template id');
		}

		// Security: ensure template belongs to current store
		$check = db()->prepare(
			"SELECT t.template_name\n"
			. "FROM `pos_templates` t\n"
			. "INNER JOIN `pos_template_to_store` pt2s ON (t.template_id = pt2s.ttemplate_id)\n"
			. "WHERE pt2s.store_id = ? AND t.template_id = ?\n"
			. "LIMIT 1"
		);
		$check->execute(array((int)store_id(), $template_id));
		$row = $check->fetch(PDO::FETCH_ASSOC);
		if (!$row) {
			throw new Exception('Template not found');
		}

		// Non-admin users can only save custom copies
		$tmeta = postemplate_parse_custom_marker($row['template_name']);
		$is_custom_for_store = (
			$tmeta['is_custom'] && (int)$tmeta['store_id'] === (int)store_id()
		) || postemplate_is_legacy_custom_template_name($row['template_name'], store_id());
		if (user_group_id() != 1 && !$is_custom_for_store) {
			throw new Exception('Permission denied');
		}

		$statement = db()->prepare("UPDATE `pos_templates` SET `template_css` = ? WHERE `template_id` = ?");
		$statement->execute(array($content, $template_id));

		header('Content-Type: application/json');
		echo json_encode(array('msg' => trans('text_template_css_update_success')));
		exit();

	} catch (Exception $e) { 

		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}

// Reset custom receipt template (copy latest from global/base)
if($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'RESETPOSRECEIPTTEMPLATE') 
{
	try {
		if (DEMO || (user_group_id() != 1 && !has_permission('access', 'receipt_template'))) {
			throw new Exception(trans('error_update_permission'));
		}

		$template_id = isset($request->post['template_id']) ? (int)$request->post['template_id'] : 0;
		$base_template_id = isset($request->post['base_template_id']) ? (int)$request->post['base_template_id'] : 0;
		if (!$template_id || !$base_template_id) {
			throw new Exception('Invalid template id');
		}

		// Ensure the template belongs to current store
		$check = db()->prepare(
			"SELECT t.template_name\n"
			. "FROM `pos_templates` t\n"
			. "INNER JOIN `pos_template_to_store` pt2s ON (t.template_id = pt2s.ttemplate_id)\n"
			. "WHERE pt2s.store_id = ? AND t.template_id = ?\n"
			. "LIMIT 1"
		);
		$check->execute(array((int)store_id(), $template_id));
		$row = $check->fetch(PDO::FETCH_ASSOC);
		if (!$row) {
			throw new Exception('Template not found');
		}

		$meta = postemplate_parse_custom_marker($row['template_name']);
		if (!$meta['is_custom'] || (int)$meta['store_id'] !== (int)store_id()) {
			throw new Exception('Only custom templates can be reset');
		}
		if ((int)$meta['base_id'] !== $base_template_id) {
			throw new Exception('Base template mismatch');
		}

		$base_stmt = db()->prepare("SELECT `template_content`, `template_css` FROM `pos_templates` WHERE `template_id` = ? LIMIT 1");
		$base_stmt->execute(array($base_template_id));
		$base = $base_stmt->fetch(PDO::FETCH_ASSOC);
		if (!$base) {
			throw new Exception('Base template not found');
		}

		$update = db()->prepare("UPDATE `pos_templates` SET `template_content` = ?, `template_css` = ?, `updated_at` = NOW() WHERE `template_id` = ?");
		$update->execute(array($base['template_content'], $base['template_css'], $template_id));

		header('Content-Type: application/json');
		echo json_encode(array('msg' => 'Modelo resetado com sucesso!'));
		exit();

	} catch (Exception $e) { 

		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}

// Update opening balance
if($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'UPDATEOPENINGBALANCE') 
{
	try {
		$balance = my_str_replace(',', '', $request->post['balance']);
		if (!is_numeric($balance)) {
			throw new Exception(trans('error_invalid_balance'));
		}

		// UPDATE OPENING BALANCE
		$from = date('Y-m-d');
		$day = date('d', strtotime($from));
		$month = date('m', strtotime($from));
		$year = date('Y', strtotime($from));
		$where_query = " DAY(`pos_register`.`created_at`) = $day";
		$where_query .= " AND MONTH(`pos_register`.`created_at`) = $month";
		$where_query .= " AND YEAR(`pos_register`.`created_at`) = $year";

		// If not exist then insert
		$statement = db()->prepare("SELECT `id` FROM `pos_register` WHERE $where_query AND `store_id` = ?");
		$statement->execute(array(store_id()));
		$row = $statement->fetch(PDO::FETCH_ASSOC);
		if (!$row) {
			$statement = db()->prepare("INSERT INTO `pos_register` SET `store_id` = ?, `created_at` = ?");
			$statement->execute(array(store_id(), date_time()));
		}

		$statement = db()->prepare("UPDATE `pos_register` SET `opening_balance` = ? WHERE $where_query AND `store_id` = ?");
		$statement->execute(array($balance, store_id()));

		// UPDATE CLOSING BALANCE
		$date = date('Y-m-d');
		$from = date( 'Y-m-d', strtotime( $date . ' -1 day' ) );
		$day = date('d', strtotime($from));
		$month = date('m', strtotime($from));
		$year = date('Y', strtotime($from));
		$where_query = " DAY(`pos_register`.`created_at`) = $day";
		$where_query .= " AND MONTH(`pos_register`.`created_at`) = $month";
		$where_query .= " AND YEAR(`pos_register`.`created_at`) = $year";
		$statement = db()->prepare("UPDATE `pos_register` SET `opening_balance` = ? WHERE $where_query AND `store_id` = ?");
		$statement->execute(array($balance, store_id()));

		header('Content-Type: application/json');
		echo json_encode(array('msg' => trans('text_opening_balance_update_success')));
		exit();

	} catch (Exception $e) { 

		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}

if($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'PURCHASECSVITEM') 
{
	$data = array();
	$path = DIR_STORAGE.'products/stock-'.store_id().'.csv';
	if (isset($request->post['sup_id']) && file_exists($path)) {
		$sup_id = (int) $request->post['sup_id'];
		$file = fopen($path,"r");
		$i = 0;
		$lines = array();
		while (($line = fgetcsv($file, 1000, ",")) !== FALSE) {
			if (!$line) {
				continue;
			}
			$p_name = $line[0];
			if ($p_name == 'Name') {
				continue;
			}
			$p_code = $line[1];
			$qty = $line[2];
			if ($qty <= 0) {
				continue;
			}
	    	$products = get_products(array('filter_sup_id' => $sup_id, 'filter_p_code' => $p_code));
	    	if (!$products) {
	    		continue;
	    	}
	    	$product = $products[0];
			$purchase_price = $product['purchase_price'];
	    	$sell_price = $product['sell_price'];
	    	$tax_amount = 0;
	    	$tax_method = $product['tax_method'] ? $product['tax_method'] : 'exclusive';
	    	$taxrate = 0;
	    	$product_info = get_the_product($product['p_id']);
	    	if ($product_info && $product_info['taxrate']) {
	    		$taxrate = $product_info['taxrate']['taxrate'];
	    		$tax_amount = ($product_info['taxrate']['taxrate'] / 100 ) * $purchase_price;
	    	}
			$data[] = array(
				'p_id' => $product['p_id'],
				'p_name' => $product['p_name'],
				'p_code' => $product['p_code'],
				'category_id' => $product['category_id'],
				'available' => $product['quantity_in_stock'],
				'unit_name' => get_the_unit($product['unit_id'],'unit_name'),
				'purchase_price' => $purchase_price ,
				'sell_price' => $sell_price,
				'tax_amount' => $tax_amount,
				'tax_method' => $tax_method,
				'taxrate' => $taxrate,
				'qty' => $qty,
			);
		}
		fclose($file);
	}
	echo json_encode($data);
	exit();
}

if($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'PURCHASEITEM') 
{
	$sup_id = isset($request->post['sup_id']) ? $request->post['sup_id'] : null;
	$type = $request->post['type'];
	$name = $request->post['name_starts_with'];
	$query = "SELECT `p_id`, `p_name`, `p_code`, `category_id`, `unit_id`, `p2s`.`tax_method`, `p2s`.`purchase_price`, `p2s`.`sell_price`, `p2s`.`quantity_in_stock` 
		FROM `products` 
		LEFT JOIN `product_to_store` p2s ON (`products`.`p_id` = `p2s`.`product_id`)
		WHERE `p2s`.`store_id` = ? AND `p2s`.`status` = ? AND `p_type` != 'service'";
	if ($sup_id) {
		$query .= " AND `p2s`.`sup_id` = ?";
	}
	$query .= " AND (UPPER($type) LIKE '" . utf8_strtoupper($name) . "%' OR `p_code` = '{$name}') ORDER BY `p_id` DESC LIMIT 10";
	$statement = db()->prepare($query);
	if ($sup_id) {
		$statement->execute(array(store_id(), 1, $sup_id));
	} else {
		$statement->execute(array(store_id(), 1));
	}
	$products = $statement->fetchAll(PDO::FETCH_ASSOC);
	$data = array();
    foreach ($products as $product) {
    	$purchase_price = $product['purchase_price'];
    	$sell_price = $product['sell_price'];
    	$tax_amount = 0;
    	$tax_method = $product['tax_method'] ? $product['tax_method'] : 'exclusive';
    	$taxrate = 0;
    	$product_info = get_the_product($product['p_id']);
    	if ($product_info && $product_info['taxrate']) {
    		$taxrate = $product_info['taxrate']['taxrate'];
    		$tax_amount = ($product_info['taxrate']['taxrate'] / 100 ) * $purchase_price;
    	}
		$name = $product['p_id'].'|'.$product['p_name'].'|'.$product['p_code'].'|'.$product['category_id'].'|'.$product['quantity_in_stock'].'|'.get_the_unit($product['unit_id'],'unit_name').'|'.$purchase_price .'|'.$sell_price.'|'.$tax_amount.'|'.$tax_method.'|'.$taxrate.'|'.$product['quantity_in_stock'];
		array_push($data, $name);
    }
	echo json_encode($data);
	exit();
}

// Product list
if($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'SELLINGITEM') 
{
	$sup_id = isset($request->post['sup_id']) ? $request->post['sup_id'] : null;
	$type = $request->post['type'];
	$name = $request->post['name_starts_with'];
	$query = "SELECT `p_id`, `p_name`, `p_code`, `category_id`, `p2s`.`tax_method`, `p2s`.`purchase_price`, `p2s`.`sell_price`, `p2s`.`quantity_in_stock` 
		FROM `products` 
		LEFT JOIN `product_to_store` p2s ON (`products`.`p_id` = `p2s`.`product_id`)
		WHERE `p2s`.`store_id` = ? AND `p2s`.`status` = ?";
	if ($sup_id) {
		$query .= " AND `p2s`.`sup_id` = ?";
	}
	// $query .= " AND UPPER($type) LIKE '" . utf8_strtoupper($name) . "%' ORDER BY `p_id` DESC LIMIT 10";
	$query .= " AND (UPPER($type) LIKE '" . utf8_strtoupper($name) . "%' OR `p_code` = '{$name}') ORDER BY `p_id` DESC LIMIT 10";
	$statement = db()->prepare($query);
	if ($sup_id) {
		$statement->execute(array(store_id(), 1, $sup_id));
	} else {
		$statement->execute(array(store_id(), 1));
	}
	$products = $statement->fetchAll(PDO::FETCH_ASSOC);
	$data = array();
    foreach ($products as $product) {
    	$purchase_price = $product['purchase_price'];
    	$sell_price = $product['sell_price'];
    	$tax_amount = 0;
    	$tax_method = $product['tax_method'] ? $product['tax_method'] : 'exclusive';
    	$taxrate = 0;
    	$product_info = get_the_product($product['p_id']);
    	if ($product_info && $product_info['taxrate']) {
    		$taxrate = $product_info['taxrate']['taxrate'];
    		$tax_amount = ($product_info['taxrate']['taxrate'] / 100 ) * $sell_price;
    	}
		$name = $product['p_id'].'|'.$product['p_name'].'|'.$product['p_code'].'|'.$product['category_id'].'|'.$product['quantity_in_stock'].'|'.$purchase_price .'|'.$sell_price.'|'.$tax_amount.'|'.$tax_method.'|'.$taxrate;
		array_push($data, $name);
    }
	echo json_encode($data);
	exit();
}

// StockItems
if($request->server['REQUEST_METHOD'] == 'GET' AND $request->get['type'] == 'STOCKITEMS') 
{
	try {
		$store_id = $request->get['store_id'] ? $request->get['store_id'] : store_id();
		$statement = db()->prepare("SELECT `purchase_item`.*, `purchase_info`.`inv_type` FROM `purchase_item` LEFT JOIN `purchase_info` ON (`purchase_item`.`invoice_id` = `purchase_info`.`invoice_id`) WHERE `purchase_item`.`store_id` = ? AND `purchase_item`.`item_quantity` > `purchase_item`.`total_sell` AND `purchase_item`.`status` IN ('stock','active') AND `purchase_info`.`inv_type` = ?");
	    $statement->execute(array($store_id, 'purchase'));
	    $products = $statement->fetchAll(PDO::FETCH_ASSOC);

	    header('Content-Type: application/json');
	    echo json_encode(array('msg' => trans('text_success'), 'products' => $products));
	    exit();

	  } catch (Exception $e) { 
	    
	    header('HTTP/1.1 422 Unprocessable Entity');
	    header('Content-Type: application/json; charset=UTF-8');
	    echo json_encode(array('errorMsg' => $e->getMessage()));
	    exit();
	  }
}

// StockItem
if($request->server['REQUEST_METHOD'] == 'GET' AND $request->get['type'] == 'STOCKITEM') 
{
	try {
		$id = $request->get['id'];
		$quantity = $request->get['quantity'];
		$statement = db()->prepare("SELECT * FROM `purchase_item` WHERE `id` = ? AND `item_quantity` > `total_sell` AND `status` IN ('stock','active')");
		$statement->execute(array($id));
		$products = $statement->fetch(PDO::FETCH_ASSOC);

		header('Content-Type: application/json');
		echo json_encode(array('msg' => trans('text_success'), 'products' => $products));
		exit();
	} catch (Exception $e) {
		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}