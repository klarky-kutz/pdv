window.angularApp.factory("HoldingOrderDetailsModal", ["API_URL", "window", "jQuery", "$http", "$uibModal", "$sce", "$rootScope", function (API_URL, window, $, $http, $uibModal, $sce, $scope) {
    return function($scope) {
        $scope.showOrderDetails = false;
        $scope.refID = '';
        $scope.orders = [];
        $scope.orderDetails = null;
        $scope.search = '';
        
        var uibModalInstance = $uibModal.open({
            animation: true,
            ariaLabelledBy: "modal-title",
            ariaDescribedBy: "modal-body",
            windowClass: "holding-order-details-modal",
            template: 
                '<style>' +
                    '.holding-order-details-modal .modal-dialog { width: 950px; max-width: 95%; }' +
                    '.holding-order-details-modal .modal-content { border-radius: 10px; overflow: visible; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }' +
                    '.holding-order-details-modal .modal-header { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 18px 25px; border: none; border-radius: 10px 10px 0 0; }' +
                    '.holding-order-details-modal .modal-title { color: #fff; font-weight: 600; font-size: 18px; }' +
                    '.holding-order-details-modal .modal-header .close { color: #fff; opacity: 1; text-shadow: none; font-size: 28px; margin-top: -12px; }' +
                    '.holding-order-details-modal .modal-header .close:hover { color: #f0f0f0; opacity: 0.8; }' +
                    '.holding-order-details-modal .order-list { background: linear-gradient(180deg, #f8f9fa 0%, #fff 100%); border-right: 1px solid #e9ecef; min-height: 450px; max-height: 500px; overflow-y: auto; }' +
                    '.holding-order-details-modal .order-item { padding: 15px 18px; border-bottom: 1px solid #eee; cursor: pointer; transition: all 0.25s ease; position: relative; }' +
                    '.holding-order-details-modal .order-item::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: transparent; transition: all 0.25s ease; }' +
                    '.holding-order-details-modal .order-item:hover { background: #e8f4fc; transform: translateX(2px); }' +
                    '.holding-order-details-modal .order-item:hover::before { background: #3498db; }' +
                    '.holding-order-details-modal .order-item.active { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: #fff; transform: translateX(0); }' +
                    '.holding-order-details-modal .order-item.active::before { background: #fff; }' +
                    '.holding-order-details-modal .order-item.active .text-muted { color: rgba(255,255,255,0.85) !important; }' +
                    '.holding-order-details-modal .order-item .order-title { font-weight: 600; margin-bottom: 5px; font-size: 14px; }' +
                    '.holding-order-details-modal .order-item .order-meta { font-size: 12px; display: flex; gap: 12px; }' +
                    '.holding-order-details-modal .order-item .delete-btn { opacity: 0; transition: all 0.2s; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); z-index: 10; }' +
                    '.holding-order-details-modal .order-item:hover .delete-btn { opacity: 0.7; }' +
                    '.holding-order-details-modal .order-item:hover .delete-btn:hover { opacity: 1; color: #e74c3c !important; }' +
                    '.holding-order-details-modal .order-item.active .delete-btn { color: #fff !important; }' +
                    '.holding-order-details-modal .order-details { padding: 25px; min-height: 450px; background: #fff; }' +
                    '.holding-order-details-modal .empty-state { text-align: center; padding: 80px 20px; color: #95a5a6; }' +
                    '.holding-order-details-modal .empty-state i { font-size: 64px; margin-bottom: 20px; opacity: 0.4; color: #bdc3c7; }' +
                    '.holding-order-details-modal .empty-state p { font-size: 15px; margin: 0; }' +
                    '.holding-order-details-modal .info-card { background: #fff; border-radius: 10px; border: 1px solid #e9ecef; padding: 18px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }' +
                    '.holding-order-details-modal .info-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f5f5f5; }' +
                    '.holding-order-details-modal .info-row:last-child { border-bottom: none; }' +
                    '.holding-order-details-modal .info-label { color: #7f8c8d; font-size: 13px; }' +
                    '.holding-order-details-modal .info-label i { margin-right: 8px; width: 16px; }' +
                    '.holding-order-details-modal .info-value { font-weight: 600; color: #2c3e50; font-size: 14px; }' +
                    '.holding-order-details-modal .items-table { width: 100%; border-collapse: separate; border-spacing: 0; }' +
                    '.holding-order-details-modal .items-table th { background: linear-gradient(180deg, #f8f9fa 0%, #ecf0f1 100%); padding: 12px 10px; font-weight: 600; border-bottom: 2px solid #3498db; color: #2c3e50; font-size: 13px; }' +
                    '.holding-order-details-modal .items-table td { padding: 12px 10px; border-bottom: 1px solid #f0f0f0; font-size: 13px; }' +
                    '.holding-order-details-modal .items-table tbody tr:hover { background: #f8f9fa; }' +
                    '.holding-order-details-modal .total-row { background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); }' +
                    '.holding-order-details-modal .total-row td { border-bottom: none; }' +
                    '.holding-order-details-modal .search-box { padding: 15px 18px; background: #fff; border-bottom: 1px solid #e9ecef; }' +
                    '.holding-order-details-modal .search-box input { border-radius: 25px; padding: 10px 15px 10px 40px; border: 1px solid #ddd; transition: all 0.3s; }' +
                    '.holding-order-details-modal .search-box input:focus { border-color: #3498db; box-shadow: 0 0 0 3px rgba(52,152,219,0.1); }' +
                    '.holding-order-details-modal .search-box .fa-search { position: absolute; left: 30px; top: 50%; transform: translateY(-50%); color: #95a5a6; }' +
                    '.holding-order-details-modal .badge-count { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: #fff; padding: 3px 10px; border-radius: 12px; font-size: 12px; margin-left: 10px; font-weight: 600; }' +
                    '.holding-order-details-modal .section-title { color: #2980b9; margin-top: 0; margin-bottom: 15px; font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; }' +
                    '.holding-order-details-modal .section-title i { color: #3498db; }' +
                    '.holding-order-details-modal .modal-footer { background: #f8f9fa; border-top: 1px solid #e9ecef; padding: 15px 25px; }' +
                    '.holding-order-details-modal .modal-footer .btn { padding: 10px 20px; border-radius: 6px; font-weight: 500; }' +
                    '.holding-order-details-modal .btn-success { background: linear-gradient(135deg, #27ae60 0%, #229954 100%); border: none; }' +
                    '.holding-order-details-modal .btn-success:hover { background: linear-gradient(135deg, #229954 0%, #1e8449 100%); }' +
                '</style>' +
                '<div class="modal-header">' +
                    '<button ng-click="closeModal();" type="button" class="close"><span>&times;</span></button>' +
                    '<h4 class="modal-title"><i class="fa fa-pause-circle"></i> Pedidos em Espera <span class="badge-count">{{ orders.length }}</span></h4>' +
                '</div>' +
                '<div class="modal-body" style="padding: 0;">' +
                    '<div class="row" style="margin: 0;">' +
                        '<div class="col-md-4 col-sm-5" style="padding: 0;">' +
                            '<div class="order-list">' +
                                '<div class="search-box" style="position: relative;">' +
                                    '<i class="fa fa-search"></i>' +
                                    '<input type="text" class="form-control" ng-model="search" placeholder="Buscar pedido...">' +
                                '</div>' +
                                '<div ng-if="orders.length === 0" class="empty-state" style="padding: 40px 20px;">' +
                                    '<i class="fa fa-inbox"></i>' +
                                    '<p>Nenhum pedido em espera</p>' +
                                '</div>' +
                                '<div ng-repeat="order in filteredOrders = (orders | filter:search)" ' +
                                    'class="order-item" ' +
                                    'ng-class="{active: refID === order.ref_no}" ' +
                                    'ng-click="loadHoldingOrderDetails(order.ref_no);">' +
                                    '<div style="display: flex; justify-content: space-between; align-items: start;">' +
                                        '<div style="flex: 1;">' +
                                            '<div class="order-title"><i class="fa fa-file-text-o"></i> {{ order.order_title }}</div>' +
                                            '<div class="order-meta text-muted">' +
                                                '<span><i class="fa fa-hashtag"></i> {{ order.ref_no }}</span>' +
                                                '<span style="margin-left: 10px;"><i class="fa fa-money"></i> {{ order.payable_amount | currency }}</span>' +
                                            '</div>' +
                                        '</div>' +
                                        '<button type="button" class="btn btn-xs btn-link delete-btn" style="color: inherit; z-index: 100;" ng-click="deleteHoldingOrder(order.ref_no); $event.stopPropagation();" title="Excluir">' +
                                            '<i class="fa fa-trash fa-lg"></i>' +
                                        '</button>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="col-md-8 col-sm-7" style="padding: 0;">' +
                            '<div class="order-details">' +
                                '<div ng-if="!showOrderDetails" class="empty-state">' +
                                    '<i class="fa fa-hand-pointer-o"></i>' +
                                    '<p>Selecione um pedido para ver os detalhes</p>' +
                                '</div>' +
                                '<div ng-if="showOrderDetails">' +
                                    '<div class="info-card">' +
                                        '<h5 class="section-title"><i class="fa fa-info-circle"></i> Informações do Pedido</h5>' +
                                        '<div class="info-row">' +
                                            '<span class="info-label"><i class="fa fa-user"></i> Cliente</span>' +
                                            '<span class="info-value">{{ orderDetails.customer || "Consumidor" }}</span>' +
                                        '</div>' +
                                        '<div class="info-row">' +
                                            '<span class="info-label"><i class="fa fa-calendar"></i> Data</span>' +
                                            '<span class="info-value">{{ orderDetails.created_at }}</span>' +
                                        '</div>' +
                                        '<div class="info-row">' +
                                            '<span class="info-label"><i class="fa fa-hashtag"></i> Referência</span>' +
                                            '<span class="info-value">{{ orderDetails.ref_no }}</span>' +
                                        '</div>' +
                                    '</div>' +
                                    '<div class="info-card">' +
                                        '<h5 class="section-title"><i class="fa fa-shopping-cart"></i> Itens do Pedido</h5>' +
                                        '<table class="items-table">' +
                                            '<thead>' +
                                                '<tr>' +
                                                    '<th style="width: 40px;">#</th>' +
                                                    '<th>Produto</th>' +
                                                    '<th style="width: 80px; text-align: right;">Preço</th>' +
                                                    '<th style="width: 70px; text-align: center;">Qtd</th>' +
                                                    '<th style="width: 90px; text-align: right;">Total</th>' +
                                                '</tr>' +
                                            '</thead>' +
                                            '<tbody>' +
                                                '<tr ng-repeat="item in orderDetails.items">' +
                                                    '<td>{{ $index + 1 }}</td>' +
                                                    '<td>{{ item.item_name }}</td>' +
                                                    '<td style="text-align: right;">{{ item.item_price | number:2 }}</td>' +
                                                    '<td style="text-align: center;">{{ item.item_quantity }}</td>' +
                                                    '<td style="text-align: right;">{{ item.item_total | number:2 }}</td>' +
                                                '</tr>' +
                                            '</tbody>' +
                                            '<tfoot>' +
                                                '<tr>' +
                                                    '<td colspan="4" style="text-align: right;"><strong>Subtotal</strong></td>' +
                                                    '<td style="text-align: right;">{{ orderDetails.subtotal | number:2 }}</td>' +
                                                '</tr>' +
                                                '<tr ng-if="orderDetails.discount_amount > 0">' +
                                                    '<td colspan="4" style="text-align: right;">Desconto</td>' +
                                                    '<td style="text-align: right; color: #e74c3c;">-{{ orderDetails.discount_amount | number:2 }}</td>' +
                                                '</tr>' +
                                                '<tr ng-if="(orderDetails.item_tax + orderDetails.order_tax) > 0">' +
                                                    '<td colspan="4" style="text-align: right;">Impostos</td>' +
                                                    '<td style="text-align: right;">{{ (orderDetails.item_tax + orderDetails.order_tax) | number:2 }}</td>' +
                                                '</tr>' +
                                                '<tr ng-if="orderDetails.shipping_amount > 0">' +
                                                    '<td colspan="4" style="text-align: right;">Frete</td>' +
                                                    '<td style="text-align: right;">{{ orderDetails.shipping_amount | number:2 }}</td>' +
                                                '</tr>' +
                                                '<tr ng-if="orderDetails.others_charge > 0">' +
                                                    '<td colspan="4" style="text-align: right;">Outras taxas</td>' +
                                                    '<td style="text-align: right;">{{ orderDetails.others_charge | number:2 }}</td>' +
                                                '</tr>' +
                                                '<tr class="total-row">' +
                                                    '<td colspan="4" style="text-align: right; font-size: 15px;"><strong>Total a Pagar</strong></td>' +
                                                    '<td style="text-align: right; font-size: 15px; color: #27ae60;"><strong>{{ orderDetails.payable_amount | number:2 }}</strong></td>' +
                                                '</tr>' +
                                            '</tfoot>' +
                                        '</table>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="modal-footer">' +
                    '<button ng-click="closeModal();" type="button" class="btn btn-default"><i class="fa fa-times"></i> Fechar</button>' +
                    '<button ng-click="editTheOrder();" type="button" class="btn btn-success" ng-disabled="!refID"><i class="fa fa-play"></i> Continuar Pedido</button>' +
                '</div>',
            controller: function ($scope, $uibModalInstance) {
                
                function getErrorMsg(response) {
                    if (response && response.data && response.data.errorMsg) {
                        return response.data.errorMsg;
                    }
                    if (response && response.statusText) {
                        return response.statusText;
                    }
                    return "Ocorreu um erro inesperado";
                }
                
                $scope.loadModal = function() {
                    $(document).find("body").addClass("overlay-loader");
                    $http({
                        url: window.baseUrl + "_inc/holding_order.php?action_type=HOLDINGORDERDETAILSMODAL",
                        method: "GET"
                    })
                    .then(function(response) {
                        $scope.orders = response.data.orders || [];
                        $("#total-holding_order").text($scope.orders.length);
                        $(document).find("body").removeClass("overlay-loader");
                    }, function(response) {
                        $(document).find("body").removeClass("overlay-loader");
                        window.toastr.error(getErrorMsg(response), "Erro");
                    });
                };
                $scope.loadModal();

                $scope.loadHoldingOrderDetails = function(refNo) {
                    $(document).find("body").addClass("overlay-loader");
                    $http({
                        url: window.baseUrl + "_inc/holding_order.php?action_type=HOLDINGORDERDETAILS&ref_no=" + refNo,
                        method: "GET"
                    })
                    .then(function(response) {
                        $scope.showOrderDetails = true;
                        $scope.orderDetails = response.data.order || {};
                        $scope.orderDetails.items = response.data.items || [];
                        $scope.refID = response.data.order ? response.data.order.ref_no : '';
                        $(document).find("body").removeClass("overlay-loader");
                    }, function(response) {
                        $(document).find("body").removeClass("overlay-loader");
                        window.toastr.error(getErrorMsg(response), "Erro");
                    });
                };

                $scope.closeModal = function() {
                    $uibModalInstance.dismiss("cancel");
                };

                $scope.editTheOrder = function() {
                    if (!$scope.refID) {
                        window.toastr.warning("Por favor, selecione um pedido", "Atenção");
                        return false;
                    }
                    window.location = window.baseUrl + "admin/pos.php?holding_id=" + $scope.refID;
                };

                $scope.deleteHoldingOrder = function(refNo) {
                    window.swal({
                        title: "Excluir Pedido",
                        text: "Tem certeza que deseja excluir este pedido?",
                        icon: "warning",
                        buttons: ["Cancelar", "Sim, Excluir"],
                        dangerMode: true,
                    })
                    .then(function(willDelete) {
                        if (willDelete) {
                            $(document).find("body").addClass("overlay-loader");
                            $http({
                                url: window.baseUrl + "_inc/holding_order.php?action_type=DELETE",
                                method: "POST",
                                data: "ref_no=" + refNo,
                                headers: {'Content-Type': 'application/x-www-form-urlencoded'}
                            })
                            .then(function(response) {
                                $(document).find("body").removeClass("overlay-loader");
                                if ($scope.refID === refNo) {
                                    $scope.showOrderDetails = false;
                                    $scope.refID = '';
                                    $scope.orderDetails = null;
                                }
                                $scope.loadModal();
                                window.toastr.success(response.data.msg || "Pedido excluído!", "Sucesso");
                                if (window.store && window.store.sound_effect == 1) {
                                    window.storeApp.playSound("modify.mp3");
                                }
                            }, function(response) {
                                $(document).find("body").removeClass("overlay-loader");
                                if (window.store && window.store.sound_effect == 1) {
                                    window.storeApp.playSound("error.mp3");
                                }
                                window.toastr.error(getErrorMsg(response), "Erro");
                            });
                        }
                    });
                };
            },
            scope: $scope,
            size: "lg",
            backdrop: "static",
            keyboard: true,
        });

        uibModalInstance.result.catch(function() { 
            uibModalInstance.close(); 
        });
    };
}]);
