<?php
/**
 * Script de Limpeza de Registros Órfãos
 * Remove registros que não estão vinculados a nenhuma loja
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
    <title>Limpeza de Banco de Dados</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        .warning strong { color: #856404; }
        .results { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 10px 0; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        tr:hover { background: #f5f5f5; }
        .btn { display: inline-block; padding: 10px 20px; margin: 5px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .count { font-weight: bold; color: #dc3545; font-size: 1.2em; }
    </style>
</head>
<body>
<div class="container">
    <h1>🧹 Limpeza de Registros Órfãos</h1>
    
    <div class="warning">
        <strong>⚠️ ATENÇÃO:</strong> Este script irá remover permanentemente registros que não estão vinculados a nenhuma loja.
        Esta operação não pode ser desfeita. Faça backup do banco de dados antes de continuar.
    </div>

    <?php
    $action = isset($_POST['action']) ? $_POST['action'] : 'analyze';
    
    if ($action === 'analyze') {
        // Modo de análise - apenas mostra o que seria excluído
        ?>
        <div class="section">
            <h2>Análise de Registros Órfãos</h2>
            <p>Verificando registros que não estão vinculados a nenhuma loja...</p>
            
            <?php
            $orphans = array();
            
            // Categorias órfãs
            $stmt = db()->prepare("
                SELECT c.ccategory_id, c.category_name 
                FROM categorys c 
                LEFT JOIN category_to_store cts ON c.ccategory_id = cts.ccategory_id 
                WHERE cts.ccategory_id IS NULL
            ");
            $stmt->execute();
            $orphans['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fornecedores órfãos
            $stmt = db()->prepare("
                SELECT s.sup_id, s.sup_name 
                FROM suppliers s 
                LEFT JOIN supplier_to_store sts ON s.sup_id = sts.sup_id 
                WHERE sts.sup_id IS NULL
            ");
            $stmt->execute();
            $orphans['suppliers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Marcas órfãs
            $stmt = db()->prepare("
                SELECT b.brand_id, b.brand_name 
                FROM brands b 
                LEFT JOIN brand_to_store bts ON b.brand_id = bts.brand_id 
                WHERE bts.brand_id IS NULL
            ");
            $stmt->execute();
            $orphans['brands'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Produtos órfãos
            $stmt = db()->prepare("
                SELECT p.p_id, p.p_name 
                FROM products p 
                LEFT JOIN product_to_store pts ON p.p_id = pts.product_id 
                WHERE pts.product_id IS NULL
            ");
            $stmt->execute();
            $orphans['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contas bancárias órfãs
            $stmt = db()->prepare("
                SELECT ba.id, ba.account_name 
                FROM bank_accounts ba 
                LEFT JOIN bank_account_to_store bats ON ba.id = bats.account_id 
                WHERE bats.account_id IS NULL
            ");
            $stmt->execute();
            $orphans['bank_accounts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Templates órfãos
            $stmt = db()->prepare("
                SELECT t.ttemplate_id, t.template_name 
                FROM pos_receipt_template t 
                LEFT JOIN pos_template_to_store tts ON t.ttemplate_id = tts.ttemplate_id 
                WHERE tts.ttemplate_id IS NULL
            ");
            $stmt->execute();
            $orphans['templates'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalOrphans = array_sum(array_map('count', $orphans));
            ?>
            
            <h3>Resultado da Análise</h3>
            
            <?php if ($totalOrphans > 0): ?>
                <p>Total de registros órfãos encontrados: <span class="count"><?php echo $totalOrphans; ?></span></p>
                
                <!-- Categorias -->
                <?php if (count($orphans['categories']) > 0): ?>
                    <h4>Categorias Órfãs (<?php echo count($orphans['categories']); ?>)</h4>
                    <table>
                        <tr><th>ID</th><th>Nome</th></tr>
                        <?php foreach ($orphans['categories'] as $item): ?>
                            <tr><td><?php echo $item['ccategory_id']; ?></td><td><?php echo htmlspecialchars($item['category_name']); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
                
                <!-- Fornecedores -->
                <?php if (count($orphans['suppliers']) > 0): ?>
                    <h4>Fornecedores Órfãos (<?php echo count($orphans['suppliers']); ?>)</h4>
                    <table>
                        <tr><th>ID</th><th>Nome</th></tr>
                        <?php foreach ($orphans['suppliers'] as $item): ?>
                            <tr><td><?php echo $item['sup_id']; ?></td><td><?php echo htmlspecialchars($item['sup_name']); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
                
                <!-- Marcas -->
                <?php if (count($orphans['brands']) > 0): ?>
                    <h4>Marcas Órfãs (<?php echo count($orphans['brands']); ?>)</h4>
                    <table>
                        <tr><th>ID</th><th>Nome</th></tr>
                        <?php foreach ($orphans['brands'] as $item): ?>
                            <tr><td><?php echo $item['brand_id']; ?></td><td><?php echo htmlspecialchars($item['brand_name']); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
                
                <!-- Produtos -->
                <?php if (count($orphans['products']) > 0): ?>
                    <h4>Produtos Órfãos (<?php echo count($orphans['products']); ?>)</h4>
                    <table>
                        <tr><th>ID</th><th>Nome</th></tr>
                        <?php foreach ($orphans['products'] as $item): ?>
                            <tr><td><?php echo $item['p_id']; ?></td><td><?php echo htmlspecialchars($item['p_name']); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
                
                <!-- Contas Bancárias -->
                <?php if (count($orphans['bank_accounts']) > 0): ?>
                    <h4>Contas Bancárias Órfãs (<?php echo count($orphans['bank_accounts']); ?>)</h4>
                    <table>
                        <tr><th>ID</th><th>Nome</th></tr>
                        <?php foreach ($orphans['bank_accounts'] as $item): ?>
                            <tr><td><?php echo $item['id']; ?></td><td><?php echo htmlspecialchars($item['account_name']); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
                
                <!-- Templates -->
                <?php if (count($orphans['templates']) > 0): ?>
                    <h4>Templates de Recibo Órfãos (<?php echo count($orphans['templates']); ?>)</h4>
                    <table>
                        <tr><th>ID</th><th>Nome</th></tr>
                        <?php foreach ($orphans['templates'] as $item): ?>
                            <tr><td><?php echo $item['ttemplate_id']; ?></td><td><?php echo htmlspecialchars($item['template_name']); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
                
                <form method="post" onsubmit="return confirm('⚠️ ATENÇÃO: Você tem certeza que deseja EXCLUIR permanentemente estes <?php echo $totalOrphans; ?> registros?\n\nEsta ação NÃO pode ser desfeita!');">
                    <input type="hidden" name="action" value="cleanup">
                    <button type="submit" class="btn btn-danger">🗑️ EXCLUIR TODOS OS REGISTROS ÓRFÃOS</button>
                </form>
                
            <?php else: ?>
                <div class="results">
                    <strong>✅ Banco de dados limpo!</strong> Não foram encontrados registros órfãos.
                </div>
            <?php endif; ?>
        </div>
        
    <?php } elseif ($action === 'cleanup') {
        // Modo de limpeza - executa a exclusão
        ?>
        <div class="section">
            <h2>Executando Limpeza...</h2>
            
            <?php
            try {
                db()->beginTransaction();
                
                $deleted = array();
                
                // Excluir categorias órfãs
                $stmt = db()->prepare("
                    DELETE c FROM categorys c 
                    LEFT JOIN category_to_store cts ON c.ccategory_id = cts.ccategory_id 
                    WHERE cts.ccategory_id IS NULL
                ");
                $stmt->execute();
                $deleted['categories'] = $stmt->rowCount();
                
                // Excluir fornecedores órfãos
                $stmt = db()->prepare("
                    DELETE s FROM suppliers s 
                    LEFT JOIN supplier_to_store sts ON s.sup_id = sts.sup_id 
                    WHERE sts.sup_id IS NULL
                ");
                $stmt->execute();
                $deleted['suppliers'] = $stmt->rowCount();
                
                // Excluir marcas órfãs
                $stmt = db()->prepare("
                    DELETE b FROM brands b 
                    LEFT JOIN brand_to_store bts ON b.brand_id = bts.brand_id 
                    WHERE bts.brand_id IS NULL
                ");
                $stmt->execute();
                $deleted['brands'] = $stmt->rowCount();
                
                // Excluir produtos órfãos
                $stmt = db()->prepare("
                    DELETE p FROM products p 
                    LEFT JOIN product_to_store pts ON p.p_id = pts.product_id 
                    WHERE pts.product_id IS NULL
                ");
                $stmt->execute();
                $deleted['products'] = $stmt->rowCount();
                
                // Excluir contas bancárias órfãs
                $stmt = db()->prepare("
                    DELETE ba FROM bank_accounts ba 
                    LEFT JOIN bank_account_to_store bats ON ba.id = bats.account_id 
                    WHERE bats.account_id IS NULL
                ");
                $stmt->execute();
                $deleted['bank_accounts'] = $stmt->rowCount();
                
                // Excluir templates órfãos
                $stmt = db()->prepare("
                    DELETE t FROM pos_receipt_template t 
                    LEFT JOIN pos_template_to_store tts ON t.ttemplate_id = tts.ttemplate_id 
                    WHERE tts.ttemplate_id IS NULL
                ");
                $stmt->execute();
                $deleted['templates'] = $stmt->rowCount();
                
                db()->commit();
                
                $totalDeleted = array_sum($deleted);
                ?>
                
                <div class="results">
                    <h3>✅ Limpeza Concluída com Sucesso!</h3>
                    <p>Total de registros excluídos: <strong><?php echo $totalDeleted; ?></strong></p>
                    <ul>
                        <li>Categorias: <?php echo $deleted['categories']; ?></li>
                        <li>Fornecedores: <?php echo $deleted['suppliers']; ?></li>
                        <li>Marcas: <?php echo $deleted['brands']; ?></li>
                        <li>Produtos: <?php echo $deleted['products']; ?></li>
                        <li>Contas Bancárias: <?php echo $deleted['bank_accounts']; ?></li>
                        <li>Templates: <?php echo $deleted['templates']; ?></li>
                    </ul>
                </div>
                
            <?php } catch (Exception $e) {
                db()->rollBack();
                ?>
                <div class="error">
                    <strong>❌ Erro:</strong> <?php echo htmlspecialchars($e->getMessage()); ?>
                </div>
            <?php } ?>
            
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-success">🔄 Analisar Novamente</a>
        </div>
    <?php } ?>
    
    <div class="section">
        <h3>📋 Informações</h3>
        <p><strong>O que este script faz:</strong></p>
        <ul>
            <li>Identifica registros em categorias, fornecedores, marcas, produtos, contas bancárias e templates que não estão vinculados a nenhuma loja</li>
            <li>Mostra uma lista detalhada antes de excluir</li>
            <li>Usa transações para garantir integridade dos dados</li>
            <li>Requer confirmação antes de executar a exclusão</li>
        </ul>
        <p><strong>Quando usar:</strong></p>
        <ul>
            <li>Após testes ou importações de dados</li>
            <li>Quando lojas são excluídas mas seus dados ficam órfãos</li>
            <li>Para manutenção periódica do banco de dados</li>
        </ul>
    </div>
</div>
</body>
</html>
