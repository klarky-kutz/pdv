window.angularApp.factory("HoldingOrderModal", ["API_URL", "window", "jQuery", "$http", "$uibModal", "$sce", "$rootScope", 
    function (API_URL, window, $, $http, $uibModal, $sce, $scope) {
        return function($scope) {
            var uibModalInstance = $uibModal.open({
                animation: true,
                ariaLabelledBy: "modal-title",
                ariaDescribedBy: "modal-body",
                template: 
                    '<style>' +
                        '.holding-modal .modal-dialog { width: 500px; max-width: 95%; }' +
                    '</style>' +
                    '<div class="modal-header" style="background:#3498db;border-radius:5px 5px 0 0;">' +
                        '<button ng-click="closeModal();" type="button" class="close" style="color:#fff;opacity:1;"><span>&times;</span></button>' +
                        '<h4 class="modal-title" style="color:#fff;"><i class="fa fa-pause-circle"></i> Guardar Pedido</h4>' +
                    '</div>' +
                    '<div class="modal-body" style="padding:20px;">' +
                        '<div class="panel panel-info" style="margin-bottom:15px;">' +
                            '<div class="panel-heading"><i class="fa fa-shopping-cart"></i> Resumo do Pedido</div>' +
                            '<div class="panel-body" style="padding:10px 15px;">' +
                                '<div class="row">' +
                                    '<div class="col-xs-6"><strong>Cliente:</strong></div>' +
                                    '<div class="col-xs-6 text-right">{{ customerName || "Consumidor" }}</div>' +
                                '</div>' +
                                '<div class="row" style="margin-top:5px;">' +
                                    '<div class="col-xs-6"><strong>Itens:</strong></div>' +
                                    '<div class="col-xs-6 text-right">{{ totalItem || 0 }}</div>' +
                                '</div>' +
                                '<hr style="margin:10px 0;">' +
                                '<div class="row">' +
                                    '<div class="col-xs-6"><strong style="font-size:16px;">Total:</strong></div>' +
                                    '<div class="col-xs-6 text-right"><strong style="font-size:16px;color:#27ae60;">{{ totalPayable | currency }}</strong></div>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<form class="form-horizontal">' +
                            '<div class="form-group">' +
                                '<label class="col-sm-3 control-label">Título <span class="text-danger">*</span></label>' +
                                '<div class="col-sm-9">' +
                                    '<input type="text" class="form-control" ng-model="holdTitle" placeholder="Ex: Mesa 5, Cliente João..." ng-keypress="holdOrderWhilePressEnter($event)" autofocus>' +
                                '</div>' +
                            '</div>' +
                            '<div class="form-group" style="margin-bottom:0;">' +
                                '<label class="col-sm-3 control-label">Nota</label>' +
                                '<div class="col-sm-9">' +
                                    '<textarea class="form-control" ng-model="holdNote" rows="2" placeholder="Observações (opcional)"></textarea>' +
                                '</div>' +
                            '</div>' +
                        '</form>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button ng-click="closeModal();" type="button" class="btn btn-default"><i class="fa fa-times"></i> Cancelar</button>' +
                        '<button ng-click="putOrderOnHold();" type="button" class="btn btn-primary" ng-disabled="saving">' +
                            '<i class="fa" ng-class="{\'fa-spinner fa-spin\': saving, \'fa-pause\': !saving}"></i> ' +
                            '{{ saving ? "Salvando..." : "Guardar Pedido" }}' +
                        '</button>' +
                    '</div>',
                controller: function ($scope, $uibModalInstance) {
                    $scope.saving = false;
                    $scope.holdTitle = '';
                    $scope.holdNote = '';
                    
                    $scope.holdOrderWhilePressEnter = function(e) {
                        if (e.keyCode === 13) {
                            e.preventDefault();
                            $scope.putOrderOnHold();
                        }
                    };
                    
                    $scope.putOrderOnHold = function() {
                        if ($scope.saving) return;
                        if (!$scope.holdTitle || !$scope.holdTitle.trim()) {
                            window.toastr.warning("Informe um título para o pedido", "Atenção");
                            return;
                        }
                        if (!$scope.itemArray || $scope.itemArray.length === 0) {
                            window.toastr.warning("Carrinho vazio", "Atenção");
                            return;
                        }
                        
                        $scope.saving = true;
                        
                        // Calcular desconto real baseado no tipo
                        var realDiscountAmount = $scope.discountAmount || 0;
                        if ($scope.discountType === 'percentage' && $scope.totalAmount > 0) {
                            realDiscountAmount = ($scope.totalAmount / 100) * realDiscountAmount;
                        }
                        
                        // Calcular frete real baseado no tipo
                        var realShippingAmount = $scope.shippingAmount || 0;
                        if ($scope.shippingType === 'percentage' && $scope.totalAmount > 0) {
                            realShippingAmount = ($scope.totalAmount / 100) * realShippingAmount;
                        }
                        
                        // Montar dados no formato correto para holding_order.php
                        var postData = 'customer-id=' + encodeURIComponent($scope.customerId || 1) +
                            '&customer-mobile-number=' + encodeURIComponent($scope.customerMobileNumber || '') +
                            '&order-title=' + encodeURIComponent($scope.holdTitle.trim()) +
                            '&invoice-note=' + encodeURIComponent($scope.holdNote || $scope.invoiceNote || '') +
                            '&sub-total=' + encodeURIComponent($scope.totalAmount || 0) +
                            '&discount-type=' + encodeURIComponent($scope.discountType || 'plain') +
                            '&discount-amount=' + encodeURIComponent(realDiscountAmount) +
                            '&tax-amount=' + encodeURIComponent($scope.taxAmount || 0) +
                            '&shipping-type=' + encodeURIComponent($scope.shippingType || 'plain') +
                            '&shipping-amount=' + encodeURIComponent(realShippingAmount) +
                            '&others-charge=' + encodeURIComponent($scope.othersCharge || 0) +
                            '&payable-amount=' + encodeURIComponent($scope.totalPayable || 0);
                        
                        // Adicionar cada item do produto com todos os campos necessários
                        for (var j = 0; j < $scope.itemArray.length; j++) {
                            var item = $scope.itemArray[j];
                            postData += '&product-item[' + j + '][item_id]=' + encodeURIComponent(item.id);
                            postData += '&product-item[' + j + '][category_id]=' + encodeURIComponent(item.categoryId || 0);
                            postData += '&product-item[' + j + '][sup_id]=' + encodeURIComponent(item.supId || 0);
                            postData += '&product-item[' + j + '][item_name]=' + encodeURIComponent(item.name);
                            postData += '&product-item[' + j + '][item_price]=' + encodeURIComponent(item.price);
                            postData += '&product-item[' + j + '][item_quantity]=' + encodeURIComponent(item.quantity);
                            postData += '&product-item[' + j + '][item_total]=' + encodeURIComponent(item.subTotal);
                        }
                        
                        $http({
                            url: window.baseUrl + "_inc/holding_order.php?action_type=HOLD",
                            method: "POST",
                            data: postData,
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'}
                        }).then(function(response) {
                            $scope.saving = false;
                            window.toastr.success(response.data.msg || "Pedido guardado com sucesso!", "Sucesso");
                            if (window.store && window.store.sound_effect == 1) {
                                window.storeApp.playSound("access.mp3");
                            }
                            // Atualizar contador de pedidos em espera
                            var countEl = $("#total-holding_order");
                            if (countEl.length) {
                                countEl.text(parseInt(countEl.text() || 0) + 1);
                            }
                            $scope.closeModal();
                            // Resetar o POS
                            if ($scope.$parent && typeof $scope.$parent.resetPos === 'function') {
                                $scope.$parent.resetPos(0);
                            } else if (typeof $scope.resetPos === 'function') {
                                $scope.resetPos(0);
                            }
                        }, function(response) {
                            $scope.saving = false;
                            if (window.store && window.store.sound_effect == 1) {
                                window.storeApp.playSound("error.mp3");
                            }
                            var errorMsg = "Erro ao guardar pedido";
                            if (response && response.data && response.data.errorMsg) {
                                errorMsg = response.data.errorMsg;
                            } else if (response && response.statusText) {
                                errorMsg = response.statusText;
                            }
                            window.toastr.error(errorMsg, "Erro");
                        });
                    };

                    $scope.closeModal = function () { $uibModalInstance.dismiss("cancel"); };
                },
                scope: $scope,
                size: "md",
                windowClass: "holding-modal",
                backdrop: "static",
                keyboard: true,
            });

            uibModalInstance.result.catch(function () { uibModalInstance.close(); });
        };
    }
]);
