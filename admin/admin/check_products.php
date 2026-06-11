<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=modernpos", "root", "");
    $st = $pdo->query("SELECT id, product_id, content FROM concierge_status");
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    echo "DATA IN CONCIERGE_STATUS:\n";
    print_r($rows);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
