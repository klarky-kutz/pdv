<?php define('APPNAME', 'Modern-POS');define('APPID', '9ff2bef8eb72df7603ede89e60d1b223');
ini_set('max_execution_time', 300); //300 seconds = 5 minutes
define('ENVIRONMENT', 'production');
defined('START') OR exit('No direct access allowed!');
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

set_error_handler(function($code, $message, $file, $line) use($log) {
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
	}

	return true;
});

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

if ( ! function_exists('encode_data'))
{
    function encode_data($data)
    {
        return base64_encode($data);
    }
}

if ( ! function_exists('decode_data'))
{
    function decode_data($data, $strict = false)
    {
        return base64_decode($data, $strict);
    }
}

if (!function_exists('store'))
{
	function store($data)
	{
		return '';
	}
}

if (!function_exists('my_str_replace'))
{
	function my_str_replace($search, $replace, $subject)
	{
		if (!$replace && !is_numeric($replace)) {
			$replace = '';
		}
		return str_replace($search, $replace, $subject);
	}
}

if (!function_exists('valid_unserialize'))
{
	function valid_unserialize($data)
	{
		if (!$data) {
			return array();
		}

		return unserialize($data);
	}
}

if (!function_exists('my_trim'))
{
	function my_trim($data)
	{
		if (!$data && !is_numeric($data)) {
			return '';
		}

		return trim($data);
	}
}

if (!function_exists('my_rtrim'))
{
	function my_rtrim($data)
	{
		if (!$data && !is_numeric($data)) {
			return '';
		}

		return rtrim($data);
	}
}

if (!function_exists('my_ltrim'))
{
	function my_ltrim($data)
	{
		if (!is_numeric($data) && $data === null) {
			return '';
		}

		return ltrim($data);
	}
}

if (!function_exists('utf8_strtoupper'))
{
	function utf8_strtoupper($string) {
		return mb_strtoupper($string ?: '');
	}
}

if (!function_exists('utf8_strtolower'))
{
	function utf8_strtolower($string) {
		return mb_strtolower($string ?: '');
	}
}

if (!function_exists('utf8_ucfirst'))
{
	function utf8_ucfirst($string) {
		return mb_convert_case($string, MB_CASE_TITLE, "UTF-8");
	}
}

