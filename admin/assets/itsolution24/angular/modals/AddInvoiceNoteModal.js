window.angularApp.factory("AddInvoiceNoteModal", ["API_URL", "window", "jQuery", "$http", "$uibModal", "$sce", "$rootScope", function (API_URL, window, $, $http, $uibModal, $sce, $scope) {
    return function($scope) {
        var uibModalInstance = $uibModal.open({
            animation: true,
            ariaLabelledBy: "modal-title",
            ariaDescribedBy: "modal-body",
            windowClass: "invoice-note-modal",
            template: 
                '<style>' +
                    '.invoice-note-modal .modal-dialog { width: 500px; max-width: 95%; }' +
                '</style>' +
                '<div class="modal-header" style="background:#3498db;border-radius:5px 5px 0 0;">' +
                    '<button ng-click="closeAddNoteModal();" type="button" class="close" style="color:#fff;opacity:1;"><span>&times;</span></button>' +
                    '<h4 class="modal-title" style="color:#fff;"><i class="fa fa-sticky-note"></i> Nota da Venda</h4>' +
                    '<small style="color:rgba(255,255,255,0.8);">Adicione observações importantes</small>' +
                '</div>' +
                '<div class="modal-body" style="padding:20px;">' +
                    '<div class="form-group" style="margin-bottom:15px;">' +
                        '<label><i class="fa fa-comment"></i> Observação</label>' +
                        '<textarea id="note" class="form-control" rows="4" placeholder="Digite sua observação aqui...">{{ invoiceNote }}</textarea>' +
                    '</div>' +
                    '<div class="alert alert-info" style="margin-bottom:0;">' +
                        '<i class="fa fa-info-circle"></i> Esta nota será <b>impressa no cupom</b> e ficará visível no histórico da venda.' +
                    '</div>' +
                '</div>' +
                '<div class="modal-footer">' +
                    '<button type="button" class="btn btn-danger" ng-click="clear()"><i class="fa fa-trash"></i> Limpar</button>' +
                    '<button type="button" class="btn btn-primary" ng-click="closeAddNoteModal()"><i class="fa fa-check"></i> Salvar Nota</button>' +
                '</div>',
            controller: function ($scope, $uibModalInstance) {
                $scope.invoiceNote = $("#invoice-note").data("note") || "";
                
                $(document).on("change keyup blur", "#note", function () {
                    var $this = $(this);
                    var invoiceNote = $this.val();
                    $("#invoice-note").data("note", invoiceNote);
                    $scope.invoiceNote = invoiceNote;
                });
                
                $scope.closeAddNoteModal = function () {
                    $uibModalInstance.dismiss("cancel");
                };
                
                $scope.clear = function () {
                    $("#invoice-note").data("note", "");
                    $("#note").val("");
                    $scope.invoiceNote = "";
                };
            },
            scope: $scope,
            size: "md",
            backdrop: "static",
            keyboard: true,
        });
        uibModalInstance.result.catch(function () { 
            uibModalInstance.close(); 
        });
    };
}]);
