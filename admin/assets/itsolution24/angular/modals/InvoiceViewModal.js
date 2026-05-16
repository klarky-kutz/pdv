window.angularApp.factory("InvoiceViewModal", [
    "API_URL",
    "window",
    "jQuery",
    "$http",
    "$uibModal",
    "$sce",
    "InvoiceSMSModal",
    "EmailModal",
    "$rootScope", 
function (API_URL,
    window,
    $,
    $http,
    $uibModal,
    $sce,
    InvoiceSMSModal,
    EmailModal,
    $scope
) {
    return function (invoice) {
        // Verificar se invoice_id foi fornecido
        var invoiceId = invoice ? (invoice.invoice_id || invoice.invoiceId || invoice.id) : null;
        if (!invoiceId) {
            console.error('InvoiceViewModal: invoice_id não fornecido', invoice);
            window.toastr && window.toastr.error('ID da nota não encontrado', 'Erro');
            return;
        }
        
        var uibModalInstance = $uibModal.open({
            animation: true,
            ariaLabelledBy: "modal-title",
            ariaDescribedBy: "modal-body",
            template:
                '<style>' +
                    '.invoice-modal .modal-dialog { width: 500px; max-width: 95%; margin: 30px auto; }' +
                    '.invoice-modal .modal-content { border-radius: 8px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.3); overflow: visible; position: relative; }' +
                    '.invoice-modal .btn-close-modal { position: absolute; top: -16px; right: -16px; width: 40px; height: 40px; background: #fff; border: 3px solid #ddd; border-radius: 50%; font-size: 16px; color: #666; cursor: pointer; box-shadow: 0 3px 12px rgba(0,0,0,0.25); z-index: 1070; line-height: 34px; text-align: center; }' +
                    '.invoice-modal .btn-close-modal:hover { background: #e74c3c; color: #fff; border-color: #e74c3c; }' +
                    '.invoice-modal .modal-header { background: #2c3e50; padding: 15px 20px; border-radius: 8px 8px 0 0; }' +
                    '.invoice-modal .modal-header h4 { color: #fff; margin: 0; font-size: 16px; font-weight: 600; }' +
                    '.invoice-modal .modal-header h4 i { margin-right: 8px; }' +
                    '.invoice-modal .modal-header .badge { background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 12px; font-size: 12px; margin-left: 10px; }' +
                    '.invoice-modal .modal-body { padding: 0; max-height: 70vh; overflow-y: auto; background: #fff; border-radius: 0 0 8px 8px; }' +
                    '.invoice-modal .modal-body .loading-box { padding: 60px; text-align: center; color: #999; }' +
                    '.invoice-modal .modal-body .loading-box i { font-size: 36px; margin-bottom: 10px; }' +
                '</style>' +
                '<div class="modal-header">' +
                    '<button ng-click="closeModal();" type="button" class="btn-close-modal">&times;</button>' +
                    '<h4><i class="fa fa-file-text-o"></i> Comprovante <span class="badge">#{{ invoiceId }}</span></h4>' +
                '</div>' +
                '<div class="modal-body">' +
                    '<div ng-show="loading" class="loading-box">' +
                        '<i class="fa fa-spinner fa-spin"></i>' +
                        '<p>Carregando...</p>' +
                    '</div>' +
                    '<div ng-hide="loading" bind-html-compile="rawHtml"></div>' +
                '</div>',
            controller: function ($scope, $uibModalInstance) {
                $scope.loading = true;
                $scope.invoiceId = invoiceId;
                
                // Carregar nota
                $http({
                    url: window.baseUrl + "_inc/invoice.php?invoice_id=" + invoiceId + '&action_type=INVOICEVIEW',
                    method: "GET"
                }).then(function (response) {
                    $scope.loading = false;
                    $scope.rawHtml = $sce.trustAsHtml(response.data);
                }, function (response) {
                    $scope.loading = false;
                    window.toastr.error(response.data.errorMsg || "Erro ao carregar nota", "Erro");
                    $scope.closeModal();
                });
                
                $scope.closeModal = function() {
                    $uibModalInstance.dismiss("cancel");
                };
                
                // Alias para compatibilidade
                $scope.closeInvoiceViewModal = $scope.closeModal;

                // Remover handlers antigos para evitar duplicação
                $(document).undelegate("#sms-btn", "click");
                $(document).undelegate("#email-btn", "click");
                
                // Handlers para botões existentes no conteúdo da nota
                $(document).delegate("#sms-btn", "click", function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    var btnInvoiceId = $(this).data("invoiceid");
                    if (!btnInvoiceId) {
                        window.toastr.error('ID da nota não encontrado', 'Erro');
                        return;
                    }
                    $scope.invoiceID = btnInvoiceId;
                    InvoiceSMSModal($scope);
                });

                $(document).delegate("#email-btn", "click", function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    var btnInvoiceId = $(this).data("invoiceid");
                    if (!btnInvoiceId) {
                        window.toastr.error('ID da nota não encontrado', 'Erro');
                        return;
                    }
                    var html = $("#invoice").html();
                    EmailModal({
                        template: "invoice",
                        styles: '',
                        subject: "Nota #" + btnInvoiceId,
                        title: "Enviar Nota #" + btnInvoiceId,
                        recipientName: $(this).data("customername") || '',
                        senderName: window.store.name,
                        html: html
                    });
                });
            },
            scope: $scope,
            size: "md",
            windowClass: "invoice-modal",
            backdrop: "static",
            keyboard: true,
        });

        uibModalInstance.result.catch(function () { 
            uibModalInstance.close(); 
        });
    };
}]);
