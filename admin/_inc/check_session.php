<?php
session_start();
include("../_init.php");

echo "<h1>Informações da Sessão Atual</h1>";
echo "<hr>";

echo "<h2>SESSION Variables</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Funções do Sistema</h2>";
echo "<table border='1' cellpadding='5'>";

if (function_exists('user_id')) {
    echo "<tr><td><strong>user_id():</strong></td><td>" . user_id() . "</td></tr>";
}

if (function_exists('user_group_id')) {
    echo "<tr><td><strong>user_group_id():</strong></td><td>" . user_group_id() . "</td></tr>";
}

if (function_exists('is_loggedin')) {
    echo "<tr><td><strong>is_loggedin():</strong></td><td>" . (is_loggedin() ? 'SIM' : 'NÃO') . "</td></tr>";
}

echo "</table>";

try {
    $pdo = db();
    
    if (function_exists('user_id')) {
        $uid = user_id();
        
        echo "<h2>Informações do Usuário Logado (ID: {$uid})</h2>";
        
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$uid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "<table border='1' cellpadding='5'>";
            foreach ($user as $key => $value) {
                if ($key === 'password') continue; // Não mostra a senha
                echo "<tr><td><strong>{$key}:</strong></td><td>{$value}</td></tr>";
            }
            echo "</table>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Erro: " . $e->getMessage() . "</p>";
}
?>
