<?php
/*
| ----------------------------------------------------------------------------
| PRODUCT NAME:    Modern POS - Point of Sale with Stock Management System
| ... (seu cabeçalho) ...
| ADAPTADO POR GEMINI COM NOVO DESIGN BOOTSTRAP 5
| ----------------------------------------------------------------------------
*/

ob_start();
session_start();
include ("_init.php");

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

// Redirect, If User Not Logged In
if (!isset($session->data['username'])) {
    if (!$user->isLogged()) {
        redirect(root_url().'index.php?redirect_to=' . url());
    }
    
    // CORREÇÃO: Armazena também o ID e a IMAGEM do usuário
    $session->data['user_id'] = user('id');
    $session->data['user_image'] = user('user_image');
    $session->data['email'] = user('email');
    $session->data['username'] = user('username');
    $session->data['ref_url'] = isset($session->data['ref_url']) ? $session->data['ref_url'] : '';
    
    // Desloga o usuário para "travar" a sessão
    $user->logout();
}

$error = '';
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->post['password'])) {
    try {

        if (!$request->post['password']) {
            throw new \Exception(trans('error_invalid_password'));
        }

        if (!$session->data['username']) {
            throw new \Exception(trans('error_invalid_username'));
        }

        $email = $session->data['email']; 
        $password = $request->post['password'];

        if ($user->login($email, $password)) {
            $url = $session->data['ref_url'] ? $session->data['ref_url'] : root_url().'admin/dashboard.php';
            redirect($url); 
        } 

        // Mensagem de erro específica
        $error = trans('error_invalid_password'); 

    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $document->langTag($active_lang);?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo trans('text_lockscreen');?><?php echo ' | '.store('name') ? store('name') : ''; ?></title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    <?php if ($store->get('favicon')): ?>
        <link rel="shortcut icon" href="assets/itsolution24/img/logo-favicons/<?php echo $store->get('favicon'); ?>">
    <?php else: ?>
        <link rel="shortcut icon" href="assets/itsolution24/img/logo-favicons/nofavicon.png">
    <?php endif; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/itsolution24/css/login_custom.css"> 
    
    <script type="text/javascript">
        var baseUrl = "<?php echo root_url(); ?>";
        var adminDir = "<?php echo ADMINDIRNAME; ?>";
        var refUrl = "<?php echo isset($session->data['ref_url']) ? $session->data['ref_url'] : ''?>";
    </script>
</head>
<body class="lockscreen-body">

    <div class="dots-container lockscreen-dots" style="z-index: -1;">
        <div class="dot" style="left: 23.6793%; top: 42.4053%; animation-delay: 2.77652s; animation-duration: 2.69295s;"></div>
        <div class="dot" style="left: 92.9901%; top: 21.8401%; animation-delay: 1.36437s; animation-duration: 2.46269s;"></div>
        <div class="dot" style="left: 64.619%; top: 96.2055%; animation-delay: 2.22649s; animation-duration: 2.25787s;"></div>
        <div class="dot" style="left: 81.4625%; top: 13.9353%; animation-delay: 0.970917s; animation-duration: 2.4286s;"></div>
        <div class="dot" style="left: 7.85054%; top: 68.8357%; animation-delay: 1.84759s; animation-duration: 2.53761s;"></div>
        <div class="dot" style="left: 89.5369%; top: 99.9492%; animation-delay: 0.429202s; animation-duration: 3.53453s;"></div>
        <div class="dot" style="left: 23.5046%; top: 14.6719%; animation-delay: 0.774562s; animation-duration: 3.82956s;"></div>
        <div class="dot" style="left: 18.4097%; top: 32.3832%; animation-delay: 1.19985s; animation-duration: 2.70498s;"></div>
        <div class="dot" style="left: 61.3773%; top: 28.6156%; animation-delay: 0.300755s; animation-duration: 2.55061s;"></div>
        <div class="dot" style="left: 89.9316%; top: 44.0974%; animation-delay: 0.779933s; animation-duration: 2.54211s;"></div>
        <div class="dot" style="left: 99.7029%; top: 95.8718%; animation-delay: 2.44932s; animation-duration: 3.73897s;"></div>
        <div class="dot" style="left: 70.117%; top: 20.8783%; animation-delay: 0.369026s; animation-duration: 2.57448s;"></div>
        <div class="dot" style="left: 12.5132%; top: 56.6815%; animation-delay: 1.00361s; animation-duration: 2.45502s;"></div>
        <div class="dot" style="left: 19.8971%; top: 72.8128%; animation-delay: 0.973751s; animation-duration: 3.10265s;"></div>
        <div class="dot" style="left: 74.3535%; top: 2.19766%; animation-delay: 1.59715s; animation-duration: 2.68791s;"></div>
    </div>

    <div class="container">
        <div class="row min-vh-100-sm justify-content-center align-items-center py-5">
            <div class="col-12 col-md-8 col-lg-5 col-xl-4">
                
                <div class="text-center mb-4">
                    <img src="assets/itsolution24/img/logo/logo-lock.png" alt="Logo PDV Loja" id="lockscreen-logo" style="max-height: 160px;">
                </div>

                <div class="card login-card p-4 p-sm-5 text-center">
                    
                    <div class="lockscreen-avatar mb-3 mx-auto">
                        <?php 
                        $user_image = $session->data['user_image'] ?? null;
                        $image_url = ''; 
                        if ($user_image && defined('FILEMANAGERPATH') && is_file(FILEMANAGERPATH . $user_image) && file_exists(FILEMANAGERPATH . $user_image)) {
                            $image_url = FILEMANAGERURL . $user_image; 
                        } else if ($user_image && is_file(DIR_STORAGE . 'users/' . $user_image) && file_exists(DIR_STORAGE . 'users/' . $user_image)) {
                            $image_url = root_url().'storage/users/' . $user_image;
                        }

                        if ($image_url) : 
                        ?>
                            <img src="<?php echo $image_url; ?>">
                        <?php else : ?>
                            <i class="bi bi-person-fill"></i>
                        <?php endif; ?>
                    </div>
                    
                    <h3 class="h4 fw-bold mb-1">Bem-vindo de volta, <?php echo $session->data['username'];?>!</h3>
                    <p class="text-muted">Por segurança, digite sua senha para continuar.</p>

                    <?php if ($error):?>
                        <div class="alert alert-danger mt-3" role="alert">
                            <?php echo $error;?>
                        </div>
                    <?php endif;?>

                    <form action="" method="post" autocomplete="off" class="mt-4">
                        <div class="mb-3">
                            <label for="password" class="form-label visually-hidden">Senha</label>
                            <div class="form-icon-wrapper">
                                <i class="bi bi-lock form-icon"></i>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Senha" required autofocus>
                                <i class="bi bi-eye-slash-fill toggle-password" id="togglePassword"></i>
                            </div>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-brand btn-lg">Continuar Sessão</button>
                        </div>
                    </form>

                    <div class="text-center">
                        <a href="index.php" class="link-brand small">Sair e entrar com outro usuário</a>
                    </div>
                </div>

                <div class="lockscreen-footer text-center mt-4">
                    © <?php echo date('Y'); ?> PDV Loja, v<?php echo settings('version');?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/jquery/jquery.min.js" type="text/javascript"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        $("#togglePassword").click(function() {
            $(this).toggleClass("bi-eye-fill bi-eye-slash-fill");
            var input = $("#password");
            if (input.attr("type") === "password") {
                input.attr("type", "text");
            } else {
                input.attr("type", "password");
            }
        });
    });
    </script>

<noscript>You need to have javascript enabled in order to use <strong><?php echo store('name');?></strong>.</noscript>
</body>
</html>