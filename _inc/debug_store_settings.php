<?php
// Debug script para investigar o erro 422
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();
session_start();
include("../_init.php");

echo "=== DEBUG STORE SETTINGS ===\n\n";

echo "1. Usuário logado: " . (is_loggedin() ? "SIM" : "NÃO") . "\n";

if (is_loggedin()) {
    echo "2. User ID: " . (function_exists('user_id') ? user_id() : 'N/A') . "\n";
    echo "3. User Group ID: " . (function_exists('user_group_id') ? user_group_id() : 'N/A') . "\n";
    echo "4. É Admin (group_id == 1): " . (function_exists('user_group_id') && user_group_id() == 1 ? "SIM" : "NÃO") . "\n";
}

echo "\n5. SESSION tenant_id: " . (isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : 'não definido') . "\n";

echo "\n6. POST data recebido:\n";
echo "   - action: " . ($_POST['action'] ?? 'não enviado') . "\n";
echo "   - store_id: " . ($_POST['store_id'] ?? 'não enviado') . "\n";

echo "\n7. Método da requisição: " . $_SERVER['REQUEST_METHOD'] . "\n";

// Testa acesso ao banco
try {
    $pdo = db();
    echo "\n8. Conexão com banco: OK\n";
    
    // Se veio store_id, verifica se a loja existe
    if (isset($_POST['store_id']) && $_POST['store_id']) {
        $storeId = (int)$_POST['store_id'];
        $stmt = $pdo->prepare('SELECT store_id, name FROM stores WHERE store_id = ? LIMIT 1');
        $stmt->execute([$storeId]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($store) {
            echo "9. Loja encontrada: ID={$store['store_id']}, Nome={$store['name']}\n";
        } else {
            echo "9. ERRO: Loja com ID={$storeId} NÃO ENCONTRADA no banco\n";
        }
    }
    
} catch (Exception $e) {
    echo "\n8. ERRO na conexão: " . $e->getMessage() . "\n";
}

echo "\n=== FIM DEBUG ===\n";
