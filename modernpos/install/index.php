<?php 
ob_start();
session_start();
define('START', true);
include ("_init.php");

if (defined('INSTALLED')) {
	if (is_ajax()) {
		$json['redirect'] = root_url().'index.php';
		echo json_encode($json);
		exit();
	} else {
		header('Location: ../index.php');
	}
}

$title = 'Checklist-Modern POS';
include("header.php"); 

$errors = array();
$success = array();

$config_path = dirname(__FILE__) . '/../config.php';
// Check if config file exists or not
if (file_exists($config_path)) {
	$success[] = 'Config file is OK';
} else {
	$errors[] = 'Config file problem'; // sudo chmod -R 777 /modernpos/www
}

if (is_writable($config_path)) {
	$success[] = 'Config file is writable';
} else {
	$errors[] = $config_path . ' must be writable! After installation you can chagne permission back.';
}

// Check if storage dir exits or not
$storage_dir = dirname(__FILE__) . '/../storage';
if (is_dir($storage_dir)) {
	$success[] = 'Storage directory is OK';
} else {
	$errors[] = $storage_dir.' directory problem'; // sudo chmod -R 777 /modernpos/www
}

if (is_writable($storage_dir)) {
	$success[] = 'Storage directory is writable';
} else {
	$errors[] = $storage_dir . ' must writable! After installation you can chagne permission back.';
}

// Check PHP version
if (version_compare(phpversion(), '8', '<')) {
	$errors[] = 'PHP 8 OR 8+ Required';
} else {
	$phpversion = phpversion();
	$success[] = ' You are running PHP '.$phpversion;
}

// Check Mysql PHP extension
if(!extension_loaded('mysqli')) {
	$errors[] = 'Mysqli PHP extension unloaded!';
} else {
	$success[] = 'Mysqli PHP extension loaded.';
}

// Check PDO PHP extension
if (!defined('PDO::ATTR_DRIVER_NAME') || !extension_loaded ('PDO') || !extension_loaded('pdo_mysql')) {
	$errors[] = 'PDO PHP extension is unloaded!';
} else {
	$success[] = 'PDO PHP extension loaded.';
}

// Check MBString PHP extension
if(!extension_loaded('mbstring')) {
	$errors[] = 'MBString PHP extension unloaded!';
} else {
	$success[] = 'MBString PHP extension loaded.';
}

// Check GD PHP extension
if(!extension_loaded('gd')) {
	$errors[] = 'GD PHP extension unloaded!';
} else {
	$success[] = 'GD PHP extension loaded.';
}

// Check CURL PHP extension
if(!extension_loaded('curl')) {
	$errors[] = 'CURL PHP extension unloaded!';
} else {
	$success[] = 'CURL PHP extension loaded.';
}

// Check Openssl PHP extension
if(!extension_loaded('openssl')) {
	$errors[] = 'Openssl PHP extension unloaded!';
} else {
	$success[] = 'Openssl PHP extension loaded.';
}

// Check Internet Connection
if(checkInternetConnection()) {
	$success[] = 'Internet connection OK';
} else {
	$errors[] = 'Internet connection problem!';
}

// Check Validation Server Connection
if(checkValidationServerConnection()) {
	$success[] = 'Validation server OK';
} else {
	$errors[] = 'Validation server connection problem!';
}

// Check Envato Server Connection
if(checkEnvatoServerConnection()) {
	$success[] = 'Envato server OK';
} else {
	$errors[] = 'Envato server connection problem!';
}

include '../_inc/template/install/index.php'; 

include("footer.php");