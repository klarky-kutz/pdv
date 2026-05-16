<?php
/**
 * checkout_pix.php — Checkout PIX Transparente · Moda IA
 * Página pública (sem autenticação). Gerada automaticamente pela IA ao criar pedidos.
 * URL: http://localhost/modernpos/checkout_pix.php?order_id=N
 */

// ─── Conexão via config.php (sem _init.php que exige sessão) ─────────────────
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . $sql_details['host'] . ';port=' . ($sql_details['port'] ?? '3306') . ';dbname=' . $sql_details['db'] . ';charset=utf8mb4',
        $sql_details['user'],
        $sql_details['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Exception $e) {
    http_response_code(503);
    die('Serviço temporariamente indisponível.');
}

// ─── Biblioteca QR Code (compartilhada com o SaaS) ───────────────────────────
$qrLibAvailable = false;
$qrLibAutoload  = __DIR__ . '/../saas/PIX/vendor/autoload.php';
if (file_exists($qrLibAutoload)) {
    require_once $qrLibAutoload;
    $qrLibAvailable = true;
}

// ─── Funções auxiliares EMV / QR ─────────────────────────────────────────────

function ckpix_buildQrBase64(string $data, int $size = 260): ?string
{
    global $qrLibAvailable;
    if (!$qrLibAvailable || $data === '') return null;
    try {
        $writer = new \Endroid\QrCode\Writer\PngWriter();
        $qrCode = new \Endroid\QrCode\QrCode(
            data: $data,
            encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
            errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::Low,
            size: $size,
            margin: 10,
            roundBlockSizeMode: \Endroid\QrCode\RoundBlockSizeMode::Margin
        );
        return base64_encode($writer->write($qrCode)->getString());
    } catch (\Throwable $e) {
        return null;
    }
}

function ckpix_emvField(string $id, string $value): string
{
    return $id . sprintf('%02d', strlen($value)) . $value;
}

