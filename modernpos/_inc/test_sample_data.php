<?php
/**
 * Script de teste para verificar criação de dados de exemplo
 * 
 * Acesse: http://localhost/modernpos/_inc/test_sample_data.php?store_id=1
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("../_init.php");

// Verificar se usuário está logado
if (!is_loggedin() || user_group_id() != 1) {
    die('<h1>❌ Acesso Negado</h1><p>Apenas administradores.</p>');
}

$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Teste de Dados de Exemplo</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: white; padding: 15px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>🧪 Teste de Criação de Dados de Exemplo</h1>
    
    <?php if ($store_id): ?>
        <h2>Testando com Store ID: <?php echo $store_id; ?></h2>
        
        <?php
        echo "<pre>";
        echo "=== VERIFICAÇÕES ===\n\n";
        
        // 1. Verificar se função existe
        echo "1. Função create_sample_data_for_store existe? ";
        if (function_exists('create_sample_data_for_store')) {
            echo "<span class='success'>✅ SIM</span>\n";
        } else {
            echo "<span class='error'>❌ NÃO - PROBLEMA AQUI!</span>\n";
        }
        
        // 2. Verificar se função de templates existe
        echo "2. Função link_global_templates_to_store existe? ";
        if (function_exists('link_global_templates_to_store')) {
            echo "<span class='success'>✅ SIM</span>\n";
        } else {
            echo "<span class='error'>❌ NÃO</span>\n";
        }
        
        // 3. Verificar se loja existe
        echo "3. Loja existe no banco? ";
        $stmt = db()->prepare("SELECT store_id, name FROM stores WHERE store_id = ?");
        $stmt->execute([$store_id]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($store) {
            echo "<span class='success'>✅ SIM - {$store['name']}</span>\n";
        } else {
            echo "<span class='error'>❌ NÃO - Loja não encontrada</span>\n";
            echo "</pre>";
            exit;
        }
        
        echo "\n=== EXECUTANDO CRIAÇÃO ===\n\n";
        
        try {
            $result = create_sample_data_for_store($store_id);
            
            if ($result) {
                echo "<span class='success'>✅ Função executada com sucesso!</span>\n\n";
            } else {
                echo "<span class='error'>❌ Função retornou false</span>\n\n";
            }
            
            // Verificar o que foi criado
            echo "=== VERIFICANDO DADOS CRIADOS ===\n\n";
            
            // Categorias (via vínculo)
            $stmt = db()->prepare("SELECT COUNT(*) as total FROM category_to_store WHERE store_id = ?");
            $stmt->execute([$store_id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "Categorias: {$count['total']}\n";
            
            // Fornecedores (via vínculo)
            $stmt = db()->prepare("SELECT COUNT(*) as total FROM supplier_to_store WHERE store_id = ?");
            $stmt->execute([$store_id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "Fornecedores: {$count['total']}\n";
            
            // Marcas (via vínculo)
            $stmt = db()->prepare("SELECT COUNT(*) as total FROM brand_to_store WHERE store_id = ?");
            $stmt->execute([$store_id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "Marcas: {$count['total']}\n";
            
            // Contas bancárias
            // bank_accounts PK = id; bank_account_to_store usa account_id
            $stmt = db()->prepare("SELECT COUNT(*) as total FROM bank_accounts ba INNER JOIN bank_account_to_store bas ON ba.id = bas.account_id WHERE bas.store_id = ?");
            $stmt->execute([$store_id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "Contas Bancárias: {$count['total']}\n";
            
            // Nota: Produtos não são criados automaticamente devido à complexidade
            
            // Templates
            $stmt = db()->prepare("SELECT COUNT(*) as total FROM pos_template_to_store WHERE store_id = ?");
            $stmt->execute([$store_id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "Templates Vinculados: {$count['total']}\n";

            // Produtos (via vínculo)
            $stmt = db()->prepare("SELECT COUNT(*) as total FROM product_to_store WHERE store_id = ?");
            $stmt->execute([$store_id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "Produtos Vinculados: {$count['total']}\n";
            
        } catch (Exception $e) {
            echo "<span class='error'>❌ ERRO: " . $e->getMessage() . "</span>\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        }
        
        echo "</pre>";
        ?>
        
        <p><a href="<?php echo root_url(); ?>admin/category.php">Ver Categorias</a> | 
           <a href="<?php echo root_url(); ?>admin/supplier.php">Ver Fornecedores</a> | 
           <a href="<?php echo root_url(); ?>admin/brand.php">Ver Marcas</a> | 
           <a href="<?php echo root_url(); ?>admin/product.php">Ver Produtos</a> | 
           <a href="<?php echo root_url(); ?>admin/bank_account.php">Ver Contas Bancárias</a> | 
           <a href="<?php echo root_url(); ?>admin/select_receipt_template.php">Ver Templates</a></p>
        
    <?php else: ?>
        <p>Selecione uma loja:</p>
        <ul>
        <?php
        $stmt = db()->prepare("SELECT store_id, name FROM stores ORDER BY store_id DESC LIMIT 10");
        $stmt->execute();
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($stores as $s) {
            echo '<li><a href="?store_id=' . $s['store_id'] . '">' . htmlspecialchars($s['name']) . ' (ID: ' . $s['store_id'] . ')</a></li>';
        }
        ?>
        </ul>
    <?php endif; ?>
</body>
</html>
