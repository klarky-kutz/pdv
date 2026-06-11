<?php
/**
 * Arquivo de teste - REMOVER APÓS USO
 * Acesse: https://pdv.easysaascloud.com/test_config.php
 */

echo "<h2>Teste de Configuração</h2>";
echo "<pre>";

echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'NÃO DEFINIDO') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'NÃO DEFINIDO') . "\n\n";

// Inclui o config
require_once __DIR__ . '/config.php';

echo "=== Configurações Carregadas ===\n";
echo "DB Host: " . $sql_details['host'] . "\n";
echo "DB Name: " . $sql_details['db'] . "\n";
echo "DB User: " . $sql_details['user'] . "\n";
echo "DB Pass: " . (empty($sql_details['pass']) ? '(vazio)' : '***' . substr($sql_details['pass'], -4)) . "\n";
echo "ROOT_URL: " . ROOT_URL . "\n\n";

// Testa conexão
echo "=== Teste de Conexão ===\n";
try {
    $pdo = new PDO(
        'mysql:host=' . $sql_details['host'] . ';dbname=' . $sql_details['db'] . ';port=' . $sql_details['port'] . ';charset=utf8mb4',
        $sql_details['user'],
        $sql_details['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "Conexão: SUCESSO ✓\n";
    
    // Testa query
    $stmt = $pdo->query("SELECT COUNT(*) FROM tenants");
    echo "Tenants no banco: " . $stmt->fetchColumn() . "\n";
    
} catch (PDOException $e) {
    echo "Conexão: FALHOU ✗\n";
    echo "Erro: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p style='color:red;font-weight:bold;'>⚠️ REMOVA ESTE ARQUIVO APÓS O TESTE!</p>";
