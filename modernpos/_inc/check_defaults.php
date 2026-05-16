<?php
require_once dirname(__DIR__) . '/_inc/lib/database.php';
require_once dirname(__DIR__) . '/_inc/lib/config.php';

$config = new Config();
$db = new Database(
    $config->get('db_hostname'),
    $config->get('db_username'),
    $config->get('db_password'),
    $config->get('db_database')
);

echo "=== UNITS ===\n";
$stmt = $db->prepare('SELECT uunit_id, unit_name FROM units LIMIT 5');
$stmt->execute();
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($units as $u) {
    echo $u['uunit_id'] . ' - ' . $u['unit_name'] . "\n";
}

echo "\n=== BOXES ===\n";
$stmt = $db->prepare('SELECT box_id, box_name FROM box LIMIT 5');
$stmt->execute();
$boxes = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($boxes as $b) {
    echo $b['box_id'] . ' - ' . $b['box_name'] . "\n";
}

echo "\n=== TAX RATES ===\n";
$stmt = $db->prepare('SELECT taxrate_id, taxrate_name FROM taxrates LIMIT 5');
$stmt->execute();
$taxrates = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($taxrates as $t) {
    echo $t['taxrate_id'] . ' - ' . $t['taxrate_name'] . "\n";
}
