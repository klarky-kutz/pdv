<?php
/**
 * Arquivo de diagnóstico para a rota /conta/suporte
 * Acesse: https://pdv.easysaascloud.com/conta/debug_suporte.php
 * REMOVER APÓS O TESTE!
 */

echo "<h2>🔍 Diagnóstico da Rota /conta/suporte</h2>";
echo "<pre>";

echo "=== Variáveis de Servidor ===\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'NÃO DEFINIDO') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'NÃO DEFINIDO') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'NÃO DEFINIDO') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NÃO DEFINIDO') . "\n\n";

echo "=== Verificando arquivos na pasta /conta/ ===\n";
$contaDir = __DIR__;
echo "Diretório atual: $contaDir\n";

$files = glob($contaDir . '/*.php');
echo "Arquivos PHP em /conta/:\n";
foreach ($files as $f) {
    echo "  - " . basename($f) . "\n";
}

echo "\n=== Verificando pasta suporte ===\n";
if (is_dir($contaDir . '/suporte')) {
    echo "⚠️ EXISTE pasta /conta/suporte/\n";
    $suporteFiles = glob($contaDir . '/suporte/*.php');
    echo "Arquivos dentro:\n";
    foreach ($suporteFiles as $f) {
        echo "  - " . basename($f) . "\n";
    }
} else {
    echo "✓ NÃO existe pasta /conta/suporte/\n";
}

if (file_exists($contaDir . '/suporte.php')) {
    echo "⚠️ EXISTE arquivo /conta/suporte.php\n";
} else {
    echo "✓ NÃO existe arquivo /conta/suporte.php\n";
}

echo "\n=== Testando conexão via config.php ===\n";

// Muda para o diretório raiz do modernpos
chdir(__DIR__ . '/..');

// Carrega config
if (file_exists('config.php')) {
    require_once 'config.php';
    
    echo "DB Host: " . $sql_details['host'] . "\n";
    echo "DB Name: " . $sql_details['db'] . "\n";
    echo "DB User: " . $sql_details['user'] . "\n";
    echo "DB Pass: " . (empty($sql_details['pass']) ? '(VAZIO!)' : '***' . substr($sql_details['pass'], -4)) . "\n";
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
        
        // Testa tabela support_tickets
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM support_tickets");
            echo "Tabela support_tickets: OK (" . $stmt->fetchColumn() . " tickets)\n";
        } catch (Exception $e) {
            echo "Tabela support_tickets: " . $e->getMessage() . "\n";
        }
        
    } catch (PDOException $e) {
        echo "Conexão: FALHOU ✗\n";
        echo "Erro: " . $e->getMessage() . "\n";
    }
} else {
    echo "ERRO: config.php não encontrado!\n";
}

echo "</pre>";
echo "<p style='color:red;font-weight:bold;'>⚠️ REMOVA ESTE ARQUIVO APÓS O TESTE!</p>";
