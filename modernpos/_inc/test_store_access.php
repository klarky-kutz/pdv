<?php
/**
 * Script de teste para verificar acesso de usuário à loja
 * Acesse: http://localhost/modernpos/_inc/test_store_access.php?user_id=273&store_id=292
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("../_init.php");

$testUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$testStoreId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;

echo "<h1>Teste de Acesso à Loja</h1>";
echo "<hr>";

if ($testUserId <= 0 || $testStoreId <= 0) {
    echo "<p style='color:red;'>Por favor, forneça user_id e store_id na URL.</p>";
    echo "<p>Exemplo: ?user_id=273&store_id=292</p>";
    exit;
}

try {
    $pdo = db();
    
    // Simula login do usuário para teste
    $_SESSION['user_id'] = $testUserId;
    
    // Busca informações do usuário
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$testUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "<p style='color:red;'>Usuário ID {$testUserId} não encontrado!</p>";
        exit;
    }
    
    echo "<h2>Informações do Usuário</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><td><strong>ID:</strong></td><td>{$user['id']}</td></tr>";
    echo "<tr><td><strong>Nome:</strong></td><td>{$user['username']}</td></tr>";
    echo "<tr><td><strong>Email:</strong></td><td>{$user['email']}</td></tr>";
    echo "<tr><td><strong>Group ID:</strong></td><td>{$user['group_id']}</td></tr>";
    echo "<tr><td><strong>Tenant ID:</strong></td><td>" . ($user['tenant_id'] ?? 'null') . "</td></tr>";
    echo "<tr><td><strong>Status:</strong></td><td>{$user['status']}</td></tr>";
    echo "</table>";
    
    // Busca informações da loja
    $stmt = $pdo->prepare('SELECT * FROM stores WHERE store_id = ? LIMIT 1');
    $stmt->execute([$testStoreId]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$store) {
        echo "<p style='color:red;'>Loja ID {$testStoreId} não encontrada!</p>";
        exit;
    }
    
    echo "<h2>Informações da Loja</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><td><strong>ID:</strong></td><td>{$store['store_id']}</td></tr>";
    echo "<tr><td><strong>Nome:</strong></td><td>{$store['name']}</td></tr>";
    echo "<tr><td><strong>Code Name:</strong></td><td>{$store['code_name']}</td></tr>";
    echo "<tr><td><strong>Tenant ID:</strong></td><td>" . ($store['tenant_id'] ?? 'null') . "</td></tr>";
    echo "<tr><td><strong>Status:</strong></td><td>{$store['status']}</td></tr>";
    echo "</table>";
    
    // Verifica vínculo user_to_store
    $stmt = $pdo->prepare('SELECT * FROM user_to_store WHERE user_id = ? AND store_id = ?');
    $stmt->execute([$testUserId, $testStoreId]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>Vínculo User_to_Store</h2>";
    if ($link) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><td><strong>Existe:</strong></td><td style='color:green;'>SIM</td></tr>";
        echo "<tr><td><strong>Status:</strong></td><td>{$link['status']}</td></tr>";
        echo "<tr><td><strong>Sort Order:</strong></td><td>" . ($link['sort_order'] ?? 'null') . "</td></tr>";
        echo "</table>";
    } else {
        echo "<p style='color:orange;'>Não há vínculo direto na tabela user_to_store (mas admin pode acessar mesmo assim).</p>";
    }
    
    // Carrega funções de verificação
    require_once 'account_store.php';
    
    // Testa a função account_user_can_access_store
    echo "<h2>Teste de Acesso</h2>";
    echo "<p>Chamando <code>account_user_can_access_store(\$pdo, {$testStoreId})</code>...</p>";
    
    $canAccess = account_user_can_access_store($pdo, $testStoreId);
    
    if ($canAccess) {
        echo "<div style='background-color:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:15px; border-radius:5px;'>";
        echo "<h3>✅ ACESSO PERMITIDO</h3>";
        echo "<p>O usuário <strong>{$user['username']}</strong> (ID: {$testUserId}) pode acessar a loja <strong>{$store['name']}</strong> (ID: {$testStoreId}).</p>";
        echo "</div>";
    } else {
        echo "<div style='background-color:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:15px; border-radius:5px;'>";
        echo "<h3>❌ ACESSO NEGADO</h3>";
        echo "<p>O usuário <strong>{$user['username']}</strong> (ID: {$testUserId}) NÃO pode acessar a loja <strong>{$store['name']}</strong> (ID: {$testStoreId}).</p>";
        echo "</div>";
    }
    
    // Análise do motivo
    echo "<h2>Análise</h2>";
    echo "<ul>";
    
    if ((int)$user['group_id'] === 1) {
        echo "<li><strong>Usuário é Admin (group_id = 1):</strong> ✅ SIM</li>";
        
        if (isset($user['tenant_id']) && isset($store['tenant_id'])) {
            if ((int)$user['tenant_id'] === (int)$store['tenant_id']) {
                echo "<li><strong>Tenant ID corresponde:</strong> ✅ SIM (Usuário: {$user['tenant_id']}, Loja: {$store['tenant_id']})</li>";
            } else {
                echo "<li><strong>Tenant ID corresponde:</strong> ❌ NÃO (Usuário: {$user['tenant_id']}, Loja: {$store['tenant_id']})</li>";
            }
        } else {
            echo "<li><strong>Modo tenant:</strong> ℹ️ Modo single-tenant ou tenant_id não definido</li>";
        }
    } else {
        echo "<li><strong>Usuário é Admin:</strong> ❌ NÃO (group_id = {$user['group_id']})</li>";
        echo "<li><strong>Requer vínculo em user_to_store:</strong> " . ($link ? "✅ SIM" : "❌ NÃO") . "</li>";
    }
    
    echo "</ul>";
    
    echo "<hr>";
    echo "<h2>Logs do Apache</h2>";
    echo "<p>Verifique o arquivo de log do Apache para mensagens detalhadas:</p>";
    echo "<pre>C:\\xampp\\apache\\logs\\error.log</pre>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'><strong>ERRO:</strong> " . $e->getMessage() . "</p>";
}
?>
