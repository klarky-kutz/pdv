<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();
session_start();
include('_init.php');

require_once DIR_HELPER . 'ai_groups_helper.php';

ob_end_clean();
header('Content-Type: text/plain; charset=UTF-8');

echo "=== Adicionando coluna allow_requeue na tabela concierge_campaigns ===" . PHP_EOL;

try {
    $checkCol = db()->query("SHOW COLUMNS FROM concierge_campaigns LIKE 'allow_requeue'");
    if ($checkCol && $checkCol->rowCount() > 0) {
        echo "✅ Coluna allow_requeue já existe!" . PHP_EOL;
    } else {
        echo "⏳ Adicionando coluna..." . PHP_EOL;
        db()->exec("ALTER TABLE concierge_campaigns ADD COLUMN allow_requeue TINYINT(1) NOT NULL DEFAULT 1");
        echo "✅ Coluna allow_requeue adicionada com sucesso!" . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;
echo "=== Verificando se a coluna existe ===" . PHP_EOL;
try {
    $checkCol2 = db()->query("SHOW COLUMNS FROM concierge_campaigns LIKE 'allow_requeue'");
    if ($checkCol2 && $checkCol2->rowCount() > 0) {
        $row = $checkCol2->fetch(PDO::FETCH_ASSOC);
        echo "✅ Coluna encontrada: " . var_export($row, true) . PHP_EOL;
    } else {
        echo "❌ Coluna não encontrada!" . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . PHP_EOL;
}
