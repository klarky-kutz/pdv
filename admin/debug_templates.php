<?php
ob_start();
session_start();
include '../_init.php';

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug: Templates no Banco de Dados</h2>";

try {
    // Tentar conectar ao banco
    $pdo = new PDO('mysql:host=localhost;dbname=modernpos', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✓ Conexão com banco estabelecida</p>";
    
    // Verificar se a tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'pos_templates'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "<p style='color: green;'>✓ Tabela pos_templates existe</p>";
        
        // Contar registros
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM pos_templates");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Total de templates: <strong>" . $count['total'] . "</strong></p>";
        
        // Listar todos os templates
        $stmt = $pdo->query("SELECT template_id, template_name, created_at FROM pos_templates ORDER BY template_id ASC");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($templates) > 0) {
            echo "<h3>Templates encontrados:</h3>";
            echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Nome</th><th>Criado em</th></tr>";
            foreach ($templates as $tpl) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($tpl['template_id']) . "</td>";
                echo "<td>" . htmlspecialchars($tpl['template_name']) . "</td>";
                echo "<td>" . htmlspecialchars($tpl['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: red;'>✗ Nenhum template encontrado na tabela</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Tabela pos_templates NÃO existe</p>";
    }
    
    // Testar função do sistema
    echo "<hr><h3>Testando função do sistema:</h3>";
    
    echo "<p style='color: green;'>✓ Arquivo _init.php já carregado</p>";
    
    if (function_exists('get_postemplates')) {
        echo "<p style='color: green;'>✓ Função get_postemplates() existe</p>";
        
        $system_templates = get_postemplates();
        echo "<p>Templates retornados pela função: <strong>" . count($system_templates) . "</strong></p>";
        
        if (count($system_templates) > 0) {
            echo "<h4>Dados dos templates:</h4>";
            echo "<pre>";
            print_r($system_templates);
            echo "</pre>";
        }
    } else {
        echo "<p style='color: red;'>✗ Função get_postemplates() NÃO existe</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