if (!function_exists('trans'))
{
	function trans($key, $case = '')
	{
		$local_trans = array(
		    'text_summary_of_purchase'=>'Here\'s the summary of your purchase.',
		    'text_thank_you_for_choosing'=>'Thank you for choosing ',
		    'text_thanks_regards'=>'Thanks with Best Regards,',
		    'text_database_conf'=>'Database Configuration',
		    'text_step_3_of_6'=>'Running step 3 of 6',
		    'text_checklist'=>'Checklist',
		    'text_verification'=>'Verification',
		    'text_timezone'=>'Timezone',
		    'text_site_config'=>'Site Config',
		    'text_done'=>'Done!',
		    'text_hostname'=>'Hostname',
		    'text_database'=>'Database',
		    'text_username'=>'Username',
		    'text_password'=>'Password',
		    'text_port_3306'=>'Port (3306)',
		    'text_install_instruction'=>'*** This action may take several minutes. Please keep patience while processing this action and never close the browser. Otherwise system will not work properly. Enjoy a cup of coffee while you are waiting... :)',
		    'text_prev_step'=>'Previous Step',
		    'text_next_step'=>'Next Step',
		    'text_footer_link'=>'https://codecanyon.net',
		    'text_footer_link_text'=>'codecanyon.net',
		    'text_all_right_reserved'=>'All right reserved.',
		    'text_congrats_almost_done'=>'Congratulations! Almost Done... :)',
		    'text_login_credentials'=>'Login Credentials',
		    'text_role'=>'Role',
		    'text_admin'=>'Admin',
		    'text_cashier'=>'Cashier',
		    'text_salesman'=>'Salesman',
		    'text_login_now'=>'Login Now',
		    'text_pre_installation_checklist'=>'Pre-Installation Checklist',
		    'text_running_step_1_of_6'=>'Running step 1 of 6',
		    'text_installation_instruction'=>'Please, Resolve all the warning showings in check list to proceed to next step.',
		    'text_verify_purchase_code'=>'Verify Purchase Code',
		    'text_running_step_2_of_6'=>'Running step 2 of 6',
		    'text_envato_username'=>'Envato Username',
		    'text_purchase_code'=>'Purchase Code',
		    'text_select_timezone'=>'-- Select Timezone --',
		    'text_go_back'=>'Go Back',
		    'text_database_password'=>'Database Password',
		    'text_database_username'=>'Database Username',
		    'text_database_name'=>'Database Name',
		    'text_pre_requirements'=>'Pre-Requirements',
		    'text_pre_requirement_info'=>'Although we have tested several times, but we can not give you 100% guarantee to update successfully. So, before proceed to update. Please, read/follow the pre-requirements list. You are only the responsible person while the system does not work after updated successfully.',
		    'text_internet_connection_required'=>'NEED INTERNET CONNECTION',
		    'text_latest_php_verison_required'=>'PHP 8.0 or LATER VERSION (8.3 Recommended)',
		    'text_take_db_backup'=>'TAKE DATABASE BACKUP',
		    'text_take_backup_old_files'=>'TAKE ALL FILES BACKUP',
		    'text_update_info'=>'*** This action may take several minutes. Please keep patience while processing this action and never close the browser. Otherwise system will not work properly. Enjoy a cup of coffee while you are waiting... :)',
		    'text_database_port'=>'Port (3306)',
		    'text_step_5_of_6'=>'Running step 5 of 6',
		    'text_step_4_of_6'=>'Running step 4 of 6',
		    'text_store_configuration'=>'Store Configuration',
		    'text_address'=>'Address',
		    'text_email'=>'Email (username)',
		    'text_phone'=>'Phone',
		    'text_store_name'=>'Store Name',
		    'text_timezone_setup'=>'Timezone Setup',
		    'text_update_v33_to_v34'=>'Update Modern POS from v3.3 to v3.4',
		    'text_purchase_code_revalidate_instruction'=>'Your purchase code is not valid. If you have a valid purchase code then  to revalide that or Claim a valid purchase code here:',
		    'text_invalid_info'=>'Your purchase code is not valid. If you have a valid purchase code then claim a valid purchase code to itsolution24bd@gmail.com',
		    'text_to_revalidate_ext'=>'to revalidate that or Claim a valid purchase code here:',
		    'text_invalid_purchase_code'=>'Invalid Purchase Code22211!',
		    'text_app_blocked'=>'App Blocked!',
		    'text_'=>'label',
		);

		if (isset($local_trans[$key]) && $local_trans[$key]) {
			return html_entity_decode($local_trans[$key]);
		}

		$ignore_prefix = array('_', 'menu', 'label', 'text', 'button', 'title', 'success', 'hint', 'placeholder', 'error_', 'btn_', 'nav_', 'label_', 'text_', 'button_', 'hint_', 'placeholder_', 'tab_');
		
		$key = str_replace($ignore_prefix, ' ', $key);

		switch ($case) {
			case 'UC': //UPPERCASE
				$key = utf8_strtoupper($key);
				break;
			case 'LC': // LOWERCASE
				$key = utf8_strtolower($key);
				break;
			case 'SC': //SENTENCECASE
				$key = preg_replace_callback('/((?:^|[!.?])\s*)(\p{Ll})/u', function($match) { return $match[1].utf8_strtoupper($match[2], 'UTF-8'); }, $key);
				break;
			case 'WC': //WORDCASE
				// TODO:
			default:
				$key = utf8_ucfirst($key);
				break;
		}

		return trim($key);
	}
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
$config_path = dirname(__FILE__) . '/../config.php';

if (!file_exists($config_path)) {
	die($config_path.' file not found!');
}

require_once $config_path;

if (!defined('REFRESH')) {
	require_once(DIR_HELPER . 'network.php');
}

define('INSTALLDIRNAME', 'install');
if (!ROOT_URL) {
	$line = "define('ROOT_URL', '".$protocol . $_SERVER['HTTP_HOST'] . rtrim(rtrim(dirname($_SERVER['SCRIPT_NAME']), INSTALLDIRNAME), '/.\\') . '/'."');";
	replace_lines($config_path, array(21 => $line));
}

// AUTOLOADER
function autoload($class) {
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

// REGISTER
$registry = new Registry();

// LOADER
$loader = new Loader($registry);
$registry->set('loader', $loader);

require_once(DIR_HELPER . 'security.php');

// REQUEST
$request = new Request();
$registry->set('request', $request);

// SESSION
$session = new Session($registry);
$registry->set('session', $session);

// HELPER FUNCTION
require_once(DIR_HELPER . 'common.php');
require_once(DIR_HELPER . 'validator.php');
require_once(DIR_HELPER . 'file.php');

if (is_file(DIR_INCLUDE.'config/purchase.php') && file_exists(DIR_INCLUDE.'config/purchase.php')) {
	define('ESNECIL', json_encode(require_once DIR_INCLUDE.'config/purchase.php'));
} else {
	define('ESNECIL', 'error');
}