<style>
    .installment-content { padding: 20px; }
    .customer-card { background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; align-items: center; gap: 15px; }
    .customer-card .avatar { width: 60px; height: 60px; background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 24px; }
    .customer-card .info h4 { margin: 0 0 5px 0; font-size: 18px; font-weight: 600; color: #333; }
    .customer-card .info p { margin: 0; color: #666; font-size: 14px; }
    .customer-card .info p i { margin-right: 5px; color: #9b59b6; }
    
    .section-title { font-size: 15px; font-weight: 600; color: #333; margin: 20px 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #9b59b6; display: flex; align-items: center; gap: 8px; }
    .section-title i { color: #9b59b6; }
    
    .payments-table { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .payments-table table { margin: 0; }
    .payments-table thead tr { background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); }
    .payments-table thead th { color: #fff !important; font-weight: 600; padding: 12px 10px; border: none !important; font-size: 12px; text-transform: uppercase; }
    .payments-table tbody tr { transition: background 0.2s ease; }
    .payments-table tbody tr:hover { background: #f8f5ff; }
    .payments-table tbody td { padding: 12px 10px; border-color: #eee; vertical-align: middle; }
    
    .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
    .status-badge.paid { background: #d4edda; color: #155724; }
    .status-badge.due { background: #f8d7da; color: #721c24; }
    
    .btn-pay { background: linear-gradient(135deg, #00a65a 0%, #026c3c 100%); border: none; color: #fff; padding: 6px 15px; border-radius: 5px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
    .btn-pay:hover { transform: translateY(-1px); box-shadow: 0 3px 10px rgba(0,166,90,0.3); color: #fff; }
    
    .details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
    .detail-card { background: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .detail-card .label { font-size: 11px; color: #888; text-transform: uppercase; margin-bottom: 5px; }
    .detail-card .value { font-size: 16px; font-weight: 600; color: #333; }
    .detail-card .value.highlight { color: #9b59b6; }
    .detail-card .value.success { color: #28a745; }
    .detail-card .value.danger { color: #dc3545; }
    .detail-card.full-width { grid-column: span 2; }
    
    .note-icon { color: #9b59b6; cursor: pointer; }
    
    @media (max-width: 768px) {
        .details-grid { grid-template-columns: 1fr; }
        .detail-card.full-width { grid-column: span 1; }
    }
</style>

<div class="installment-content">
    <!-- Customer Info Card -->
    <div class="customer-card">
        <div class="avatar">
            <i class="fa fa-user"></i>
        </div>
        <div class="info">
            <h4><?php echo get_the_customer($invoice['customer_id'], 'customer_name');?></h4>
            <p><i class="fa fa-phone"></i> <?php echo get_the_customer($invoice['customer_id'], 'customer_mobile') ?: 'N/A';?></p>
        </div>
    </div>
    
    <!-- Payments Section -->
    <div class="section-title">
        <i class="fa fa-credit-card"></i>
        <?php echo trans('text_payments'); ?>
    </div>
    
    <div class="payments-table">
        <table class="table">
            <thead>
                <tr>
                    <th class="text-center"><?php echo trans('label_payment_date'); ?></th>
                    <th class="text-right"><?php echo trans('label_interest'); ?></th>
                    <th class="text-right"><?php echo trans('label_payable'); ?></th>
                    <th class="text-right"><?php echo trans('label_paid'); ?></th>
                    <th class="text-right"><?php echo trans('label_due'); ?></th>
                    <th class="text-center"><?php echo trans('label_status'); ?></th>
                    <th class="text-center"><?php echo trans('label_action'); ?></th>
                    <th class="text-center"><?php echo trans('label_note'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($payments)) : ?>
                    <?php $counter = 1; foreach ($payments as $payment): ?>
                        <tr>
                            <td class="text-center">
                                <strong>#<?php echo $counter; ?></strong><br>
                                <small><?php echo date("d/m/Y", strtotime($payment['payment_date']));?></small>
                            </td>
                            <td class="text-right"><?php echo currency_format($payment['interest']);?></td>
                            <td class="text-right"><strong><?php echo currency_format($payment['payable']);?></strong></td>
                            <td class="text-right text-success"><?php echo currency_format($payment['paid']);?></td>
                            <td class="text-right <?php echo $payment['due'] > 0 ? 'text-danger' : ''; ?>">
                                <?php echo currency_format($payment['due']);?>
                            </td>
                            <td class="text-center">
                                <span class="status-badge <?php echo $payment['payment_status'];?>">
                                    <?php echo $payment['payment_status'] == 'paid' ? 'Pago' : 'Pendente';?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($payment['payment_status'] == 'due') : ?>
                                    <button ng-click="payForm(<?php echo $payment['id'];?>)" class="btn btn-pay">
                                        <i class="fa fa-money"></i> Pagar
                                    </button>
                                <?php else: ?>
                                    <i class="fa fa-check-circle text-success" style="font-size:20px;"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($payment['note']): ?>
                                    <i class="fa fa-comment note-icon" data-toggle="tooltip" title="<?php echo htmlspecialchars($payment['note']);?>"></i>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php $counter++; endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding:30px;">
                            <i class="fa fa-info-circle"></i> Nenhuma parcela encontrada
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Details Section -->
    <div class="section-title">
        <i class="fa fa-info-circle"></i>
        <?php echo trans('text_installment_details'); ?>
    </div>
    
    <div class="details-grid">
        <div class="detail-card">
            <div class="label"><?php echo trans('label_invoice_id'); ?></div>
            <div class="value highlight">#<?php echo $invoice['invoice_id']; ?></div>
        </div>
        <div class="detail-card">
            <div class="label"><?php echo trans('label_payable_amount'); ?></div>
            <div class="value"><?php echo currency_format($invoice['payable_amount']); ?></div>
        </div>
        <div class="detail-card">
            <div class="label"><?php echo trans('label_initial_payment'); ?></div>
            <div class="value success"><?php echo currency_format($invoice['initial_amount']); ?></div>
        </div>
        <div class="detail-card">
            <div class="label"><?php echo trans('label_due'); ?></div>
            <div class="value <?php echo $invoice['due'] > 0 ? 'danger' : 'success'; ?>">
                <?php echo currency_format($invoice['due']); ?>
            </div>
        </div>
        <div class="detail-card">
            <div class="label"><?php echo trans('label_interest'); ?></div>
            <div class="value"><?php echo number_format($invoice['interest_percentage'], 2); ?>%</div>
        </div>
        <div class="detail-card">
            <div class="label"><?php echo trans('label_interest_amount'); ?></div>
            <div class="value"><?php echo currency_format($invoice['interest_amount']); ?></div>
        </div>
        <div class="detail-card">
            <div class="label"><?php echo trans('label_duration'); ?></div>
            <div class="value"><?php echo $invoice['duration']; ?> dias</div>
        </div>
        <div class="detail-card">
            <div class="label"><?php echo trans('label_interval_count'); ?></div>
            <div class="value"><?php echo $invoice['interval_count']; ?> dias</div>
        </div>
        <div class="detail-card">
            <div class="label"><?php echo trans('label_installment_count'); ?></div>
            <div class="value highlight"><?php echo $invoice['installment_count']; ?> parcelas</div>
        </div>
        <div class="detail-card">
            <div class="label"><?php echo trans('label_payment_status'); ?></div>
            <div class="value">
                <span class="status-badge <?php echo $invoice['payment_status'];?>">
                    <?php echo $invoice['payment_status'] == 'paid' ? 'Quitado' : 'Em Aberto'; ?>
                </span>
            </div>
        </div>
        <div class="detail-card">
            <div class="label"><?php echo trans('label_last_installment_date'); ?></div>
            <div class="value"><?php echo date("d/m/Y H:i", strtotime($invoice['last_installment_date'])); ?></div>
        </div>
        <div class="detail-card">
            <div class="label"><?php echo trans('label_installment_end_date'); ?></div>
            <div class="value"><?php echo date("d/m/Y", strtotime($invoice['installment_end_date'])); ?></div>
        </div>
        <div class="detail-card full-width">
            <div class="label"><?php echo trans('label_created_at'); ?></div>
            <div class="value"><?php echo date("d/m/Y H:i", strtotime($invoice['created_at'])); ?></div>
        </div>
    </div>
</div>
