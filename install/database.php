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
		$json['redirect'] = root_url().'install/purchase_code.php';
		echo json_encode($json);
		exit();
	} else {
		redirect('purchase_code.php');
	}
}
*/
//This is mandatory, NOT used for customer password and not associated with customer security issue.
$ALGORITHM = 'AES-128-CBC';
$IV    = '12dasdq3g5b2434b';
$password   = '123456789!@#$%^&*((*&^%$#@!))';

if (isset($request->get['action_type']) && $request->get['action_type'] == 'DBTIMPORTFILE')
{
	require_once ROOT."/config.php";
	$host = $sql_details['host'];
	$dbname = $sql_details['db'];
	$user = $sql_details['user'];
	$pass = $sql_details['pass'];
	$port = $sql_details['port'];
	try {
		$mysqli = new mysqli($host, $user, $pass, $dbname, $port);
	    if (mysqli_connect_errno()) {
			throw new \Exception('Oop!, Something went wrong. Please check your input');
	    }

		$filepath = DIR_STORAGE.".sql";

		if (!is_file($filepath)) {
			$json['error'] = 'Temporary file is not exist';
		}	

		if (isset($request->get['from'])) {
			$from = $request->get['from']-1;
		} else {
			$from = 0; 	
		}

		if (isset($request->get['to'])) {
			$to = $request->get['to'];
		} else {
			$to = 50; 	
		}

		if (isset($request->get['line_done'])) {
			$line_done = $request->get['line_done'];
		} else {
			$line_done = 50; 	
		}

		$query = $mysqli->query("SHOW TABLES FROM {$dbname}");
		if ($from == 0) {
			if (is_object($query) && property_exists($query, 'num_rows') && $query->num_rows > 0) {
				$json['error'] = 'The database is not empty!';
			} else if (is_array($query) && isset($query['num_rows']) && $query['num_rows'] > 0) {
				$json['error'] = 'The database is not empty!';
			}
		}
				
		if (!$json) 
		{
			$data_available = false;
			$templine = '';
			$sql_data = file_get_contents($filepath);
			$sql_data = openssl_decrypt($sql_data, $ALGORITHM, $password, 0, $IV);
			$temp = tmpfile();
			$temppath = stream_get_meta_data($temp)['uri'];
			fwrite($temp, $sql_data);
			$lines = file($temppath);
			fclose($temp);

			$totalLines = count($lines);
			$line_done = 0;
			$t = 0;
			foreach ($lines as $line) {
				$line_done++;
				if (substr($line, 0, 2) == '--' || substr($line, 0, 2) == '/*' || $line == ''){
			        continue;
				}
				if ($t >= $to){
					break;
				}
				if ($t > $from){
			    	$templine .= $line;
			    }
			    if (substr(trim($line), -1, 1) == ';') {
			        $t++;
			        if ($t > $from && $templine){
			        	$data_available = true;
				        $mysqli->query($templine);
				        $templine = '';
					}
			    }
			}

			$from = $to;
			$to += 50;
			$json['total'] = round(($line_done / $totalLines) * 100);
			if ($data_available) {
				$json['next'] = root_url().'install/database.php?from=' . $from . '&to=' . $to . '&line_done=' . $line_done . '&action_type=DBTIMPORTFILE';
			} else {
				$mysqli->close();
				unlink($filepath);
				$json['success'] = 'Data successfully imported';
			}
		}
	} catch (\Exception $e) {
		$json['error'] = $e->getMessage();
	}
    header('Content-Type: application/json');
    echo json_encode($json);
    exit();
}

$errors = array();
$success = array();
$info = array();

function database_import() 
{
	global $request, $errors, $success, $info, $ALGORITHM, $IV, $password;

	$dbhost = my_trim($request->post['host']);
	$dbname = my_trim($request->post['database']);
	$dbuser = my_trim($request->post['user']);
	$dbpass = my_trim($request->post['password']);
	$dbport = my_trim($request->post['port']);

	$info['username'] = get_pusername();
	$info['purchase_code'] = get_pcode();
	$info['domain'] = ROOT_URL;
	$info['app_id'] = APPID;
	$info['ip'] = get_real_ip();
	$info['mac'] = json_encode(getMAC());
	$info['action'] = 'install';

    $apiCall = apiCall($info);
	if(!$apiCall || !is_object($apiCall)) {
		$errors['dbimport'] = 'Internet connection problem';
		return false;
	}
	if ($apiCall->status == 'error') {
		$errors['dbimport'] = $apiCall->message;
		return false;
	}
	if(empty($apiCall->schema)) {
		$errors['dbimport'] = 'Sql was not found!';
		return false;
	}
	$sql_data = $apiCall->schema;

	$encrypt_data = openssl_encrypt($sql_data, $ALGORITHM, $password, 0, $IV);
	write_file(DIR_STORAGE.'.sql', $encrypt_data);

	$config_path = ROOT . '/config.php';
	chmod($config_path, FILE_WRITE_MODE);
	if (is_writable($config_path) === false) {
		$errors['database_import'] = 'Config file is unwritable';
		return false;
	} else {
		$file = $config_path;
		$line_host 	= "'host' => '". $dbhost ."',";
		$line_db 	= "'db' => '". $dbname ."',";
		$line_user 	= "'user' => '". $dbuser ."',";
		$line_pass 	= "'pass' => '". $dbpass ."',";
		$line_port 	= "'port' => '". $dbport ."'";
		$fileArray = array(5 => $line_host, 6 => $line_db, 7 => $line_user, 8 => $line_pass, 9 => $line_port);
		replace_lines($file, $fileArray);
		chmod($config_path, FILE_READ_MODE);
		return true;
	}
	return true;
}

if ($request->server['REQUEST_METHOD'] == 'POST') 
{
	$json = array();
	if (empty($request->post['host'])) {
		$errors['host'] = 'Host field is required.';
	}

	if (empty($request->post['database'])) {
		$errors['database'] = 'Database field is required.';
	}

	if (empty($request->post['user'])) {
		$errors['user'] = 'Username field is required.';
	}

	if (empty($request->post['port'])) {
		$errors['port'] = 'Port field is required.';
	}

	database_import();

	if(empty($errors)) {
		$json['next'] = root_url().'install/database.php?action_type=DBTIMPORTFILE';
	} else {
		$json = array_filter($errors);
	}
	echo json_encode($json);
	exit();
}
?>

<?php 
$title = 'Database-Modern POS';
include("header.php"); ?>

<?php include '../_inc/template/install/database.php'; ?>

<?php include("footer.php"); ?>