<?php
define('ROOT_PATH', 'C:/xampp/htdocs/modernpos/');
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . '_init.php';

header('Content-Type: application/json');

try {
    $stmt = db()->prepare("SELECT * FROM ai_catalogo_models WHERE sku = 'EXK-376'");
    $stmt->execute();
    $res['inactive_models'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = db()->prepare("SELECT * FROM ai_catalogo_variants WHERE sku = 'EXK-376'");
    $stmt->execute();
    $res['inactive_variants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($res, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
