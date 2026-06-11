<?php 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('START')) {
    define('START', true);
}

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

// Ignorar conexões com servidores de validação
/*
if(!checkValidationServerConnection() || !checkEnvatoServerConnection()) {
    if (is_ajax()) {
        $json['redirect'] = root_url().'install/index.php';
        echo json_encode($json);
        exit();
    } else {
        redirect('index.php');
    }
}
*/

$errors = array();
$success = array();
$info = array();

$errors['internet_connection'] = null;
$errors['purchase_username'] = null;
$errors['purchase_code'] = null;
$errors['config_error'] = null;

$ecnesil_path = DIR_INCLUDE.'config/purchase.php';
$config_path = ROOT . '/config.php';

// Verificar antes de declarar a função purchase_code_validation
if (!function_exists('purchase_code_validation')) {
    function purchase_code_validation() {
    	global $request, $errors, $success, $info;
        return true; // Ignorar validações e permitir continuidade
    }
}

if ($request->server['REQUEST_METHOD'] == 'POST') {
    // Pulando validações
    $json['redirect'] = 'database.php';
    echo json_encode($json);
    exit();
}

// Definir ESNECIL como válido
if (is_file(DIR_INCLUDE.'config/purchase.php') && file_exists(DIR_INCLUDE.'config/purchase.php')) {
    define('ESNECIL', json_encode(['status' => 'valid']));
} else {
    define('ESNECIL', json_encode(['status' => 'valid'])); // Simula licença válida
}

// Evitar erros de protocolo
$protocol = 'http://'; // Força HTTP
$_SERVER['HTTPS'] = false;

// Evitar bloqueios por arquivos ausentes
if (!file_exists($config_path)) {
    file_put_contents($config_path, "<?php\n// Configuração padrão\n"); // Cria arquivo de configuração vazio
}

// Ajustes gerais de ambiente
ini_set('max_execution_time', 300); // 300 segundos = 5 minutos
define('ENVIRONMENT', 'production');

switch (ENVIRONMENT) {
    case 'development':
        error_reporting(-1);
        ini_set('display_errors', 1);
    break;
    case 'production':
        ini_set('display_errors', 0);
        error_reporting(0); // Nenhuma mensagem de erro
    break;
}

// AUTOLOADER
/*if (!function_exists('autoload')) {
    function autoload($class) {
        $file = DIR_INCLUDE . 'lib/' . my_str_replace('\\', '/', strtolower($class)) . '.php';

        if (file_exists($file)) {
            include($file);
            return true;
        } else {
            return false;
        }
    }
}*/
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

?>

<?php 
$title = 'Validation-Modern POS';
include("header.php"); 
?>
<?php include '../_inc/template/install/purchase_code.php'; ?>
<?php include("footer.php"); ?>
