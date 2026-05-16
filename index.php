<?php
/*
| -----------------------------------------------------
| PRODUCT NAME:    Modern POS - Point of Sale with Stock Management System
| ... (seu cabeçalho)
| ADAPTADO POR GEMINI COM NOVO DESIGN BOOTSTRAP 5
*/

// 1. INICIALIZAÇÃO DO SISTEMA ORIGINAL
session_start(); // Iniciando a sessão no topo
include("_init.php");

// =======================================================
// CORREÇÃO PHPMailer v6: Carrega os arquivos ANTES
// =======================================================
require_once '_inc/src/PHPMailer/src/Exception.php';
require_once '_inc/src/PHPMailer/src/PHPMailer.php';
require_once '_inc/src/PHPMailer/src/SMTP.php';

// Declarações "use" vêm DEPOIS do require
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException; 

$document->setTitle(trans('text_login_title'));

// 2. VERIFICA SE JÁ ESTÁ LOGADO
if ($user->isLogged()) {
    redirect(ADMINDIRNAME . '/dashboard.php');
}

// 3. FUNÇÃO DE LOG DE ERRO (Original)
if (!function_exists('insert_error_log')) {
    function insert_error_log() {
        try {
            $statement = db()->prepare("INSERT INTO `login_logs` SET `ip` = ?, `status` = ?");
            $statement->execute(array(get_real_ip(), 'error'));
        } catch (\Exception $e) { /* CORREÇÃO: Usa \Exception global */ }
    }
}

// 4. PROCESSAMENTO DO LOGIN (AJAX - Lógica Original)
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->get['action_type']) && $request->get['action_type'] == "LOGIN") {
    try {
        $from = date('Y-m-d H:i:s', strtotime('-' . (int)UNLOCK_ACCOUNT_AFTER . ' minutes', time()));
        $to = date('Y-m-d H:i:s');
        $ip = get_real_ip();
        $statement = db()->prepare("SELECT `id` FROM `login_logs` WHERE `status` = ? AND `ip` = ? AND `created_at` >= ? AND `created_at` <= ?");
        $statement->execute(array('error', $ip, $from, $to));
        $total_try = $statement->rowCount();
        if ($total_try >= (int)TOTAL_LOGIN_TRY) {
            throw new \Exception($language->get('error_login_attempts_exceeded') . '. Try after ' . UNLOCK_ACCOUNT_AFTER . ' minute(s)');
        }
        if (!isset($request->post['username']) || !validateString($request->post['username'])) {
            insert_error_log(); throw new \Exception(trans('error_username')); 
        }
        if (empty($request->post['password'])) {
            insert_error_log(); throw new \Exception(trans('error_password')); 
        }
        $username = $request->post['username'];
        $password = $request->post['password'];

        // CORREÇÃO: Verifica se há erro de login armazenado na sessão (ex: assinatura pendente)
        if (isset($_SESSION['login_error'])) {
            $errorMessage = $_SESSION['login_error'];
            unset($_SESSION['login_error']);
            throw new \Exception($errorMessage);
        }
        
        if ($user->login($username, $password)) {
            $statement = db()->prepare("INSERT INTO `login_logs` SET `user_id` = ?, `username` = ?, `ip` = ?");
            $statement->execute(array(user_id(), $username, get_real_ip()));
            $statement = db()->prepare("UPDATE `users` SET `last_login` = ? WHERE `id` = ?");
            $statement->execute(array(date_time(), user_id()));
            $statement = db()->prepare("DELETE FROM `login_logs` WHERE `ip` = ? AND `status` = ?");
            $statement->execute(array(get_real_ip(), 'error'));

            // Lógica para Salvar/Limpar o Cookie
            if (!empty($_POST["remember"])) {
                setcookie("user_login", $_POST["username"], time() + (10 * 365 * 24 * 60 * 60), "/");
            } else {
                if (isset($_COOKIE["user_login"])) { 
                    setcookie("user_login", "", time() - 3600, "/"); 
                }
            }

            // ==================================================
            // CORREÇÃO CRÍTICA: Retorna o JSON que o login.js espera
            // ==================================================
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array(
                'msg' => trans('login_success'),
                'sessionUserId' => $session->data['id'],
                'count_user_store' => count_user_store(),
                'store_id' => $user->getSingleStoreId()
            ));
            exit();
        }
        insert_error_log();
        throw new \Exception(trans('error_invalid_username_password')); 
    } catch (\Exception $e) { 
        header('HTTP/1.1 422 Unprocessable Entity');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('errorMsg' => $e->getMessage()));
        exit();
    }
}

