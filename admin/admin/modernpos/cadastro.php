<?php
// 1. INICIALIZAÇÃO BÁSICA
session_start();
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
use PHPMailer\PHPMailer\Exception as PHPMailerException; // Renomeia para evitar conflito

$document->setTitle(trans('title_register'));

// 2. VERIFICA SE JÁ ESTÁ LOGADO
if ($user->isLogged()) {
    redirect(ADMINDIRNAME . '/dashboard.php');
}

// 3. FUNÇÃO DE LOG DE ERRO (Original)
if (!function_exists('insert_error_log')) {
    function insert_error_log()
    {
        try {
            $statement = db()->prepare("INSERT INTO `login_logs` SET `ip` = ?, `status` = ?");
            $statement->execute(array(get_real_ip(), 'error'));
        } catch (\Exception $e) { /* CORREÇÃO: Usa \Exception global */
        }
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
            throw new \Exception($language->get('error_login_attempts_exceeded') . '. Try after ' . UNLOCK_ACCOUNT_AFTER . ' minute(s)'); // CORREÇÃO: \Exception
        }
        if (!isset($request->post['username']) || !validateString($request->post['username'])) {
            insert_error_log();
            throw new \Exception(trans('error_username')); // CORREÇÃO: \Exception
        }
        if (empty($request->post['password'])) {
            insert_error_log();
            throw new \Exception(trans('error_password')); // CORREÇÃO: \Exception
        }
        $username = $request->post['username'];
        $password = $request->post['password'];
        if ($user->login($username, $password)) {
            $statement = db()->prepare("INSERT INTO `login_logs` SET `user_id` = ?, `username` = ?, `ip` = ?");
            $statement->execute(array(user_id(), $username, get_real_ip()));
            $statement = db()->prepare("UPDATE `users` SET `last_login` = ? WHERE `id` = ?");
            $statement->execute(array(date_time(), user_id()));
            $statement = db()->prepare("DELETE FROM `login_logs` WHERE `ip` = ? AND `status` = ?");
            $statement->execute(array(get_real_ip(), 'error'));
            if (!empty($_POST["remember"])) {
                setcookie("user_login", $_POST["username"], time() + (10 * 365 * 24 * 60 * 60), "/");
            } else {
                if (isset($_COOKIE["user_login"])) {
                    setcookie("user_login", "", time() - 3600, "/");
                }
            }
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('msg' => trans('login_success'), 'redirect_to' => ADMINDIRNAME . '/dashboard.php'));
            exit();
        }
        insert_error_log();
        throw new \Exception(trans('error_invalid_username_password')); // CORREÇÃO: \Exception
    } catch (\Exception $e) { // CORREÇÃO: Captura \Exception
        header('HTTP/1.1 422 Unprocessable Entity');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('errorMsg' => $e->getMessage()));
        exit();
    }
}

