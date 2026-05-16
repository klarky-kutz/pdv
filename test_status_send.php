<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . '_init.php';
set_time_limit(300);
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err) {
        file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'test_crash.txt', json_encode($err));
    }
});
error_reporting(0);
ini_set('display_errors', 0);
set_error_handler(function() { return true; });

// Simula um tenant e um status para teste
$tenantId = 347; // Baseado nos logs do usuário
$statusId = 19;  // ID de exemplo dos logs

// ob_start();
echo "--- INICIANDO TESTE DE VALIDAÇÃO DE ENVIO DE STATUS ---\n\n";

try {
    // 1. Verifica se o banco de dados está acessível
    echo "1. Verificando banco de dados... ";
    $status = db()->query("SELECT * FROM concierge_status WHERE id = $statusId")->fetch(PDO::FETCH_ASSOC);
    if (!$status) {
        // Se o 19 não existir, pega o último criado
        $status = db()->query("SELECT * FROM concierge_status ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($status) {
        echo "OK (ID encontrado: " . $status['id'] . ")\n";
    } else {
        echo "AVISO: Nenhum status encontrado para teste real. Usando dados fictícios.\n";
        $status = [
            'id' => 999,
            'tenant_id' => $tenantId,
            'content' => 'Teste de Validação',
            'media_url' => 'http://localhost/modernpos/storage/products/300/MODA AI/produto_10_variante_16_1775329008.webp',
            'status' => 'pending'
        ];
    }

    // 2. Testa a normalização e conversão para Base64
    echo "2. Testando normalização e Base64... ";
    require_once __DIR__ . DIRECTORY_SEPARATOR . '_inc' . DIRECTORY_SEPARATOR . 'helper' . DIRECTORY_SEPARATOR . 'ai_evolution.php';
    require_once __DIR__ . DIRECTORY_SEPARATOR . '_inc' . DIRECTORY_SEPARATOR . 'helper' . DIRECTORY_SEPARATOR . 'ai_groups_helper.php';
    
    // Simula a lógica de conversão dentro do helper para verificar se o arquivo é lido
    $mediaUrl = $status['media_url'];
    $isLocalUrl = (strpos($mediaUrl, 'localhost') !== false || strpos($mediaUrl, '127.0.0.1') !== false || strpos($mediaUrl, 'ngrok-free.dev') !== false);
    
    if ($isLocalUrl) {
        $urlParts = parse_url($mediaUrl);
        $urlPath = urldecode((string)($urlParts['path'] ?? ''));
        echo "\n   - URL Path decodificado: " . $urlPath . "\n";
        
        // Testa Estratégia 1: DIR_STORAGE
        echo "   - Testando Estratégia 1 (DIR_STORAGE): ";
        if (defined('DIR_STORAGE')) {
            $search = '/storage/';
            $pos = strpos($urlPath, $search);
            if ($pos !== false) {
                $sub = substr($urlPath, $pos + strlen($search));
                $path = rtrim(DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub);
                if (file_exists($path)) { echo "SUCESSO ✅ ($path)\n"; } else { echo "FALHA ❌ ($path)\n"; }
            } else { echo "PULAR (não contém /storage/)\n"; }
        } else { echo "PULAR (não definido)\n"; }

        // Testa Estratégia 2: ROOT
        echo "   - Testando Estratégia 2 (ROOT): ";
        if (defined('ROOT')) {
            $relativePath = '';
            if (strpos($urlPath, '/storage/') !== false) {
                $relativePath = substr($urlPath, strpos($urlPath, '/storage/'));
            } elseif (strpos($urlPath, '/modernpos/') !== false) {
                $relativePath = substr($urlPath, strpos($urlPath, '/modernpos/') + 10);
            }
            if ($relativePath !== '') {
                $path = rtrim(ROOT, '/\\') . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
                if (file_exists($path)) { echo "SUCESSO ✅ ($path)\n"; } else { echo "FALHA ❌ ($path)\n"; }
            } else { echo "PULAR (não mapeado)\n"; }
        } else { echo "PULAR (não definido)\n"; }

        // Testa Estratégia 3: XAMPP Comum
        echo "   - Testando Estratégia 3 (XAMPP): ";
        $possibleRoots = ['C:\xampp\htdocs\modernpos', 'C:\xampp\htdocs'];
        $foundX = false;
        foreach ($possibleRoots as $pr) {
            if (strpos($urlPath, '/storage/') !== false) {
                $sub = substr($urlPath, strpos($urlPath, '/storage/'));
                $path = $pr . str_replace('/', DIRECTORY_SEPARATOR, $sub);
                if (file_exists($path)) { echo "SUCESSO ✅ ($path)\n"; $foundX = true; break; }
            }
        }
        if (!$foundX) echo "FALHA ❌\n";
    }

    // 3. Verifica a conexão com a Evolution
    echo "\n3. Verificando conexão Evolution... ";
    
    // Usa as funções reais do sistema
    if (function_exists('ai_evolution_get_connection')) {
        $conn = ai_evolution_get_connection($tenantId);
        
        if ($conn && !empty($conn['base_url'])) {
            echo "OK (Instância: " . $conn['instance_name'] . " | URL: " . $conn['base_url'] . ")\n";
            echo "   - Global Token: " . ($conn['global_token'] ? 'Configurado' : 'Vazio') . "\n";
            
            // 4. TENTA UM DISPARO REAL
            echo "\n4. Tentando DISPARO REAL para WhatsApp... ";
            $row = db()->query("SELECT * FROM concierge_status WHERE id = 25")->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $row = db()->query("SELECT * FROM concierge_status ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            }
            
            if ($row) {
                echo "--- TESTANDO DISPARO DE IMAGEM COM NOVO HELPER ---\n";
                $result = ai_groups_dispatch_status_via_system($tenantId, $row);
                echo "   - Resultado Imagem: " . ($result['ok'] ? 'OK ✅' : 'FALHA ❌') . " (Code: " . $result['http_code'] . ")\n";
                if (!$result['ok']) echo "   - Erro: " . $result['error'] . "\n";
                if (!empty($result['external_message_id'])) echo "   - ID Evolution: " . $result['external_message_id'] . "\n";
            } else {
                echo "ERRO: Nenhum registro de status encontrado no banco para disparar.\n";
            }
        } else {
            echo "ERRO CRÍTICO: Nenhuma conexão Evolution encontrada para o tenant $tenantId. ❌\n";
            echo "   - Base URL: " . ($conn['base_url'] ?? 'null') . "\n";
            echo "   - Instance: " . ($conn['instance_name'] ?? 'null') . "\n";
        }
    } else {
        echo "ERRO: Função ai_evolution_get_connection não existe.\n";
    }

    echo "\n--- TESTE CONCLUÍDO ---\n";
    // $output = ob_get_clean();
    // echo $output;
    // file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'test_final_result.txt', $output);

} catch (Exception $e) {
    echo "\nERRO CRÍTICO DURANTE O TESTE: " . $e->getMessage() . "\n";
}
