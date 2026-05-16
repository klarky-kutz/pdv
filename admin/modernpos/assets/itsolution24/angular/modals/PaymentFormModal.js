window.angularApp.factory("PaymentFormModal", ["API_URL", "window", "jQuery", "$http", "$uibModal", "$sce", "InvoiceViewModal", "PrintReceiptModal", "$rootScope", function (API_URL, window, $, $http, $uibModal, $sce, InvoiceViewModal, PrintReceiptModal, $scope) {
    return function($scope) {
        var uibModalInstance = $uibModal.open({
            animation: true,
            ariaLabelledBy: "modal-title",
            ariaDescribedBy: "modal-body",
            template: 
                // CSS Styles
                '<style>' +
                    // Modal layout
                    '.payment-modal .modal-dialog { width: 950px; max-width: 95%; margin: 30px auto; }' +
                    '.payment-modal .modal-content { border-radius: 12px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: visible; position: relative; }' +
                    // Close button - positioned relative to modal-content
                    '.payment-modal .btn-close-modal { position: absolute; top: -18px; right: -18px; width: 44px; height: 44px; background: #fff; border: 3px solid #ddd; border-radius: 50%; font-size: 18px; color: #666; cursor: pointer; box-shadow: 0 3px 15px rgba(0,0,0,0.3); z-index: 1070; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; line-height: 1; }' +
                    '.payment-modal .btn-close-modal:hover { background: #dc3545; color: #fff; border-color: #dc3545; transform: scale(1.1); }' +
                    '.payment-modal .modal-header { background: linear-gradient(135deg, #00a65a 0%, #026c3c 100%); border: none; padding: 20px 25px; border-radius: 12px 12px 0 0; }' +
                    '.payment-modal .modal-header .modal-title { color: #fff; font-size: 22px; font-weight: 600; display: flex; align-items: center; gap: 10px; }' +
                    '.payment-modal .modal-body { padding: 0; background: #f5f7fa; }' +
                    '.payment-modal .modal-footer { background: #fff; border-top: 1px solid #e9ecef; padding: 15px 25px; border-radius: 0 0 12px 12px; }' +
                    
                    // Layout
                    '.payment-content { display: flex; min-height: 480px; }' +
                    '.payment-left { flex: 0 0 58%; padding: 25px; background: #fff; border-right: 1px solid #e9ecef; }' +
                    '.payment-right { flex: 0 0 42%; padding: 25px; background: #f8fafc; }' +
                    
                    // Payment Methods Section - Dynamic grid based on count
                    '.section-title { font-size: 14px; font-weight: 600; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }' +
                    '.section-title i { color: #00a65a; }' +
                    
                    '.payment-methods { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-bottom: 20px; max-height: 200px; overflow-y: auto; padding: 5px; }' +
                    '.pmethod-card { position: relative; background: #fff; border: 2px solid #e9ecef; border-radius: 10px; padding: 12px 8px; text-align: center; cursor: pointer; transition: all 0.2s ease; min-height: 70px; }' +
                    '.pmethod-card:hover { border-color: #00a65a; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,166,90,0.15); }' +
                    '.pmethod-card.active { border-color: #00a65a; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); }' +
                    '.pmethod-card.active::after { content: "\\f00c"; font-family: FontAwesome; position: absolute; top: 5px; right: 5px; color: #00a65a; font-size: 11px; }' +
                    '.pmethod-card i { font-size: 22px; color: #00a65a; display: block; margin-bottom: 6px; }' +
                    '.pmethod-card span { font-size: 11px; font-weight: 600; color: #333; display: block; line-height: 1.2; }' +
                    
                    // Customer Credit Balance Badge
                    '.customer-credit-info { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); border-radius: 10px; padding: 15px; margin-bottom: 15px; color: #fff; display: none; }' +
                    '.customer-credit-info.show { display: block; }' +
                    '.customer-credit-info .credit-label { font-size: 12px; opacity: 0.9; margin-bottom: 5px; display: flex; align-items: center; gap: 6px; }' +
                    '.customer-credit-info .credit-value { font-size: 24px; font-weight: 700; }' +
                    '.customer-credit-info.insufficient { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }' +
                    '.customer-credit-info.sufficient { background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); }' +
                    
                    // Gift Card Section
                    '.giftcard-section { background: linear-gradient(135deg, #9c27b0 0%, #7b1fa2 100%); border-radius: 10px; padding: 15px; margin-bottom: 15px; color: #fff; display: none; }' +
                    '.giftcard-section.show { display: block; }' +
                    '.giftcard-section .giftcard-title { font-size: 14px; font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }' +
                    '.giftcard-section .giftcard-input-group { display: flex; gap: 10px; margin-bottom: 10px; }' +
                    '.giftcard-section .giftcard-input { flex: 1; padding: 10px 15px; border: 2px solid rgba(255,255,255,0.3); border-radius: 8px; background: rgba(255,255,255,0.15); color: #fff; font-size: 14px; }' +
                    '.giftcard-section .giftcard-input::placeholder { color: rgba(255,255,255,0.6); }' +
                    '.giftcard-section .giftcard-input:focus { outline: none; border-color: #fff; background: rgba(255,255,255,0.25); }' +
                    '.giftcard-section .btn-check-card { padding: 10px 20px; background: #fff; color: #9c27b0; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; }' +
                    '.giftcard-section .btn-check-card:hover { transform: scale(1.05); }' +
                    '.giftcard-section .giftcard-balance { background: rgba(255,255,255,0.15); border-radius: 8px; padding: 12px; margin-top: 10px; }' +
                    '.giftcard-section .giftcard-balance .balance-label { font-size: 12px; opacity: 0.9; }' +
                    '.giftcard-section .giftcard-balance .balance-value { font-size: 22px; font-weight: 700; }' +
                    '.giftcard-section .giftcard-balance.insufficient { background: rgba(220,53,69,0.3); }' +
                    '.giftcard-section .giftcard-balance.sufficient { background: rgba(40,167,69,0.3); }' +
                    '.giftcard-section .giftcard-error { background: rgba(220,53,69,0.3); padding: 10px; border-radius: 6px; margin-top: 10px; font-size: 13px; }' +
                    
                    // Amount Input
                    '.amount-section { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; padding: 20px; margin-bottom: 15px; }' +
                    '.amount-input-wrapper { position: relative; }' +
                    '.amount-input-wrapper .currency-symbol { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); font-size: 24px; font-weight: 600; color: #00a65a; }' +
                    '.amount-input { width: 100%; font-size: 32px; font-weight: 700; text-align: center; padding: 18px 20px 18px 50px; border: 3px solid #e9ecef; border-radius: 12px; background: #fff; color: #333; transition: all 0.2s ease; }' +
                    '.amount-input:focus { outline: none; border-color: #00a65a; box-shadow: 0 0 0 4px rgba(0,166,90,0.1); }' +
                    
                    // Quick Action Buttons
                    '.quick-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }' +
                    '.quick-btn { padding: 10px 15px; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 6px; }' +
                    '.quick-btn-success { background: linear-gradient(135deg, #00a65a 0%, #026c3c 100%); color: #fff; }' +
                    '.quick-btn-success:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,166,90,0.3); }' +
                    '.quick-btn-danger { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: #fff; }' +
                    '.quick-btn-danger:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(220,53,69,0.3); }' +
                    '.quick-btn-warning { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: #333; }' +
                    '.quick-btn-warning:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(255,193,7,0.3); }' +
                    
                    // Note Field
                    '.note-field { position: relative; margin-bottom: 12px; }' +
                    '.note-field input { width: 100%; padding: 10px 15px 10px 38px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 13px; transition: all 0.2s ease; }' +
                    '.note-field input:focus { outline: none; border-color: #00a65a; }' +
                    '.note-field i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #adb5bd; }' +
                    
                    // Split Payment
                    '.split-toggle { background: #fff; border: 2px solid #e9ecef; border-radius: 8px; padding: 10px 15px; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }' +
                    '.split-toggle:hover { border-color: #17a2b8; }' +
                    '.split-toggle.active { border-color: #17a2b8; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); }' +
                    '.split-toggle-label { font-weight: 600; color: #333; display: flex; align-items: center; gap: 8px; font-size: 13px; }' +
                    '.split-toggle-label i { color: #17a2b8; }' +
                    '.split-toggle-switch { width: 40px; height: 22px; background: #ccc; border-radius: 11px; position: relative; transition: all 0.2s ease; }' +
                    '.split-toggle-switch::after { content: ""; position: absolute; width: 18px; height: 18px; background: #fff; border-radius: 50%; top: 2px; left: 2px; transition: all 0.2s ease; }' +
                    '.split-toggle.active .split-toggle-switch { background: #17a2b8; }' +
                    '.split-toggle.active .split-toggle-switch::after { left: 20px; }' +
                    
                    '.split-payment-section { background: #fff; border: 2px solid #17a2b8; border-radius: 10px; padding: 12px; margin-bottom: 12px; }' +
                    '.split-payment-item { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; }' +
                    '.split-payment-item select { flex: 1; padding: 8px; border: 2px solid #e9ecef; border-radius: 6px; font-size: 13px; }' +
                    '.split-payment-item input { width: 100px; padding: 8px; border: 2px solid #e9ecef; border-radius: 6px; font-size: 13px; text-align: right; }' +
                    '.split-payment-item .btn-remove { width: 32px; height: 32px; padding: 0; border: none; background: #dc3545; color: #fff; border-radius: 6px; cursor: pointer; }' +
                    '.split-add-btn { width: 100%; padding: 8px; border: 2px dashed #17a2b8; background: transparent; color: #17a2b8; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 12px; transition: all 0.2s ease; }' +
                    '.split-add-btn:hover { background: #e3f2fd; }' +
                    '.split-summary { display: flex; justify-content: space-between; padding: 8px 0; border-top: 1px solid #e9ecef; margin-top: 8px; font-weight: 600; font-size: 13px; }' +
                    '.split-summary.success { color: #00a65a; }' +
                    '.split-summary.warning { color: #ffc107; }' +
                    '.split-summary.danger { color: #dc3545; }' +
                    
                    // Order Summary (Right Side)
                    '.order-summary { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); overflow: hidden; }' +
                    '.order-summary-header { background: linear-gradient(135deg, #343a40 0%, #212529 100%); color: #fff; padding: 12px 18px; }' +
                    '.order-summary-header h4 { margin: 0; font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; }' +
                    
                    '.order-items { max-height: 180px; overflow-y: auto; }' +
                    '.order-item { display: flex; justify-content: space-between; padding: 8px 12px; border-bottom: 1px solid #f1f3f4; font-size: 12px; }' +
                    '.order-item:last-child { border-bottom: none; }' +
                    '.order-item-name { flex: 1; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px; }' +
                    '.order-item-qty { color: #6c757d; margin: 0 8px; }' +
                    '.order-item-price { font-weight: 600; color: #333; }' +
                    
                    '.order-totals { background: #f8f9fa; padding: 12px; }' +
                    '.order-total-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 12px; color: #555; }' +
                    '.order-total-row.highlight { font-size: 15px; font-weight: 700; color: #00a65a; padding: 10px 0 5px 0; border-top: 2px solid #00a65a; margin-top: 6px; }' +
                    '.order-total-row.due { color: #dc3545; font-weight: 600; }' +
                    '.order-total-row.change { color: #17a2b8; font-weight: 600; }' +
                    
                    // Footer Buttons
                    '.payment-modal .btn-cancel { background: #6c757d; border: none; color: #fff; padding: 12px 25px; border-radius: 8px; font-weight: 600; transition: all 0.2s ease; }' +
                    '.payment-modal .btn-cancel:hover { background: #5a6268; }' +
                    '.payment-modal .btn-pay { background: linear-gradient(135deg, #00a65a 0%, #026c3c 100%); border: none; color: #fff; padding: 12px 35px; border-radius: 8px; font-weight: 600; font-size: 16px; transition: all 0.2s ease; }' +
                    '.payment-modal .btn-pay:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,166,90,0.4); }' +
                    '.payment-modal .btn-pay:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }' +

                    // Customer Info Badge
                    '.customer-badge { background: rgba(255,255,255,0.15); border-radius: 20px; padding: 5px 15px; font-size: 13px; margin-left: auto; }' +

                    // Installment section
                    '.installment-section { background: #fff3cd; border: 2px solid #ffc107; border-radius: 10px; padding: 12px; margin-top: 12px; }' +
                    '.installment-section h5 { color: #856404; margin: 0 0 12px 0; font-weight: 600; font-size: 14px; }' +
                    '.installment-row { display: flex; gap: 10px; margin-bottom: 8px; }' +
                    '.installment-field { flex: 1; }' +
                    '.installment-field label { display: block; font-size: 11px; color: #6c757d; margin-bottom: 4px; }' +
                    '.installment-field input { width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; text-align: center; font-size: 13px; }' +

                    // Responsive
                    '@media (max-width: 768px) { .payment-content { flex-direction: column; } .payment-left, .payment-right { flex: none; width: 100%; } .payment-methods { grid-template-columns: repeat(3, 1fr); } }' +
                '</style>' +
                
                // Modal Header with close button
                '<div class="modal-header">' +
                    '<button ng-click="closePaymentFormModal();" type="button" class="btn-close-modal" title="Fechar">&times;</button>' +
                    '<h4 class="modal-title">' +
                        '<i class="fa fa-shopping-cart"></i> Finalizar Venda' +
                        '<span class="customer-badge"><i class="fa fa-user"></i> {{ customerName || "Cliente" }}</span>' +
                    '</h4>' +
                '</div>' +
                
                // Modal Body
                '<div class="modal-body">' +
                    '<form id="checkout-form" action="place_order.php">' +
                        // Hidden Fields
                        '<input type="hidden" name="invoice-id" value="{{ invoiceId || \'\'}}">' +
                        '<input type="hidden" name="created-at" value="{{ createdAt || \'\'}}">' +
                        '<input type="hidden" name="customer-id" value="{{ customerId || 1 }}">' +
                        '<input type="hidden" name="salesman-id" value="{{ salesmanId || \'\'}}">' +
                        '<input type="hidden" name="customer-mobile-number" value="{{ customerMobileNumber || \'\'}}">' +
                        '<input type="hidden" name="pmethod-id" value="{{ pmethodId || 1 }}">' +
                        '<input type="hidden" name="is_installment_order" value="{{ isInstallmentOrder || 0 }}">' +
                        '<input type="hidden" name="qref" value="{{ qRef || \'\'}}">' +
                        '<input type="hidden" name="split_payment" value="{{ splitPaymentEnabled ? \'1\' : \'0\' }}">' +
                        '<input type="hidden" name="sub-total" value="{{ totalAmount || 0 }}">' +
                        '<input type="hidden" name="discount-type" value="{{ discountType || \'plain\' }}">' +
                        '<input type="hidden" name="discount-amount" value="{{ discountType == \'percentage\' ? _percentage(totalAmount, discountAmount) : (discountAmount || 0) }}">' +
                        '<input type="hidden" name="tax-amount" value="{{ taxAmount || 0 }}">' +
                        '<input type="hidden" name="shipping-type" value="{{ shippingType || \'plain\' }}">' +
                        '<input type="hidden" name="shipping-amount" value="{{ shippingType == \'percentage\' ? _percentage(totalAmount, shippingAmount) : (shippingAmount || 0) }}">' +
                        '<input type="hidden" name="others-charge" value="{{ othersCharge || 0 }}">' +
                        '<input type="hidden" name="previous-due" value="{{ dueAmount || 0 }}">' +
                        '<input type="hidden" name="payable-amount" value="{{ totalPayable || 0 }}">' +
                        '<input type="hidden" name="payment_details[card_no]" value="{{ giftCardNumber || \'\'}}" ng-if="showGiftCardSection && giftCardValidated">' +
                        
                        // Split payment hidden fields
                        '<div ng-repeat="sp in splitPayments track by $index">' +
                            '<input type="hidden" name="split_payments[{{ $index }}][pmethod_id]" value="{{ sp.pmethod_id }}">' +
                            '<input type="hidden" name="split_payments[{{ $index }}][amount]" value="{{ sp.amount }}">' +
                        '</div>' +
                        
                        // Product items hidden fields
                        '<div ng-repeat="items in itemArray">' +
                            '<input type="hidden" name="product-item[{{ items.id }}][p_type]" value="{{ items.pType }}">' +
                            '<input type="hidden" name="product-item[{{ items.id }}][item_id]" value="{{ items.id }}">' +
                            '<input type="hidden" name="product-item[{{ items.id }}][category_id]" value="{{ items.categoryId }}">' +
                            '<input type="hidden" name="product-item[{{ items.id }}][sup_id]" value="{{ items.supId }}">' +
                            '<input type="hidden" name="product-item[{{ items.id }}][item_name]" value="{{ items.name }}">' +
                            '<input type="hidden" name="product-item[{{ items.id }}][item_price]" value="{{ items.price | formatDecimal:2 }}">' +
                            '<input type="hidden" name="product-item[{{ items.id }}][item_quantity]" value="{{ items.quantity }}">' +
                            '<input type="hidden" name="product-item[{{ items.id }}][item_total]" value="{{ items.subTotal | formatDecimal:2 }}">' +
                        '</div>' +
                        
                        '<div class="payment-content">' +
                            // Left Side - Payment Options
                            '<div class="payment-left">' +
                // Payment Methods
                                '<div class="section-title"><i class="fa fa-credit-card"></i> Forma de Pagamento</div>' +
                                
                                // Customer Credit Balance Display
                                '<div class="customer-credit-info" ng-class="{\'show\': showCreditInfo, \'insufficient\': customerBalance < totalPayable, \'sufficient\': customerBalance >= totalPayable}">' +
                                    '<div class="credit-label"><i class="bi bi-wallet2"></i> Crédito Disponível do Cliente</div>' +
                                    '<div class="credit-value">R$ {{ customerBalance | number:2 }}</div>' +
                                    '<small ng-show="customerBalance < totalPayable" style="opacity:0.9;"><i class="fa fa-exclamation-triangle"></i> Saldo insuficiente para esta compra</small>' +
                                    '<small ng-show="customerBalance >= totalPayable" style="opacity:0.9;"><i class="fa fa-check-circle"></i> Saldo suficiente</small>' +
                                '</div>' +
                                
                                // Gift Card Section
                                '<div class="giftcard-section" ng-class="{\'show\': showGiftCardSection}">' +
                                    '<div class="giftcard-title"><i class="fa fa-gift"></i> Vale Presente</div>' +
                                    '<div class="giftcard-input-group">' +
                                        '<input type="text" class="giftcard-input" ng-model="giftCardNumber" placeholder="Digite o número do cartão" ng-disabled="giftCardValidated">' +
                                        '<button type="button" class="btn-check-card" ng-click="checkGiftCard()" ng-hide="giftCardValidated"><i class="fa fa-search"></i> Verificar</button>' +
                                        '<button type="button" class="btn-check-card" ng-click="clearGiftCard()" ng-show="giftCardValidated" style="background:#dc3545;color:#fff;"><i class="fa fa-times"></i> Limpar</button>' +
                                    '</div>' +
                                    '<div ng-show="giftCardError" class="giftcard-error"><i class="fa fa-exclamation-circle"></i> {{ giftCardError }}</div>' +
                                    '<div ng-show="giftCardValidated" class="giftcard-balance" ng-class="{\'insufficient\': giftCardBalance < totalPayable, \'sufficient\': giftCardBalance >= totalPayable}">' +
                                        '<div class="balance-label">Saldo do Cartão</div>' +
                                        '<div class="balance-value">R$ {{ giftCardBalance | number:2 }}</div>' +
                                        '<small ng-show="giftCardBalance < totalPayable"><i class="fa fa-exclamation-triangle"></i> Saldo insuficiente</small>' +
                                        '<small ng-show="giftCardBalance >= totalPayable"><i class="fa fa-check-circle"></i> Saldo suficiente - será debitado automaticamente</small>' +
                                    '</div>' +
                                '</div>' +
                                
                                '<div class="payment-methods" ng-show="paymentMethods.length > 0">' +
                                '<div ng-repeat="pm in paymentMethods" ' +
                                         'class="pmethod-card" ' +
                                         'ng-class="{\'active\': pmethodId == pm.id && !splitPaymentEnabled}" ' +
                                         'ng-click="selectPaymentMethod(pm.id, pm.code)">' +
                                        '<i ng-class="getPaymentIconClass(pm.code)"></i>' +
                                        '<span>{{ pm.name }}</span>' +
                                    '</div>' +
                                '</div>' +
                                '<div ng-show="paymentMethods.length == 0" style="text-align:center;padding:20px;color:#999;">' +
                                    '<i class="fa fa-spinner fa-spin fa-2x"></i><br><small>Carregando...</small>' +
                                '</div>' +
                                
                                // Split Payment Toggle
                                '<div class="split-toggle" ng-class="{\'active\': splitPaymentEnabled}" ng-click="toggleSplitPayment()">' +
                                    '<div class="split-toggle-label"><i class="fa fa-exchange"></i> Pagamento Dividido</div>' +
                                    '<div class="split-toggle-switch"></div>' +
                                '</div>' +
                                
                                // Split Payment Section
                                '<div ng-show="splitPaymentEnabled" class="split-payment-section">' +
                                    '<div ng-repeat="sp in splitPayments track by $index" class="split-payment-item">' +
                                        '<select ng-model="sp.pmethod_id" ng-options="p.id as p.name for p in availablePmethods">' +
                                            '<option value="">Selecione...</option>' +
                                        '</select>' +
                                        '<input type="text" ng-model="sp.amount" placeholder="0.00" onkeypress="return IsNumeric(event);" ng-change="updateSplitPaidAmount()">' +
                                        '<button type="button" class="btn-remove" ng-click="removeSplitPayment($index)" ng-show="splitPayments.length > 1"><i class="fa fa-trash"></i></button>' +
                                    '</div>' +
                                    '<button type="button" class="split-add-btn" ng-click="addSplitPayment()"><i class="fa fa-plus"></i> Adicionar Forma de Pagamento</button>' +
                                    '<div class="split-summary" ng-class="{\'success\': getSplitTotal() >= totalPayable, \'warning\': getSplitTotal() < totalPayable && getSplitTotal() > 0, \'danger\': getSplitTotal() == 0}">' +
                                        '<span>Total Informado:</span>' +
                                        '<span>R$ {{ getSplitTotal() | number:2 }}</span>' +
                                    '</div>' +
                                '</div>' +
                                
                                // Amount Section (Normal Payment)
                                '<div ng-hide="splitPaymentEnabled" class="amount-section">' +
                                    '<div class="section-title"><i class="fa fa-money"></i> Valor Recebido</div>' +
                                    '<div class="quick-actions">' +
                                        '<button type="button" class="quick-btn quick-btn-success" ng-click="setFullPayment()">' +
                                            '<i class="fa fa-check-circle"></i> Pagar Total' +
                                        '</button>' +
                                        '<button type="button" class="quick-btn quick-btn-danger" ng-click="setZeroPayment()">' +
                                            '<i class="fa fa-clock-o"></i> Deixar Pendente' +
                                        '</button>' +
                                    '</div>' +
                                    '<div class="amount-input-wrapper">' +
                                        '<span class="currency-symbol">R$</span>' +
                                        '<input type="text" class="amount-input" name="paid-amount" ng-model="paidAmount" ' +
                                               'placeholder="0,00" ng-keypress="checkoutWhilePressEnter($event)" ' +
                                               'onkeypress="return IsNumeric(event);" ondrop="return false;" onpaste="return false;" ' +
                                               'onClick="this.select();" autofocus>' +
                                    '</div>' +
                                '</div>' +
                                
                                // Note Field
                                '<div class="note-field">' +
                                    '<i class="fa fa-pencil"></i>' +
                                    '<input type="text" name="invoice-note" value="{{ invoiceNote }}" placeholder="Observações (opcional)">' +
                                '</div>' +
                                
                                // Installment Toggle & Section
                                '<div ng-show="isInstallment">' +
                                    '<button type="button" class="quick-btn" style="width: 100%; margin-bottom: 10px;" ' +
                                            'ng-class="{\'quick-btn-warning\': isInstallmentOrder}" ' +
                                            'ng-style="{\'background\': !isInstallmentOrder ? \'#6c757d\' : null, \'color\': !isInstallmentOrder ? \'#fff\' : null}" ' +
                                            'ng-click="sellWithInstallment()">' +
                                        '<i class="fa fa-refresh"></i> {{ isInstallmentOrder ? "Parcelamento Ativo" : "Ativar Parcelamento" }}' +
                                    '</button>' +
                                '</div>' +
                                
                                '<div ng-show="isInstallment && isInstallmentOrder" class="installment-section">' +
                                    '<h5><i class="fa fa-calendar"></i> Configuração do Parcelamento</h5>' +
                                    '<div class="installment-row">' +
                                        '<div class="installment-field">' +
                                            '<label>Duração (dias)</label>' +
                                            '<input type="text" name="installment_duration" ng-model="installmentDuration" onclick="this.select();" onkeypress="return IsNumeric(event);">' +
                                        '</div>' +
                                        '<div class="installment-field">' +
                                            '<label>Intervalo (dias)</label>' +
                                            '<input type="text" name="installment_interval_count" ng-model="installmentIntervalCount" onclick="this.select();" onkeypress="return IsNumeric(event);">' +
                                        '</div>' +
                                    '</div>' +
                                    '<div class="installment-row">' +
                                        '<div class="installment-field">' +
                                            '<label>Nº Parcelas</label>' +
                                            '<input type="text" name="installment_count" value="{{ installmentDuration/installmentIntervalCount | formatDecimal:0 }}" readonly style="background:#f5f5f5;">' +
                                        '</div>' +
                                        '<div class="installment-field">' +
                                            '<label>Juros (%)</label>' +
                                            '<input type="text" name="installment_interest_percentage" ng-model="installmentInterestPercentage" onclick="this.select();" onkeypress="return IsNumeric(event);">' +
                                        '</div>' +
                                        '<div class="installment-field">' +
                                            '<label>Valor Juros</label>' +
                                            '<input type="text" name="installment_interest_amount" value="{{ installmentInterestAmount | formatDecimal:2 }}" readonly style="background:#f5f5f5;">' +
                                        '</div>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                            
                            // Right Side - Order Summary
                            '<div class="payment-right">' +
                                '<div class="order-summary">' +
                                    '<div class="order-summary-header">' +
                                        '<h4><i class="fa fa-file-text-o"></i> Resumo do Pedido</h4>' +
                                    '</div>' +
                                    '<div class="order-items">' +
                                        '<div ng-repeat="items in itemArray" class="order-item">' +
                                            '<span class="order-item-name" title="{{ items.name }}">{{ items.name }}</span>' +
                                            '<span class="order-item-qty">x{{ items.quantity }}</span>' +
                                            '<span class="order-item-price">{{ items.subTotal | formatDecimal:2 }}</span>' +
                                        '</div>' +
                                    '</div>' +
                                    '<div class="order-totals">' +
                                        '<div class="order-total-row">' +
                                            '<span>Subtotal ({{ totalItem }} itens)</span>' +
                                            '<span>{{ totalAmount | formatDecimal:2 }}</span>' +
                                        '</div>' +
                                        '<div class="order-total-row" ng-show="discountAmount > 0">' +
                                            '<span>Desconto {{ discountType == \'percentage\' ? \'(\' + discountAmount + \'%)\' : \'\' }}</span>' +
                                            '<span style="color:#dc3545">-{{ discountType == \'percentage\' ? (_percentage(totalAmount, discountAmount) | formatDecimal:2) : (discountAmount | formatDecimal:2) }}</span>' +
                                        '</div>' +
                                        '<div class="order-total-row" ng-show="taxAmount > 0">' +
                                            '<span>Impostos</span>' +
                                            '<span>{{ taxAmount | formatDecimal:2 }}</span>' +
                                        '</div>' +
                                        '<div class="order-total-row" ng-show="shippingAmount > 0">' +
                                            '<span>Frete {{ shippingType == \'percentage\' ? \'(\' + shippingAmount + \'%)\' : \'\' }}</span>' +
                                            '<span>{{ shippingType == \'percentage\' ? (_percentage(totalAmount, shippingAmount) | formatDecimal:2) : (shippingAmount | formatDecimal:2) }}</span>' +
                                        '</div>' +
                                        '<div class="order-total-row" ng-show="othersCharge > 0">' +
                                            '<span>Outras Taxas</span>' +
                                            '<span>{{ othersCharge | formatDecimal:2 }}</span>' +
                                        '</div>' +
                                        '<div class="order-total-row" ng-show="installmentInterestAmount > 0">' +
                                            '<span>Juros Parcelamento</span>' +
                                            '<span>{{ installmentInterestAmount | formatDecimal:2 }}</span>' +
                                        '</div>' +
                                        '<div class="order-total-row" ng-show="dueAmount > 0">' +
                                            '<span>Débito Anterior</span>' +
                                            '<span style="color:#dc3545">{{ dueAmount | formatDecimal:2 }}</span>' +
                                        '</div>' +
                                        '<div class="order-total-row highlight">' +
                                            '<span>TOTAL A PAGAR</span>' +
                                            '<span>R$ {{ totalPayable | formatDecimal:2 }}</span>' +
                                        '</div>' +
                                        '<div class="order-total-row">' +
                                            '<span>Valor Recebido</span>' +
                                            '<span>{{ paidAmount | formatDecimal:2 }}</span>' +
                                        '</div>' +
                                        '<div class="order-total-row due" ng-show="totalPayable > paidAmount">' +
                                            '<span><i class="fa fa-exclamation-circle"></i> Valor Pendente</span>' +
                                            '<span>R$ {{ totalPayable - paidAmount | formatDecimal:2 }}</span>' +
                                        '</div>' +
                                        '<div class="order-total-row change" ng-show="paidAmount > totalPayable">' +
                                            '<span><i class="fa fa-exchange"></i> Troco</span>' +
                                            '<span>R$ {{ paidAmount - totalPayable | formatDecimal:2 }}</span>' +
                                        '</div>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</form>' +
                '</div>' +
                
                // Modal Footer
                '<div class="modal-footer">' +
                    '<button ng-click="closePaymentFormModal();" type="button" class="btn btn-cancel"><i class="fa fa-times"></i> Cancelar</button>' +
                    '<button ng-click="checkout();" type="button" class="btn btn-pay" ng-disabled="processing">' +
                        '<i class="fa" ng-class="{\'fa-spinner fa-spin\': processing, \'fa-check\': !processing}"></i> ' +
                        '{{ processing ? "Processando..." : "Finalizar Venda" }}' +
                    '</button>' +
                '</div>',
            controller: function ($scope, $uibModalInstance) {
                $scope.processing = false;
                $scope.paymentMethods = [];
                $scope.availablePmethods = [];
                $scope.splitPaymentEnabled = false;
                $scope.splitPayments = [];
                
                // Initialize paid amount
                if (!$scope.paidAmount && $scope.paidAmount !== 0) {
                    $scope.paidAmount = $scope.totalPayable || 0;
                }
                
                // Load payment methods
                $http({
                    url: window.baseUrl + "_inc/payment.php?action_type=FETCHALL",
                    method: "GET"
                }).then(function(response) {
                    console.log('Payment methods response:', response.data);
                    var pmethodsData = [];
                    
                    // Handle different response formats
                    if (response.data && response.data.data && Array.isArray(response.data.data)) {
                        pmethodsData = response.data.data;
                    } else if (response.data && Array.isArray(response.data)) {
                        pmethodsData = response.data;
                    }
                    
                    if (pmethodsData.length > 0) {
                        $scope.paymentMethods = pmethodsData.map(function(p) {
                            return {
                                id: String(p.pmethod_id || p.id),
                                name: p.name,
                                code: p.code_name || p.code || 'cash'
                            };
                        });
                        $scope.availablePmethods = $scope.paymentMethods;
                        
                        // Select first payment method
                        if ($scope.paymentMethods.length > 0 && !$scope.pmethodId) {
                            $scope.selectPaymentMethod($scope.paymentMethods[0].id, $scope.paymentMethods[0].code);
                        }
                    } else {
                        console.warn('Nenhum método de pagamento retornado');
                        // Fallback: criar método padrão
                        $scope.paymentMethods = [{
                            id: '1',
                            name: 'Dinheiro',
                            code: 'cash'
                        }];
                        $scope.availablePmethods = $scope.paymentMethods;
                        $scope.selectPaymentMethod('1', 'cash');
                    }
                }, function(error) {
                    console.error('Erro ao carregar métodos de pagamento:', error);
                    // Fallback em caso de erro
                    $scope.paymentMethods = [{
                        id: '1',
                        name: 'Dinheiro',
                        code: 'cash'
                    }];
                    $scope.availablePmethods = $scope.paymentMethods;
                    $scope.selectPaymentMethod('1', 'cash');
                });

                // Get customer balance for credit payment
                $scope.customerBalance = 0;
                $scope.showCreditInfo = false;
                
                // Gift Card variables
                $scope.showGiftCardSection = false;
                $scope.giftCardNumber = '';
                $scope.giftCardBalance = 0;
                $scope.giftCardValidated = false;
                $scope.giftCardError = '';
                
                if ($scope.customerId) {
                    $http({
                        url: window.baseUrl + "_inc/pos.php?customer_id=" + $scope.customerId + "&action_type=CUSTOMER",
                        method: "GET"
                    }).then(function(response) {
                        if (response.data && response.data.balance) {
                            $scope.customerBalance = parseFloat(response.data.balance);
                        }
                    });
                }
                
                // Check Gift Card
                $scope.checkGiftCard = function() {
                    if (!$scope.giftCardNumber) {
                        $scope.giftCardError = 'Digite o número do cartão';
                        return;
                    }
                    
                    $scope.giftCardError = '';
                    
                    $http({
                        url: window.baseUrl + "_inc/ajax.php?type=GIFTCARDINFO&card_no=" + encodeURIComponent($scope.giftCardNumber) + "&customer_id=" + $scope.customerId,
                        method: "GET"
                    }).then(function(res) {
                        if (res.data && res.data.balance !== undefined) {
                            $scope.giftCardBalance = parseFloat(res.data.balance);
                            $scope.giftCardValidated = true;
                            
                            if ($scope.giftCardBalance >= $scope.totalPayable) {
                                $scope.paidAmount = $scope.totalPayable;
                                window.toastr.success('Cartão validado! Saldo: R$ ' + $scope.giftCardBalance.toFixed(2), 'Sucesso');
                            } else {
                                $scope.giftCardValidated = true; // Still validated, just insufficient
                                window.toastr.warning('Saldo insuficiente no cartão. Disponível: R$ ' + $scope.giftCardBalance.toFixed(2), 'Atenção');
                            }
                        } else {
                            $scope.giftCardError = 'Cartão não encontrado ou não pertence a este cliente';
                            window.toastr.error('Cartão inválido', 'Erro');
                        }
                    }, function(err) {
                        var errorMsg = (err.data && err.data.errorMsg) ? err.data.errorMsg : 'Cartão não encontrado ou expirado';
                        $scope.giftCardError = errorMsg;
                        window.toastr.error(errorMsg, 'Erro');
                    });
                };
                
                // Clear Gift Card
                $scope.clearGiftCard = function() {
                    $scope.giftCardNumber = '';
                    $scope.giftCardBalance = 0;
                    $scope.giftCardValidated = false;
                    $scope.giftCardError = '';
                };

                // Função que retorna a classe completa do ícone (suporta FA e Bootstrap Icons)
                $scope.getPaymentIconClass = function(code) {
                    if (!code) return 'fa fa-money';
                    var codeLower = code.toLowerCase();
                    
                // Mapeamento de ícones - agora com suporte a Bootstrap Icons para débito
                    var icons = {
                        // English codes
                        'cash': 'fa fa-money',
                        'credit_card': 'fa fa-credit-card',
                        'debit_card': 'bi bi-credit-card-2-back',
                        'pix': 'fa fa-qrcode',
                        'bank_transfer': 'fa fa-university',
                        'cheque': 'fa fa-file-text-o',
                        'credit': 'bi bi-wallet2',
                        'gift_card': 'fa fa-gift',
                        'other': 'fa fa-ellipsis-h',
                        'cod': 'bi bi-truck',
                        'bkash': 'fa fa-mobile',
                        // Portuguese codes
                        'dinheiro': 'fa fa-money',
                        'cartao_credito': 'fa fa-credit-card',
                        'card_credit': 'fa fa-credit-card',
                        'cartao_debito': 'bi bi-credit-card-2-back',
                        'card_debit': 'bi bi-credit-card-2-back',
                        'transferencia': 'fa fa-university',
                        'boleto': 'fa fa-barcode',
                        'credito_conta': 'bi bi-wallet2',
                        'credito_em_conta': 'bi bi-wallet2',
                        'store_credit': 'bi bi-wallet2',
                        'pagamento_entrega': 'bi bi-truck',
                        'pagamento_na_entrega': 'bi bi-truck',
                        'delivery': 'bi bi-truck',
                        // Generic
                        'credito': 'fa fa-credit-card',
                        'debito': 'bi bi-credit-card-2-back'
                    };
                    
                    // Direct match
                    if (icons[codeLower]) return icons[codeLower];
                    
                    // Partial match - check specific patterns first
                    if (codeLower.includes('conta') || codeLower.includes('store_credit')) return 'bi bi-wallet2';
                    if (codeLower.includes('entrega') || codeLower.includes('delivery') || codeLower.includes('cod')) return 'bi bi-truck';
                    if (codeLower.includes('credit') || codeLower.includes('credito')) return 'fa fa-credit-card';
                    if (codeLower.includes('debit') || codeLower.includes('debito')) return 'bi bi-credit-card-2-back';
                    if (codeLower.includes('pix')) return 'fa fa-qrcode';
                    if (codeLower.includes('cash') || codeLower.includes('dinheiro')) return 'fa fa-money';
                    if (codeLower.includes('transfer') || codeLower.includes('bank')) return 'fa fa-university';
                    if (codeLower.includes('gift') || codeLower.includes('vale')) return 'fa fa-gift';
                    if (codeLower.includes('cheque')) return 'fa fa-file-text-o';
                    if (codeLower.includes('boleto')) return 'fa fa-barcode';
                    
                    return 'fa fa-money';
                };

                // Manter compatibilidade com a função antiga (retorna apenas o nome do ícone FA)
                $scope.getPaymentIcon = function(code) {
                    var fullClass = $scope.getPaymentIconClass(code);
                    // Se for Bootstrap Icons, retorna fa-credit-card como fallback
                    if (fullClass.startsWith('bi ')) return 'fa-credit-card';
                    return fullClass.replace('fa ', '');
                };

                $scope.sellWithInstallment = function() {
                    $scope.isInstallmentOrder = $scope.isInstallmentOrder == 0 ? 1 : 0;
                };

                $scope.selectPaymentMethod = function(pmethodId, pmethodCode) {
                    if ($scope.splitPaymentEnabled) return;
                    $scope.pmethodId = pmethodId;
                    $scope.pmethodCode = pmethodCode;
                    
                    // Find method name
                    var method = $scope.paymentMethods.find(function(m) { return m.id == pmethodId; });
                    $scope.pmethodName = method ? method.name : '';
                    
                    // Handle credit payment - show credit info panel
                    var codeLower = (pmethodCode || '').toLowerCase();
                    
                    // Reset all special payment sections
                    $scope.showCreditInfo = false;
                    $scope.showGiftCardSection = false;
                    
                    // Credit payment
                    if (codeLower == 'credit' || codeLower.includes('credito_conta') || codeLower.includes('store_credit') || codeLower.includes('credito_em_conta')) {
                        $scope.showCreditInfo = true;
                        $scope.clearGiftCard();
                        if (parseFloat($scope.customerBalance || 0) < parseFloat($scope.totalPayable)) {
                            window.toastr.warning("Saldo do cliente insuficiente! Disponível: R$ " + ($scope.customerBalance || 0).toFixed(2), "Atenção");
                        } else {
                            $scope.paidAmount = $scope.totalPayable;
                            window.toastr.success("Crédito disponível: R$ " + $scope.customerBalance.toFixed(2), "Saldo Suficiente");
                        }
                    }
                    // Gift Card payment
                    else if (codeLower == 'gift_card' || codeLower.includes('vale_presente') || codeLower.includes('giftcard') || codeLower.includes('gift')) {
                        $scope.showGiftCardSection = true;
                        $scope.clearGiftCard();
                        window.toastr.info("Digite o número do Vale Presente para continuar", "Vale Presente");
                    }
                };

                // Split Payment Functions
                $scope.toggleSplitPayment = function() {
                    $scope.splitPaymentEnabled = !$scope.splitPaymentEnabled;
                    if ($scope.splitPaymentEnabled && $scope.splitPayments.length === 0) {
                        // Calcular valor dividido igualmente entre os dois primeiros pagamentos
                        var halfValue = Math.round(($scope.totalPayable / 2) * 100) / 100;
                        var firstValue = halfValue;
                        var secondValue = Math.round(($scope.totalPayable - halfValue) * 100) / 100;
                        
                        // Adicionar os dois pagamentos com valores já preenchidos
                        $scope.splitPayments.push({
                            pmethod_id: '',
                            amount: firstValue
                        });
                        $scope.splitPayments.push({
                            pmethod_id: '',
                            amount: secondValue
                        });
                    }
                    $scope.updateSplitPaidAmount();
                };

                $scope.addSplitPayment = function() {
                    // Novos pagamentos adicionados manualmente vêm com valor zero
                    $scope.splitPayments.push({
                        pmethod_id: '',
                        amount: 0
                    });
                };

                $scope.removeSplitPayment = function(index) {
                    if ($scope.splitPayments.length > 1) {
                        $scope.splitPayments.splice(index, 1);
                        $scope.updateSplitPaidAmount();
                    }
                };

                $scope.getSplitTotal = function() {
                    var total = 0;
                    for (var i = 0; i < $scope.splitPayments.length; i++) {
                        total += parseFloat($scope.splitPayments[i].amount) || 0;
                    }
                    return total;
                };

                $scope.updateSplitPaidAmount = function() {
                    if ($scope.splitPaymentEnabled) {
                        $scope.paidAmount = $scope.getSplitTotal();
                    }
                };

                $scope.setFullPayment = function() {
                    $scope.paidAmount = $scope.totalPayable;
                };

                $scope.setZeroPayment = function() {
                    $scope.paidAmount = 0;
                };

                $scope.checkout = function() {
                    if ($scope.processing) return;
                    
                    // Gift Card validation
                    if ($scope.showGiftCardSection) {
                        if (!$scope.giftCardValidated) {
                            window.toastr.error("Por favor, verifique o Vale Presente antes de continuar", "Atenção");
                            return;
                        }
                        if ($scope.giftCardBalance < $scope.totalPayable) {
                            window.toastr.error("Saldo insuficiente no Vale Presente", "Atenção");
                            return;
                        }
                    }
                    
                    // Validations
                    if ($scope.splitPaymentEnabled) {
                        var hasEmptyMethod = $scope.splitPayments.some(function(sp) {
                            return !sp.pmethod_id || parseFloat(sp.amount) <= 0;
                        });
                        if (hasEmptyMethod) {
                            window.toastr.warning("Preencha todos os campos do pagamento dividido", "Atenção");
                            return;
                        }
                    }
                    
                    $scope.processing = true;
                    $(document).find(".modal").addClass("overlay-loader");
                    
                    var form = $("#checkout-form");
                    var actionUrl = form.attr("action");
                    var data = form.serialize();
                    
                    $http({
                        url: window.baseUrl + "_inc/" + actionUrl,
                        method: "POST",
                        data: data,
                        cache: false,
                        processData: false,
                        contentType: false,
                        dataType: "json"
                    }).then(function(response) {
                        window.onbeforeunload = null;
                        $scope.processing = false;
                        $(document).find(".modal").removeClass("overlay-loader");
                        
                        // Extrair invoice_id da resposta (tentar vários nomes possíveis)
                        var resData = response.data || {};
                        $scope.invoiceId = resData.invoice_id || resData.invoiceId || resData.id || resData.sell_id;
                        
                        console.log('Checkout response:', resData);
                        console.log('Invoice ID:', $scope.invoiceId);
                        
                        $scope.invoiceInfo = resData.invoice_info;
                        $scope.invoiceItems = resData.invoice_items;
                        $scope.done = true;
                        
                        if (window.store.sound_effect == 1) {
                            window.storeApp.playSound("modify.mp3");
                        }
                        
                        if (window.store.auto_print == 1 && window.store.remote_printing == 1) {
                            PrintReceiptModal($scope);
                        }
                        
                        if (window.getParameterByName("holding_id") || window.getParameterByName("qref")) {
                            window.swal({
                                title: "Sucesso!",
                                text: "Venda #" + $scope.invoiceId + " finalizada com sucesso!",
                                type: "success",
                                timer: 3000,
                                showConfirmButton: false
                            }).then(function() {
                                window.location = "pos.php";
                            });
                        } else {
                            if (window.settings.after_sell_page == 'receipt_in_new_window') {
                                window.open(window.baseUrl + "admin/view_invoice.php?invoice_id=" + $scope.invoiceId);
                            } else if (window.settings.after_sell_page == 'receipt_in_popup') {
                                InvoiceViewModal({'invoice_id': $scope.invoiceId});
                            } else if (window.settings.after_sell_page == 'toastr_msg') {
                                window.toastr.success("Venda #" + $scope.invoiceId + " finalizada!", "Sucesso");
                            } else {
                                window.swal("Sucesso!", "Venda #" + $scope.invoiceId + " finalizada com sucesso!", "success");
                            }
                        }
                        
                        // Send SMS if configured
                        if ($scope.customerMobileNumber && window.settings.invoice_auto_sms == '1') {
                            $http({
                                url: window.baseUrl + "_inc/sms/index.php",
                                method: "POST",
                                data: "phone_number=" + $scope.customerMobileNumber + "&invoice_id=" + $scope.invoiceId + "&action_type=SEND",
                                cache: false,
                                processData: false,
                                contentType: false,
                                dataType: "json"
                            }).then(function() {
                                window.toastr.success("SMS enviado para: " + $scope.customerMobileNumber, "Sucesso");
                            });
                        }
                        
                        $scope.resetPos();
                        $scope.closePaymentFormModal();
                        
                    }, function(response) {
                        $scope.processing = false;
                        $(document).find(".modal").removeClass("overlay-loader");
                        
                        if (window.store.sound_effect == 1) {
                            window.storeApp.playSound("error.mp3");
                        }
                        window.swal("Erro!", response.data.errorMsg || "Erro ao processar pagamento", "error");
                    });
                };

                $scope.checkoutWhilePressEnter = function($event) {
                    if (($event.keyCode || $event.which) == 13) {
                        $scope.checkout();
                    }
                };

                $scope.closePaymentFormModal = function() {
                    $uibModalInstance.dismiss("cancel");
                };

                $scope.$watch('installmentInterestPercentage', function() {
                    if ($scope.payable) {
                        $scope.installmentInterestAmount = ($scope.installmentInterestPercentage / 100) * $scope.payable;
                        if (typeof $scope._calcTotalPayable === 'function') {
                            $scope._calcTotalPayable($scope);
                        }
                    }
                }, true);
            },
            scope: $scope,
            size: "lg",
            windowClass: "payment-modal",
            backdrop: "static",
            keyboard: true,
        });

        uibModalInstance.result.catch(function() { 
            uibModalInstance.close(); 
        });
    };
}]);
