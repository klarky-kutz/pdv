<?php
/**
 * Script de Inicialização de Dados de Exemplo Globais
 * Cria UMA VEZ os dados de exemplo que serão reutilizados por todas as lojas
 * Execute este script UMA ÚNICA VEZ após a instalação
 */

require_once dirname(__DIR__) . '/_init.php';

// Verificar se é admin
if (!is_admin()) {
    die('Acesso negado. Apenas administradores podem executar este script.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Inicializar Dados de Exemplo Globais</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .info { background: #d1ecf1; border-left: 4px solid #0c5460; padding: 15px; margin: 20px 0; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; }
        .btn { display: inline-block; padding: 10px 20px; margin: 5px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .data-info { background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 4px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Inicializar Dados de Exemplo Globais</h1>
    
    <?php
    $action = isset($_POST['action']) ? $_POST['action'] : 'check';
    
    if ($action === 'check') {
        // Verificar se já existem dados globais
        ?>
        <div class="info">
            <strong>ℹ️ Sobre este script:</strong>
            <p>Este script cria dados de exemplo GLOBAIS que serão reutilizados por todas as lojas novas.</p>
            <p>Os dados são criados UMA VEZ e apenas VINCULADOS às lojas, evitando registros duplicados e lixo no banco.</p>
        </div>
        
        <?php
        // Verificar se já existem
        $existing = array();
        
        // Categoria
        $stmt = db()->prepare("SELECT category_id, category_name FROM categorys WHERE category_name = ?");
        $stmt->execute(['[Global] Eletrônicos']);
        $existing['category'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Fornecedor
        $stmt = db()->prepare("SELECT sup_id, sup_name FROM suppliers WHERE sup_name = ?");
        $stmt->execute(['[Global] Fornecedor Exemplo LTDA']);
        $existing['supplier'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Marca
        $stmt = db()->prepare("SELECT brand_id, brand_name FROM brands WHERE brand_name = ?");
        $stmt->execute(['[Global] Marca Exemplo']);
        $existing['brand'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Conta Bancária
        $stmt = db()->prepare("SELECT id, account_name FROM bank_accounts WHERE account_name = ?");
        $stmt->execute(['[Global] Conta Corrente Exemplo']);
        $existing['bank_account'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $allExist = $existing['category'] && $existing['supplier'] && $existing['brand'] && $existing['bank_account'];
        
        if ($allExist) {
            ?>
            <div class="success">
                <strong>✅ Dados Globais já existem!</strong>
                <p>Os seguintes dados já foram criados:</p>
                <div class="data-info">
                    <strong>Categoria:</strong> <?php echo $existing['category']['category_name']; ?> (ID: <?php echo $existing['category']['category_id']; ?>)<br>
                    <strong>Fornecedor:</strong> <?php echo $existing['supplier']['sup_name']; ?> (ID: <?php echo $existing['supplier']['sup_id']; ?>)<br>
                    <strong>Marca:</strong> <?php echo $existing['brand']['brand_name']; ?> (ID: <?php echo $existing['brand']['brand_id']; ?>)<br>
                    <strong>Conta Bancária:</strong> <?php echo $existing['bank_account']['account_name']; ?> (ID: <?php echo $existing['bank_account']['id']; ?>)
                </div>
                <p>Novas lojas serão automaticamente vinculadas a estes dados.</p>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="recreate">
                <button type="submit" class="btn" style="background: #ffc107;" onclick="return confirm('Tem certeza? Isto irá RECRIAR os dados globais (os vínculos existentes permanecerão).');">
                    🔄 Recriar Dados Globais
                </button>
            </form>
            <?php
        } else {
            ?>
            <div class="warning">
                <strong>⚠️ Dados Globais NÃO encontrados!</strong>
                <p>Os dados de exemplo globais ainda não foram criados.</p>
                <?php if ($existing['category']) echo "<p>✅ Categoria encontrada</p>"; ?>
                <?php if ($existing['supplier']) echo "<p>✅ Fornecedor encontrado</p>"; ?>
                <?php if ($existing['brand']) echo "<p>✅ Marca encontrada</p>"; ?>
                <?php if ($existing['bank_account']) echo "<p>✅ Conta Bancária encontrada</p>"; ?>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="create">
                <button type="submit" class="btn">
                    ✨ Criar Dados Globais Agora
                </button>
            </form>
            <?php
        }
        
    } elseif ($action === 'create' || $action === 'recreate') {
        ?>
        <div class="info">
            <strong>🔄 Criando dados globais...</strong>
        </div>
        
        <?php
        try {
            db()->beginTransaction();
            
            $created = array();
            
            // Se for recreate, deletar existentes
            if ($action === 'recreate') {
                db()->prepare("DELETE FROM categorys WHERE category_name = ?")->execute(['[Global] Eletrônicos']);
                db()->prepare("DELETE FROM suppliers WHERE sup_name = ?")->execute(['[Global] Fornecedor Exemplo LTDA']);
                db()->prepare("DELETE FROM brands WHERE brand_name = ?")->execute(['[Global] Marca Exemplo']);
                db()->prepare("DELETE FROM bank_accounts WHERE account_name = ?")->execute(['[Global] Conta Corrente Exemplo']);
            }
            
            // 1. Criar Categoria Global
            $stmt = db()->prepare("
                INSERT INTO `categorys` (category_name, category_slug, parent_id, category_details, category_image, created_at) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                '[Global] Eletrônicos',
                'global-eletronicos',
                0,
                'Categoria global de exemplo - Produtos eletrônicos diversos',
                '',
                date('Y-m-d H:i:s')
            ]);
            $created['category_id'] = db()->lastInsertId();
            
            // 2. Criar Fornecedor Global
            $stmt = db()->prepare("
                INSERT INTO `suppliers` (sup_name, code_name, sup_mobile, sup_email, gtin, sup_address, sup_city, sup_state, sup_country, sup_details, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                '[Global] Fornecedor Exemplo LTDA',
                'global-fornecedor-exemplo',
                '(11) 98765-4321',
                'contato@fornecedorexemplo.com.br',
                '',
                'Rua Exemplo, 123',
                'São Paulo',
                'SP',
                'Brasil',
                'Fornecedor global de exemplo para demonstração',
                date('Y-m-d H:i:s')
            ]);
            $created['supplier_id'] = db()->lastInsertId();
            
            // 3. Criar Marca Global
            $stmt = db()->prepare("
                INSERT INTO `brands` (brand_name, code_name, brand_details, brand_image, created_at) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                '[Global] Marca Exemplo',
                'global-marca-exemplo',
                'Marca global de exemplo para demonstração',
                '',
                date('Y-m-d H:i:s')
            ]);
            $created['brand_id'] = db()->lastInsertId();
            
            // 4. Criar Conta Bancária Global
            $stmt = db()->prepare("
                INSERT INTO `bank_accounts` (account_name, account_details, account_no, contact_person, phone_number, url, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                '[Global] Conta Corrente Exemplo',
                'Conta bancária global de exemplo para demonstração',
                '12345-6',
                'Responsável Financeiro',
                '(11) 98765-4321',
                '',
                date('Y-m-d H:i:s')
            ]);
            $created['bank_account_id'] = db()->lastInsertId();
            
            db()->commit();
            ?>
            
            <div class="success">
                <h3>✅ Dados Globais Criados com Sucesso!</h3>
                <div class="data-info">
                    <strong>Categoria:</strong> [Global] Eletrônicos (ID: <?php echo $created['category_id']; ?>)<br>
                    <strong>Fornecedor:</strong> [Global] Fornecedor Exemplo LTDA (ID: <?php echo $created['supplier_id']; ?>)<br>
                    <strong>Marca:</strong> [Global] Marca Exemplo (ID: <?php echo $created['brand_id']; ?>)<br>
                    <strong>Conta Bancária:</strong> [Global] Conta Corrente Exemplo (ID: <?php echo $created['bank_account_id']; ?>)
                </div>
                <p><strong>IDs salvos em:</strong> _inc/config/global_sample_data_ids.php</p>
            </div>
            
            <?php
            // Salvar IDs em arquivo de configuração
            $config_content = "<?php\n";
            $config_content .= "// IDs dos dados de exemplo globais\n";
            $config_content .= "// Criados em: " . date('Y-m-d H:i:s') . "\n";
            $config_content .= "return array(\n";
            $config_content .= "    'category_id' => " . $created['category_id'] . ",\n";
            $config_content .= "    'supplier_id' => " . $created['supplier_id'] . ",\n";
            $config_content .= "    'brand_id' => " . $created['brand_id'] . ",\n";
            $config_content .= "    'bank_account_id' => " . $created['bank_account_id'] . ",\n";
            $config_content .= ");\n";
            
            $config_dir = dirname(__DIR__) . '/_inc/config';
            if (!is_dir($config_dir)) {
                mkdir($config_dir, 0755, true);
            }
            file_put_contents($config_dir . '/global_sample_data_ids.php', $config_content);
            
        } catch (Exception $e) {
            db()->rollBack();
            ?>
            <div class="error">
                <strong>❌ Erro:</strong> <?php echo htmlspecialchars($e->getMessage()); ?>
            </div>
            <?php
        }
        
        ?>
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn">🔙 Voltar</a>
        <?php
    }
    ?>
    
    <div class="info" style="margin-top: 30px;">
        <strong>📋 Próximos Passos:</strong>
        <ol>
            <li>Execute este script UMA VEZ para criar os dados globais</li>
            <li>Os dados ficarão armazenados com prefixo [Global] para identificação</li>
            <li>Ao criar novas lojas, estes dados serão automaticamente vinculados</li>
            <li>Cada loja terá seus próprios vínculos independentes</li>
            <li>Use o script de limpeza para remover dados órfãos antigos</li>
        </ol>
    </div>
</div>
</body>
</html>
