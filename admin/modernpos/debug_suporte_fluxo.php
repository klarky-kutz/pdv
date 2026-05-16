<?php
/**
 * Diagnóstico: Simula exatamente o fluxo da rota /conta/suporte
 * Acesse: https://pdv.easysaascloud.com/debug_suporte_fluxo.php
 * REMOVER APÓS O TESTE!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Diagnóstico do Fluxo /conta/suporte</h2>";
echo "<pre>";

echo "=== PASSO 1: Carregando _init.php ===\n";
try {
    ob_start();
    include("_init.php");
    $initOutput = ob_get_clean();
    echo "✓ _init.php carregado com sucesso\n";
    if (!empty($initOutput)) {
        echo "Output do _init: " . substr($initOutput, 0, 200) . "...\n";
    }
} catch (Throwable $e) {
    echo "✗ ERRO no _init.php: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    die("</pre>");
}

echo "\n=== PASSO 2: Verificando função db() ===\n";
try {
    $pdo = db();
    echo "✓ db() retornou PDO válido\n";
    
    $stmt = $pdo->query("SELECT 1");
    echo "✓ Query de teste OK\n";
} catch (Throwable $e) {
    echo "✗ ERRO no db(): " . $e->getMessage() . "\n";
}

echo "\n=== PASSO 3: Carregando account_access.php ===\n";
try {
    require_once __DIR__ . '/account/includes/account_access.php';
    echo "✓ account_access.php carregado\n";
} catch (Throwable $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
}

echo "\n=== PASSO 4: Simulando store_select_admin.php (parte inicial) ===\n";
try {
    // Código do início do store_select_admin.php
    $section = 'support';
    $tab = null;
    
    $accountIsOwnerOrAdmin = (function_exists('user_group_id') && (int)user_group_id() === 1)
      || (function_exists('is_tenant_owner') && is_tenant_owner());
    echo "accountIsOwnerOrAdmin: " . ($accountIsOwnerOrAdmin ? 'true' : 'false') . "\n";
    
    // Verifica SaasLimitsBridge
    if (!class_exists('SaasLimitsBridge')) {
        $saasLimitsPath = __DIR__ . '/../saas/includes/SaasLimitsBridge.php';
        echo "SaasLimitsBridge path: $saasLimitsPath\n";
        echo "Exists: " . (file_exists($saasLimitsPath) ? 'yes' : 'no') . "\n";
        if (file_exists($saasLimitsPath)) {
            require_once $saasLimitsPath;
        }
    }
    echo "✓ Código inicial OK\n";
} catch (Throwable $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== PASSO 5: Carregando support.php ===\n";
try {
    // Simula as variáveis necessárias
    $_SESSION['tenant_id'] = $_SESSION['tenant_id'] ?? 0;
    
    // Tenta incluir o arquivo de suporte
    ob_start();
    include __DIR__ . '/account/pages/support.php';
    $supportOutput = ob_get_clean();
    
    // Verifica se houve erro
    if (strpos($supportOutput, 'Connection error') !== false || strpos($supportOutput, 'Access denied') !== false) {
        echo "✗ ERRO detectado no support.php:\n";
        echo substr($supportOutput, 0, 500) . "\n";
    } else {
        echo "✓ support.php carregado sem erros de conexão\n";
        echo "Output size: " . strlen($supportOutput) . " bytes\n";
    }
} catch (Throwable $e) {
    echo "✗ ERRO no support.php: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== VERIFICAÇÃO FINAL ===\n";
echo "Usuário logado: " . (isset($user) && $user->isLogged() ? 'SIM (ID: ' . user_id() . ')' : 'NÃO') . "\n";
echo "tenant_id na sessão: " . (isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : 'NÃO DEFINIDO') . "\n";

echo "</pre>";
echo "<p style='color:red;font-weight:bold;'>⚠️ REMOVA ESTE ARQUIVO APÓS O TESTE!</p>";
