window.angularApp.factory("InvoiceSMSModal", ["API_URL", "window", "jQuery", "$http", "$uibModal", "$sce", "$rootScope", function (API_URL, window, $, $http, $uibModal, $sce, $scope) {
    return function($scope) {
        var uibModalInstance = $uibModal.open({
            animation: true,
            ariaLabelledBy: "modal-title",
            ariaDescribedBy: "modal-body",
            template: 
                '<style>' +
                    '.sms-modal .modal-dialog { width: 450px; max-width: 95%; margin: 30px auto; }' +
                    '.sms-modal .modal-content { border-radius: 8px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.3); overflow: visible; position: relative; }' +
                    '.sms-modal .btn-close-modal { position: absolute; top: -16px; right: -16px; width: 40px; height: 40px; background: #fff; border: 3px solid #ddd; border-radius: 50%; font-size: 16px; color: #666; cursor: pointer; box-shadow: 0 3px 12px rgba(0,0,0,0.25); z-index: 1070; line-height: 34px; text-align: center; }' +
                    '.sms-modal .btn-close-modal:hover { background: #e74c3c; color: #fff; border-color: #e74c3c; }' +
                    '.sms-modal .modal-header { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); padding: 18px 20px; border-radius: 8px 8px 0 0; border: none; }' +
                    '.sms-modal .modal-header h4 { color: #fff; margin: 0; font-size: 18px; font-weight: 600; }' +
                    '.sms-modal .modal-header h4 i { margin-right: 10px; }' +
                    '.sms-modal .modal-body { padding: 25px; background: #f8f9fa; }' +
                    '.sms-modal .modal-body .loading-box { padding: 40px; text-align: center; color: #999; }' +
                    '.sms-modal .modal-body .loading-box i { font-size: 32px; margin-bottom: 10px; }' +
                    '.sms-modal .modal-body .form-group { margin-bottom: 15px; }' +
                    '.sms-modal .modal-body label { font-weight: 600; color: #333; margin-bottom: 8px; display: block; }' +
                    '.sms-modal .modal-body .form-control { border: 2px solid #e9ecef; border-radius: 8px; padding: 12px 15px; font-size: 14px; transition: all 0.2s ease; }' +
                    '.sms-modal .modal-body .form-control:focus { border-color: #e74c3c; box-shadow: 0 0 0 3px rgba(231,76,60,0.15); outline: none; }' +
                    '.sms-modal .modal-body textarea.form-control { min-height: 100px; resize: vertical; }' +
                    '.sms-modal .box { border: none; box-shadow: none; margin: 0; }' +
                    '.sms-modal .box-header { display: none; }' +
                    '.sms-modal .box-body { padding: 0; }' +
                    '.sms-modal .box-footer { background: #fff; border-top: 1px solid #e9ecef; padding: 15px; border-radius: 0 0 8px 8px; text-align: right; }' +
                    '.sms-modal .btn-send-sms { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); border: none; color: #fff; padding: 10px 25px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }' +
                    '.sms-modal .btn-send-sms:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(231,76,60,0.3); }' +
                    '.sms-modal .alert { border-radius: 6px; margin-bottom: 15px; }' +
                '</style>' +
                '<div class="modal-header">' +
                    '<button ng-click="closeInvoiceSMSModal();" type="button" class="btn-close-modal">&times;</button>' +
                    '<h4><i class="fa fa-comment"></i> Enviar SMS</h4>' +
                '</div>' +
                '<div class="modal-body">' +
                    '<div ng-show="loading" class="loading-box">' +
                        '<i class="fa fa-spinner fa-spin"></i>' +
                        '<p>Carregando...</p>' +
                    '</div>' +
                    '<div ng-hide="loading" bind-html-compile="rawHtml"></div>' +
                '</div>',
            windowClass: "sms-modal",
            controller: function ($scope, $uibModalInstance) {
                $scope.loading = true;
                
                $http({
                  url: window.baseUrl+"_inc/sms/index.php?action_type=FORM&invoice_id="+$scope.invoiceID,
                  method: "GET"
                })
                .then(function(response, status, headers, config) {
                    $scope.loading = false;
                    $scope.modal_title = "Enviar SMS";
                    $scope.rawHtml = $sce.trustAsHtml(response.data);   
                }, function(response) {
                    $scope.loading = false;
                    window.swal("Oops!", response.data.errorMsg || "Erro ao carregar formulário", "error");
                });

                // Send SMS
                $(document).delegate("#send", "click", function(e) {
                    e.stopImmediatePropagation();
                    e.stopPropagation();
                    e.preventDefault();

                    var $tag = $(this);
                    var $btn = $tag.button("loading");
                    var form = $($tag.data("form"));
                    form.find(".alert").remove();
                    var actionUrl = form.attr("action");
                    
                    $http({
                        url: window.baseUrl+"_inc/" + actionUrl,
                        method: "POST",
                        data: form.serialize(),
                        cache: false,
                        processData: false,
                        contentType: false,
                        dataType: "json"
                    }).
                    then(function(response) {
                        $btn.button("reset");
                        var alertMsg = "<div class=\"alert alert-success\">";
                        alertMsg += "<p><i class=\"fa fa-check\"></i> " + response.data.msg + ".</p>";
                        alertMsg += "</div>";
                        form.find(".box-body").before(alertMsg);

                        // Alert
                        window.swal("Success", response.data.msg, "success")
                        .then(function(value) {
                            $scope.closeInvoiceSMSModal();
                            $(document).find(".close").trigger("click");
                        });

                    }, function(response) {
                        $btn.button("reset");
                        var alertMsg = "<div class=\"alert alert-danger\">";
                        window.angular.forEach(response.data, function(value, key) {
                            alertMsg += "<p><i class=\"fa fa-warning\"></i> " + value + ".</p>";
                        });
                        alertMsg += "</div>";
                        form.find(".box-body").before(alertMsg);
                        $(":input[type=\"button\"]").prop("disabled", false);
                        window.swal("Oops!", response.data.errorMsg, "error");
                    });
                });

                $scope.closeInvoiceSMSModal = function () {
                    $uibModalInstance.dismiss("cancel");
                };
            },
            scope: $scope,
            size: "md",
            backdrop  : "static",
            keyboard: true,
        });

        uibModalInstance.result.catch(function () { 
            uibModalInstance.close(); 
        });
    };
}]);