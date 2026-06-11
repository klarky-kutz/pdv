<?php
require_once dirname(__DIR__) . '/_init.php';

// Pegar store_id do argumento ou usar 1
$store_id = isset($argv[1]) ? (int)$argv[1] : 1;

echo "=== TESTE DE CRIAÇÃO DE DADOS DE EXEMPLO ===\n\n";
echo "Store ID: {$store_id}\n\n";

// Verificar se loja existe
$stmt = db()->prepare("SELECT name FROM stores WHERE store_id = ?");
$stmt->execute([$store_id]);
$store = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$store) {
    echo "❌ ERRO: Loja ID {$store_id} não existe\n";
    exit(1);
}

echo "Loja: {$store['name']}\n\n";
echo "=== EXECUTANDO CRIAÇÃO ===\n";

if (function_exists('create_sample_data_for_store')) {
    $result = create_sample_data_for_store($store_id);
    echo $result ? "✅ Sucesso\n" : "❌ Falhou\n";
} else {
    echo "❌ Função não existe\n";
}

echo "\n=== VERIFICANDO DADOS CRIADOS ===\n\n";

try {
    // Categorias
    $stmt = db()->prepare("SELECT COUNT(*) as total FROM category_to_store WHERE store_id = ?");
    $stmt->execute([$store_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Categorias: {$count['total']}\n";
    
    // Fornecedores
    $stmt = db()->prepare("SELECT COUNT(*) as total FROM supplier_to_store WHERE store_id = ?");
    $stmt->execute([$store_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Fornecedores: {$count['total']}\n";
    
    // Marcas
    $stmt = db()->prepare("SELECT COUNT(*) as total FROM brand_to_store WHERE store_id = ?");
    $stmt->execute([$store_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Marcas: {$count['total']}\n";
    
    // Contas bancárias
    $stmt = db()->prepare("SELECT COUNT(*) as total FROM bank_account_to_store WHERE store_id = ?");
    $stmt->execute([$store_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Contas Bancárias: {$count['total']}\n";
    
    // Templates
    $stmt = db()->prepare("SELECT COUNT(*) as total FROM pos_template_to_store WHERE store_id = ?");
    $stmt->execute([$store_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Templates: {$count['total']}\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}

echo "\n=== FIM DO TESTE ===\n";
