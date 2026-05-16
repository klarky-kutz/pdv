window.angularApp.factory("EmailModal", ["API_URL", "window", "jQuery", "$http", "$uibModal", "$sce", "$rootScope", function (API_URL, window, $, $http, $uibModal, $sce, $scope) {
    return function(content) {
        var uibModalInstance = $uibModal.open({
            animation: true,
            ariaLabelledBy: "modal-title",
            ariaDescribedBy: "modal-body",
            template: 
                '<style>' +
                    '.email-modal .modal-dialog { width: 450px; max-width: 95%; margin: 30px auto; }' +
                    '.email-modal .modal-content { border-radius: 8px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.3); overflow: visible; position: relative; }' +
                    '.email-modal .btn-close-modal { position: absolute; top: -16px; right: -16px; width: 40px; height: 40px; background: #fff; border: 3px solid #ddd; border-radius: 50%; font-size: 16px; color: #666; cursor: pointer; box-shadow: 0 3px 12px rgba(0,0,0,0.25); z-index: 1070; line-height: 34px; text-align: center; }' +
                    '.email-modal .btn-close-modal:hover { background: #e74c3c; color: #fff; border-color: #e74c3c; }' +
                    '.email-modal .modal-header { background: linear-gradient(135deg, #27ae60 0%, #1e8449 100%); padding: 18px 20px; border-radius: 8px 8px 0 0; border: none; }' +
                    '.email-modal .modal-header h4 { color: #fff; margin: 0; font-size: 18px; font-weight: 600; }' +
                    '.email-modal .modal-header h4 i { margin-right: 10px; }' +
                    '.email-modal .modal-body { padding: 25px; background: #f8f9fa; }' +
                    '.email-modal .modal-body .form-group { margin-bottom: 15px; }' +
                    '.email-modal .modal-body label { font-weight: 600; color: #333; margin-bottom: 8px; display: block; }' +
                    '.email-modal .modal-body .form-control { border: 2px solid #e9ecef; border-radius: 8px; padding: 12px 15px; font-size: 14px; transition: all 0.2s ease; }' +
                    '.email-modal .modal-body .form-control:focus { border-color: #27ae60; box-shadow: 0 0 0 3px rgba(39,174,96,0.15); outline: none; }' +
                    '.email-modal .modal-footer { background: #fff; border-top: 1px solid #e9ecef; padding: 15px 20px; border-radius: 0 0 8px 8px; display: flex; justify-content: flex-end; gap: 10px; }' +
                    '.email-modal .btn-send { background: linear-gradient(135deg, #27ae60 0%, #1e8449 100%); border: none; color: #fff; padding: 10px 25px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }' +
                    '.email-modal .btn-send:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(39,174,96,0.3); }' +
                    '.email-modal .btn-cancel { background: #6c757d; border: none; color: #fff; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }' +
                    '.email-modal .btn-cancel:hover { background: #5a6268; }' +
                    '.email-modal .invoice-info { background: #fff; border-radius: 8px; padding: 15px; margin-bottom: 15px; border: 1px solid #e9ecef; }' +
                    '.email-modal .invoice-info p { margin: 0; color: #666; font-size: 13px; }' +
                    '.email-modal .invoice-info strong { color: #333; }' +
                '</style>' +
                '<div class="modal-header">' +
                    '<button ng-click="cancel();" type="button" class="btn-close-modal">&times;</button>' +
                    '<h4><i class="fa fa-envelope"></i> Enviar Nota por Email</h4>' +
                '</div>' +
                '<div class="modal-body">' +
                    '<div class="invoice-info">' +
                        '<p><i class="fa fa-file-text-o"></i> <strong>Assunto:</strong> {{ modal_title }}</p>' +
                    '</div>' +
                    '<div bind-html-compile="rawHtml"></div>' +
                '</div>' +
                '<div class="modal-footer">' +
                    '<button ng-click="cancel();" type="button" class="btn btn-cancel"><i class="fa fa-times"></i> Cancelar</button>' +
                    '<button id="sendEmailBtn" type="button" class="btn btn-send" data-loading-text="Enviando..."><i class="fa fa-paper-plane"></i> Enviar Email</button>' +
                '</div>',
            windowClass: "email-modal",
            controller: function ($scope, $uibModalInstance) {
                $scope.modal_title = content.title;
                var form = "<form method=\"post\" action=\"#\" id=\"email-form\" style=\"margin:40px 20px;\">";
                form += "<input type=\"hidden\" name=\"recipient_name\" value=\""+content.recipientName+"\">";
                form += "<input type=\"hidden\" name=\"template\" value=\""+content.template+"\">";
                form += "<input type=\"hidden\" name=\"subject\" value=\""+content.subject+"\">";
                form += "<input type=\"hidden\" name=\"title\" value=\""+content.title.trim()+"\">";
                form += "<input type=\"email\" name=\"email\" class=\"form-control\" placeholder=\"Please, type a valid email address\" required>";
                if (content.styles && content.styles != undefined) {
                    form += "<textarea style=\"display:none;\" name=\"styles\">"+content.styles.trim()+"</textarea>";
                }
                form += "<textarea style=\"display:none;\" name=\"emailbody\">"+content.html.trim()+"</textarea>";
                form += "</form>";
                $scope.rawHtml = $sce.trustAsHtml(form);
                $scope.content = content;
                $(document).delegate("#sendEmailBtn", "click", function(e) {
                    e.stopImmediatePropagation();
                    e.stopPropagation();
                    e.preventDefault();
                    var $tag = $(this);
                    var $btn = $tag.button("loading");
                    $("body").addClass("overlay-loader");
                    $http({
                        url: window.baseUrl+"_inc/send_email.php",
                        method: "POST",
                        data: $("#email-form").serialize(),
                        cache: false,
                        processData: false,
                        contentType: false,
                        dataType: "json"
                    }).
                    then(function(response) {
                        $("body").removeClass("overlay-loader");
                        $btn.button("reset");
                        window.swal("Success", response.data.msg, "success").then(function() {
                            $scope.cancel();
                        });
                    }, function(response) {
                        $("body").removeClass("overlay-loader");
                        $btn.button("reset");
                        window.swal("Oops!", response.data.errorMsg, "error");
                    });
                });
                $scope.cancel = function () {
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