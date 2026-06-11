<?php
require __DIR__ . '/config.php';

$dsn = 'mysql:host=' . $sql_details['host'] . ';dbname=' . $sql_details['db'] . ';port=' . $sql_details['port'] . ';charset=utf8';
$pdo = new PDO($dsn, $sql_details['user'], $sql_details['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function h($title) {
    echo "\n==== {$title} ====\n";
}

h('DB OK');

h('customers columns');
$cols = $pdo->query('SHOW COLUMNS FROM customers')->fetchAll();
foreach ($cols as $r) {
    echo $r['Field'] . "\t" . $r['Type'] . "\t" . $r['Null'] . "\t" . ($r['Default'] ?? '') . "\n";
}

h('stores last 5 (order by store_id desc) + walkin_customer_id from preference');
$stores = $pdo->query('SELECT store_id, name, created_at, preference FROM stores ORDER BY store_id DESC LIMIT 5')->fetchAll();
foreach ($stores as $r) {
    $pref = [];
    if (is_string($r['preference']) && $r['preference'] !== '') {
        $tmp = @unserialize($r['preference']);
        if (is_array($tmp)) $pref = $tmp;
    }
    $walkin = isset($pref['walkin_customer_id']) ? (int)$pref['walkin_customer_id'] : 0;
    echo 'store_id=' . $r['store_id'] . ' name=' . $r['name'] . ' created_at=' . $r['created_at'] . ' walkin_customer_id=' . $walkin . "\n";
}

h('stores last 10 (order by created_at desc) + walkin_customer_id from preference');
$storesByDate = $pdo->query('SELECT store_id, name, created_at, preference FROM stores ORDER BY created_at DESC LIMIT 10')->fetchAll();
foreach ($storesByDate as $r) {
    $pref = [];
    if (is_string($r['preference']) && $r['preference'] !== '') {
        $tmp = @unserialize($r['preference']);
        if (is_array($tmp)) $pref = $tmp;
    }
    $walkin = isset($pref['walkin_customer_id']) ? (int)$pref['walkin_customer_id'] : 0;
    echo 'store_id=' . $r['store_id'] . ' name=' . $r['name'] . ' created_at=' . $r['created_at'] . ' walkin_customer_id=' . $walkin . "\n";
}

h('store_id=14 raw preference (if exists)');
$st14 = $pdo->prepare('SELECT store_id, name, created_at, preference FROM stores WHERE store_id = 14 LIMIT 1');
$st14->execute();
$row14 = $st14->fetch();
if ($row14) {
    echo 'store_id=' . $row14['store_id'] . ' name=' . $row14['name'] . ' created_at=' . $row14['created_at'] . "\n";
    $pref = @unserialize($row14['preference']);
    if (is_array($pref)) {
        echo 'walkin_customer_id=' . (isset($pref['walkin_customer_id']) ? (int)$pref['walkin_customer_id'] : 0) . "\n";
    } else {
        echo "preference not unserializable or empty\n";
    }
} else {
    echo "store_id=14 not found\n";
}

h('customer_to_store last 20');
$c2s = $pdo->query('SELECT c2s_id, store_id, customer_id, status, sort_order FROM customer_to_store ORDER BY c2s_id DESC LIMIT 20')->fetchAll();
foreach ($c2s as $r) {
    echo 'c2s_id=' . $r['c2s_id'] . ' store_id=' . $r['store_id'] . ' customer_id=' . $r['customer_id'] . ' status=' . $r['status'] . ' sort_order=' . $r['sort_order'] . "\n";
}

h('customers last 10');
$customers = $pdo->query('SELECT customer_id, customer_name, created_at FROM customers ORDER BY customer_id DESC LIMIT 10')->fetchAll();
foreach ($customers as $r) {
    echo 'customer_id=' . $r['customer_id'] . ' name=' . $r['customer_name'] . ' created_at=' . $r['created_at'] . "\n";
}
