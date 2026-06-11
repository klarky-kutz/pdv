<?php
/**
 * Script de Correção - Atualizar registros sem tenant_id
 * Acesse via: http://localhost/modernpos/migrations/fix_tenant_id.php
 */

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'modernpos');

header('Content-Type: application/json');

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Falha na conexão: " . $conn->connect_error);
    }
    
    $tables = ['selling_info', 'selling_item', 'customers', 'products'];
    $results = [];
    
    foreach ($tables as $table) {
        // Contar registros sem tenant_id
        $result = $conn->query("SELECT COUNT(*) as total FROM `$table` WHERE tenant_id IS NULL");
        $row = $result->fetch_assoc();
        $before = $row['total'];
        
        // Atualizar registros sem tenant_id
        $conn->query("UPDATE `$table` SET tenant_id = 1 WHERE tenant_id IS NULL");
        $updated = $conn->affected_rows;
        
        // Contar novamente
        $result = $conn->query("SELECT COUNT(*) as total FROM `$table` WHERE tenant_id IS NULL");
        $row = $result->fetch_assoc();
        $after = $row['total'];
        
        $results[$table] = [
            'before' => $before,
            'updated' => $updated,
            'after' => $after
        ];
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Correção aplicada com sucesso!',
        'data' => $results
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
