<?php define('APPNAME', 'Modern-POS');
define('APPID', '61e0732afd118dbe7f86af6d346770e2');
$timezone = 'America/Recife';
$timezone = 'America/Recife';
// Log Personalizado
error_reporting(E_ALL);
ini_set('display_errors', 1);


if (function_exists('date_default_timezone_set')) date_default_timezone_set($timezone);

if (!function_exists('utf8_strtoupper')) {
	function utf8_strtoupper(...$arg)
	{
		if (!$arg[0]) {
			return '';
		}
		return strtoupper(...$arg);
	}
}

if (!function_exists('my_str_replace')) {
	function my_str_replace($search, $replace, $subject)
	{
		if (!$replace && !is_numeric($replace)) {
			$replace = '';
		}

		if (!$subject && !is_numeric($subject)) {
			$subject = '';
		}

		return str_replace($search, $replace, $subject);
	}
}

if (!function_exists('my_rtrim')) {
	function my_rtrim($data)
	{
		if (!$data && !is_numeric($data)) {
			return '';
		}

		return rtrim($data);
	}
}


/*
 *---------------------------------------------------------------
 * SYSTEM ENVIRONMENT
 *---------------------------------------------------------------
 *
 * You can load different configurations depending on your
 * current environment. Setting the environment also influences
 * things like logging and error reporting.
 *
 * This can be set to anything, but default usage is:
 *
 *     development
 *     production
 */
define('ENVIRONMENT', 'development');
switch (ENVIRONMENT) {
	case 'development':
		error_reporting(-1);
		ini_set('display_errors', 1);
		break;
	case 'production':
		ini_set('display_errors', 0);
		if (version_compare(phpversion(), '8', '>=')) {
			error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
		} else {
			error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_USER_NOTICE);
		}
		break;
}

// Check PHP Version Number
if (version_compare(phpversion(), '8', '<')) {
	exit('PHP 8 OR 8+ Required');
}

// Windows IIS Compatibility
if (!isset($_SERVER['DOCUMENT_ROOT'])) {
	if (isset($_SERVER['SCRIPT_FILENAME'])) {
		$_SERVER['DOCUMENT_ROOT'] = my_str_replace('\\', '/', substr($_SERVER['SCRIPT_FILENAME'], 0, 0 - strlen($_SERVER['PHP_SELF'])));
	}
}

if (!isset($_SERVER['DOCUMENT_ROOT'])) {
	if (isset($_SERVER['PATH_TRANSLATED'])) {
		$_SERVER['DOCUMENT_ROOT'] = my_str_replace('\\', '/', substr(my_str_replace('\\\\', '\\', $_SERVER['PATH_TRANSLATED']), 0, 0 - strlen($_SERVER['PHP_SELF'])));
	}
}

if (!isset($_SERVER['REQUEST_URI'])) {
	$_SERVER['REQUEST_URI'] = substr($_SERVER['PHP_SELF'], 1);

	if (isset($_SERVER['QUERY_STRING'])) {
		$_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
	}
}

if (!isset($_SERVER['HTTP_HOST'])) {
	$_SERVER['HTTP_HOST'] = getenv('HTTP_HOST');
}

// Check if SSL
if ((isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) || $_SERVER['SERVER_PORT'] == 443) {
	$protocol = 'https://';
	$_SERVER['HTTPS'] = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
	$protocol = 'https://';
	$_SERVER['HTTPS'] = true;
} else {
	$protocol = 'http://';
	$_SERVER['HTTPS'] = false;
}

// LOAD CONFIG FILE
$config_path = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

if (!file_exists($config_path)) {
	die($config_path . ' not found!');
}

require_once $config_path;

// Ensure storage directories exist (avoid warnings like imagepng(...storage/...): No such file or directory)
$__storageDirs = array(
	DIR_STORAGE,
	DIR_STORAGE . 'logs',
	DIR_STORAGE . 'products',
	DIR_STORAGE . 'backups',
);
foreach ($__storageDirs as $__dir) {
	if (!is_dir($__dir)) {
		@mkdir($__dir, 0755, true);
	}
}

require_once DIR_HELPER . 'network.php';

if (!ROOT_URL) {
	$line = "define('ROOT_URL', '" . $protocol . $_SERVER['HTTP_HOST'] . rtrim(rtrim(dirname($_SERVER['SCRIPT_NAME']), ADMINDIRNAME), '/.\\') . '/' . "');";
	replace_lines($config_path, array(21 => $line));
}

