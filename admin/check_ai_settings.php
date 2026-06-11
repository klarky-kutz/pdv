<?php
ob_start();
include '_init.php';

echo '<h1>Estrutura da tabela ai_settings</h1>';
try {
    $stmt = db()->query("DESCRIBE ai_settings");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo '<div style="background:#f8f9fa;padding:10px;border-radius:6px;font-family:monospace">';
    foreach ($columns as $col) {
        echo htmlspecialchars($col['Field']) . ' - ' . htmlspecialchars($col['Type']) . '<br>';
    }
    echo '</div>';

    echo '<h3>Primeiros 10 registros:</h3>';
    $stmt2 = db()->query("SELECT * FROM ai_settings LIMIT 10");
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo '<div style="background:#f8f9fa;padding:10px;border-radius:6px;font-family:monospace;max-height:400px;overflow:auto">';
    foreach ($rows as $row) {
        echo '<div style="padding:4px 0;border-bottom:1px solid #e2e8f0">';
        foreach ($row as $k => $v) {
            echo '<span style="font-weight:700;color:#7c3aed">' . htmlspecialchars($k) . '</span>: ' . htmlspecialchars($v) . ' | ';
        }
        echo '</div>';
    }
    echo '</div>';
} catch (Throwable $e) {
    echo '<h3>Erro:</h3>';
    echo '<div style="background:#fee2e2;padding:10px;border-radius:6px;color:#dc2626">' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>