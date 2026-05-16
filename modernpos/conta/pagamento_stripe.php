<?php
/**
 * Checkout Transparente - Stripe Elements
 * 
 * Esta página renderiza o formulário de cartão embutido usando Stripe.js/Elements
 * para processar pagamentos sem redirecionar para a página externa do Stripe.
 * 
 * URL: /conta/pagamento_stripe.php?order_id=123
 * 
 * Pode receber dados via:
 * - GET: order_id (para buscar dados do banco)
 * - Session: stripe_checkout_data (dados do processo de checkout)
 */

session_start();

// Carrega o _init.php do ModernPOS
$initPath = __DIR__ . '/../_init.php';
if (!file_exists($initPath)) {
    die('Sistema não configurado corretamente.');
}
require_once $initPath;

// Verifica se o usuário está logado
if (!function_exists('user_id') || !user_id()) {
    header('Location: ' . root_url() . 'index.php');
    exit;
}

// Carrega PaymentGatewayConfig
$pgcPath = __DIR__ . '/_inc/PaymentGatewayConfig.php';
if (!file_exists($pgcPath)) {
    die('PaymentGatewayConfig não encontrado.');
}
require_once $pgcPath;

// Dados do checkout
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$clientSecret = isset($_GET['client_secret']) ? trim($_GET['client_secret']) : '';

$order = null;
$plan = null;
$error = null;
$publishableKey = '';

