<?php
ob_start();
session_start();
include '../_init.php';

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

echo "<h2>Inserir Modelo Minimalista de Recibo</h2>";

try {
    // Conectar ao banco usando a conexão do sistema
    global $db;
    
    // HTML do template minimalista
    $template_content = '<div class="receipt-minimal">
  <div class="receipt-header">
    <div class="logo-section">
      {{ logo }}
    </div>
    <h2 class="store-name">{{ store_name }}</h2>
    <div class="store-info">
      <p>{{ store_address }}</p>
      <p>{{ store_phone }} • {{ store_email }}</p>
    </div>
  </div>

  <div class="divider"></div>

  <div class="invoice-info">
    <div class="invoice-row">
      <span class="label">Fatura:</span>
      <span class="value">#{{ invoice_id }}</span>
    </div>
    <div class="invoice-row">
      <span class="label">Data:</span>
      <span class="value">{{ date_time }}</span>
    </div>
    <div class="invoice-row">
      <span class="label">Cliente:</span>
      <span class="value">{{ customer_name }}</span>
    </div>
  </div>

  <div class="divider"></div>

  <div class="items-section">
    <table class="items-table">
      <thead>
        <tr>
          <th>Item</th>
          <th>Qtd</th>
          <th>Preço</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        {{ items }}
        <tr>
          <td>{{ item_name }}</td>
          <td>{{ item_quantity }}</td>
          <td>R$ {{ item_price }}</td>
          <td>R$ {{ item_total }}</td>
        </tr>
        {{ /items }}
      </tbody>
    </table>
  </div>

  <div class="divider"></div>

  <div class="totals-section">
    <div class="total-row">
      <span class="label">Subtotal:</span>
      <span class="value">R$ {{ subtotal }}</span>
    </div>
    <div class="total-row">
      <span class="label">Desconto:</span>
      <span class="value">R$ {{ discount_amount }}</span>
    </div>
    <div class="total-row">
      <span class="label">Impostos:</span>
      <span class="value">R$ {{ total_tax }}</span>
    </div>
    <div class="total-row total-final">
      <span class="label">TOTAL:</span>
      <span class="value">R$ {{ payable_amount }}</span>
    </div>
  </div>

  <div class="divider"></div>

  <div class="payment-section">
    <div class="payment-row">
      <span class="label">Pago:</span>
      <span class="value">R$ {{ paid_amount }}</span>
    </div>
    <div class="payment-row">
      <span class="label">Troco:</span>
      <span class="value">R$ {{ change }}</span>
    </div>
  </div>

  <div class="footer-section">
    <div class="qr-code">
      {{ qrcode }}
    </div>
    <p class="footer-text">{{ footer_text }}</p>
    <p class="thank-you">Obrigado pela preferência!</p>
    <p class="cashier-info">Atendente: {{ cashier_name }}</p>
  </div>
</div>';

    // CSS do template minimalista
    $template_css = '* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

.receipt-minimal {
  max-width: 300px;
  margin: 0 auto;
  padding: 20px;
  font-family: \'Courier New\', Courier, monospace;
  background: #ffffff;
  color: #1a1a1a;
  line-height: 1.6;
}

/* Header */
.receipt-header {
  text-align: center;
  margin-bottom: 20px;
}

.logo-section {
  margin-bottom: 15px;
}

.logo-section img {
  max-width: 120px;
  height: auto;
}

.store-name {
  font-size: 20px;
  font-weight: bold;
  margin: 10px 0;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.store-info {
  font-size: 11px;
  color: #666;
  margin-top: 8px;
}

.store-info p {
  margin: 3px 0;
}

/* Divider */
.divider {
  border-top: 1px dashed #333;
  margin: 15px 0;
}

/* Invoice Info */
.invoice-info {
  margin-bottom: 15px;
}

.invoice-row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  margin: 5px 0;
}

.invoice-row .label {
  font-weight: bold;
}

.invoice-row .value {
  text-align: right;
}

/* Items Table */
.items-section {
  margin: 15px 0;
}

.items-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;
}

.items-table thead {
  border-bottom: 1px solid #333;
}

.items-table th {
  text-align: left;
  padding: 5px 2px;
  font-weight: bold;
  font-size: 11px;
}

.items-table th:nth-child(2),
.items-table th:nth-child(3),
.items-table th:nth-child(4) {
  text-align: right;
}

.items-table td {
  padding: 8px 2px;
  border-bottom: 1px dotted #ddd;
}

.items-table td:nth-child(2),
.items-table td:nth-child(3),
.items-table td:nth-child(4) {
  text-align: right;
}

/* Totals */
.totals-section {
  margin: 15px 0;
}

.total-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  margin: 8px 0;
}

.total-row .label {
  font-weight: 600;
}

.total-row .value {
  text-align: right;
  font-weight: 600;
}

.total-final {
  margin-top: 15px;
  padding-top: 10px;
  border-top: 2px solid #333;
  font-size: 16px;
  font-weight: bold;
}

/* Payment */
.payment-section {
  margin: 15px 0;
}

.payment-row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  margin: 5px 0;
}

.payment-row .label {
  font-weight: 600;
}

.payment-row .value {
  text-align: right;
}

/* Footer */
.footer-section {
  text-align: center;
  margin-top: 20px;
  font-size: 11px;
}

.qr-code {
  margin: 15px auto;
  display: inline-block;
}

.qr-code img {
  max-width: 100px;
  height: auto;
}

.footer-text {
  margin: 10px 0;
  color: #666;
  font-size: 10px;
}

.thank-you {
  font-weight: bold;
  font-size: 14px;
  margin: 15px 0 10px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.cashier-info {
  color: #999;
  font-size: 10px;
  margin-top: 10px;
}

/* Print Styles */
@media print {
  .receipt-minimal {
    max-width: 300px;
    padding: 10px;
  }
  
  .divider {
    page-break-inside: avoid;
  }
}';

    // Escapar dados para evitar SQL injection
    $name = addslashes('Minimalista');
    $content = addslashes($template_content);
    $css = addslashes($template_css);
    
    // Inserir no banco
    $sql = "INSERT INTO pos_templates (template_name, template_content, template_css, created_at, updated_at) 
            VALUES ('$name', '$content', '$css', NOW(), NOW())";
    
    $db->query($sql);
    $template_id = $db->lastInsertId();
    
    echo "<div style='padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; color: #155724;'>";
    echo "<h3>✓ Sucesso!</h3>";
    echo "<p>Modelo 'Minimalista' inserido com sucesso!</p>";
    echo "<p>ID do template: <strong>" . $template_id . "</strong></p>";
    echo "<p><a href='select_receipt_template.php' class='btn btn-success'>Ver Modelos de Recibo</a></p>";
    echo "<p><a href='receipt_template.php?template_id=" . $template_id . "' class='btn btn-primary'>Editar Template</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;'>";
    echo "<h3>✗ Erro!</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
