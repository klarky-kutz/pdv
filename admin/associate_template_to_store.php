<?php
ob_start();
session_start();
include '../_init.php';

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

echo "<h2>Associar Template 'Minimalista' à Loja</h2>";

try {
    global $db;
    
    $store_id = store_id(); // Pega o ID da loja atual
    $template_id = 3; // ID do template Minimalista que acabamos de criar
    
    // Verificar se já existe
    $check_sql = "SELECT * FROM pos_template_to_store WHERE store_id = $store_id AND ttemplate_id = $template_id";
    $db->query($check_sql);
    $existing = $db->single();
    
    if ($existing) {
        echo "<div style='padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; color: #856404;'>";
        echo "<h3>⚠ Aviso</h3>";
        echo "<p>O template 'Minimalista' já está associado a esta loja.</p>";
        echo "<p><a href='select_receipt_template.php' class='btn btn-primary'>Ver Modelos de Recibo</a></p>";
        echo "</div>";
    } else {
        // Pegar o próximo sort_order
        $sort_sql = "SELECT MAX(sort_order) as max_sort FROM pos_template_to_store WHERE store_id = $store_id";
        $db->query($sort_sql);
        $sort_result = $db->single();
        $next_sort = ($sort_result && isset($sort_result['max_sort'])) ? $sort_result['max_sort'] + 1 : 1;
        
        // Inserir associação
        $insert_sql = "INSERT INTO pos_template_to_store (store_id, ttemplate_id, is_active, status, sort_order) 
                       VALUES ($store_id, $template_id, 1, 1, $next_sort)";
        
        $db->query($insert_sql);
        
        echo "<div style='padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; color: #155724;'>";
        echo "<h3>✓ Sucesso!</h3>";
        echo "<p>Template 'Minimalista' associado à loja com sucesso!</p>";
        echo "<p><strong>Store ID:</strong> " . $store_id . "</p>";
        echo "<p><strong>Template ID:</strong> " . $template_id . "</p>";
        echo "<p><strong>Sort Order:</strong> " . $next_sort . "</p>";
        echo "<br>";
        echo "<p><a href='select_receipt_template.php' class='btn btn-success'>Ver Modelos de Recibo</a></p>";
        echo "<p><a href='receipt_template.php?template_id=" . $template_id . "' class='btn btn-primary'>Editar Template</a></p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;'>";
    echo "<h3>✗ Erro!</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    padding: 20px;
    max-width: 800px;
    margin: 0 auto;
}
.btn {
    display: inline-block;
    padding: 10px 20px;
    margin: 5px;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 600;
}
.btn-success {
    background: #28a745;
    color: white;
}
.btn-primary {
    background: #007bff;
    color: white;
}
</style>
