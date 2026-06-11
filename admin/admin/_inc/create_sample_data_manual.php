<?php
/**
 * Script para criar dados de exemplo manualmente
 * 
 * Use este script para adicionar dados de exemplo a uma loja EXISTENTE
 * 
 * Acesse: http://localhost/modernpos/_inc/create_sample_data_manual.php?store_id=1
 */

session_start();
include("../_init.php");

// Verificar se usuário está logado e é admin
if (!is_loggedin() || user_group_id() != 1) {
    die('<h1>❌ Acesso Negado</h1><p>Apenas administradores podem acessar esta página.</p>');
}

// Pegar store_id da URL
$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Dados de Exemplo Manualmente</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid;
            border-radius: 4px;
        }
        .alert-info {
            background: #e3f2fd;
            border-left-color: #2196F3;
            color: #1976D2;
        }
        .alert-success {
            background: #e8f5e9;
            border-left-color: #4CAF50;
            color: #2E7D32;
        }
        .alert-error {
            background: #ffebee;
            border-left-color: #f44336;
            color: #C62828;
        }
        .alert-warning {
            background: #fff3e0;
            border-left-color: #ff9800;
            color: #E65100;
        }
        .form-group {
            margin: 20px 0;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        select {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 10px 5px;
        }
        button:hover {
            background: #45a049;
        }
        button.secondary {
            background: #2196F3;
        }
        button.secondary:hover {
            background: #1976D2;
        }
        .data-list {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .data-list h3 {
            margin-top: 0;
            color: #4CAF50;
        }
        .data-list ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .data-list li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎯 Criar Dados de Exemplo Manualmente</h1>
        
        <?php if ($store_id && isset($_GET['action']) && $_GET['action'] == 'create'): ?>
            <?php
            // Verificar se loja existe
            $stmt = db()->prepare("SELECT store_id, name FROM stores WHERE store_id = ?");
            $stmt->execute([$store_id]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                echo '<div class="alert alert-error">';
                echo '<strong>❌ Erro:</strong> Loja com ID ' . $store_id . ' não encontrada.';
                echo '</div>';
            } else {
                try {
                    // Criar dados de exemplo
                    $success = create_sample_data_for_store($store_id);
                    
                    if ($success) {
                        echo '<div class="alert alert-success">';
                        echo '<strong>✅ Sucesso!</strong><br>';
                        echo 'Dados de exemplo criados para a loja: <strong>' . htmlspecialchars($store['name']) . '</strong> (ID: ' . $store_id . ')';
                        echo '</div>';
                        
                        echo '<div class="data-list">';
                        echo '<h3>📦 Dados Criados:</h3>';
                        echo '<ul>';
                        echo '<li>✅ 1 Categoria (Exemplo - Eletrônicos)</li>';
                        echo '<li>✅ 1 Fornecedor (Fornecedor Exemplo LTDA)</li>';
                        echo '<li>✅ 1 Marca (Marca Exemplo)</li>';
                        echo '<li>✅ 1 Conta Bancária (Conta Corrente Exemplo - R$ 5.000,00)</li>';
                        echo '<li>✅ 4 Produtos:</li>';
                        echo '<ul>';
                        echo '<li>Mouse Sem Fio Exemplo (R$ 59,90)</li>';
                        echo '<li>Teclado USB Exemplo (R$ 79,90)</li>';
                        echo '<li>Webcam HD Exemplo (R$ 149,90)</li>';
                        echo '<li>Headset com Microfone Exemplo (R$ 99,90)</li>';
                        echo '</ul>';
                        echo '</ul>';
                        echo '</div>';
                        
                        echo '<div style="margin-top: 30px;">';
                        echo '<a href="' . root_url() . 'admin/category.php"><button>Ver Categorias</button></a>';
                        echo '<a href="' . root_url() . 'admin/supplier.php"><button>Ver Fornecedores</button></a>';
                        echo '<a href="' . root_url() . 'admin/brand.php"><button>Ver Marcas</button></a>';
                        echo '<a href="' . root_url() . 'admin/product.php"><button>Ver Produtos</button></a>';
                        echo '<a href="' . root_url() . 'admin/bank_account.php"><button>Ver Contas Bancárias</button></a>';
                        echo '</div>';
                    } else {
                        echo '<div class="alert alert-error">';
                        echo '<strong>❌ Erro:</strong> Falha ao criar dados de exemplo. Verifique os logs.';
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="alert alert-error">';
                    echo '<strong>❌ Erro:</strong> ' . htmlspecialchars($e->getMessage());
                    echo '</div>';
                }
            }
            ?>
            
            <div style="margin-top: 30px;">
                <a href="?"><button class="secondary">← Voltar</button></a>
            </div>
            
        <?php else: ?>
            
            <div class="alert alert-info">
                <strong>ℹ️ Informações</strong><br>
                Este script cria dados de exemplo em uma loja <strong>existente</strong>.<br>
                Útil para adicionar dados de demonstração a lojas já criadas.
            </div>
            
            <div class="alert alert-warning">
                <strong>⚠️ Atenção</strong><br>
                Os dados criados são apenas exemplos e podem ser editados ou excluídos a qualquer momento.
            </div>
            
            <form method="get" action="">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label for="store_id">Selecione a Loja:</label>
                    <select name="store_id" id="store_id" required>
                        <option value="">-- Selecione uma loja --</option>
                        <?php
                        $stmt = db()->prepare("SELECT store_id, name FROM stores ORDER BY name");
                        $stmt->execute();
                        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($stores as $store) {
                            $selected = ($store_id == $store['store_id']) ? 'selected' : '';
                            echo '<option value="' . $store['store_id'] . '" ' . $selected . '>';
                            echo htmlspecialchars($store['name']) . ' (ID: ' . $store['store_id'] . ')';
                            echo '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="data-list">
                    <h3>📦 O Que Será Criado:</h3>
                    <ul>
                        <li><strong>1 Categoria:</strong> Exemplo - Eletrônicos</li>
                        <li><strong>1 Fornecedor:</strong> Fornecedor Exemplo LTDA</li>
                        <li><strong>1 Marca:</strong> Marca Exemplo</li>
                        <li><strong>1 Conta Bancária:</strong> Conta Corrente Exemplo (R$ 5.000,00)</li>
                        <li><strong>4 Produtos:</strong>
                            <ul>
                                <li>Mouse Sem Fio Exemplo - R$ 59,90 (50 unidades)</li>
                                <li>Teclado USB Exemplo - R$ 79,90 (30 unidades)</li>
                                <li>Webcam HD Exemplo - R$ 149,90 (20 unidades)</li>
                                <li>Headset com Microfone Exemplo - R$ 99,90 (40 unidades)</li>
                            </ul>
                        </li>
                    </ul>
                </div>
                
                <button type="submit">🚀 Criar Dados de Exemplo</button>
            </form>
            
            <div style="margin-top: 30px;">
                <a href="<?php echo root_url(); ?>admin/dashboard.php">
                    <button class="secondary">← Voltar ao Dashboard</button>
                </a>
            </div>
            
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;">
            <strong>Nota:</strong> Dados de exemplo também são criados automaticamente quando você cria uma nova loja.
        </div>
    </div>
</body>
</html>