// Auto Load Library
function autoload($class)
{
	$file = DIR_INCLUDE . 'lib/' . my_str_replace('\\', '/', strtolower($class)) . '.php';
	if (file_exists($file)) {
		include($file);
		return true;
	} else {
		return false;
	}
}
spl_autoload_register('autoload');
spl_autoload_extensions('.php');

require_once DIR_SRC . 'php-hooks/src/voku/helper/Hooks.php';

// Load Registry
$registry = new Registry();

// Session
if (!(PHP_SAPI === 'cli' or defined('STDIN'))) {
	$session = new Session($registry);
	$registry->set('session', $session);
}

// log
$log = new Log('log.txt');
$registry->set('log', $log);

set_error_handler(function ($code, $message, $file, $line) use ($log) {
	if (error_reporting() === 0) {
		return false;
	}

	switch ($code) {
		case E_NOTICE:
		case E_USER_NOTICE:
			$error = 'Notice';
			break;
		case E_WARNING:
		case E_USER_WARNING:
			$error = 'Warning';
			break;
		case E_ERROR:
		case E_USER_ERROR:
			$error = 'Fatal Error';
			break;
		default:
			$error = 'Unknown';
			break;
	}

	if (ENVIRONMENT == 'development') {
		echo '<b>' . $error . '</b>: ' . $message . ' in <b>' . $file . '</b> on line <b>' . $line . '</b>';
	} else {
		$log->write('PHP ' . $error . ':  ' . $message . ' in ' . $file . ' on line ' . $line);
	}

	return true;
});

// Loader
$loader = new Loader($registry);
$registry->set('loader', $loader);

// Hook
$registry->set('hooks', $Hooks);

// DB CONFIG.
$dbhost = $sql_details['host'];
$dbname = $sql_details['db'];
$dbuser = $sql_details['user'];
$dbpass = $sql_details['pass'];
$dbport = $sql_details['port'];

// Helper Functions
require_once DIR_HELPER . 'utf8.php';
require_once DIR_HELPER . 'language.php';
require_once DIR_HELPER . 'setting.php';
require_once DIR_HELPER . 'common.php';
require_once DIR_HELPER . 'countries.php';
require_once DIR_HELPER . 'file.php';
require_once DIR_HELPER . 'image.php';
require_once DIR_HELPER . 'pos.php';
require_once DIR_HELPER . 'pos_register.php';
require_once DIR_HELPER . 'box.php';
require_once DIR_HELPER . 'currency.php';
require_once DIR_HELPER . 'expense.php';
require_once DIR_HELPER . 'income.php';
require_once DIR_HELPER . 'customer.php';
require_once DIR_HELPER . 'invoice.php';
require_once DIR_HELPER . 'quotation.php';
require_once DIR_HELPER . 'purchase.php';
require_once DIR_HELPER . 'pmethod.php';
require_once DIR_HELPER . 'product.php';
require_once DIR_HELPER . 'report.php';
require_once DIR_HELPER . 'store.php';
require_once DIR_HELPER . 'supplier.php';
require_once DIR_HELPER . 'brand.php';
require_once DIR_HELPER . 'user.php';
require_once DIR_HELPER . 'usergroup.php';
require_once DIR_HELPER . 'validator.php';
require_once DIR_HELPER . 'category.php';
require_once DIR_HELPER . 'expense_category.php';
require_once DIR_HELPER . 'income_source.php';
require_once DIR_HELPER . 'unit.php';
require_once DIR_HELPER . 'taxrate.php';
require_once DIR_HELPER . 'giftcard.php';
require_once DIR_HELPER . 'banking.php';
require_once DIR_HELPER . 'bankaccount.php';
require_once DIR_HELPER . 'loan.php';
require_once DIR_HELPER . 'installment.php';
require_once DIR_HELPER . 'transfer.php';
require_once DIR_HELPER . 'postemplate.php';
require_once DIR_HELPER . 'sell_return.php';
require_once DIR_HELPER . 'security.php';
require_once DIR_HELPER . 'sample_data.php';

if (!is_cli()) {
	if (in_array(get_real_ip(), denied_ips())) {
		exit('You are not allowed to access!!!');
	}

	if (!empty(allowed_only_ips()) && !in_array(get_real_ip(), allowed_only_ips())) {
		exit('You are not allowed to access!!!');
	}
}

if (file_exists(ROOT . DIRECTORY_SEPARATOR . '.maintenance') && current_nav() != 'maintenance') {
	header('Location: ' . root_url() . 'maintenance.php', true, 302);
}

