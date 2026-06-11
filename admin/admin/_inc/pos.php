<?php 
ob_start();
session_start();
include ("../_init.php");

// Check, if user logged in or not
// If user is not logged in then return an alert message
if (!is_loggedin()) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_login')));
  exit();
}

// Check, if user has reading permission or not
// If user have not reading permission return an alert message
if (user_group_id() != 1 && !has_permission('access', 'create_sell_invoice')) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_read_permission')));
  exit();
}

// $product_model e $store_id serão carregados quando necessário

// Fetch customer by id
if ($request->server['REQUEST_METHOD'] == 'GET' && isset($request->get['action_type']) && $request->get['action_type'] == 'CUSTOMER') {
	try {

		// validte customer id
		if (!validateInteger($request->get['customer_id'])) {
			throw new Exception(trans('error_customer_id'));
		}

		$id = $request->get['customer_id'];

		$statement = db()->prepare("SELECT * FROM `customers`  
			LEFT JOIN `customer_to_store` c2s ON (`customers`.`customer_id` = `c2s`.`customer_id`)
			WHERE `c2s`.`store_id` = ? AND `customers`.`customer_id` = ? AND `c2s`.`status` = ?");
		$statement->execute(array(store_id(), $id, 1));
		$the_customer = $statement->fetch(PDO::FETCH_ASSOC);
		$customer = $the_customer ? $the_customer : array();

	    header('Content-Type: application/json');
	    echo json_encode($customer); 
	    exit();

	} catch (Exception $e) {

	    header('HTTP/1.1 422 Unprocessable Entity');
	    header('Content-Type: application/json; charset=UTF-8');
	    echo json_encode(array('errorMsg' => $e->getMessage()));
	    exit();
	}
}

// Fetch customer list
if ($request->server['REQUEST_METHOD'] == 'GET' && isset($request->get['action_type']) && $request->get['action_type'] == 'CUSTOMERLIST') {
	try {

		$limit = isset($request->get['limit']) ? (int)$request->get['limit'] : 20;
		$query_string = isset($request->get['query_string']) ? trim($request->get['query_string']) : '';
		
		// Prepara a busca - remove caracteres especiais para busca de telefone
		$search_query = utf8_strtoupper($query_string);
		$phone_search = preg_replace('/[^0-9]/', '', $query_string); // Apenas números
		
		// Construir a query com busca por nome OU telefone
		$where_conditions = array();
		$params = array();
		
		// Busca por nome (início do nome)
		if (!empty($search_query)) {
			$where_conditions[] = "UPPER(`customers`.`customer_name`) LIKE ?";
			$params[] = $search_query . '%';
		}
		
		// Busca por telefone (completo ou últimos 4 dígitos)
		if (!empty($phone_search)) {
			if (strlen($phone_search) >= 4) {
				// Busca pelo número completo ou pelos últimos dígitos
				$where_conditions[] = "(
					`customers`.`customer_mobile` LIKE ? OR 
					`customers`.`customer_mobile` LIKE ? OR
					REPLACE(REPLACE(REPLACE(`customers`.`customer_mobile`, ' ', ''), '-', ''), '(', '') LIKE ?
				)";
				$params[] = '%' . $phone_search; // Termina com
				$params[] = $phone_search . '%'; // Começa com
				$params[] = '%' . $phone_search . '%'; // Contém (números limpos)
			}
		}
		
		// Se não houver condições, busca todos
		$where_sql = !empty($where_conditions) 
			? '(' . implode(' OR ', $where_conditions) . ') AND '
			: '';
		
		$sql = "SELECT
			GROUP_CONCAT(DISTINCT `customers`.`customer_id`) AS customer_id,
			GROUP_CONCAT(DISTINCT `customers`.`customer_name`) AS customer_name,
			GROUP_CONCAT(DISTINCT `customers`.`customer_email`) AS customer_email,
			GROUP_CONCAT(DISTINCT `customers`.`customer_mobile`) AS customer_mobile,
			GROUP_CONCAT(DISTINCT `customers`.`customer_address`) AS customer_address,
			GROUP_CONCAT(DISTINCT `customers`.`dob`) AS dob,
			GROUP_CONCAT(DISTINCT `customers`.`customer_sex`) AS customer_sex,
			GROUP_CONCAT(DISTINCT `customers`.`customer_age`) AS customer_age,
			GROUP_CONCAT(DISTINCT `customers`.`gtin`) AS gtin,
			GROUP_CONCAT(DISTINCT `customers`.`customer_city`) AS customer_city,
			GROUP_CONCAT(DISTINCT `customers`.`customer_state`) AS customer_state,
			GROUP_CONCAT(DISTINCT `customers`.`customer_country`) AS customer_country,
			GROUP_CONCAT(DISTINCT `customers`.`is_giftcard`) AS is_giftcard,
			GROUP_CONCAT(DISTINCT `customers`.`password`) AS password,
			GROUP_CONCAT(DISTINCT `customers`.`created_at`) AS created_at,
			GROUP_CONCAT(DISTINCT `customers`.`updated_at`) AS updated_at,
			GROUP_CONCAT(DISTINCT `c2s`.`c2s_id`) AS c2s_id,
			GROUP_CONCAT(DISTINCT `c2s`.`store_id`) AS store_id,
			GROUP_CONCAT(DISTINCT `c2s`.`balance`) AS balance,
			GROUP_CONCAT(DISTINCT `c2s`.`due`) AS due,
			GROUP_CONCAT(DISTINCT `c2s`.`status`) AS status,
			GROUP_CONCAT(DISTINCT `c2s`.`sort_order`) AS sort_order
			FROM `customers` 
			LEFT JOIN `customer_to_store` c2s ON (`customers`.`customer_id` = `c2s`.`customer_id`)
			WHERE {$where_sql}`c2s`.`store_id` = ? AND `c2s`.`status` = ?
			GROUP BY `customers`.`customer_id` 
			ORDER BY `customers`.`customer_name` ASC 
			LIMIT {$limit}";
		
		$params[] = store_id();
		$params[] = 1;
		
		$statement = db()->prepare($sql);
		$statement->execute($params);
		$customers = $statement->fetchAll(PDO::FETCH_ASSOC);
		
		$customer_array = array();
		if ($statement->rowCount() > 0) {
		    $customer_array = $customers;
		}

	    header('Content-Type: application/json');
	    echo json_encode($customer_array); 
	    exit();

	} catch (Exception $e) {

	    header('HTTP/1.1 422 Unprocessable Entity');
	    header('Content-Type: application/json; charset=UTF-8');
	    echo json_encode(array('errorMsg' => $e->getMessage()));
	    exit();
	}
}

