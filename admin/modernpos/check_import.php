<?php
include '_init.php';

echo "<h3>Lojas Cadastradas:</h3>";
$stmt = db()->query("SELECT * FROM stores LIMIT 10");
echo "<table border='1'><tr><th>ID</th><th>Nome</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>{$row['store_id']}</td><td>{$row['name']}</td></tr>";
}
echo "</table>";

echo "<h3>Últimos Produtos em product_to_store:</h3>";
$stmt = db()->query("SELECT pts.product_id, pts.store_id, p.p_name FROM product_to_store pts JOIN products p ON p.p_id = pts.product_id ORDER BY pts.product_id DESC LIMIT 15");
echo "<table border='1'><tr><th>Produto ID</th><th>Store ID</th><th>Nome</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>{$row['product_id']}</td><td>{$row['store_id']}</td><td>{$row['p_name']}</td></tr>";
}
echo "</table>";

echo "<h3>Loja Ativa na Sessão:</h3>";
echo "Store ID ativo: " . (function_exists('store_id') ? store_id() : 'N/A');
