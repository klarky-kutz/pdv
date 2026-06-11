<?php
/**
 * Script para corrigir tenant_id na sessão
 * Acesse: http://localhost/modernpos/_inc/fix_session_tenant.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("../_init.php");

echo "<h1>Correção de Tenant ID na Sessão</h1>";
echo "<hr>";

try {
    $pdo = db();
    
    if (!function_exists('user_id') || !user_id()) {
        echo "<p style='color:red;'>Você não está logado! Faça login primeiro.</p>";
        exit;
    }
    
    $uid = user_id();
    
    echo "<h2>Sessão Atual</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><td><strong>user_id:</strong></td><td>" . $uid . "</td></tr>";
    echo "<tr><td><strong>SESSION tenant_id:</strong></td><td>" . ($_SESSION['tenant_id'] ?? 'não definido') . "</td></tr>";
    echo "</table>";
    
    // Busca tenant_id correto do banco
    $stmt = $pdo->prepare('SELECT id, username, email, group_id, tenant_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "<p style='color:red;'>Usuário não encontrado no banco!</p>";
        exit;
    }
    
    echo "<h2>Dados do Banco</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><td><strong>ID:</strong></td><td>{$user['id']}</td></tr>";
    echo "<tr><td><strong>Username:</strong></td><td>{$user['username']}</td></tr>";
    echo "<tr><td><strong>Email:</strong></td><td>{$user['email']}</td></tr>";
    echo "<tr><td><strong>Group ID:</strong></td><td>{$user['group_id']}</td></tr>";
    echo "<tr><td><strong>Tenant ID (DB):</strong></td><td>" . ($user['tenant_id'] ?? 'NULL') . "</td></tr>";
    echo "</table>";
    
    $dbTenantId = isset($user['tenant_id']) ? (int)$user['tenant_id'] : 0;
    $sessionTenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    
    if ($dbTenantId > 0 && $dbTenantId !== $sessionTenantId) {
        echo "<h2 style='color:orange;'>⚠️ INCONSISTÊNCIA DETECTADA!</h2>";
        echo "<p>O tenant_id na sessão ({$sessionTenantId}) é diferente do tenant_id no banco ({$dbTenantId}).</p>";
        
        // Botão para corrigir
        if (isset($_GET['fix']) && $_GET['fix'] === '1') {
            $_SESSION['tenant_id'] = $dbTenantId;
            
            echo "<div style='background-color:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:15px; border-radius:5px;'>";
            echo "<h3>✅ SESSÃO CORRIGIDA!</h3>";
            echo "<p>O tenant_id foi atualizado para: <strong>{$dbTenantId}</strong></p>";
            echo "</div>";
            
            echo "<hr>";
            echo "<p><a href='/modernpos/conta/lojas/configuracoes?store_id=294' class='btn'>Testar Acesso à Loja 294</a></p>";
            
        } else {
            echo "<p><a href='?fix=1' style='display:inline-block; padding:10px 20px; background-color:#007bff; color:white; text-decoration:none; border-radius:5px;'>Corrigir Agora</a></p>";
        }
        
    } else if ($dbTenantId > 0) {
        echo "<div style='background-color:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:15px; border-radius:5px;'>";
        echo "<h3>✅ SESSÃO OK!</h3>";
        echo "<p>O tenant_id está correto: <strong>{$dbTenantId}</strong></p>";
        echo "</div>";
        
        // Verifica lojas disponíveis
        echo "<h2>Lojas do seu Tenant</h2>";
        $stmt = $pdo->prepare('SELECT store_id, name, code_name, status FROM stores WHERE tenant_id = ? ORDER BY store_id DESC LIMIT 10');
        $stmt->execute([$dbTenantId]);
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($stores)) {
            echo "<p style='color:orange;'>Nenhuma loja encontrada para este tenant.</p>";
        } else {
            echo "<table border='1' cellpadding='5' style='width:100%;'>";
            echo "<tr><th>ID</th><th>Nome</th><th>Code Name</th><th>Status</th><th>Ações</th></tr>";
            foreach ($stores as $store) {
                $statusText = $store['status'] == 1 ? '✅ Ativo' : '❌ Inativo';
                echo "<tr>";
                echo "<td>{$store['store_id']}</td>";
                echo "<td>{$store['name']}</td>";
                echo "<td>{$store['code_name']}</td>";
                echo "<td>{$statusText}</td>";
                echo "<td><a href='/modernpos/conta/lojas/configuracoes?store_id={$store['store_id']}' target='_blank'>Acessar Configurações</a></td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<p style='color:orange;'>Este usuário não tem tenant_id definido (modo single-tenant).</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'><strong>ERRO:</strong> " . $e->getMessage() . "</p>";
}
?>

<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; }
    th { background-color: #f0f0f0; }
    .btn { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
    .btn:hover { background-color: #0056b3; }
</style>