try {
    $pdo = db();
    
    // Resolve tenant do usuário
    $tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    if ($tenantId <= 0) {
        $userId = (int)user_id();
        $stmtUser = $pdo->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
        $stmtUser->execute([$userId]);
        $tenantId = (int)$stmtUser->fetchColumn();
    }
    
    if ($orderId <= 0) {
        throw new Exception('Pedido não informado.');
    }
    
    // Busca dados do pedido
    $stmtOrder = $pdo->prepare("
        SELECT o.*, p.name AS plan_name 
        FROM saas_orders o 
        LEFT JOIN plans p ON p.plan_id = o.plan_id 
        WHERE o.order_id = :order_id 
        LIMIT 1
    ");
    $stmtOrder->execute([':order_id' => $orderId]);
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Pedido não encontrado.');
    }
    
    // Verifica se o pedido pertence ao tenant do usuário
    if ((int)$order['tenant_id'] !== $tenantId) {
        throw new Exception('Acesso negado a este pedido.');
    }
    
    // Verifica status do pedido
    if ($order['status'] === 'paid') {
        header('Location: ' . root_url() . 'conta/planos?payment_confirmed=1&order_id=' . $orderId);
        exit;
    }
    
    // Busca tenant dono das configs de gateway
    $gatewayTenantId = 1;
    try {
        $stmtGT = $pdo->query("SELECT tenant_id FROM landing_pages ORDER BY is_default DESC, id ASC LIMIT 1");
        if ($stmtGT) {
            $tmp = (int)$stmtGT->fetchColumn();
            if ($tmp > 0) $gatewayTenantId = $tmp;
        }
    } catch (Throwable $e) {
        $gatewayTenantId = 1;
    }
    
    // Busca configuração Stripe
    $gw = PaymentGatewayConfig::get($pdo, $gatewayTenantId, 'stripe');
    
    if (!$gw || empty($gw['enabled']) || empty($gw['stripe_publishable_key'])) {
        throw new Exception('Stripe não configurado para checkout transparente.');
    }
    
    $publishableKey = (string)$gw['stripe_publishable_key'];
    $secretKey = (string)($gw['stripe_secret_key'] ?? '');
    $currency = (string)($gw['stripe_currency'] ?? 'BRL');
    
    // Se não temos client_secret, precisamos criar/recuperar o PaymentIntent
    if ($clientSecret === '' && !empty($order['transaction_id'])) {
        // Carrega Stripe SDK
        $stripeAutoload = realpath(__DIR__ . '/../../saas/Stripe/vendor/autoload.php');
        if ($stripeAutoload && file_exists($stripeAutoload)) {
            require_once $stripeAutoload;
            \Stripe\Stripe::setApiKey($secretKey);
            
            try {
                $paymentIntent = \Stripe\PaymentIntent::retrieve($order['transaction_id']);
                $clientSecret = $paymentIntent->client_secret;
            } catch (Throwable $ePi) {
                throw new Exception('Não foi possível recuperar os dados do pagamento: ' . $ePi->getMessage());
            }
        } else {
            throw new Exception('Stripe SDK não instalado.');
        }
    }
    
    if ($clientSecret === '') {
        throw new Exception('Dados de pagamento não disponíveis. Tente novamente.');
    }
    
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$displayAmount = $order ? (float)$order['amount'] : 0;
$planName = $order ? (string)($order['plan_name'] ?? $order['reference_no'] ?? 'Plano') : 'Plano';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pagamento - <?php echo htmlspecialchars($planName); ?></title>
    
    <base href="<?php echo rtrim(root_url(), '/'); ?>/AdminLTE-4.0.0-rc4/dist/">
    
    <!-- Fontes -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="./css/adminlte.css">
    
    <!-- Stripe.js -->
    <script src="https://js.stripe.com/v3/"></script>
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .checkout-container {
            max-width: 500px;
            width: 100%;
        }
        
        .checkout-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        
        .checkout-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
            color: #fff;
            padding: 24px;
            text-align: center;
        }
        
        .checkout-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 8px 0;
        }
        
        .checkout-header .amount {
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .checkout-header .amount small {
            font-size: 1rem;
            font-weight: 400;
            opacity: 0.8;
        }
        
        .checkout-body {
            padding: 24px;
        }
        
        .order-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        
        .order-info .label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6c757d;
            margin-bottom: 4px;
        }
        
        .order-info .value {
            font-weight: 600;
            color: #212529;
        }
        
        /* Stripe Elements styles */
        #payment-element {
            margin-bottom: 24px;
        }
        
        #payment-message {
            color: #df1b41;
            font-size: 14px;
            line-height: 1.5;
            padding: 12px;
            border-radius: 8px;
            background: #fef2f2;
            margin-bottom: 16px;
            display: none;
        }
        
        #payment-message.visible {
            display: block;
        }
        
        #submit-btn {
            width: 100%;
            padding: 14px 24px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        #submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .spinner-payment {
            display: none;
        }
        
        #submit-btn.loading .spinner-payment {
            display: inline-block;
        }
        
        #submit-btn.loading .btn-text {
            display: none;
        }
        
        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .secure-badge i {
            color: #198754;
        }
        
        .back-link {
            text-align: center;
            margin-top: 16px;
        }
        
        .back-link a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .back-link a:hover {
            color: #212529;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #d1fae5;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        
        .success-icon i {
            font-size: 40px;
            color: #059669;
        }
        
        #success-message {
            display: none;
            text-align: center;
        }
        
        #payment-form.hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="checkout-card">
            <?php if ($error): ?>
                <div class="checkout-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <div class="text-center">
                        <a href="<?php echo root_url(); ?>conta/planos" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Voltar para Planos
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="checkout-header">
                    <h1><?php echo htmlspecialchars($planName); ?></h1>
                    <div class="amount">
                        R$ <?php echo number_format($displayAmount, 2, ',', '.'); ?>
                    </div>
                    <div class="text-white-50 small mt-1">Pedido #<?php echo (int)$orderId; ?></div>
                </div>
                
                <div class="checkout-body">
                    <!-- Mensagem de sucesso (oculta inicialmente) -->
                    <div id="success-message">
                        <div class="success-icon">
                            <i class="bi bi-check-lg"></i>
                        </div>
                        <h4 class="mb-3">Pagamento Realizado!</h4>
                        <p class="text-muted mb-4">Seu pagamento foi processado com sucesso.</p>
                        <a href="<?php echo root_url(); ?>conta/pagamento.php?gateway=stripe&order_id=<?php echo (int)$orderId; ?>" 
                           class="btn btn-success btn-lg">
                            <i class="bi bi-check-circle me-2"></i>Ver Detalhes
                        </a>
                    </div>
                    
                    <!-- Formulário de pagamento -->
                    <form id="payment-form">
                        <div id="payment-message"></div>
                        
                        <div class="order-info">
                            <div class="row">
                                <div class="col-6">
                                    <div class="label">Plano</div>
                                    <div class="value"><?php echo htmlspecialchars($planName); ?></div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="label">Total</div>
                                    <div class="value text-primary">R$ <?php echo number_format($displayAmount, 2, ',', '.'); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Stripe Payment Element -->
                        <div id="payment-element"></div>
                        
                        <button id="submit-btn" type="submit" class="btn btn-primary btn-lg">
                            <span class="spinner-border spinner-border-sm me-2 spinner-payment" role="status"></span>
                            <span class="btn-text"><i class="bi bi-lock me-2"></i>Pagar R$ <?php echo number_format($displayAmount, 2, ',', '.'); ?></span>
                        </button>
                        
                        <div class="secure-badge">
                            <i class="bi bi-shield-check"></i>
                            <span>Pagamento seguro processado por Stripe</span>
                        </div>
                    </form>
                    
                    <div class="back-link">
                        <a href="<?php echo root_url(); ?>conta/planos">
                            <i class="bi bi-arrow-left me-1"></i>Cancelar e voltar
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!$error && $clientSecret && $publishableKey): ?>
    <script>
        // Inicializa Stripe
        const stripe = Stripe('<?php echo htmlspecialchars($publishableKey, ENT_QUOTES); ?>');
        
        // Opções de aparência do Stripe Elements
        const appearance = {
            theme: 'stripe',
            variables: {
                colorPrimary: '#667eea',
                colorBackground: '#ffffff',
                colorText: '#212529',
                colorDanger: '#df1b41',
                fontFamily: '"Source Sans 3", system-ui, sans-serif',
                spacingUnit: '4px',
                borderRadius: '8px',
            }
        };
        
        // Inicializa Elements
        const elements = stripe.elements({
            clientSecret: '<?php echo htmlspecialchars($clientSecret, ENT_QUOTES); ?>',
            appearance,
            locale: 'pt-BR'
        });
        
        // Cria o Payment Element
        const paymentElement = elements.create('payment', {
            layout: 'tabs'
        });
        paymentElement.mount('#payment-element');
        
        // Referências DOM
        const form = document.getElementById('payment-form');
        const submitBtn = document.getElementById('submit-btn');
        const messageDiv = document.getElementById('payment-message');
        const successDiv = document.getElementById('success-message');
        
        function buildPlansRedirectUrl(orderId, paymentIntentId) {
            const base = '<?php echo root_url(); ?>';
            const params = new URLSearchParams();
            params.set('payment_confirmed', '1');
            params.set('order_id', String(orderId || ''));
            if (paymentIntentId) {
                params.set('payment_intent_id', String(paymentIntentId));
            }
            return base + 'conta/planos?' + params.toString();
        }

        async function confirmOnBackend(orderId, paymentIntentId) {
            const res = await fetch('<?php echo root_url(); ?>conta/_ajax/confirm_card_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    order_id: orderId,
                    payment_intent_id: paymentIntentId
                })
            });
            const data = await res.json().catch(() => null);
            if (!data || !data.success) {
                throw new Error((data && data.message) ? data.message : 'Não foi possível confirmar o pagamento no servidor.');
            }
            return data;
        }

        async function handlePaymentSuccess(paymentIntentId) {
            try {
                setLoading(true);
                hideMessage();

                // Confirma no backend (verifica PI no Stripe + ativa o plano)
                await confirmOnBackend(<?php echo (int)$orderId; ?>, paymentIntentId);

                // Redireciona para Planos com modal de confirmação
                window.location.href = buildPlansRedirectUrl(<?php echo (int)$orderId; ?>, paymentIntentId);
            } catch (e) {
                setLoading(false);
                showMessage(e.message || 'Não foi possível confirmar o pagamento.');
            }
        }

        // Se voltamos de um redirect 3DS, Stripe adiciona payment_intent_client_secret/redirect_status.
        async function checkRedirectReturn() {
            try {
                const params = new URLSearchParams(window.location.search);
                const clientSecret = params.get('payment_intent_client_secret');
                const redirectStatus = params.get('redirect_status');

                if (!clientSecret || !redirectStatus) {
                    return;
                }

                // remove params para evitar loop no refresh
                params.delete('payment_intent_client_secret');
                params.delete('payment_intent');
                params.delete('redirect_status');
                const newUrl = window.location.pathname + '?'+ params.toString();
                window.history.replaceState({}, document.title, newUrl);

                const result = await stripe.retrievePaymentIntent(clientSecret);
                const pi = result && result.paymentIntent ? result.paymentIntent : null;

                if (pi && pi.status === 'succeeded') {
                    form.classList.add('hidden');
                    successDiv.style.display = 'block';
                    await handlePaymentSuccess(pi.id);
                    return;
                }

                if (pi && pi.status === 'processing') {
                    showMessage('Pagamento em processamento. Aguarde alguns instantes e verifique no histórico.');
                    return;
                }

                showMessage('Pagamento não foi confirmado. Status: ' + (pi ? pi.status : redirectStatus));
            } catch (e) {
                // ignore
            }
        }

        // Handler do formulário
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            setLoading(true);
            hideMessage();

            const { error, paymentIntent } = await stripe.confirmPayment({
                elements,
                confirmParams: {
                    // Mantém o usuário na mesma página após 3DS
                    return_url: '<?php echo root_url(); ?>conta/pagamento_stripe.php?order_id=<?php echo (int)$orderId; ?>'
                },
                redirect: 'if_required'
            });

            if (error) {
                if (error.type === 'card_error' || error.type === 'validation_error') {
                    showMessage(error.message);
                } else {
                    showMessage('Ocorreu um erro inesperado. Tente novamente.');
                }
                setLoading(false);
                return;
            }

            if (paymentIntent && paymentIntent.status === 'succeeded') {
                form.classList.add('hidden');
                successDiv.style.display = 'block';
                await handlePaymentSuccess(paymentIntent.id);
                return;
            }

            showMessage('O pagamento está sendo processado. Aguarde...');
            setLoading(false);
        });

        // Utilitários UI
        function setLoading(isLoading) {
            submitBtn.disabled = isLoading;
            if (isLoading) {
                submitBtn.classList.add('loading');
            } else {
                submitBtn.classList.remove('loading');
            }
        }

        function showMessage(text) {
            messageDiv.textContent = text;
            messageDiv.classList.add('visible');
        }

        function hideMessage() {
            messageDiv.classList.remove('visible');
        }

        // Checa retorno de 3DS
        checkRedirectReturn();
    </script>
    <?php endif; ?>
</body>
</html>