// 5. PROCESSAMENTO DO "ESQUECI A SENHA"
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->get['action_type']) && $request->get['action_type'] == "SEND_PASSWORD_RESET_CODE")
{
    try {
        if(DEMO) {
            throw new \Exception(trans('error_disable_in_demo')); 
        }
        if (!validateEmail($request->post['email'])) {
            throw new \Exception(trans('error_email')); 
        }

        $email = $request->post['email']; 

        $statement = db()->prepare("SELECT * FROM `users` LEFT JOIN `user_to_store` as `u2s` ON (`users`.`id` = `u2s`.`user_id`) WHERE `email` = ? AND `u2s`.`status` = ?");
        $statement->execute(array($email, 1));
        $the_user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$the_user) {
            throw new \Exception(trans('error_email_address_not_found')); 
        }

        $driver = get_preference('email_driver');
        if ($driver != 'smtp_server') {
            throw new \Exception(trans('error_smtp_server')); 
        }

        $subject        = trans('text_password_reset');
        $recipient_name = $the_user['username'];
        $from_name      = get_preference('email_from');
        $from_address   = get_preference('email_address');
        $smtp_host      = get_preference('smtp_host');
        $smtp_username  = get_preference('smtp_username');
        $smtp_password  = get_preference('smtp_password');
        $smtp_port      = get_preference('smtp_port');
        $ssl_tls        = get_preference('ssl_tls');
        
        $mail = new PHPMailer(true); 
        $mail->CharSet = 'UTF-8';
        $mail->SMTPDebug = 0; 
        $mail->isSMTP(); 
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true; 
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password;
        $mail->SMTPSecure = $ssl_tls; 
        $mail->Port = $smtp_port;
        $mail->Timeout = 900;
        
        $mail->setFrom($from_address, $from_name);
        $mail->AddReplyTo($from_address, $from_name);
        $mail->Subject = $subject;

        $template_name = 'password-reset';
        if (!file_exists(DIR_EMAIL_TEMPLATE . $template_name . '.php') || !is_file(DIR_EMAIL_TEMPLATE . $template_name . '.php')) {
            throw new \Exception(trans('error_email_template_not_found')); 
        }
        $uniqid_str = md5(uniqid(mt_rand()));
        $reset_pass_link = root_url() . '/password_reset.php?fp_code=' . $uniqid_str;
        ob_start();
        require('_inc/template/email/' . $template_name . '.php');
        $body = ob_get_contents();
        ob_end_clean();

        $mail->MsgHTML($body);
        $mail->AddAddress($email, $recipient_name);

        if (!$mail->Send()) {
            throw new PHPMailerException(trans('error_unable_to_send_an_email') . ' Erro: ' . $mail->ErrorInfo); 
        }

        $statement = db()->prepare("UPDATE `users` SET `pass_reset_code` = ?, `reset_code_time` = ? WHERE `id` = ?");
        $statement->execute(array($uniqid_str, date('Y-m-d H:i:s'), $the_user['id']));

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('msg' => trans('success_reset_code_sent')));
        exit();

    } catch (\Exception $e) { 

        header('HTTP/1.1 422 Unprocessable Entity');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('errorMsg' => $e->getMessage()));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $document->langTag($active_lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo store('name') ? store('name') . ' | ' : ''; ?><?php echo trans('title_log_in'); ?></title>
    
    <?php if ($store->get('favicon')): ?>
        <link rel="shortcut icon" href="assets/itsolution24/img/logo-favicons/<?php echo $store->get('favicon'); ?>">
    <?php else: ?>
        <link rel="shortcut icon" href="assets/itsolution24/img/logo-favicons/nofavicon.png">
    <?php endif; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link type="text/css" href="assets/toastr/toastr.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/itsolution24/css/login_custom.css"> 
    
    <script type="text/javascript">
        var isDemo = <?php echo DEMO ? 'true' : 'false'; ?>;
        var baseUrl = "<?php echo root_url(); ?>";
        var adminDir = "<?php echo ADMINDIRNAME; ?>";
        var refUrl = "<?php echo isset($request->get['redirect_to']) ? $request->get['redirect_to'] : ''?>";
    </script>
</head>
<body class="bg-light">

    <div class="container-fluid">
        <div class="row min-vh-100-sm">
            
            <div class="col-lg-6 d-none d-lg-flex flex-column justify-content-center align-items-center bg-brand min-vh-100-lg p-5 position-relative col-lg-6-animated">
                
                <div class="dots-container">
                    </div>

                <div id="login-svg-animation-container" class="position-relative z-1 text-center">
    <?php include '_inc/template/login_svg_animation.php'; ?>
                </div>
            </div>

            <div class="col-lg-6 d-flex justify-content-center align-items-center py-5">
                
                <div class="col-12" style="max-width: 460px;">
                    <div class="card login-card p-4 p-sm-5">
                        
                        <div class="text-center mb-4">
                            <img src="assets/itsolution24/img/logo/logo.avif" alt="Logo PDV Loja" style="max-height: 130px;">
                        </div>

                        
                        <form id="login-form" action="login.php?action_type=LOGIN" method="POST" class="mt-3">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label fw-medium">E-mail</label>
                                <div class="form-icon-wrapper">
                                    <i class="bi bi-envelope form-icon"></i>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           placeholder="Digite seu E-mail" required 
                                           value="<?php echo isset($_COOKIE['user_login']) ? htmlspecialchars($_COOKIE['user_login']) : ''; ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-label-wrapper">
                                    <label for="password" class="form-label fw-medium mb-0">Senha</label>
                                    <a href="#forgotPasswordModal" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal" class="link-brand small">Esqueceu sua Senha?</a>
                                </div>
                                <div class="form-icon-wrapper">
                                    <i class="bi bi-lock form-icon"></i>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Digite Sua Senha" required>
                                    <i class="bi bi-eye-slash-fill toggle-password" id="togglePassword"></i>
                                </div>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember" <?php echo isset($_COOKIE['user_login']) ? 'checked' : ''; ?>>
                                <label class="form-check-label text-muted" for="remember">
                                    Lembrar
                                </label>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" id="login-btn" class="btn btn-brand btn-lg" data-loading-text="<?php echo trans('text_logging_in'); ?>">
                                    Entrar
                                </button>
                            </div>
                            
                            <div class="text-center my-3">
                                <span class="text-muted small">Não tem uma conta?</span>
                            </div>
                            <div class="d-grid">
                                <a href="cadastro.php" class="btn btn-warning btn-lg fw-bold shadow-sm">
                                    Criar nova conta
                                </a>
                            </div>
                            
                            <?php if(DEMO) : ?>
                            <hr class="my-4">
                            <div class="text-center mb-3"><span class="text-muted small">Acesso rápido demonstração</span></div>
                            <div id="credentials" class="d-grid gap-2 d-sm-flex justify-content-center">
                                <?php foreach (get_users() as $the_user) : ?>
                                    <?php if (in_array($the_user['email'], array('admin@itsolution24.com', 'cashier@itsolution24.com'))) : ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm username" data-username="<?php echo $the_user['email'];?>" data-password="<?php echo $the_user['raw_password'];?>">
                                            Login as <?php echo ucfirst(str_replace('@itsolution24.com', '', $the_user['email']));?>
                                        </button>
                                    <?php endif;?>
                                <?php endforeach;?>
                            </div>
                            <?php endif;?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!DEMO) : ?>
    <div id="forgotPasswordModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-3 shadow">
                <form id="forgot-password-form" action="login.php?action_type=SEND_PASSWORD_RESET_CODE" method="post">
                    <div class="modal-header border-bottom-0">
                        <h4 class="modal-title fw-bold">Recuperar Sua Senha</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-0">
                        <p class="text-muted">Digite seu e-mail. Enviaremos um link. Basta seguir os passos.</p>
                        <div class="mb-3">
                            <label for="fp_email" class="form-label">E-mail</label> <input id="fp_email" type="email" name="email" placeholder="Digite seu E-mail" autocomplete="off" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer flex-column align-items-stretch w-100 gap-2 pb-3 border-top-0">
                        <button id="reset-btn" name="reset-btn" class="btn btn-brand" type="submit" data-loading-text="<?php echo trans('text_sending'); ?>">
                            Enviar
                        </button>
                        <button data-bs-dismiss="modal" class="btn btn-secondary" type="button">Fechar</button>
                    </div>
                </form> 
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="assets/jquery/jquery.min.js" type="text/javascript"></script>
    <script src="assets/toastr/toastr.min.js" type="text/javascript"></script>
    <script src="assets/itsolution24/js/common.js"></script>
    <script src="assets/itsolution24/js/login.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        $("#togglePassword").click(function() {
            // Alterna o ícone
            $(this).toggleClass("bi-eye-fill bi-eye-slash-fill");

            // Encontra o input de senha
            var input = $("#password");

            // Alterna o tipo do input
            if (input.attr("type") === "password") {
                input.attr("type", "text");
            } else {
                input.attr("type", "password");
            }
        });
    });
    </script>
</body>
</html>