// 5. PROCESSAMENTO DO "ESQUECI A SENHA" (Lógica Corrigida)
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->get['action_type']) && $request->get['action_type'] == "SEND_PASSWORD_RESET_CODE") {
    try {
        // As 3 linhas "use" foram MOVIDAS para o topo do arquivo.

        if (DEMO) {
            throw new \Exception(trans('error_disable_in_demo')); // CORREÇÃO: \Exception
        }
        if (!validateEmail($request->post['email'])) {
            throw new \Exception(trans('error_email')); // CORREÇÃO: \Exception
        }

        $email = $request->post['email'];

        $statement = db()->prepare("SELECT * FROM `users` LEFT JOIN `user_to_store` as `u2s` ON (`users`.`id` = `u2s`.`user_id`) WHERE `email` = ? AND `u2s`.`status` = ?");
        $statement->execute(array($email, 1));
        $the_user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$the_user) {
            throw new \Exception(trans('error_email_address_not_found')); // CORREÇÃO: \Exception
        }

        $driver = get_preference('email_driver');
        if ($driver != 'smtp_server') {
            throw new \Exception(trans('error_smtp_server')); // CORREÇÃO: \Exception
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

        // Start Email

        // Os "require_once" foram MOVIDOS para o topo do arquivo.

        $mail = new PHPMailer(true); // Habilita exceções
        $mail->CharSet = 'UTF-8';
        $mail->SMTPDebug = 0; // Mude para 2 para depurar
        $mail->isSMTP(); // Habilita SMTP
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
            throw new \Exception(trans('error_email_template_not_found')); // CORREÇÃO: \Exception
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
            throw new PHPMailerException(trans('error_unable_to_send_an_email') . ' Erro: ' . $mail->ErrorInfo); // CORREÇÃO: PHPMailerException
        }

        // End Email

        // Update Users Password Reset Code
        $statement = db()->prepare("UPDATE `users` SET `pass_reset_code` = ?, `reset_code_time` = ? WHERE `id` = ?");
        $statement->execute(array($uniqid_str, date('Y-m-d H:i:s'), $the_user['id']));

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('msg' => trans('success_reset_code_sent')));
        exit();
    } catch (\Exception $e) { // CORREÇÃO: Captura \Exception

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
    <title><?php echo store('name') ? store('name') . ' | ' : ''; ?><?php echo trans('title_register'); ?></title>

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
        var refUrl = "<?php echo isset($request->get['redirect_to']) ? $request->get['redirect_to'] : '' ?>";
    </script>
</head>

<body class="bg-light">

    <div class="container-fluid">
        <div class="row min-vh-100-sm">

            <div class="col-lg-6 d-none d-lg-flex flex-column justify-content-center align-items-center bg-brand min-vh-100-lg p-5 position-relative col-lg-6-animated">

                <div class="dots-container">
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

                <div class="position-relative z-1 text-center">
                    <?php include '_inc/template/login_svg_animation.php'; ?>
                </div>
            </div>

            <div class="col-lg-6 d-flex justify-content-center align-items-center py-5">
                <div class="col-12" style="max-width: 480px;">

                    <div class="card login-card p-0">

                        <div class="step-row" id="step-indicator">
                            <div id="progress"></div>
                            <div class="step-col active" data-step="1">Etapa 1</div>
                            <div class="step-col" data-step="2">Etapa 2</div>
                            <div class="step-col" data-step="3">Etapa 3</div>
                        </div>

                        <div class="p-4 p-sm-5">

                            <div id="register-error" class="alert alert-danger" role="alert" style="display: none;"></div>

                            <form id="registerForm" action="process_register.php" method="POST">

                                <div id="step-1" class="form-step">
                                    <div class="text-center mb-4">
                                        <h3 class="h4 fw-bold mb-1">Vamos começar!</h3>
                                        <p class="text-muted">Primeiro, precisamos saber quem é você.</p>
                                    </div>
                                    <div class="mb-3">
                                        <label for="nome" class="form-label fw-medium">Nome Completo</label>
                                        <div class="form-icon-wrapper">
                                            <i class="bi bi-person form-icon"></i>
                                            <input type="text" class="form-control" id="nome" name="nome" placeholder="Digite seu nome completo" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label fw-medium">Seu melhor E-mail</label>
                                        <div class="form-icon-wrapper">
                                            <i class="bi bi-envelope form-icon"></i>
                                            <input type="email" class="form-control" id="email" name="email" placeholder="email@exemplo.com" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="whatsapp" class="form-label fw-medium">WhatsApp</label>
                                        <div class="form-icon-wrapper">
                                            <i class="bi bi-whatsapp form-icon"></i>
                                            <input type="tel" class="form-control" id="whatsapp" name="whatsapp" placeholder="(27) 99999-8888" required>
                                        </div>
                                    </div>
                                    <div class="d-grid mt-4">
                                        <button type="button" class="btn btn-brand btn-lg" id="btn-step-1">Continuar</button>
                                    </div>
                                </div>

                                <div id="step-2" class="form-step" style="display: none;">
                                    <div class="text-center mb-4">
                                        <h3 class="h4 fw-bold mb-1">Verifique seu E-mail</h3>
                                        <p class="text-muted">Enviamos um código de 6 dígitos para <strong id="email-confirm-text">email@exemplo.com</strong>.</p>
                                    </div>
                                    <div class="mb-3">
                                        <label for="code" class="form-label fw-medium">Código de Verificação</label>
                                        <input type="text" class="form-control text-center" id="code" name="code" placeholder="------" required style="font-size: 1.2rem; padding-left: 1rem; letter-spacing: 0.5rem;">
                                        <div class="text-center mt-2">
                                            <a href="#" id="resend-code" class="link-brand small">Não recebeu? Reenviar código</a>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="password" class="form-label fw-medium">Crie sua Senha</label>
                                        <div class="form-icon-wrapper">
                                            <i class="bi bi-lock form-icon"></i>
                                            <input type="password" class="form-control" id="password" name="password" placeholder="Mínimo 6 caracteres" required>
                                            <i class="bi bi-eye-slash-fill toggle-password"></i>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="password_confirm" class="form-label fw-medium">Confirme sua Senha</label>
                                        <div class="form-icon-wrapper">
                                            <i class="bi bi-lock form-icon"></i>
                                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Repita a senha" required>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-3 justify-content-center justify-content-sm-between mt-4">
                                        <button type="button" class="btn btn-outline-secondary btn-lg prev-step">Voltar</button>
                                        <button type="button" class="btn btn-brand btn-lg next-step">Continuar</button>
                                    </div>
                                </div>

                                <div id="step-3" class="form-step" style="display: none;">
                                    <div class="text-center mb-4">
                                        <h3 class="h4 fw-bold mb-1">Sobre seu Negócio</h3>
                                        <p class="text-muted">Último passo! Preencha os dados da sua conta.</p>
                                    </div>

                                    <div class="mb-3">
                                        <label for="nome_loja" class="form-label fw-medium">Nome da Loja (Nome Fantasia)</label>
                                        <input type="text" class="form-control" id="nome_loja" name="nome_loja" placeholder="Ex: Jheneffer Modas" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="segmento" class="form-label fw-medium">Qual o seu segmento?</label>
                                        <select class="form-select" id="segmento" name="segmento" required>
                                            <option value="" selected disabled>Selecione uma opção</option>
                                            <option value="roupas_calcados">Loja de Roupas / Calçados</option>
                                            <option value="restaurante">Bar / Restaurante / Lanchonete</option>
                                            <option value="mercado">Mercadinho / Padaria</option>
                                            <option value="servicos">Serviços (Oficina, Salão, etc)</option>
                                            <option value="outros">Outros</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">

                                        <div class="mb-3">
                                            <label class="form-label fw-medium">Tipo de Conta</label>

                                            <div class="btn-group w-100" role="group" aria-label="Tipo de Conta">

                                                <input type="radio" class="btn-check" name="tipo_pessoa" id="tipo_pf" value="PF" autocomplete="off" checked>
                                                <label class="btn btn-outline-brand w-50" for="tipo_pf">
                                                    <i class="bi bi-person"></i> Pessoa Física
                                                </label>

                                                <input type="radio" class="btn-check" name="tipo_pessoa" id="tipo_pj" value="PJ" autocomplete="off">
                                                <label class="btn btn-outline-brand w-50" for="tipo_pj">
                                                    <i class="bi bi-building"></i> Pessoa Jurídica
                                                </label>

                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3" id="campo_documento_wrapper">
                                        <label for="documento" class="form-label fw-medium" id="label_documento">CPF</label>

                                        <input type="tel" class="form-control" id="documento" name="documento"
                                            placeholder="Digite seu CPF" required
                                            onkeyup="mascaraDocumento(this)" maxlength="14">
                                    </div>

                                    <div class="mb-3" id="campo_razao_social" style="display: none;">
                                        <label for="razao_social" class="form-label fw-medium">Razão Social (Opcional)</label>
                                        <input type="text" class="form-control" id="razao_social" name="razao_social" placeholder="Ex: JHENIFFER MODAS LTDA">
                                    </div>

                                    <div class="form-check mb-4 mt-4">
                                        <input class="form-check-input" type="checkbox" value="1" id="terms" name="terms" required>
                                        <label class="form-check-label text-muted" for="terms">
                                            Eu li e aceito os <a href="/termos.php" target="_blank" class="link-brand">Termos de Uso</a> e a <a href="/privacidade.php" target="_blank" class="link-brand">Política de Privacidade</a>.
                                        </label>
                                    </div>

                                    <<div class="d-flex gap-3 justify-content-center justify-content-sm-between mt-4">
                                        <button type="button" class="btn btn-outline-secondary btn-lg prev-step">Voltar</button>
                                        <button type="submit" class="btn btn-brand btn-lg" id="btn-submit">Finalizar Cadastro!</button>
                                </div>
                            </form>

                            <hr class="my-4">
                            <div class="text-center">
                                <span class="text-muted small">Já tem uma conta? <a href="login.php" class="link-brand fw-medium">Faça Login</a></span>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalSucessoCadastro" tabindex="-1" aria-labelledby="modalSucessoLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                <div class="modal-body p-4 p-sm-5 text-center">

                    <div class="mb-3">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                    </div>

                    <h4 class="modal-title fw-bold mb-2" id="modalSucessoLabel">Conta Criada com Sucesso!</h4>

                    <p class="text-muted">Seu cadastro foi realizado. Você está pronto para configurar sua loja. Clique no botão abaixo para começar.</p>

                    <a href="store_select.php" class="btn btn-brand btn-lg w-100 mt-3">
                        Acessar meu Painel
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php if (!DEMO) : ?>
        <div id="forgotPasswordModal" class="modal fade" tabindex="-1" role="dialog">
        </div>
    <?php endif; ?>
    <script src="assets/jquery/jquery.min.js" type="text/javascript"></script>
    <script src="assets/jquery/jquery.min.js" type="text/javascript"></script>
    <script src="assets/toastr/toastr.min.js" type="text/javascript"></script>
    <script src="assets/itsolution24/js/common.js"></script>
    <script src="assets/itsolution24/js/login.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {

                    let currentStep = 1;
                    const totalSteps = 3;

                    // Função para mostrar um passo e atualizar a barra
                    function showStep(step) {
                        $(".form-step").hide();
                        $("#step-" + step).show();

                        let progressWidth = "33.33%";
                        $(".step-col").removeClass("active");

                        if (step === 1) {
                            progressWidth = "33.33%";
                            $(".step-col[data-step='1']").addClass("active");
                        } else if (step === 2) {
                            progressWidth = "66.66%";
                            $(".step-col[data-step='1']").addClass("active");
                            $(".step-col[data-step='2']").addClass("active");
                        } else if (step === 3) {
                            progressWidth = "100%";
                            $(".step-col[data-step='1']").addClass("active");
                            $(".step-col[data-step='2']").addClass("active");
                            $(".step-col[data-step='3']").addClass("active");
                        }

                        $("#progress").css("width", progressWidth);
                        currentStep = step;
                    }

                    // Função de validação simples
                    function validateStep(step) {
                        let isValid = true;
                        $("#step-" + step + " [required]").each(function() {
                            $(this).removeClass("is-invalid");

                            if ($(this).val() === "" || ($(this).is(':checkbox') && !$(this).is(':checked'))) {
                                isValid = false;
                                $(this).addClass("is-invalid");
                            }
                        });

                        if (!isValid) {
                            showError("Por favor, preencha todos os campos obrigatórios.");
                        } else {
                            hideError();
                        }
                        return isValid;
                    }

                    function showError(message) {
                        $("#register-error").text(message).show();
                    }

                    function hideError() {
                        $("#register-error").hide();
                    }

                    // --- ETAPA 1: DADOS PESSOAIS ---
                    $("#btn-step-1").click(function() {
                        if (!validateStep(1)) return;

                        const email = $("#email").val();
                        $("#email-confirm-text").text(email);

                        $(this).prop("disabled", true).text("Enviando código...");

                        $.ajax({
                            url: 'send_code.php',
                            type: 'POST',
                            data: {
                                email: email
                            },
                            dataType: 'json',
                            success: function(response) {
                                toastr.success(response.message, 'Sucesso!');
                                showStep(2);
                            },
                            error: function(xhr) {
                                var errorMsg = xhr.responseJSON ? xhr.responseJSON.error : 'Não foi possível enviar o código.';
                                showError(errorMsg);
                            },
                            complete: function() {
                                $("#btn-step-1").prop("disabled", false).text("Continuar");
                            }
                        });
                    });

                    // Botão de Reenviar Código
                    $("#resend-code").click(function(e) {
                        e.preventDefault();
                        const email = $("#email").val();
                        $(this).text("Enviando...");

                        $.ajax({
                            url: 'send_code.php',
                            type: 'POST',
                            data: {
                                email: email
                            },
                            dataType: 'json',
                            success: function(response) {
                                toastr.success(response.message, 'Código Reenviado!');
                            },
                            error: function() {
                                toastr.error('Erro ao reenviar o código.');
                            },
                            complete: function() {
                                $("#resend-code").text("Não recebeu? Reenviar código");
                            }
                        });
                    });


                    <
                    script src = "assets/jquery/jquery.min.js"
                    type = "text/javascript" >
    </script>
    <script src="assets/toastr/toastr.min.js" type="text/javascript"></script>
    <script src="assets/itsolution24/js/common.js"></script>
    <script src="assets/itsolution24/js/login.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {

            let currentStep = 1;
            const totalSteps = 3;

            // Função para mostrar um passo e atualizar a barra
            function showStep(step) {
                $(".form-step").hide();
                $("#step-" + step).show();

                let progressWidth = "33.33%";
                $(".step-col").removeClass("active");

                if (step === 1) {
                    progressWidth = "33.33%";
                    $(".step-col[data-step='1']").addClass("active");
                } else if (step === 2) {
                    progressWidth = "66.66%";
                    $(".step-col[data-step='1']").addClass("active");
                    $(".step-col[data-step='2']").addClass("active");
                } else if (step === 3) {
                    progressWidth = "100%";
                    $(".step-col[data-step='1']").addClass("active");
                    $(".step-col[data-step='2']").addClass("active");
                    $(".step-col[data-step='3']").addClass("active");
                }

                $("#progress").css("width", progressWidth);
                currentStep = step;
            }

            // Função de validação simples
            function validateStep(step) {
                let isValid = true;
                $("#step-" + step + " [required]").each(function() {
                    $(this).removeClass("is-invalid");

                    if ($(this).val() === "" || ($(this).is(':checkbox') && !$(this).is(':checked'))) {
                        isValid = false;
                        $(this).addClass("is-invalid");
                    }
                });

                if (!isValid) {
                    showError("Por favor, preencha todos os campos obrigatórios.");
                } else {
                    hideError();
                }
                return isValid;
            }

            function showError(message) {
                $("#register-error").text(message).show();
            }

            function hideError() {
                $("#register-error").hide();
            }

            // --- ETAPA 1: DADOS PESSOAIS ---
            $("#btn-step-1").click(function() {
                if (!validateStep(1)) return;

                const email = $("#email").val();
                $("#email-confirm-text").text(email);

                $(this).prop("disabled", true).text("Enviando código...");

                $.ajax({
                    url: 'send_code.php',
                    type: 'POST',
                    data: {
                        email: email
                    },
                    dataType: 'json',
                    success: function(response) {
                        toastr.success(response.message, 'Sucesso!');
                        showStep(2);
                    },
                    error: function(xhr) {
                        var errorMsg = xhr.responseJSON ? xhr.responseJSON.error : 'Não foi possível enviar o código.';
                        showError(errorMsg);
                    },
                    complete: function() {
                        $("#btn-step-1").prop("disabled", false).text("Continuar");
                    }
                });
            });

            // Botão de Reenviar Código
            $("#resend-code").click(function(e) {
                e.preventDefault();
                const email = $("#email").val();
                $(this).text("Enviando...");

                $.ajax({
                    url: 'send_code.php',
                    type: 'POST',
                    data: {
                        email: email
                    },
                    dataType: 'json',
                    success: function(response) {
                        toastr.success(response.message, 'Código Reenviado!');
                    },
                    error: function() {
                        toastr.error('Erro ao reenviar o código.');
                    },
                    complete: function() {
                        $("#resend-code").text("Não recebeu? Reenviar código");
                    }
                });
            });


            // ==================================================
            // CORREÇÃO: ETAPA 2 (Verificação do Código)
            // ==================================================
            $(".next-step").click(function() {
                // Pega o botão que foi clicado
                var $thisButton = $(this);

                // 1. Valida se os campos estão preenchidos
                if (!validateStep(currentStep)) return;

                // 2. Validação específica da Etapa 2
                if (currentStep === 2) {
                    const password = $("#password").val();
                    const confirmPassword = $("#password_confirm").val();
                    const verificationCode = $("#code").val();

                    // 2a. Valida senhas
                    if (password.length < 6) {
                        showError("Sua senha deve ter no mínimo 6 caracteres.");
                        $("#password").addClass("is-invalid");
                        return;
                    }
                    if (password !== confirmPassword) {
                        showError("As senhas não coincidem.");
                        $("#password_confirm").addClass("is-invalid");
                        return;
                    }

                    hideError();
                    $thisButton.prop("disabled", true).text("Verificando..."); // Desabilita o botão

                    // 2b. NOVA VERIFICAÇÃO AJAX do código
                    $.ajax({
                        url: 'verify_code.php', // O novo script que criamos
                        type: 'POST',
                        data: {
                            code: verificationCode
                        },
                        dataType: 'json',
                        success: function(response) {
                            // Código está CORRETO!
                            toastr.success('Código verificado!', 'Sucesso!');
                            showStep(currentStep + 1); // Avança para a Etapa 3
                        },
                        error: function(xhr) {
                            // Código está ERRADO
                            var errorMsg = xhr.responseJSON ? xhr.responseJSON.error : 'Código inválido.';
                            showError(errorMsg);
                            $("#code").addClass("is-invalid"); // Marca o campo de código como inválido
                        },
                        complete: function() {
                            $thisButton.prop("disabled", false).text("Continuar"); // Reabilita o botão
                        }
                    });

                } else {
                    // Se não for a etapa 2, apenas avança (não deve acontecer, mas por segurança)
                    hideError();
                    showStep(currentStep + 1);
                }
            });

            // NOVA FUNÇÃO PARA MÁSCARA DE CPF/CNPJ
            // ==================================================
            function mascaraDocumento(campo) {
                // Remove tudo que não é dígito
                var valor = campo.value.replace(/\D/g, '');
                var tipoPessoa = $('input[name="tipo_pessoa"]:checked').val();

                if (tipoPessoa === 'PF') {
                    // Aplica máscara de CPF: 111.111.111-11
                    valor = valor.replace(/^(\d{3})(\d)/, '$1.$2');
                    valor = valor.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
                    valor = valor.replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
                } else {
                    // Aplica máscara de CNPJ: 11.222.333/0001-44
                    valor = valor.replace(/^(\d{2})(\d)/, '$1.$2');
                    valor = valor.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                    valor = valor.replace(/^(\d{2})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3/$4');
                    valor = valor.replace(/^(\d{2})\.(\d{3})\.(\d{3})\/(\d{4})(\d)/, '$1.$2.$3/$4-$5');
                }
                campo.value = valor;
            }


            // ==================================================
            // LÓGICA DA ETAPA 3 (PF/PJ) - ATUALIZADA
            // ==================================================
            $('input[name="tipo_pessoa"]').change(function() {
                var documentoInput = $('#documento');

                // Limpa o campo ao trocar
                documentoInput.val('');

                if (this.value === 'PF') {
                    // Modo Pessoa Física
                    $('#label_documento').text('CPF');
                    documentoInput.attr('placeholder', 'Digite seu CPF');
                    documentoInput.attr('maxlength', 14); // Limite do CPF (111.111.111-11)
                    $('#campo_razao_social').hide();
                    $('#razao_social').prop('required', false);
                } else if (this.value === 'PJ') {
                    // Modo Pessoa Jurídica
                    $('#label_documento').text('CNPJ');
                    documentoInput.attr('placeholder', 'Digite o CNPJ da empresa');
                    documentoInput.attr('maxlength', 18); // Limite do CNPJ (11.222.333/0001-44)
                    $('#campo_razao_social').show();
                }
            });

            // --- ETAPA 3: SUBMISSÃO FINAL ---
            $("#registerForm").submit(function(e) {
                e.preventDefault(); // Previne o envio padrão
                if (!validateStep(3)) {
                    return; // Se a validação falhar, para aqui
                }

                $("#btn-submit").prop("disabled", true).text("Criando conta...");

                var formData = $(this).serialize(); // Pega todos os dados do formulário

                // Envia para o script PHP de processamento
                $.ajax({
                    url: 'process_register.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',

                    success: function(response) {
                        // ==================================================
                        // MUDANÇA AQUI: SUCESSO!
                        // Em vez de redirecionar, abrimos o popup.
                        // ==================================================

                        // 1. Mostra uma notificação rápida (opcional, mas bom)
                        toastr.success(response.msg, 'Sucesso!');

                        // 2. Prepara o modal do Bootstrap
                        var myModal = new bootstrap.Modal(document.getElementById('modalSucessoCadastro'));

                        // 3. Abre o modal
                        myModal.show();

                        // (O botão "Acessar meu Painel" dentro do modal agora fará o redirecionamento)
                    },

                    error: function(xhr) {
                        // Deu errado
                        var errorMsg = xhr.responseJSON ? xhr.responseJSON.error : 'Ocorreu um erro desconhecido. Tente novamente.';
                        showError(errorMsg);
                        $("#btn-submit").prop("disabled", false).text("Finalizar Cadastro!");
                    }
                });
            });

            // --- Botões "Voltar" ---
            $(".prev-step").click(function() {
                hideError();
                showStep(currentStep - 1);
            });

            // --- Script de "Mostrar/Ocultar Senha" ---
            $(".toggle-password").click(function() {
                $(this).toggleClass("bi-eye-fill bi-eye-slash-fill");
                var input = $(this).siblings("input");

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