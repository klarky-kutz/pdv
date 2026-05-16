<?php
// Arquivo: send_code.php
// (Solução Rápida - CORRIGIDA PARA PHPMailer v6.9.1)

session_start();
include("_init.php"); // Inclui sua inicialização e banco de dados

// =======================================================
// CORREÇÃO PHPMailer v6: Carrega os arquivos ANTES
// Caminho baseado na sua informação: _inc/src/PHPMailer
// =======================================================
require_once '_inc/src/PHPMailer/src/Exception.php';
require_once '_inc/src/PHPMailer/src/PHPMailer.php';
require_once '_inc/src/PHPMailer/src/SMTP.php';

// CORREÇÃO: Declarações "use" vêm DEPOIS do require
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
// Renomeia "Exception" para evitar conflito com a exceção padrão do PHP
use PHPMailer\PHPMailer\Exception as PHPMailerException; 

// Define a resposta como JSON
header('Content-Type: application/json; charset=UTF-8');

// Pega o e-mail enviado via AJAX
$email = $_POST['email'] ?? null;

try {
    
    // 1. Valida o E-mail
    if (empty($email) || !validateEmail($email)) {
        // CORREÇÃO: Usa a exceção global do PHP (\Exception)
        throw new \Exception(trans('error_email'));
    }
    
    // 2. VERIFICA SE O E-MAIL JÁ EXISTE
    $statement = db()->prepare("SELECT `email` FROM `users` WHERE `email` = ?");
    $statement->execute(array($email));
    if ($statement->rowCount() > 0) {
        // CORREÇÃO: Usa a exceção global do PHP
        throw new \Exception('Este e-mail já está cadastrado. Tente fazer login.');
    }

    // 3. PREPARA AS CONFIGURAÇÕES DE E-MAIL (Hostinger)
    $subject        = 'Código de Verificação - PDV Loja';
    $from_name      = 'PDV Loja'; // Nome que aparecerá no e-mail
    $verification_code = rand(100000, 999999); 
    
    $body = "
        <h2>Verifique sua conta PDV Loja</h2>
        <p>Seu código de verificação é:</p>
        <h3 style='font-size: 28px; letter-spacing: 4px; text-align: center; margin: 20px;'>
            <b>" . $verification_code . "</b>
        </h3>
    ";
    
    // 4. INICIA O PHPMailer (Corrigido para V6)
    
    // O 'require_once' antigo (da linha 44) foi REMOVIDO.
    
    $mail = new PHPMailer(true); // CORREÇÃO: Habilita exceções
    $mail->CharSet = 'UTF-8';
    $mail->SMTPDebug = 0; // Mude para 2 (SMTP::DEBUG_CLIENT) para ver erros
    $mail->isSMTP();      // Habilita SMTP
    
    // ---- CONFIGURAÇÕES HOSTINGER ----
    $mail->Host = 'smtp.hostinger.com';     // Servidor SMTP da Hostinger
    $mail->SMTPAuth = true;                 // Habilita autenticação
    $mail->Username = 'contato@jhenefferleone.com.br'; // Seu usuário
    $mail->Password = 'klarkyKK047521@!'; // !! SUA SENHA AQUI !!
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // CORREÇÃO: 'tls' para o padrão da V6
    $mail->Port = 587;                      // Porta 587
    // ---------------------------------

    $mail->setFrom('contato@jhenefferleone.com.br', $from_name);
    $mail->AddReplyTo('contato@jhenefferleone.com.br', $from_name);
    $mail->Subject = $subject;
    $mail->MsgHTML($body);
    $mail->AddAddress($email); 

    if (!$mail->Send()) {
        // CORREÇÃO: Lança a exceção específica do PHPMailer
        throw new PHPMailerException(trans('error_unable_to_send_an_email') . ' Erro: ' . $mail->ErrorInfo);
    }
    
    // 5. ARMAZENA O CÓDIGO NA SESSÃO
    $_SESSION['verification_code'] = $verification_code;
    $_SESSION['verification_email'] = $email;

    // 6. Resposta de Sucesso para o AJAX
    echo json_encode(['message' => 'Código enviado com sucesso para ' . $email]);
    exit();

} catch (\Exception $e) { // CORREÇÃO: Captura a exceção global
    
    // Resposta de Erro para o AJAX
    http_response_code(422); 
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}
?>