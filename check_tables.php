<?php
ob_start();
include '_init.php';

echo '<h1>Tabelas com configurações</h1>';

try {
    $stmt = db()->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $tables = [];
    foreach ($allTables as $t) {
        if (stripos($t, 'config') !== false || stripos($t, 'setting') !== false) {
            $tables[] = $t;
        }
    }
    echo '<h3>Tabelas encontradas:</h3>';
    echo '<ul>';
    foreach ($tables as $t) {
        echo '<li>' . htmlspecialchars($t) . '</li>';
    }
    echo '</ul>';

    // Verifica saas_config_store
    if (in_array('saas_config_store', $tables)) {
        echo '<h3>Tabela saas_config_store:</h3>';
        $stmt = db()->prepare("SELECT * FROM saas_config_store WHERE store_id = :tid LIMIT 20");
        $stmt->execute([':tid' => 347]);
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo '<div style="background:#f8f9fa;padding:10px;border-radius:6px;font-family:monospace;max-height:400px;overflow:auto">';
        foreach ($configs as $c) {
            echo htmlspecialchars($c['key_name']) . ' = ' . htmlspecialchars($c['key_value']) . '<br>';
        }
        echo '</div>';
    }
} catch (Throwable $e) {
    echo '<h3>Erro:</h3>';
    echo '<div style="background:#fee2e2;padding:10px;border-radius:6px;color:#dc2626">' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>