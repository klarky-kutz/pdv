<?php
// Arquivo: verify_code.php
// (Este arquivo é chamado por AJAX da Etapa 2)

session_start();
header('Content-Type: application/json; charset=UTF-8');

// Pega o código enviado pelo AJAX
$user_code = $_POST['code'] ?? null;

// Pega o código correto que foi salvo na sessão pelo send_code.php
$session_code = $_SESSION['verification_code'] ?? '-----';

// Compara os códigos
if ($user_code !== null && $user_code == $session_code) {
    
    // Sucesso! Seta uma "trava" de segurança na sessão.
    $_SESSION['code_verified'] = true; 
    
    // Retorna sucesso para o JavaScript
    echo json_encode(['success' => true]);
    
} else {
    
    // Erro!
    http_response_code(422); // Envia erro "Unprocessable Entity"
    echo json_encode(['error' => 'Código de verificação incorreto. Tente novamente.']);
}
exit();
?>