window.angularApp.factory("GiftcardCreateModal", ["API_URL", "window", "jQuery", "$http", "$uibModal", "$sce", "$rootScope", 
    function (API_URL, window, $, $http, $uibModal, $sce, $scope) {
        return function($scope) {
            var uibModalInstance = $uibModal.open({
                animation: true,
                ariaLabelledBy: "modal-title",
                ariaDescribedBy: "modal-body",
                template: 
                    '<style>' +
                        '.giftcard-modal .modal-dialog { width: 550px; max-width: 95%; }' +
                        '.gc-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 25px; color: #fff; margin-bottom: 20px; box-shadow: 0 10px 30px rgba(102,126,234,0.4); position: relative; overflow: hidden; }' +
                        '.gc-card::before { content: ""; position: absolute; top: -50%; right: -50%; width: 100%; height: 100%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%); }' +
                        '.gc-card-logo { font-size: 24px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }' +
                        '.gc-card-logo i { font-size: 32px; }' +
                        '.gc-card-number { font-family: "Courier New", monospace; font-size: 22px; letter-spacing: 3px; margin-bottom: 20px; text-shadow: 1px 1px 2px rgba(0,0,0,0.2); }' +
                        '.gc-card-details { display: flex; justify-content: space-between; }' +
                        '.gc-card-detail label { font-size: 10px; text-transform: uppercase; opacity: 0.8; display: block; }' +
                        '.gc-card-detail span { font-size: 16px; font-weight: bold; }' +
                        '.gc-card-chip { position: absolute; top: 25px; right: 25px; width: 45px; height: 35px; background: linear-gradient(135deg, #ffd700 0%, #ffb700 100%); border-radius: 5px; }' +
                        '.gc-card-chip::before { content: ""; position: absolute; top: 8px; left: 5px; right: 5px; height: 6px; background: rgba(0,0,0,0.1); border-radius: 2px; }' +
                        '.gc-card-chip::after { content: ""; position: absolute; top: 18px; left: 5px; right: 5px; height: 6px; background: rgba(0,0,0,0.1); border-radius: 2px; }' +
                    '</style>' +
                    '<div class="modal-header" style="background:#3498db;border-radius:5px 5px 0 0;">' +
                        '<button ng-click="closeModal();" type="button" class="close" style="color:#fff;opacity:1;"><span>&times;</span></button>' +
                        '<h4 class="modal-title" style="color:#fff;"><i class="fa fa-gift"></i> Novo Gift Card</h4>' +
                    '</div>' +
                    '<div class="modal-body" style="padding:20px;">' +
                        '<div ng-if="loading" class="text-center" style="padding:40px;">' +
                            '<i class="fa fa-spinner fa-spin fa-2x text-primary"></i>' +
                            '<p style="margin-top:10px;">Carregando...</p>' +
                        '</div>' +
                        '<div ng-if="!loading">' +
                            '<div class="gc-card">' +
                                '<div class="gc-card-chip"></div>' +
                                '<div class="gc-card-logo"><i class="fa fa-gift"></i> GIFT CARD</div>' +
                                '<div class="gc-card-number">{{ formatCardDisplay(cardNo) }}</div>' +
                                '<div class="gc-card-details">' +
                                    '<div class="gc-card-detail"><label>Valor</label><span>R$ {{ (giftcardValue || 0).toFixed(2) }}</span></div>' +
                                    '<div class="gc-card-detail"><label>Validade</label><span>{{ formatDateDisplay(expiry) }}</span></div>' +
                                '</div>' +
                            '</div>' +
                            '<div class="panel panel-default">' +
                                '<div class="panel-heading" style="background:#f5f5f5;"><i class="fa fa-cog"></i> Configurações do Cartão</div>' +
                                '<div class="panel-body">' +
                                    '<form class="form-horizontal">' +
                                        '<div class="form-group">' +
                                            '<label class="col-sm-4 control-label">Nº Cartão</label>' +
                                            '<div class="col-sm-8">' +
                                                '<div class="input-group">' +
                                                    '<input type="text" class="form-control" ng-model="cardNo" readonly style="font-family:monospace;">' +
                                                    '<span class="input-group-btn">' +
                                                        '<button type="button" class="btn btn-info" ng-click="generateCardNo()" title="Gerar novo número"><i class="fa fa-refresh"></i></button>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                        '<div class="form-group">' +
                                            '<label class="col-sm-4 control-label">Valor <span class="text-danger">*</span></label>' +
                                            '<div class="col-sm-8">' +
                                                '<div class="input-group">' +
                                                    '<span class="input-group-addon">R$</span>' +
                                                    '<input type="number" step="0.01" min="0" class="form-control" id="giftcard-value-input" placeholder="0.00" onchange="$(\x27#giftcard-balance-input\x27).val(this.value);">' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                        '<div class="form-group">' +
                                            '<label class="col-sm-4 control-label">Saldo Inicial</label>' +
                                            '<div class="col-sm-8">' +
                                                '<div class="input-group">' +
                                                    '<span class="input-group-addon">R$</span>' +
                                                    '<input type="number" step="0.01" min="0" class="form-control" id="giftcard-balance-input" placeholder="0.00">' +
                                                '</div>' +
                                                '<small class="text-muted">Deixe igual ao valor para cartão novo</small>' +
                                            '</div>' +
                                        '</div>' +
                                        '<div class="form-group">' +
                                            '<label class="col-sm-4 control-label">Cliente <span class="text-danger">*</span></label>' +
                                            '<div class="col-sm-8">' +
                                                '<select id="giftcard-customer-select" class="form-control">' +
                                                    '<option value="">Selecione o cliente...</option>' +
                                                '</select>' +
                                            '</div>' +
                                        '</div>' +
                                        '<div class="form-group" style="margin-bottom:0;">' +
                                            '<label class="col-sm-4 control-label">Validade <span class="text-danger">*</span></label>' +
                                            '<div class="col-sm-8">' +
                                                '<input type="text" class="form-control expiry-date-input" ng-model="expiryDisplay" placeholder="DD/MM/AAAA" maxlength="10">' +
                                            '</div>' +
                                        '</div>' +
                                    '</form>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button ng-click="closeModal();" type="button" class="btn btn-default"><i class="fa fa-times"></i> Cancelar</button>' +
                        '<button ng-click="submitGiftcard();" type="button" class="btn btn-primary" ng-disabled="saving">' +
                            '<i class="fa" ng-class="{\'fa-spinner fa-spin\': saving, \'fa-gift\': !saving}"></i> ' +
                            '{{ saving ? "Criando..." : "Criar Gift Card" }}' +
                        '</button>' +
                    '</div>',
                controller: function ($scope, $uibModalInstance) {
                    $scope.loading = true;
                    $scope.saving = false;
                    $scope.cardNo = '';
                    $scope.giftcardValue = 0;
                    $scope.balance = 0;
                    $scope.selectedCustomer = null;
                    $scope.customers = [];
                    
                    
                    // Configurar data de validade padrão (1 ano a partir de hoje)
                    var nextYear = new Date();
                    nextYear.setFullYear(nextYear.getFullYear() + 1);
                    var day = ('0' + nextYear.getDate()).slice(-2);
                    var month = ('0' + (nextYear.getMonth() + 1)).slice(-2);
                    var year = nextYear.getFullYear();
                    $scope.expiry = year + '-' + month + '-' + day;
                    $scope.expiryDisplay = day + '/' + month + '/' + year;
                    
                    $scope.generateCardNo = function() {
                        var result = '';
                        for (var i = 0; i < 16; i++) {
                            result += Math.floor(Math.random() * 10);
                        }
                        $scope.cardNo = result;
                    };
                    
                    $scope.formatCardDisplay = function(cardNo) {
                        if (!cardNo) return 'XXXX-XXXX-XXXX-XXXX';
                        return cardNo.replace(/(\d{4})/g, '$1-').slice(0, -1);
                    };
                    
                    $scope.formatDateDisplay = function(dateStr) {
                        // Usar expiryDisplay diretamente se disponível
                        if ($scope.expiryDisplay) {
                            return $scope.expiryDisplay;
                        }
                        if (!dateStr) return '--/--/----';
                        // Se for um objeto Date, converter para string
                        if (dateStr instanceof Date) {
                            var d = dateStr;
                            var day = ('0' + d.getDate()).slice(-2);
                            var month = ('0' + (d.getMonth() + 1)).slice(-2);
                            var year = d.getFullYear();
                            return day + '/' + month + '/' + year;
                        }
                        // Se for string no formato YYYY-MM-DD
                        if (typeof dateStr === 'string' && dateStr.indexOf('-') !== -1) {
                            var parts = dateStr.split('-');
                            if (parts.length === 3) {
                                return parts[2] + '/' + parts[1] + '/' + parts[0];
                            }
                        }
                        return String(dateStr);
                    };
                    
                    $scope.syncBalance = function() {
                        $scope.balance = $scope.giftcardValue;
                    };
                    
                    $http({
                        url: window.baseUrl + "_inc/customer.php?action_type=FETCHALL&exclude=1",
                        method: "GET"
                    }).then(function(response) {
                        if (response.data && response.data.data) {
                            $scope.customers = response.data.data;
                            // Popular select com jQuery após carregar
                            setTimeout(function() {
                                var select = $('#giftcard-customer-select');
                                select.find('option:not(:first)').remove();
                                $.each($scope.customers, function(i, c) {
                                    select.append('<option value="' + c.customer_id + '">' + c.customer_name + '</option>');
                                });
                            }, 100);
                        }
                        $scope.loading = false;
                        $scope.generateCardNo();
                    }, function() {
                        $scope.loading = false;
                        $scope.generateCardNo();
                    });
                    
                    $scope.submitGiftcard = function() {
                        if ($scope.saving) return;
                        if (!$scope.cardNo) { window.toastr.warning("Gere um número de cartão", "Atenção"); return; }
                        
                        // Pegar valores via jQuery
                        var custId = $('#giftcard-customer-select').val();
                        var giftcardValue = parseFloat($('#giftcard-value-input').val()) || 0;
                        var balance = parseFloat($('#giftcard-balance-input').val()) || 0;
                        
                        if (!custId || custId === '') { 
                            window.toastr.warning("Selecione um cliente", "Atenção"); 
                            return; 
                        }
                        
                        if (giftcardValue <= 0) {
                            window.toastr.warning("Informe o valor do cartão", "Atenção"); 
                            return; 
                        }
                        
                        // Converter expiryDisplay (DD/MM/AAAA) para formato do banco (AAAA-MM-DD)
                        var expiryStr = $scope.expiry;
                        if ($scope.expiryDisplay && $scope.expiryDisplay.indexOf('/') !== -1) {
                            var parts = $scope.expiryDisplay.split('/');
                            if (parts.length === 3) {
                                expiryStr = parts[2] + '-' + parts[1] + '-' + parts[0];
                            }
                        }
                        
                        $scope.saving = true;
                        $http({
                            url: window.baseUrl + "_inc/giftcard.php",
                            method: "POST",
                            data: $.param({
                                action_type: 'CREATE',
                                card_no: $scope.cardNo,
                                giftcard_value: giftcardValue,
                                balance: balance,
                                customer_id: custId,
                                expiry: expiryStr
                            }),
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'}
                        }).then(function(response) {
                            $scope.saving = false;
                            window.toastr.success("Gift Card criado!", "Sucesso");
                            $scope.closeModal();
                            if ($scope.GiftcardCreateModalCallback) {
                                // Passar objeto com card_no para o callback de visualização
                                var giftcardData = response.data.giftcard || { card_no: $scope.cardNo };
                                $scope.GiftcardCreateModalCallback(giftcardData);
                            }
                        }, function(response) {
                            $scope.saving = false;
                            window.toastr.error(response.data.errorMsg || "Erro ao criar", "Erro");
                        });
                    };

                    $scope.closeModal = function () { $uibModalInstance.dismiss("cancel"); };
                },
                scope: $scope,
                size: "md",
                windowClass: "giftcard-modal",
                backdrop: "static",
                keyboard: true,
            });

            uibModalInstance.result.catch(function () { uibModalInstance.close(); });
        };
    }
]);