// Fetch a product item
if ($request->server['REQUEST_METHOD'] == 'GET' && isset($request->get['action_type']) && $request->get['action_type'] == 'PRODUCTITEM')
{
	try {

		if (isset($request->get["is_edit_mode"]) && $request->get["is_edit_mode"])	 {
		    if (user_group_id() != 1 && !has_permission('access', 'add_item_to_invoice')) {
		      throw new Exception(trans('error_item_add_permission'));
		    }
		}

		// Validate product id
		if (!isset($request->get['p_id'])) {
			throw new Exception(trans('error_product_id'));
		}

		$p_id = $request->get['p_id'];
		$where_query = "`p2s`.`store_id` = ? AND (`p_id` = ? OR `p_code` = ?) AND `p2s`.`status` = ? AND (`p2s`.`quantity_in_stock` > 0 OR `products`.`p_type` = 'service')";
		if (get_preference('expiry_yes')) {
			$where_query .= " AND `p2s`.`e_date` > NOW()";
		}
		$statement = db()->prepare("SELECT * FROM `products` LEFT JOIN `product_to_store` p2s ON (`products`.`p_id` = `p2s`.`product_id`) WHERE {$where_query}");
		$statement->execute(array(store_id(), $p_id, $p_id, 1));
		$product = $statement->fetch(PDO::FETCH_ASSOC);
		if (!$product) {
			throw new Exception(trans('error_out_of_stock'));
		}
		$product = array_replace($product, array('p_name' => html_entity_decode($product['p_name'])));
		if ($product['taxrate_id']) {
			$product['tax_amount'] = (get_the_taxrate($product['taxrate_id'],'taxrate') / 100) * $product['sell_price'];
		}
		$product['unit_name'] = get_the_unit($product['unit_id'],'unit_name');

		echo json_encode($product); 
		exit();

	} catch (Exception $e) { 

	    header('HTTP/1.1 422 Unprocessable Entity');
	    header('Content-Type: application/json; charset=UTF-8');
	    echo json_encode(array('errorMsg' => $e->getMessage()));
	    exit();
	}
}

// Fetch product list
if ($request->server['REQUEST_METHOD'] == 'GET' && isset($request->get['action_type']) && $request->get['action_type'] == 'PRODUCTLIST')
{
	try {
		$product_model = registry()->get('loader')->model('product');
		$store_id = store_id();

		if (isset($request->get['query_string'])) {
			$query_string = $request->get['query_string'];
		} else {
			$query_string = '';
		}

		if (isset($request->get['category_id'])) {
			$category_id = $request->get['category_id'];
		} else {
			$category_id = '';
		}

		if (isset($request->get['field'])) {
			$field = $request->get['field'];
		} else {
			$field = 'p_name';
		}

		if (isset($request->get['page'])) {
			$page = $request->get['page'];
		} else {
			$page = 1;
		}

		if (isset($request->get['limit'])) {
			$limit = (int)$request->get['limit'];
		} else {
			$limit = get_preference('pos_product_display_limit') ? (int)get_preference('pos_product_display_limit') : 20;
		}

		$start = ($page - 1) * $limit;

		$data = array(
			'query_string' => $query_string,
			'field' => $field,
			'category_id' => $category_id,
			'start' => $start,
			'limit' => $limit,
		);
		$products = $product_model->getPosProducts($data, $store_id);
		$product_total = count($products);

		// Pagination
		$pagination = new Pagination();
		$pagination->total = $product_total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = root_url().'_inc/pos.php?query_string='.$query_string.'&category_id='.$category_id.'&field='.$field.'&action_type=PRODUCTLIST&page={page}&limit='.$limit;
		$pagination = $pagination->render();

	    header('Content-Type: application/json');
	    echo json_encode(array('products' => array_values($products), 'pagination' => $pagination, 'page' => $page+1)); 
	    exit();
		
	} catch (Exception $e) {

	    header('HTTP/1.1 422 Unprocessable Entity');
	    header('Content-Type: application/json; charset=UTF-8');
	    echo json_encode(array('errorMsg' => $e->getMessage()));
	    exit();
	}
}

