<?php
ob_start();
session_start();
define('START', true);
include ("_init.php");

$json = array();

if (defined('INSTALLED')) {
	if (is_ajax()) {
		$json['redirect'] = root_url().'index.php';
		echo json_encode($json);
		exit();
	} else {
		header('Location: ../index.php');
	}
}

/*if (!check_pcode()) {
	if (is_ajax()) {
		//$json['redirect'] = root_url().'install/purchase_code.php';
		//echo json_encode($json);
		exit();
	} else {
		redirect('purchase_code.php');
	}
}
*/
if (isset($session->data['timezone']))
{
	$timezone = $session->data['timezone'];
} else {
	$timezone = 'Asia/Dhaka';
}

$errors = array();
$success = array();
$info = array();

if(!checkDBConnection()) {
	if (!check_pcode()) {
		if (is_ajax()) {
			$json['redirect'] = root_url().'install/database.php';
			echo json_encode($json);
			exit();
		} else {
			redirect('database.php');
		}
	}
}

if ($request->server['REQUEST_METHOD'] == 'POST') {

	$dbhost = $sql_details['host'];
	$dbname = $sql_details['db'];
	$dbuser = $sql_details['user'];
	$dbpass = $sql_details['pass'];
	$dbport = $sql_details['port'];

	try {
		$db = new PDO("mysql:host={$dbhost};port={$dbport};dbname={$dbname};charset=utf8",$dbuser,$dbpass);
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
	catch(PDOException $e) {
		if (!check_pcode()) {
			if (is_ajax()) {
				$json['redirect'] = root_url().'install/database.php';
				echo json_encode($json);
				exit();
			} else {
				redirect('database.php');
			}
		}
	}

	// Validate Post Data
	if (!validateString($request->post['store_name'])) {
		$errors['store_name'] = 'Store name field is required.';
	} 
	if (empty($request->post['phone'])) {
		$errors['phone'] = 'Phone number field is required.';
	} 
	if (!validateEmail($request->post['email'])) {
		$errors['email'] = 'Email field is required.';
	} 
	if (($errMsg = checkPasswordStrongness($request->post['password'])) != 'ok') {
		$errors['password'] = $errMsg;
	} 
	if (empty($request->post['address'])) {
		$errors['address'] = 'Address field is required.';
	} 
	if(empty($errors)) 
	{
		$info['username'] = get_pusername();
		$info['purchase_code'] = get_pcode();
		$info['domain'] = ROOT_URL;
		$info['ip'] = get_real_ip();
		$info['mac'] = getMAC();
		$info['email'] = $request->post['email'];
		$info['phone'] = $request->post['phone'];
		$info['country'] = '';
		$info['zip_code'] = '';
		$info['address'] = $request->post['address'];
		$info['action'] = 'installinfo';
	    $apiCall = apiCall($info);

		$info = array(
			'name' => $request->post['store_name'], 
			'code_name' => strtolower(my_str_replace(array('-',' '), '_', $request->post['store_name'])),
			'email' => $request->post['email'], 
			'mobile' => $request->post['phone'],
			'country' => 'US',
			'zip_code' => '1200',
			'status' => 1,
			'cashier_id' => 2,
			'address' => $request->post['address'],
		);

		$preference = array(
			'timezone' => $timezone,
			'invoice_edit_lifespan' => 1440,
			'invoice_edit_lifespan_unit' => 'minute',
			'invoice_delete_lifespan' => 1440,
			'invoice_delete_lifespan_unit' => 'minute',
			'tax' => 0,
			'stock_alert_quantity' => 10,
			'datatable_item_limit' => 25,
			'after_sell_page' => 'pos',
			'invoice_footer_text' => 'Thank you for choosing us!',
			'email_from' => $info['name'],
			'email_address' => 'US',
			'email_driver' => 'smtp_server',
			'smtp_host' => 'smtp.google.com',
			'smtp_username' => '',
			'smtp_password' => '',
			'smtp_port' => 465,
			'ssl_tls' => 'ssl',
		);

		$store_id = 1;

    	// Insert store info
		$statement = $db->prepare("UPDATE `stores` SET `name` = ?, `code_name` = ?, `mobile` = ?, `country` = ?, `zip_code` = ?, `cashier_id` = ?, `address` = ?, `preference` = ? WHERE `store_id` = ?");
    	$statement->execute(array($info['name'], $info['code_name'], $info['mobile'], $info['country'], $info['zip_code'], $info['cashier_id'], $info['address'], serialize($preference), $store_id));
    	
		// Insert user
		$info = array(
			'username' => 'Your Name',
			'email' => $request->post['email'],
			'mobile' => $request->post['phone'],
			'password' => password_hash($request->post['password'], PASSWORD_DEFAULT),
			'ip' => get_real_ip(),
			'status' => 1,
			'created_at' => date_time(), 
		);

		$cashier_email = 'cashier@'.substr(strrchr($request->post['email'], "@"), 1);
		$salesman_email = 'salesman@'.substr(strrchr($request->post['email'], "@"), 1);

		// Update admin info
    	$statement = $db->prepare("UPDATE `users` SET username = ?, email = ?, mobile = ?, password = ?, ip = ?, created_at = ? WHERE `id` = ?");
    	$statement->execute(array($info['username'], $info['email'], $info['mobile'], $info['password'], $info['ip'], $info['created_at'], 1));

    	// Update cashier info
    	$statement = $db->prepare("UPDATE `users` SET email = ?, password = ?, ip = ?, created_at = ? WHERE `id` = ?");
    	$statement->execute(array($cashier_email, $info['password'], $info['ip'], $info['created_at'], 2));

    	// Update salesman info
    	$statement = $db->prepare("UPDATE `users` SET email = ?, password = ?, ip = ?, created_at = ? WHERE `id` = ?");
    	$statement->execute(array($salesman_email, $info['password'], $info['ip'], $info['created_at'], 3));

    	// Update admin info
    	$statement = $db->prepare("UPDATE `users` SET username = ?, email = ?, mobile = ?, password = ?, ip = ?, created_at = ? WHERE `id` = ?");
    	$statement->execute(array($info['username'], $info['email'], $info['mobile'], $info['password'], $info['ip'], $info['created_at'], 1));

    	// Update cashier info
    	$statement = $db->prepare("UPDATE `users` SET email = ?, password = ?, ip = ?, created_at = ? WHERE `id` = ?");
    	$statement->execute(array($cashier_email, $info['password'], $info['ip'], $info['created_at'], 2));

    	// Update salesman info
    	$statement = $db->prepare("UPDATE `users` SET email = ?, password = ?, ip = ?, created_at = ? WHERE `id` = ?");
    	$statement->execute(array($salesman_email, $info['password'], $info['ip'], $info['created_at'], 3));
		
		$config_path = ROOT . '/config.php';
		chmod($config_path, FILE_WRITE_MODE);
		if (is_writable($config_path) === false) {

			$errors['config'] = 'Config file is unwritable';
		} else {

			$file = $config_path;
			$line_host 	= "define('INSTALLED', true);";
			$fileArray = array(2 => $line_host);
			replace_lines($file, $fileArray);
			chmod($config_path, FILE_READ_MODE);

			$session->data['admin_username'] = $request->post['email'];
			$session->data['cashier_username'] = $cashier_email;
			$session->data['salesman_username'] = $salesman_email;
			$session->data['password'] = $request->post['password'];

			$json['redirect'] = 'done.php';
			echo json_encode($json);
			exit();
		} 
	}

	echo json_encode(array_filter($errors));
	exit();
}
?>

<?php 
$title = 'Site Configuration-Modern POS';
include("header.php"); ?>

<?php include '../_inc/template/install/site.php'; ?>

<?php include("footer.php"); ?>
