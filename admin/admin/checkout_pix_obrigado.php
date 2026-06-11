<?php
/**
 * checkout_pix_obrigado.php — Confirmação de pagamento · Moda IA
 * Página pública exibida após pagamento PIX confirmado pelo admin.
 */

require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . $sql_details['host'] . ';port=' . ($sql_details['port'] ?? '3306') . ';dbname=' . $sql_details['db'] . ';charset=utf8mb4',
        $sql_details['user'],
        $sql_details['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Exception $e) {
    http_response_code(503);
    die('Serviço temporariamente indisponível.');
}

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// Busca pedido
$order     = null;
$tenantId  = 0;
$settings  = [];
if ($orderId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, tenant_id, customer_name, total_amount, status FROM ai_orders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch() ?: null;
    } catch (Exception $e) {}
}

if ($order) {
    $tenantId = (int)$order['tenant_id'];
    try {
        $stmt2 = $pdo->prepare("SELECT key_name, value FROM ai_settings WHERE tenant_id = :tid");
        $stmt2->execute([':tid' => $tenantId]);
        $settings = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {}
}

$nomeEmpresa = trim((string)($settings['ai_checkout_nome_empresa'] ?? $settings['ai_name'] ?? 'Moda IA'));
$corAcento   = trim((string)($settings['ai_checkout_cor_acento'] ?? '#22c55e'));
if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $corAcento)) $corAcento = '#22c55e';
$msgPago = trim((string)($settings['ai_checkout_msg_pago'] ?? 'Pagamento confirmado! Obrigado pela compra. Seu pedido será preparado em breve. 🎉'));

$whatsappNum = preg_replace('/\D+/', '', (string)($settings['ai_checkout_whatsapp'] ?? ''));
if ($whatsappNum === '') $whatsappNum = '5511999999999';

$amountBRL    = $order ? (float)$order['total_amount'] : 0.0;
$customerName = $order ? trim((string)($order['customer_name'] ?? '')) : '';
if ($customerName === '') $customerName = 'Cliente';

$waTextPos = rawurlencode('Olá! Quero acompanhar meu pedido #' . $orderId . '. Já efetuei o pagamento.');
$waUrlPos  = 'https://wa.me/' . $whatsappNum . '?text=' . $waTextPos;

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pagamento Confirmado · <?php echo htmlspecialchars($nomeEmpresa); ?></title>
  <style>
    :root {
      --primary: <?php echo $corAcento; ?>;
      --primary-dark: #4c1d95;
      --bg-body: #f8fafc;
      --bg-card: #ffffff;
      --text-main: #1e293b;
      --text-muted: #64748b;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { 
      font-family: 'Inter', -apple-system, sans-serif; 
      background-color: var(--bg-body); 
      color: var(--text-main);
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
    }

    .checkout-container {
      max-width: 480px;
      margin: 0 auto;
      padding: 40px 16px;
    }

    /* ── Header Sofia ── */
    .sofia-header {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      margin-bottom: 24px;
      animation: fadeInDown 0.6s ease-out;
    }
    .sofia-avatar {
      width: 64px;
      height: 64px;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      color: white;
      margin-bottom: 12px;
      box-shadow: 0 8px 20px rgba(109, 40, 217, 0.3);
    }
    .sofia-name {
      font-weight: 800;
      font-size: 18px;
      color: var(--text-main);
      letter-spacing: -0.02em;
    }

    /* ── Success Card ── */
    .success-card {
      background: var(--bg-card);
      border-radius: 24px;
      padding: 32px 24px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.06);
      border: 1px solid #f1f5f9;
      text-align: center;
      animation: fadeInUp 0.5s ease-out both;
    }

    .ok-icon {
      width: 80px;
      height: 80px;
      background: #d1fae5;
      color: #059669;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 40px;
      margin: 0 auto 20px;
      animation: pop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
    }

    .ok-title {
      font-size: 22px;
      font-weight: 900;
      color: var(--text-main);
      margin-bottom: 12px;
      letter-spacing: -0.02em;
    }

    .ok-msg {
      font-size: 14px;
      color: var(--text-muted);
      margin-bottom: 24px;
      line-height: 1.6;
    }

    .order-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      padding: 10px 16px;
      border-radius: 12px;
      margin-bottom: 24px;
    }
    .order-id { font-weight: 800; color: var(--text-main); }
    .order-val { font-weight: 600; color: var(--primary); }

    /* ── Buttons ── */
    .btn {
      width: 100%;
      height: 52px;
      border-radius: 14px;
      border: none;
      font-weight: 700;
      font-size: 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
    }
    .btn-primary {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      box-shadow: 0 8px 20px rgba(109, 40, 217, 0.2);
    }

    @keyframes pop {
      from { transform: scale(0); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

  <div class="checkout-container">
    
    <!-- Header Sofia -->
    <div class="sofia-header">
      <div class="sofia-avatar">
        <i class="fa fa-magic"></i>
      </div>
      <div class="sofia-name"><?php echo htmlspecialchars($nomeEmpresa); ?></div>
    </div>

    <!-- Card de Sucesso -->
    <div class="success-card">
      <div class="ok-icon">
        <i class="fa fa-check"></i>
      </div>

      <h1 class="ok-title">Pagamento Confirmado!</h1>
      
      <p class="ok-msg">
        <?php echo nl2br(htmlspecialchars($msgPago)); ?>
      </p>

      <?php if ($orderId > 0): ?>
      <div class="order-badge">
        <span class="order-id">Pedido #<?php echo $orderId; ?></span>
        <span style="color: #cbd5e1;">|</span>
        <span class="order-val">R$ <?php echo number_format($amountBRL, 2, ',', '.'); ?></span>
      </div>
      <?php endif; ?>

      <a href="<?php echo htmlspecialchars($waUrlPos); ?>" target="_blank" class="btn btn-primary">
        <i class="fa fa-whatsapp" style="font-size: 20px;"></i>
        Acompanhar pelo WhatsApp
      </a>

      <p style="margin-top: 24px; font-size: 12px; color: var(--text-muted); font-weight: 600;">
        Obrigado pela confiança! ????
      </p>
    </div>

  </div>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
</body>
</html>