function ckpix_crc16(string $payload): string
{
    $poly = 0x1021; $crc = 0xFFFF;
    for ($i = 0, $len = strlen($payload); $i < $len; $i++) {
        $crc ^= (ord($payload[$i]) << 8);
        for ($b = 0; $b < 8; $b++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ $poly) & 0xFFFF : ($crc << 1) & 0xFFFF;
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

function ckpix_buildEmvPayload(string $pixKey, float $amount, string $merchantName, string $merchantCity, string $tid): string
{
    $pixKey = trim($pixKey);
    if ($pixKey === '') return '';

    $mai          = ckpix_emvField('26', ckpix_emvField('00', 'br.gov.bcb.pix') . ckpix_emvField('01', $pixKey));
    $amountField  = $amount > 0 ? ckpix_emvField('54', number_format($amount, 2, '.', '')) : '';
    $merchantName = strtoupper(substr($merchantName ?: 'PIX MANUAL', 0, 25));
    $merchantCity = strtoupper(substr($merchantCity ?: 'BRASILIA', 0, 15));
    $addData      = $tid !== '' ? ckpix_emvField('62', ckpix_emvField('05', substr($tid, 0, 25))) : '';

    $payload  = ckpix_emvField('00', '01');
    $payload .= ckpix_emvField('01', '11');
    $payload .= $mai;
    $payload .= ckpix_emvField('52', '0000');
    $payload .= ckpix_emvField('53', '986');
    $payload .= $amountField;
    $payload .= ckpix_emvField('58', 'BR');
    $payload .= ckpix_emvField('59', $merchantName);
    $payload .= ckpix_emvField('60', $merchantCity);
    $payload .= $addData;

    $base = $payload . '6304';
    return $base . ckpix_crc16($base);
}

// ─── Parâmetros e validações ──────────────────────────────────────────────────

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($orderId <= 0) {
    http_response_code(400);
    die('Pedido inválido.');
}

// Busca pedido
try {
    $stmtO = $pdo->prepare("
        SELECT id, tenant_id, whatsapp_phone, customer_name, status,
               total_amount, payment_method, notes, created_at
        FROM   ai_orders
        WHERE  id = :id
        LIMIT  1
    ");
    $stmtO->execute([':id' => $orderId]);
    $order = $stmtO->fetch() ?: null;
} catch (Exception $e) {
    $order = null;
}

if (!$order || strtolower((string)$order['payment_method']) !== 'pix') {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Pedido não encontrado</title></head><body style="font-family:sans-serif;padding:40px;text-align:center;"><h2>Pedido PIX não encontrado</h2><p>Verifique o link recebido pelo WhatsApp.</p></body></html>';
    exit;
}

// Pedido já pago → redireciona para obrigado
if (in_array($order['status'], ['pago', 'separando', 'rota', 'entregue'], true)) {
    header('Location: ' . ROOT_URL . 'checkout_pix_obrigado.php?order_id=' . $orderId);
    exit;
}

// Cancelado
if ($order['status'] === 'cancelado') {
    http_response_code(410);
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Pedido cancelado</title></head><body style="font-family:sans-serif;padding:40px;text-align:center;"><h2>Este pedido foi cancelado</h2><p>Entre em contato pelo WhatsApp para mais informações.</p></body></html>';
    exit;
}

$tenantId = (int)$order['tenant_id'];

// Busca itens do pedido
try {
    $stmtI = $pdo->prepare("
        SELECT oi.*, v.photo_webp, m.cover_webp
        FROM ai_order_items oi
        LEFT JOIN ai_catalogo_variants v ON v.id = oi.variant_id
        LEFT JOIN ai_catalogo_models m ON m.id = v.model_id
        WHERE oi.order_id = :oid
        ORDER BY oi.id ASC
    ");
    $stmtI->execute([':oid' => $orderId]);
    $orderItems = $stmtI->fetchAll();
} catch (Exception $e) {
    $orderItems = [];
}

// Busca configurações de checkout na tabela ai_settings
try {
    $stmtS = $pdo->prepare("SELECT key_name, value FROM ai_settings WHERE tenant_id = :tid");
    $stmtS->execute([':tid' => $tenantId]);
    $settings = $stmtS->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $settings = [];
}

// ─── Config checkout ──────────────────────────────────────────────────────────
$nomeEmpresa  = trim((string)($settings['ai_checkout_nome_empresa'] ?? $settings['ai_name'] ?? 'Moda IA'));
$titular      = trim((string)($settings['ai_checkout_titular'] ?? $nomeEmpresa));
$cidade       = trim((string)($settings['ai_checkout_cidade'] ?? 'BRASILIA'));
$whatsappNum  = preg_replace('/\D+/', '', (string)($settings['ai_checkout_whatsapp'] ?? ''));
$minutosTimer = max(5, (int)($settings['ai_checkout_minutos'] ?? 10));
$corAcento    = trim((string)($settings['ai_checkout_cor_acento'] ?? '#22c55e'));
if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $corAcento)) $corAcento = '#22c55e';

// ─── Chave PIX ────────────────────────────────────────────────────────────────
$pixChave   = '';
$pixKeyType = '';
$pixRaw = (string)($settings['ai_pix_keys_json'] ?? '');
if ($pixRaw !== '') {
    // Decodificação robusta para lidar com entidades HTML (XSS Clean do sistema)
    $keys = json_decode($pixRaw, true);
    if (!is_array($keys)) {
        $cleaned = html_entity_decode($pixRaw, ENT_QUOTES, 'UTF-8');
        $keys = json_decode($cleaned, true);
    }

    if (is_array($keys) && !empty($keys[0]['key'])) {
        $pixChave   = trim((string)$keys[0]['key']);
        $pixKeyType = (string)($keys[0]['type'] ?? '');
    }
}

// ─── Gerar QR PIX ─────────────────────────────────────────────────────────────
$amountBRL   = (float)$order['total_amount'];
$pixQrBase64 = null;
$pixQrText   = null;

if ($pixChave !== '') {
    $emv = ckpix_buildEmvPayload($pixChave, $amountBRL, $titular, $cidade, (string)$orderId);
    if ($emv !== '') {
        $pixQrText   = $emv;
        $pixQrBase64 = ckpix_buildQrBase64($emv);
    }
}

// ─── WhatsApp ─────────────────────────────────────────────────────────────────
if ($whatsappNum === '') $whatsappNum = '5511999999999'; // fallback
$waText = rawurlencode(
    'Olá! Realizei o pagamento via Pix.' . "\n\n" .
    'Pedido #' . $orderId . ' — R$ ' . number_format($amountBRL, 2, ',', '.') . '.' . "\n\n" .
    'Segue o comprovante em anexo.'
);
$waUrl = 'https://wa.me/' . $whatsappNum . '?text=' . $waText;

// ─── Nome do cliente (display) ────────────────────────────────────────────────
$customerName = trim((string)($order['customer_name'] ?? ''));
if ($customerName === '') {
    $phone = (string)($order['whatsapp_phone'] ?? '');
    $customerName = $phone !== '' ? $phone : 'Cliente';
}

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pagar com Pix · Pedido #<?php echo $orderId; ?></title>
  <style>
    :root {
      --primary: <?php echo $corAcento; ?>;
      --primary-dark: #4c1d95;
      --primary-light: #ddd6fe;
      --bg-body: #f8fafc;
      --bg-card: #ffffff;
      --text-main: #1e293b;
      --text-muted: #64748b;
      --radius: 12px;
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
      padding: 20px 16px 40px;
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
      position: relative;
    }
    .sofia-avatar::after {
      content: '';
      position: absolute;
      inset: -4px;
      border: 2px solid var(--primary);
      border-radius: 50%;
      opacity: 0.3;
      animation: pulse 2s infinite;
    }
    .sofia-name {
      font-weight: 800;
      font-size: 18px;
      color: var(--text-main);
      letter-spacing: -0.02em;
    }
    .sofia-status {
      font-size: 12px;
      color: var(--primary);
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .sofia-status .dot {
      width: 6px;
      height: 6px;
      background: var(--primary);
      border-radius: 50%;
    }

    /* ── Bubble Message ── */
    .sofia-bubble {
      background: var(--bg-card);
      padding: 16px;
      border-radius: 16px 16px 16px 4px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.03);
      border: 1px solid #f1f5f9;
      margin-bottom: 24px;
      font-size: 14px;
      color: var(--text-main);
      position: relative;
      animation: fadeInUp 0.5s ease-out 0.2s both;
    }

    /* ── Main Payment Card ── */
    .payment-card {
      background: var(--bg-card);
      border-radius: 24px;
      padding: 24px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.06);
      border: 1px solid #f1f5f9;
      margin-bottom: 20px;
      animation: fadeInUp 0.5s ease-out 0.4s both;
    }
    .order-info {
      text-align: center;
      margin-bottom: 20px;
    }
    .order-label {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: var(--text-muted);
      font-weight: 700;
      margin-bottom: 4px;
    }
    .order-total {
      font-size: 32px;
      font-weight: 900;
      color: var(--text-main);
      letter-spacing: -0.03em;
    }

    /* ── QR Code ── */
    .qr-container {
      background: #0f172a;
      padding: 20px;
      border-radius: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 20px;
    }
    .qr-image {
      width: 200px;
      height: 200px;
      background: white;
      padding: 10px;
      border-radius: 12px;
    }
    .qr-image img {
      width: 100%;
      height: 100%;
      display: block;
    }
    .qr-helper {
      margin-top: 16px;
      font-size: 12px;
      color: #94a3b8;
      text-align: center;
    }

    /* ── Pix Code ── */
    .pix-code-section {
      background: #f8fafc;
      border-radius: 12px;
      padding: 12px;
      border: 1px dashed #cbd5e1;
      margin-bottom: 20px;
    }
    .pix-code-label {
      font-size: 10px;
      text-transform: uppercase;
      font-weight: 700;
      color: var(--text-muted);
      margin-bottom: 6px;
    }
    .pix-code-value {
      font-family: 'JetBrains Mono', monospace;
      font-size: 12px;
      word-break: break-all;
      color: var(--text-main);
      margin-bottom: 10px;
    }

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
    .btn-primary:active { transform: scale(0.98); }
    .btn-secondary {
      background: #f1f5f9;
      color: var(--text-main);
    }

    /* ── Items Summary ── */
    .items-card {
      background: var(--bg-card);
      border-radius: 20px;
      padding: 20px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.03);
      margin-bottom: 20px;
      animation: fadeInUp 0.5s ease-out 0.6s both;
    }
    .item-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid #f1f5f9;
    }
    .item-row:last-child { border-bottom: none; }
    .item-img {
      width: 48px;
      height: 48px;
      border-radius: 8px;
      background: #f1f5f9;
      object-fit: cover;
      flex-shrink: 0;
    }
    .item-info { flex: 1; min-width: 0; }
    .item-name {
      font-size: 13px;
      font-weight: 700;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .item-meta {
      font-size: 11px;
      color: var(--text-muted);
    }
    .item-price {
      font-size: 13px;
      font-weight: 800;
      color: var(--primary);
    }

    /* ── Timer ── */
    .timer-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #fff7ed;
      color: #c2410c;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
      border: 1px solid #ffedd5;
    }

    @keyframes pulse {
      0% { transform: scale(1); opacity: 0.3; }
      50% { transform: scale(1.1); opacity: 0.1; }
      100% { transform: scale(1); opacity: 0.3; }
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .status-confirmed {
      color: #10b981;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 6px;
      justify-content: center;
      margin-top: 12px;
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
      <div class="sofia-status">
        <span class="dot"></span>
        Atendimento Concierge Ativo
      </div>
    </div>

    <!-- Bubble Sofia -->
    <div class="sofia-bubble">
      Oi, <strong><?php echo htmlspecialchars(explode(' ', $customerName)[0]); ?></strong>! ????<br>
      Aqui está o seu Pix para garantir suas peças. Assim que pagar, eu já aviso a equipe para separar seu pedido!
    </div>

    <!-- Card de Pagamento -->
    <div class="payment-card">
      <div class="order-info">
        <div class="order-label">Total do Pedido #<?php echo $orderId; ?></div>
        <div class="order-total">R$ <?php echo number_format($amountBRL, 2, ',', '.'); ?></div>
      </div>

      <?php if ($pixQrBase64): ?>
      <div class="qr-container">
        <div class="qr-image">
          <img src="data:image/png;base64,<?php echo htmlspecialchars($pixQrBase64, ENT_QUOTES, 'UTF-8'); ?>" alt="Pix QR Code" />
        </div>
        <div class="qr-helper">Escaneie o QR Code no app do seu banco</div>
      </div>
      <?php endif; ?>

      <?php if ($pixQrText): ?>
      <div class="pix-code-section">
        <div class="pix-code-label">Código Pix (Copia e Cola)</div>
        <div id="pix-code-box" class="pix-code-value"><?php echo htmlspecialchars($pixQrText); ?></div>
        <button id="btn-copy-pix" class="btn btn-secondary" style="height: 40px; font-size: 13px;">
          <i class="fa fa-copy"></i> Copiar Código
        </button>
      </div>
      <?php endif; ?>

      <div style="text-align: center;">
        <div class="timer-badge">
          <i class="fa fa-clock-o"></i> 
          Expira em <span id="pix-timer" data-minutes="<?php echo $minutosTimer; ?>"><?php printf('%02d:00', $minutosTimer); ?></span>
        </div>
      </div>

      <div id="pix-status-text" style="text-align: center; margin-top: 16px; font-size: 13px; color: var(--text-muted);">
        <i class="fa fa-spinner fa-spin" style="margin-right: 4px;"></i> Aguardando pagamento...
      </div>
    </div>

    <!-- Resumo de Itens -->
    <?php if (!empty($orderItems)): ?>
    <div class="items-card">
      <div class="order-label" style="margin-bottom: 12px;">Suas Escolhas</div>
      <?php foreach ($orderItems as $item): ?>
      <div class="item-row">
        <?php 
          $photo = !empty($item['photo_webp']) ? $item['photo_webp'] : ($item['cover_webp'] ?? null);
          $imgUrl = $photo ? ROOT_URL . 'storage/' . ltrim(str_replace('\\', '/', $photo), '/') : null;
          if ($imgUrl): 
        ?>
          <img src="<?php echo htmlspecialchars($imgUrl); ?>" class="item-img" alt="Produto" />
        <?php else: ?>
          <div class="item-img" style="display:flex;align-items:center;justify-content:center;"><i class="fa fa-camera" style="color:#cbd5e1"></i></div>
        <?php endif; ?>
        
        <div class="item-info">
          <div class="item-name"><?php echo htmlspecialchars($item['model_name']); ?></div>
          <div class="item-meta">
            <?php echo htmlspecialchars(($item['color'] ?? '') . ' · ' . ($item['size'] ?? '')); ?>
            (<?php echo (int)$item['qty']; ?>x)
          </div>
        </div>
        <div class="item-price">R$ <?php echo number_format($item['subtotal'] ?? $item['unit_price'], 2, ',', '.'); ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Botão WhatsApp -->
    <a href="<?php echo htmlspecialchars($waUrl); ?>" target="_blank" class="btn btn-primary" style="margin-top: 8px;">
      <i class="fa fa-whatsapp" style="font-size: 20px;"></i>
      Já paguei! Enviar comprovante
    </a>

    <div style="text-align: center; margin-top: 24px;">
      <div style="display: flex; align-items: center; justify-content: center; gap: 16px; opacity: 0.5;">
        <div style="font-size: 10px; font-weight: 700; color: var(--text-muted); display: flex; align-items: center; gap: 4px;">
          <i class="fa fa-lock"></i> AMBIENTE SEGURO
        </div>
        <div style="font-size: 10px; font-weight: 700; color: var(--text-muted); display: flex; align-items: center; gap: 4px;">
          <i class="fa fa-bolt"></i> PIX INSTANTÂNEO
        </div>
      </div>
    </div>

  </div>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">

  <script>
  (function() {
    var orderId    = <?php echo (int)$orderId; ?>;
    var minutes    = parseInt(document.getElementById('pix-timer').getAttribute('data-minutes') || '10', 10);
    var totalSecs  = minutes * 60;
    var redirectTo = '<?php echo htmlspecialchars(ROOT_URL . 'checkout_pix_obrigado.php?order_id=' . $orderId, ENT_QUOTES, 'UTF-8'); ?>';
    var statusEndpoint = '<?php echo htmlspecialchars(ROOT_URL . '_inc/ai_checkout_pix_status.php', ENT_QUOTES, 'UTF-8'); ?>';

    // Timer
    var timerEl = document.getElementById('pix-timer');
    function fmt(s) { return String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0'); }
    timerEl.textContent = fmt(totalSecs);

    var timerInt = setInterval(function() {
      totalSecs--;
      if (totalSecs <= 0) {
        clearInterval(timerInt);
        timerEl.textContent = '00:00';
        var st = document.getElementById('pix-status-text');
        if (st) st.textContent = 'QR Code expirado. Gere um novo pedido ou entre em contato.';
      } else {
        timerEl.textContent = fmt(totalSecs);
      }
    }, 1000);

    // Polling status
    var pollInt = setInterval(function() {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', statusEndpoint, true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
      xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4 || xhr.status !== 200) return;
        try {
          var r = JSON.parse(xhr.responseText);
          if (r && r.success) {
            var st = String(r.status || '').toLowerCase();
            if (st === 'pago' || st === 'separando' || st === 'rota' || st === 'entregue') {
              clearInterval(pollInt);
              clearInterval(timerInt);
              
              var stEl = document.getElementById('pix-status-text');
              if (stEl) {
                stEl.className = 'status-confirmed';
                stEl.innerHTML = '<i class="fa fa-check-circle"></i> Pagamento confirmado! Redirecionando...';
              }
              
              // Ocultar timer e QR se já pago
              var timerBadge = document.querySelector('.timer-badge');
              if (timerBadge) timerBadge.style.display = 'none';
              
              setTimeout(function() { window.location.href = redirectTo; }, 1800);
            } else if (st === 'cancelado') {
              clearInterval(pollInt);
              clearInterval(timerInt);
              var stEl2 = document.getElementById('pix-status-text');
              if (stEl2) stEl2.textContent = 'Pedido cancelado. Entre em contato pelo WhatsApp.';
            }
          }
        } catch(e) {}
      };
      xhr.send('order_id=' + encodeURIComponent(orderId));
    }, 5000);

    // Copiar código Pix
    var btnCopy = document.getElementById('btn-copy-pix');
    if (btnCopy) {
      btnCopy.addEventListener('click', function() {
        var box = document.getElementById('pix-code-box');
        if (!box) return;
        var text = (box.textContent || box.innerText || '').trim();
        navigator.clipboard.writeText(text).then(function() {
          btnCopy.textContent = '✓ Copiado!';
          setTimeout(function() { btnCopy.textContent = 'Copiar código Pix'; }, 1800);
        }).catch(function() {
          // fallback
          var ta = document.createElement('textarea');
          ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
          document.body.appendChild(ta); ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
          btnCopy.textContent = '✓ Copiado!';
          setTimeout(function() { btnCopy.textContent = 'Copiar código Pix'; }, 1800);
        });
      });
    }
  })();
  </script>
</body>
</html>