// Fetch form data for quick product creation
if ($request->server['REQUEST_METHOD'] == 'GET' && isset($request->get['action_type']) && $request->get['action_type'] == 'GETPRODUCTFORMDATA')
{
	try {
		$current_store_id = 1; // Default
		try {
			$current_store_id = store_id();
		} catch (Exception $e) {
			// Usa store_id padrão se falhar
		}
		
		// Get categories - busca todas as categorias disponíveis para a loja ou globais
		$categories = array();
		try {
			// Busca todas as categorias (globais e da loja)
			$statement = db()->prepare("SELECT `category_id`, `category_name` FROM `categories` ORDER BY `category_name` ASC");
			$statement->execute();
			$categories = $statement->fetchAll(PDO::FETCH_ASSOC);
		} catch (Exception $e) {
			$categories = array();
		}

		// Get units
		$units = array();
		try {
			$statement = db()->prepare("SELECT `unit_id`, `unit_name` FROM `units` WHERE `status` = ? ORDER BY `unit_name` ASC");
			$statement->execute(array(1));
			$units = $statement->fetchAll(PDO::FETCH_ASSOC);
		} catch (Exception $e) {
			// Tenta sem filtro de status
			try {
				$statement = db()->prepare("SELECT `unit_id`, `unit_name` FROM `units` ORDER BY `unit_name` ASC LIMIT 50");
				$statement->execute();
				$units = $statement->fetchAll(PDO::FETCH_ASSOC);
			} catch (Exception $e2) {
				$units = array();
			}
		}

		// Get suppliers - APENAS da loja atual
		$suppliers = array();
		try {
			// Busca fornecedores associados à loja atual
			$statement = db()->prepare("SELECT DISTINCT s.`sup_id`, s.`sup_name` FROM `suppliers` s 
				INNER JOIN `supplier_to_store` s2s ON (s.`sup_id` = s2s.`sup_id` OR s.`sup_id` = s2s.`supplier_id`)
				WHERE s2s.`store_id` = ? AND s2s.`status` = ? 
				ORDER BY s.`sup_name` ASC");
			$statement->execute(array($current_store_id, 1));
			$suppliers = $statement->fetchAll(PDO::FETCH_ASSOC);
		} catch (Exception $e) {
			// Ignora erro na query com store
		}
		
		// Fallback se não encontrar suppliers - busca apenas os que estão na loja atual
		if (empty($suppliers)) {
			try {
				// Segunda tentativa com a coluna sup_id
				$statement = db()->prepare("SELECT DISTINCT s.`sup_id`, s.`sup_name` FROM `suppliers` s 
					INNER JOIN `supplier_to_store` s2s ON s.`sup_id` = s2s.`sup_id`
					WHERE s2s.`store_id` = ? 
					ORDER BY s.`sup_name` ASC LIMIT 100");
				$statement->execute(array($current_store_id));
				$suppliers = $statement->fetchAll(PDO::FETCH_ASSOC);
			} catch (Exception $e) {
				$suppliers = array();
			}
		}

		// Get taxrates
		$taxrates = array();
		try {
			$statement = db()->prepare("SELECT `taxrate_id`, `taxrate_name`, `taxrate` FROM `taxrates` WHERE `status` = ? ORDER BY `taxrate_name` ASC");
			$statement->execute(array(1));
			$taxrates = $statement->fetchAll(PDO::FETCH_ASSOC);
		} catch (Exception $e) {
			// Tenta sem filtro de status
			try {
				$statement = db()->prepare("SELECT `taxrate_id`, `taxrate_name`, `taxrate` FROM `taxrates` ORDER BY `taxrate_name` ASC LIMIT 50");
				$statement->execute();
				$taxrates = $statement->fetchAll(PDO::FETCH_ASSOC);
			} catch (Exception $e2) {
				$taxrates = array();
			}
		}

		header('Content-Type: application/json');
		echo json_encode(array(
			'categories' => $categories ? $categories : array(),
			'units' => $units ? $units : array(),
			'suppliers' => $suppliers ? $suppliers : array(),
			'taxrates' => $taxrates ? $taxrates : array()
		));
		exit();

	} catch (Exception $e) {

		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}
