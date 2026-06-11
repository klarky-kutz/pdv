<?php 
ob_start();
session_start();
include ("../_init.php");

// Check, if user logged in or not
// If user is not logged in then return error
if (!is_loggedin()) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_login')));
  exit();
}

$store_id = store_id();
$user_id = user_id();

if ($request->server['REQUEST_METHOD'] == 'POST' && $request->get['action_type'] == 'PAYMENT')
{
  try {

    if (user_group_id() != 1 AND !has_permission('access', 'purchase_payment')) {
      throw new Exception(trans('error_payment_permission'));
    }

    $invoice_model = registry()->get('loader')->model('purchase');
    $invoice_id_length = 1;
    $invoice_ids = array();
    if (isset($request->post['selected'])) {
    	$invoice_ids = array_filter(explode('|', $request->post['selected']));
	    if (!is_array($invoice_ids)) {
	      $invoice_ids = array($invoice_ids);
	    }
    	$invoice_id_length = count($invoice_ids);
    } else {
    	$invoice_ids = array($request->post['invoice-id']);
    }

    for ($i=0; $i < $invoice_id_length; $i++) { 
        $invoice_id = $invoice_ids[$i];

    	$invoice_info = $invoice_model->getInvoiceInfo($invoice_id, $store_id);
    	if (!$invoice_info) {
    		continue;
    	}

    	$invoice_price = $invoice_model->getSellingPrice($invoice_id, $store_id);
    	if (!$invoice_price) {
    		continue;
    	}
        
	    $note = $request->post['note'] ?? NULL;
	    $sup_id = $request->post['sup-id'] ?? $invoice_info['sup_id'];
	    $pmethod_id = $request->post['pmethod-id'] ?? 1;
	    if (isset($request->post['paid-amount'])) {
	    	$paid_amount = (float)$request->post['paid-amount'];
	    } else {
	    	$paid_amount = 0;
	    }

	    if ($paid_amount <= 0) {
	    	$paid_amount = $invoice_price['due'];
	    }

	    $created_at = date('Y-m-d H:i:s');
	    $total_paid = $paid_amount;
	    $payable_amount = $invoice_price['payable_amount'] - $invoice_price['paid_amount'];
	    $due = ($invoice_price['due'] - $paid_amount) > 0 ? ($invoice_price['due'] - $paid_amount) : 0;
	    $balance = 0;
	    if ($paid_amount > $payable_amount) {
	      $due = 0;
	      $balance = $paid_amount - $payable_amount;
	      $paid_amount = $payable_amount;
	    }

	    $details = isset($request->post['payment_details']) ? $request->post['payment_details'] : array();
	    $details = serialize($details);

	    $statement = db()->prepare("INSERT INTO `purchase_payments` (type, store_id, invoice_id, pmethod_id, amount, details, note, total_paid, balance, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
	    $statement->execute(array('due_paid', $store_id, $invoice_id, $pmethod_id, $paid_amount, $details, $note, $total_paid, $balance, $user_id, $created_at));

	    $Hooks->do_action('Before_Purchase_Payment', $request);

	    // Checkout status
	    $statement = db()->prepare("UPDATE `purchase_info` SET `checkout_status` = ? WHERE `invoice_id` = ? AND `store_id` = ?");
	    $statement->execute(array(1, $invoice_id, $store_id));

	    // Add Paid Amount
	    $statement = db()->prepare("UPDATE `purchase_price` SET `paid_amount` = `paid_amount`+$paid_amount, `due_paid` = `due_paid`+$paid_amount, `due` = ? WHERE `invoice_id` = ? AND `store_id` = ?");
	    $statement->execute(array($due, $invoice_id, $store_id));

	    // Fetch invoice price
	    $invoice_price = $invoice_model->getSellingPrice($invoice_id, $store_id);
	    if ($invoice_price['payable_amount'] <= $invoice_price['paid_amount']) {
	      $statement = db()->prepare("UPDATE `purchase_info` SET `payment_status` = ? WHERE `invoice_id` = ? AND `store_id` = ?");
	      $statement->execute(array('paid', $invoice_id, $store_id));
	    }

	    if ($paid_amount > 0) {
	      $reference_no = generate_purchase_log_ref_no('due_paid');
	      $statement = db()->prepare("INSERT INTO `purchase_logs` (sup_id, reference_no, type, pmethod_id, description, amount, store_id, ref_invoice_id, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
	      $statement->execute(array($sup_id, $reference_no, 'due_paid', $pmethod_id, 'Due paid to supplier', $paid_amount, $store_id, $invoice_id, $user_id, $created_at));

	      $statement = db()->prepare("UPDATE `supplier_to_store` SET `balance` = `balance` - $paid_amount WHERE `sup_id` = ? AND `store_id` = ?");
	      $statement->execute(array($sup_id, $store_id));
	    }

	    // Withdraw
	    if (($account_id = store('deposit_account_id')) && $paid_amount > 0) {
	      $ref_no = unique_transaction_ref_no('withdraw');
	      $statement = db()->prepare("SELECT `category_id` FROM `expense_categorys` WHERE `due_paid` = ?");
	      $statement->execute(array(1));
	      $category = $statement->fetch(PDO::FETCH_ASSOC);
	      $exp_category_id = $category['category_id'];
	      $title = 'Debit for Product Purchase';
	      $details = 'Supplier name: ' . get_the_supplier($sup_id, 'sup_name');
	      $image = 'NULL';
	      $withdraw_amount = $paid_amount;
	      $transaction_type = 'withdraw';

	      $statement = db()->prepare("INSERT INTO `bank_transaction_info` (store_id, account_id, exp_category_id, ref_no, invoice_id, transaction_type, title, details, image, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
	      $statement->execute(array($store_id, $account_id, $exp_category_id, $ref_no, $invoice_id, $transaction_type, $title, $details, $image, $user_id, date_time()));
		  $info_id = db()->lastInsertId();

	      $statement = db()->prepare("INSERT INTO `bank_transaction_price` (store_id, info_id, ref_no, amount) VALUES (?, ?, ?, ?)");
	      $statement->execute(array($store_id, $info_id, $ref_no, $withdraw_amount));

	      $statement = db()->prepare("UPDATE `bank_account_to_store` SET `withdraw` = `withdraw` + $withdraw_amount WHERE `store_id` = ? AND `account_id` = ?");
	      $statement->execute(array($store_id, $account_id));

	      $statement = db()->prepare("UPDATE `bank_accounts` SET `total_deposit` = `total_deposit` + $withdraw_amount WHERE `id` = ?");
	      $statement->execute(array($account_id));
	    }

	    if ($balance > 0) {
	      $statement = $this->db->prepare("UPDATE `purchase_price` SET `balance` = ? WHERE `store_id` = ? AND `invoice_id` = ?");
	      $statement->execute(array($balance, $store_id, $invoice_id));
	    }
    }

    $Hooks->do_action('After_Purchase_Payment', $request);

    header('Content-Type: application/json');
    echo json_encode(array('msg' => trans('text_purchase_due_paid_success')));
    exit();

  } catch (Exception $e) {
    
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
}

// Payment method fields
if (isset($request->get['pmethod_id']) && $request->get['action_type'] == 'FIELD') 
{
  $pmethod_model = registry()->get('loader')->model('pmethod');
  $pmethod_id = $request->get['pmethod_id'];
  $pmethod = $pmethod_model->getPMethod($pmethod_id);
  if ($pmethod && file_exists(ROOT.'/_inc/template/partials/pmethodfield/'.strtolower(my_str_replace(' ', '_',$pmethod['name'])).'_field.php')) {
    include ROOT.'/_inc/template/partials/pmethodfield/'.strtolower(my_str_replace(' ', '_',$pmethod['name'])).'_field.php';
  }
  exit();
}

if (isset($request->get['action_type']) && $request->get['action_type'] == 'ORDERDETAILS')
{
  $invoice_id = $request->get['invoice_id'];
  if (!$invoice_id) {
    throw new Exception(trans('error_invoice_id'));
  }
  $order = array();
  $items = array();
  $where_query = "`purchase_info`.`store_id` = ? AND `purchase_info`.`invoice_id` = '{$invoice_id}'";
  $statement = db()->prepare("SELECT * FROM `purchase_info` 
        LEFT JOIN `purchase_price` ON (`purchase_price`.`invoice_id` = `purchase_info`.`invoice_id`)
        WHERE $where_query");
  $statement->execute(array(store_id()));
  $order = $statement->fetch(PDO::FETCH_ASSOC);
  
  $purchase_model = registry()->get('loader')->model('purchase');
  $payment_model = registry()->get('loader')->model('payment');
  $items = $purchase_model->getInvoiceItems($order['invoice_id'], store_id());
  $payments = $payment_model->getPurchasePayments($order['invoice_id'], store_id());

  $order['items']     = $items;
  $order['table']     = '';
  $order['payments']  = $payments;

  header('Content-Type: application/json');
  echo json_encode(array('msg' => trans('text_success'), 'order' => $order));
  exit();
}