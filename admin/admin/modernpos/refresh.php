<?php
ob_start();
session_start();
define('START', true);
define('REFRESH', true);
include ("install/_init.php");

function checkInternetConnection($domain = 'www.google.com')  
{
  if($socket = fsockopen($domain, 80, $errno, $errstr, 30)) {
    fclose($socket);
    return true;
  }
  return false;
}

function url_exists($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    curl_setopt($ch, CURLOPT_NOBODY, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $status = array();
    preg_match('/HTTP\/.* ([0-9]+) .*/', curl_exec($ch) , $status);
    curl_close($ch);
    return ($status[1] == 200 || $status[1] == 422);
}

function checkValidationServerConnection($url = 'http://tracker.itsolution24.com/pos33/check.php')  
{
    if(url_exists($url)) {
        return true;
    }
    return false;
}

function checkEnvatoServerConnection($domain = 'www.envato.com')  
{
  if($socket = fsockopen($domain, 80, $errno, $errstr, 30)) {
    fclose($socket);
    return true;
  }
  return false;
}

if (isset($_GET['APPID']) && $_GET['APPID'] == APPID) {
  if(!checkInternetConnection() || !checkValidationServerConnection() || !checkEnvatoServerConnection()) {
  	die('Need internet connection!');
  }

  redirect('index.php');

} else {
    die('Invalid Action. Required Valid APPID.');
}