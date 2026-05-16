<?php 
namespace AngularFilemanager\LocalBridge;

/**
 *  PHP Local filesystem bridge for angular-filemanager
 *
 *  @author Jakub Ďuraš <jakub@duras.me>
 *  @version 0.2.0
 */
include 'LocalBridge/Response.php';
include 'LocalBridge/Rest.php';
include 'LocalBridge/Translate.php';
include 'LocalBridge/FileManagerApi.php';
require_once dirname(__FILE__) . '/../../../config.php';

// Iniciar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Obtém o path do FileManager isolado por tenant
 * Cada tenant tem sua própria pasta: storage/products/{tenant_id}/
 */
function getFileManagerPathForTenant() {
    $basePath = defined('FILEMANAGERPATH') ? FILEMANAGERPATH : (ROOT . '/storage/products/');
    
    // Obtém tenant_id da sessão
    $tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    
    // Se não tiver tenant na sessão, tenta obter do usuário atual
    if ($tenantId <= 0 && isset($_SESSION['id'])) {
        try {
            $pdo = new \PDO(
                'mysql:host=' . $GLOBALS['sql_details']['host'] . ';dbname=' . $GLOBALS['sql_details']['db'] . ';port=' . $GLOBALS['sql_details']['port'],
                $GLOBALS['sql_details']['user'],
                $GLOBALS['sql_details']['pass'],
                array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
            );
            $stmt = $pdo->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$_SESSION['id']]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && $row['tenant_id']) {
                $tenantId = (int)$row['tenant_id'];
                $_SESSION['tenant_id'] = $tenantId;
            }
        } catch (\Exception $e) {
            // Ignora erro, usa path base
        }
    }
    
    // Se tiver tenant_id, usa pasta isolada
    if ($tenantId > 0) {
        $tenantPath = rtrim($basePath, '/\\') . '/' . $tenantId . '/';
        
        // Cria a pasta se não existir
        if (!is_dir($tenantPath)) {
            @mkdir($tenantPath, 0755, true);
        }
        
        return $tenantPath;
    }
    
    // Fallback: usa path base (modo single-tenant ou admin)
    return $basePath;
}

/**
 * Takes two arguments
 * - base path without last slash (default: '$currentDirectory/../files')
 * - language (default: 'en'); mute_errors (default: true, will call ini_set('display_errors', 0))
 */
$fileManagerPath = getFileManagerPathForTenant();
$fileManagerApi = new FileManagerApi($fileManagerPath, 'en', true);

$rest = new Rest();
$rest->post([$fileManagerApi, 'postHandler'])
     ->get([$fileManagerApi, 'getHandler'])
     ->handle();
