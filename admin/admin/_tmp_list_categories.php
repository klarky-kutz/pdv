<?php
include __DIR__ . '/_init.php';

$tenantId = isset($argv[1]) ? (int)$argv[1] : 347;

$st = db()->prepare("
    SELECT c.category_id, c.category_name, c2s.status
    FROM categorys c
    INNER JOIN category_to_store c2s ON c2s.ccategory_id = c.category_id
    WHERE c2s.store_id = :tid
    ORDER BY c.category_name
");
$st->execute([':tid' => $tenantId]);

echo json_encode([
    'tenant_id' => $tenantId,
    'categories' => $st->fetchAll(PDO::FETCH_ASSOC),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