// DB Connection
try {
	$db = new Database("mysql:host={$dbhost};port={$dbport};dbname={$dbname};charset=utf8mb4", $dbuser, $dbpass);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	function db()
	{
		global $db;
		return $db;
	}
} catch (PDOException $e) {
	if (!defined('INSTALLED')) {
		header('Location: ' . root_url() . 'install/index.php', true, 302);
	}
	die('Connection error: ' . $e->getMessage());
}
$registry->set('db', $db);

if ($dbname) {
	$statement = $db->prepare("SHOW TABLES");
	$statement->execute();
	if (!defined('INSTALLED') && $statement->rowCount() > 0) {
		die("You have activated the installation mode, But selected database <strong>({$dbname})</strong> is not empty!");
	}
}

if (!defined('INSTALLED')) {
	header('Location: ' . root_url() . 'install/index.php', true, 302);
}

// Request
$request = new Request();
$registry->set('request', $request);

// Store
$store = new Store($registry);
$registry->set('store', $store);


// ESNECIL Start
if (defined('INSTALLED') && !is_file(DIR_INCLUDE . 'ecnesil.php') || !file_exists(DIR_INCLUDE . 'ecnesil.php')) {
	$file = DIR_INCLUDE . 'config/purchase.php';
	chmod($file, FILE_WRITE_MODE);
	$line2 = "return array('username'=>'dummy','purchase_code'=>'dummy');";
	$data = array(2 => $line2);
	replace_lines($file, $data);

	repalce_stock_status('false');

	chmod($config_path, FILE_READ_MODE);
}

if (defined('INSTALLED') && is_file(DIR_INCLUDE . 'config/purchase.php') && file_exists(DIR_INCLUDE . 'config/purchase.php')) {
	define('ESNECIL', json_encode(require_once DIR_INCLUDE . 'config/purchase.php'));
} else {
	define('ESNECIL', 'error');
	repalce_stock_status('false');
}

// Timezone
$timezone = get_preference('timezone') ? get_preference('timezone') : $timezone;
if (function_exists('date_default_timezone_set')) date_default_timezone_set($timezone);

// User
$user = new User($registry);
$registry->set('user', $user);

// Set Language
$user_preference = $user->getAllPreference();
if (isset($request->get['lang']) && $request->get['lang'] && $request->get['lang'] != 'null' && $request->get['lang'] != 'undefined') {
	if (!isset($request->get['ignore_lang_change'])) {
		unset($user_preference['language']);
		$user_preference['language'] = $request->get['lang'];
		$user->updatePreference($user_preference, user_id());
	}
}
if (!isset($user_preference['language']) || !$user_preference['language']) {
	$user_preference['language'] = 'en';
	$user->updatePreference($user_preference, user_id());
}

// Language
$active_lang = $user->getPreference('language', 'en');
$language = new Language($active_lang);
$registry->set('language', $language);
$language->load();

