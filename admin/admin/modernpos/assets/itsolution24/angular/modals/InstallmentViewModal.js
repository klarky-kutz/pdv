window.angularApp.factory("InstallmentViewModal", ["API_URL", "window", "jQuery", "$http", "$uibModal", "$sce", "InstallmentPaymentModal", "$rootScope", function (API_URL, window, $, $http, $uibModal, $sce, InstallmentPaymentModal, $scope) {
    return function (invoice) {
        var uibModalInstance = $uibModal.open({
            animation: true,
            ariaLabelledBy: "modal-title",
            ariaDescribedBy: "modal-body",
            template:
                '<style>' +
                    // Modal Base
                    '.installment-view-modal .modal-dialog { width: 800px; max-width: 95%; margin: 30px auto; }' +
                    '.installment-view-modal .modal-content { border-radius: 12px; border: none; box-shadow: 0 15px 50px rgba(0,0,0,0.3); overflow: visible; position: relative; }' +
                    '.installment-view-modal .btn-close-modal { position: absolute; top: -16px; right: -16px; width: 44px; height: 44px; background: #fff; border: 3px solid #ddd; border-radius: 50%; font-size: 18px; color: #666; cursor: pointer; box-shadow: 0 3px 15px rgba(0,0,0,0.25); z-index: 1070; line-height: 38px; text-align: center; transition: all 0.2s ease; }' +
                    '.installment-view-modal .btn-close-modal:hover { background: #e74c3c; color: #fff; border-color: #e74c3c; transform: scale(1.1); }' +
                    // Header
                    '.installment-view-modal .modal-header { background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); padding: 20px 25px; border-radius: 12px 12px 0 0; border: none; }' +
                    '.installment-view-modal .modal-header h4 { color: #fff; margin: 0; font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 12px; }' +
                    '.installment-view-modal .modal-header h4 i { font-size: 24px; }' +
                    '.installment-view-modal .modal-header .badge { background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 20px; font-size: 13px; margin-left: auto; }' +
                    // Body
                    '.installment-view-modal .modal-body { padding: 0; background: #f5f7fa; max-height: 70vh; overflow-y: auto; }' +
                    '.installment-view-modal .modal-body .loading-box { padding: 60px; text-align: center; color: #999; }' +
                    '.installment-view-modal .modal-body .loading-box i { font-size: 40px; margin-bottom: 15px; color: #9b59b6; }' +
                    // Footer
                    '.installment-view-modal .modal-footer { background: #fff; border-top: 1px solid #e9ecef; padding: 15px 25px; border-radius: 0 0 12px 12px; display: flex; justify-content: center; gap: 10px; }' +
                    '.installment-view-modal .btn-print { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); border: none; color: #fff; padding: 12px 30px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }' +
                    '.installment-view-modal .btn-print:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(52,152,219,0.4); }' +
                    '.installment-view-modal .btn-close-footer { background: #6c757d; border: none; color: #fff; padding: 12px 25px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }' +
                    '.installment-view-modal .btn-close-footer:hover { background: #5a6268; }' +
                '</style>' +
                '<div id="data-modal">' +
                    '<div class="modal-header">' +
                        '<button ng-click="closeInstallmentViewModal();" type="button" class="btn-close-modal">&times;</button>' +
                        '<h4>' +
                            '<i class="fa fa-calendar-check-o"></i>' +
                            '<span>Parcelamento</span>' +
                            '<span class="badge"><i class="fa fa-file-text-o"></i> #{{ invoiceId }}</span>' +
                        '</h4>' +
                    '</div>' +
                    '<div class="modal-body">' +
                        '<div ng-show="loading" class="loading-box">' +
                            '<i class="fa fa-spinner fa-spin"></i>' +
                            '<p>Carregando parcelas...</p>' +
                        '</div>' +
                        '<div ng-hide="loading" bind-html-compile="rawHtml"></div>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button ng-click="closeInstallmentViewModal();" type="button" class="btn btn-close-footer"><i class="fa fa-times"></i> Fechar</button>' +
                        '<button ng-click="printModal()" type="button" class="btn btn-print"><i class="fa fa-print"></i> Imprimir</button>' +
                    '</div>' +
                '</div>',
            windowClass: "installment-view-modal",
            controller: function ($scope, $uibModalInstance) {
                $scope.loading = true;
                $scope.invoiceId = invoice.invoice_id;
                
                $scope.loadInstallmentView = function() {
                    $http({
                        url: window.baseUrl + "_inc/installment.php?invoice_id=" + invoice.invoice_id + '&action_type=VIEW',
                        method: "GET"
                    })
                    .then(function (response) {
                        $scope.loading = false;
                        $scope.modal_title = "Parcelamento #" + invoice.invoice_id;
                        $scope.rawHtml = $sce.trustAsHtml(response.data);
                    }, function (response) {
                        $scope.loading = false;
                        window.swal("Erro!", response.data.errorMsg || "Erro ao carregar dados", "error")
                        .then(function() {
                            $scope.closeInstallmentViewModal();
                        });
                    });
                };
                $scope.loadInstallmentView();

                $scope.payForm = function(id) {
                    $scope.id = id;
                    InstallmentPaymentModal($scope);
                };
                
                $scope.printModal = function() {
                    window.printContent('data-modal', {
                        headline: '<small>Impresso em: ' + window.formatDate(new Date()) + '</small>',
                        screenSize: 'fullScreen'
                    });
                };

                $scope.closeInstallmentViewModal = function () {
                    $uibModalInstance.dismiss("cancel");
                };
            },
            scope: $scope,
            size: "lg",
            backdrop: "static",
            keyboard: true,
        });

        uibModalInstance.result.catch(function () { 
            uibModalInstance.close(); 
        });
    };
}]);
