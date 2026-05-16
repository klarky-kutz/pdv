<?php
ob_start();
session_start();
define('START', true);
include("install/_init.php");

// Ignorar todas as validações e redirecionar para index.php
header('Location: index.php');
exit();