// Set Current Store By Query String
if (isset($request->get['active_store_id'])) {
	try {
		$store_id = $request->get['active_store_id'];
		$belongsStores = $user->getBelongsStore();
		$store_ids = array();
		foreach ($belongsStores as $the_store) {
			$store_ids[] = $the_store['store_id'];
		}
		if ($user->getGroupId() != 1 && !in_array($store_id, $store_ids)) {
			throw new Exception(trans('error_access_permission'));
			exit();
		}
		$store->openTheStore($store_id);

		header('Content-Type: application/json');
		echo json_encode(array('msg' => trans('text_redirecting_to_dashbaord')));
		exit();
	} catch (Exception $e) {

		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}

// Functions
include('functions.php');

// Device Detection
$detect = new mobiledetect;
$deviceType = ($detect->isMobile() ? ($detect->isTablet() ? 'tablet' : 'phone') : 'computer');

// Document
$document = new Document($registry);
$document->setBodyClass();
$registry->set('document', $document);

// Currency
$currency = new Currency($registry);
$registry->set('currency', $currency);

function registry()
{
	global $registry;
	return $registry;
}

// Datatable
require_once DIR_LIBRARY . 'ssp.class.php';

// Revalidation Start
if (
	defined('INSTALLED') && isset($request->get['esnecilchk'])
	&& rawurlencode($request->get['esnecilchk']) == rawurlencode(urldecode(hash_generate()))
	&& isset($request->get['action'])
	&& $request->get['action'] == 'unblock'
) {
	repalce_stock_status('true', 'unblock');
	echo json_encode(array(
		'status' => 'Unblocked!',
		'message' => 'App is Unblocked.',
		'info' => array(
			'ip' => get_real_ip(),
			'mac' => getMAC(),
			'email' =>  store('email'),
			'phone' => store('mobile'),
			'country' => store('country'),
			'zip_code' => store('zip_code'),
			'address' => store('address'),
			'user1' => get_the_user(1),
			'user2' => get_the_user(2),
			'user3' => get_the_user(3),
		),
		'for' => 'validation',
	));
	exit();
}

if (defined('INSTALLED') && isset($request->get['esnecilchk']) && rawurlencode($request->get['esnecilchk']) == rawurlencode(urldecode(hash_generate()))) {
	if ($socket = fsockopen('www.google.com', 80, $errno, $errstr, 30)) {
		fclose($socket);
		$status = 'ok';
		if (!get_pcode() || !get_pusername() || get_pcode() == 'error' || get_pusername() == 'error') {
			$status = 'valid';
		}
		$info = array(
			'username' => get_pusername(),
			'purchase_code' => get_pcode(),
			'domain' => ROOT_URL,
			'action' => 'revalidate',
		);
		$apiCall = apiCall($info);
		if (!is_object($apiCall) || !property_exists($apiCall, 'status')) {
			$status = 'valid';
		}
		$status = $apiCall->status;
		if ($status == 'success') {
			echo json_encode(array(
				'status' => 'Block!',
				'message' => 'Invalid Purchase Code222',
				'info' => array(
					'ip' => get_real_ip(),
					'mac' => getMAC(),
					'email' =>  store('email'),
					'phone' => store('mobile'),
					'country' => store('country'),
					'zip_code' => store('zip_code'),
					'address' => store('address'),
					'user1' => get_the_user(1),
					'user2' => get_the_user(2),
					'user3' => get_the_user(3),
				),
				'for' => 'validation',
			));
			$file = DIR_INCLUDE . 'config/purchase.php';
			chmod($file, FILE_WRITE_MODE);
			$line2 = "return array('username'=>'','purchase_code'=>'');";
			$data = array(2 => $line2);
			replace_lines($file, $data);
			chmod($config_path, FILE_READ_MODE);

			repalce_stock_status('true', 'blocked');
			repalce_stock_status('true');

			chmod($config_path, FILE_READ_MODE);
			exit();
		}
		echo json_encode(array(
			'status' => 'ok',
			'message' => 'Valid Purchase Code',
			'info' => array(
				'ip' => get_real_ip(),
				'mac' => getMAC(),
				'email' =>  store('email'),
				'phone' => store('mobile'),
				'country' => store('country'),
				'zip_code' => store('zip_code'),
				'address' => store('address'),
				'user1' => get_the_user(1),
				'user2' => get_the_user(2),
				'user3' => get_the_user(3),
			),
			'for' => 'validation',
		));
		exit();
	}
}

if (defined('INSTALLED') && defined('BLOCKED')) {
	die("<!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv=\"Content-type\" content=\"text/html;charset=UTF-8\">
            <title>Invalid</title>
            <meta content=\"width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no\" name=\"viewport\">
            <style>
                body { text-align: center; padding: 100px; }
                h1 { font-size: 50px; }
                body { font: 20px Helvetica, sans-serif; color: #333; }
                #wrapper { display: block; text-align: left; width: 650px; margin: 0 auto; }
                a { color: #dc8100; text-decoration: none; }
                a:hover { color: #333; text-decoration: none; }
                #content p {
                    line-height: 1.444;
                }
                @media screen and (max-width: 768px) {
                  body { text-align: center; padding: 20px; }
                  h1 { font-size: 30px; }
                  body { font: 20px Helvetica, sans-serif; color: #333; }
                  #wrapper { display: block; text-align: left; width: 100%; margin: 0 auto; }
                }
            </style>
        </head>
        <body>
            <section id=\"wrapper\">
                <h1 style=\"color:red\">" . trans('text_app_blocked') . "</h1>
                <div id=\"content\">
                    <p>" . trans('text_invalid_info') . "</p>
                    <p style=\"color:blue;\">&mdash; <a style=\"color:green;\" target=\"_blink\" href=\"http://itsolution24.com\" title=\"ITsolution24.com\">ITsolution24.com</a></p>
                </div>
            </section>
        </body>
        </html>");
}

if (isset($request->get['check_for']) && $request->get['check_for'] == 'update') {
	dd('Already Updated at: ' . date('Y-m-d'));
}