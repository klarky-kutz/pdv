<?php
// Verificar se giftcard existe
$card_no = isset($giftcard['card_no']) ? $giftcard['card_no'] : '';
$balance = isset($giftcard['balance']) ? $giftcard['balance'] : 0;
$giftcard_value = isset($giftcard['giftcard_value']) ? $giftcard['giftcard_value'] : 0;
$expiry = isset($giftcard['expiry']) ? $giftcard['expiry'] : '';
$customer_name = isset($giftcard['customer_name']) ? $giftcard['customer_name'] : 'N/A';
$created_at = isset($giftcard['created_at']) ? $giftcard['created_at'] : '';
$status = isset($giftcard['status']) ? $giftcard['status'] : 0;

// Formatar número do cartão
$card_display = $card_no ? implode('-', str_split($card_no, 4)) : 'XXXX-XXXX-XXXX-XXXX';

// Formatar validade
$expiry_display = $expiry ? date('d/m/Y', strtotime($expiry)) : '--/--/----';
?>
<style>
.gc-preview-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 25px;
    color: #fff;
    margin: 0 auto 20px;
    max-width: 400px;
    box-shadow: 0 15px 35px rgba(102,126,234,0.4);
    position: relative;
    overflow: hidden;
}
.gc-preview-card::before {
    content: "";
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 60%);
}
.gc-preview-chip {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 50px;
    height: 40px;
    background: linear-gradient(135deg, #ffd700 0%, #ffb700 100%);
    border-radius: 6px;
}
.gc-preview-chip::before,
.gc-preview-chip::after {
    content: "";
    position: absolute;
    left: 8px;
    right: 8px;
    height: 6px;
    background: rgba(0,0,0,0.15);
    border-radius: 2px;
}
.gc-preview-chip::before { top: 10px; }
.gc-preview-chip::after { top: 22px; }
.gc-preview-logo {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.gc-preview-logo i {
    font-size: 28px;
}
.gc-preview-number {
    font-family: 'Courier New', monospace;
    font-size: 22px;
    letter-spacing: 3px;
    margin-bottom: 25px;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
}
.gc-preview-details {
    display: flex;
    justify-content: space-between;
    position: relative;
    z-index: 1;
}
.gc-preview-detail label {
    font-size: 10px;
    text-transform: uppercase;
    opacity: 0.8;
    display: block;
    margin-bottom: 3px;
}
.gc-preview-detail span {
    font-size: 16px;
    font-weight: bold;
}
.gc-preview-info {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}
.gc-preview-info-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}
.gc-preview-info-row:last-child {
    border-bottom: none;
}
.gc-preview-info-label {
    color: #666;
    font-size: 13px;
}
.gc-preview-info-value {
    font-weight: 600;
    color: #333;
}
.gc-preview-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}
.gc-preview-status.active {
    background: #d4edda;
    color: #155724;
}
.gc-preview-status.inactive {
    background: #f8d7da;
    color: #721c24;
}
</style>

<div class="modal-body" style="padding: 25px;">
    <?php if (!$giftcard || !$card_no): ?>
        <div class="alert alert-warning text-center">
            <i class="fa fa-exclamation-triangle fa-2x"></i>
            <p style="margin-top: 10px;">Gift Card não encontrado</p>
        </div>
    <?php else: ?>
        <!-- Card Visual -->
        <div class="gc-preview-card">
            <div class="gc-preview-chip"></div>
            <div class="gc-preview-logo">
                <i class="fa fa-gift"></i> GIFT CARD
            </div>
            <div class="gc-preview-number"><?php echo htmlspecialchars($card_display); ?></div>
            <div class="gc-preview-details">
                <div class="gc-preview-detail">
                    <label>Saldo</label>
                    <span><?php echo currency_format($balance); ?></span>
                </div>
                <div class="gc-preview-detail">
                    <label>Validade</label>
                    <span><?php echo htmlspecialchars($expiry_display); ?></span>
                </div>
            </div>
        </div>

        <!-- Card Info -->
        <div class="gc-preview-info">
            <div class="gc-preview-info-row">
                <span class="gc-preview-info-label"><i class="fa fa-credit-card"></i> Número</span>
                <span class="gc-preview-info-value"><?php echo htmlspecialchars($card_no); ?></span>
            </div>
            <div class="gc-preview-info-row">
                <span class="gc-preview-info-label"><i class="fa fa-money"></i> Valor Original</span>
                <span class="gc-preview-info-value"><?php echo currency_format($giftcard_value); ?></span>
            </div>
            <div class="gc-preview-info-row">
                <span class="gc-preview-info-label"><i class="fa fa-balance-scale"></i> Saldo Atual</span>
                <span class="gc-preview-info-value" style="color: #27ae60; font-size: 16px;"><?php echo currency_format($balance); ?></span>
            </div>
            <div class="gc-preview-info-row">
                <span class="gc-preview-info-label"><i class="fa fa-user"></i> Cliente</span>
                <span class="gc-preview-info-value"><?php echo htmlspecialchars($customer_name); ?></span>
            </div>
            <div class="gc-preview-info-row">
                <span class="gc-preview-info-label"><i class="fa fa-calendar"></i> Validade</span>
                <span class="gc-preview-info-value"><?php echo htmlspecialchars($expiry_display); ?></span>
            </div>
            <?php 
            // Verificar se cartão está válido (não expirado)
            $is_valid = $expiry ? (strtotime($expiry) > time()) : false;
            ?>
            <div class="gc-preview-info-row">
                <span class="gc-preview-info-label"><i class="fa fa-check-circle"></i> Status</span>
                <span class="gc-preview-info-value">
                    <span class="gc-preview-status <?php echo $is_valid ? 'active' : 'inactive'; ?>">
                        <?php echo $is_valid ? 'Válido' : 'Expirado'; ?>
                    </span>
                </span>
            </div>
        </div>

        <!-- Barcode -->
        <div class="text-center" style="margin-bottom: 20px;">
            <?php
            try {
                $generator = barcode_generator();
                $symbology = barcode_symbology($generator, 'code_39');
                $barcode_data = $card_no ? encode_data($generator->getBarcode($card_no, $symbology, 2)) : '';
                if ($barcode_data):
            ?>
                <img src="data:image/png;base64,<?php echo $barcode_data; ?>" height="50" alt="Barcode" style="max-width: 100%;">
                <div style="font-family: monospace; font-size: 12px; color: #666; margin-top: 5px;">
                    <?php echo htmlspecialchars($card_no); ?>
                </div>
            <?php 
                endif;
            } catch (Exception $e) {
                // Silenciar erro do barcode
            }
            ?>
        </div>

        <!-- Actions -->
        <div class="text-center">
            <button type="button" class="btn btn-primary no-print" onclick="window.print();">
                <i class="fa fa-print"></i> <?php echo trans('button_print'); ?>
            </button>
        </div>
    <?php endif; ?>
</div>
