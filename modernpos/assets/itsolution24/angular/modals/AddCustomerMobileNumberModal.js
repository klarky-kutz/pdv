window.angularApp.factory("AddCustomerMobileNumberModal", ["API_URL", "window", "jQuery", "$http", "$uibModal", "$sce", "$rootScope", function (API_URL, window, $, $http, $uibModal, $sce, $scope) {
    "use strict";
    return function($scope) {
        var uibModalInstance = $uibModal.open({
            animation: true,
            ariaLabelledBy: "modal-title",
            ariaDescribedBy: "modal-body",
            windowClass: "mobile-number-modal",
            template: 
                '<style>' +
                    '.mobile-number-modal .modal-dialog { width: 450px; max-width: 95%; }' +
                '</style>' +
                '<div class="modal-header" style="background:#3498db;border-radius:5px 5px 0 0;">' +
                    '<button ng-click="closeModal();" type="button" class="close" style="color:#fff;opacity:1;"><span>&times;</span></button>' +
                    '<h4 class="modal-title" style="color:#fff;"><i class="fa fa-mobile"></i> Telefone do Cliente</h4>' +
                    '<small style="color:rgba(255,255,255,0.8);">{{ parentCustomerName }}</small>' +
                '</div>' +
                '<div class="modal-body" style="padding:20px;">' +
                    '<form class="form-horizontal">' +
                        '<div class="form-group" style="margin-bottom:15px;">' +
                            '<label class="col-sm-4 control-label"><i class="fa fa-whatsapp text-success"></i> Celular</label>' +
                            '<div class="col-sm-8">' +
                                '<input type="tel" id="cnumber" class="form-control" ng-model="mobileNumber" placeholder="(00) 00000-0000" ng-change="formatPhone()">' +
                            '</div>' +
                        '</div>' +
                    '</form>' +
                    '<div class="alert alert-info" style="margin-bottom:0;">' +
                        '<i class="fa fa-info-circle"></i> Este número será usado para <b>envio de SMS</b> e <b>contato via WhatsApp</b>.' +
                    '</div>' +
                '</div>' +
                '<div class="modal-footer">' +
                    '<button type="button" class="btn btn-default" ng-click="closeModal()"><i class="fa fa-times"></i> Cancelar</button>' +
                    '<button type="button" class="btn btn-danger" ng-click="clear()" ng-disabled="saving"><i class="fa fa-trash"></i> Limpar</button>' +
                    '<button type="button" class="btn btn-primary" ng-click="save()" ng-disabled="saving">' +
                        '<i class="fa" ng-class="{\'fa-spinner fa-spin\': saving, \'fa-save\': !saving}"></i> ' +
                        '{{ saving ? "Salvando..." : "Salvar" }}' +
                    '</button>' +
                '</div>',
            controller: function ($scope, $uibModalInstance) {
                $scope.mobileNumber = $scope.customerMobileNumber || '';
                $scope.saving = false;
                $scope.parentCustomerName = $scope.customerName || 'Cliente';
                $scope.parentCustomerId = $scope.customerId;

                // Formatar telefone brasileiro
                $scope.formatPhone = function() {
                    var phone = $scope.mobileNumber || '';
                    phone = phone.replace(/\D/g, '');
                    
                    if (phone.length > 11) {
                        phone = phone.substring(0, 11);
                    }
                    
                    if (phone.length > 0) {
                        if (phone.length <= 2) {
                            phone = '(' + phone;
                        } else if (phone.length <= 7) {
                            phone = '(' + phone.substring(0, 2) + ') ' + phone.substring(2);
                        } else {
                            phone = '(' + phone.substring(0, 2) + ') ' + phone.substring(2, 7) + '-' + phone.substring(7);
                        }
                    }
                    
                    $scope.mobileNumber = phone;
                };

                $scope.save = function() {
                    if ($scope.saving) return;
                    
                    // Remover máscara antes de salvar - salvar apenas números
                    var phoneClean = ($scope.mobileNumber || '').replace(/\D/g, '');
                    
                    // Se não tem cliente selecionado, apenas salva localmente
                    if (!$scope.parentCustomerId) {
                        $("#customer-mobile-number").val(phoneClean);
                        $scope.customerMobileNumber = phoneClean;
                        $uibModalInstance.dismiss("cancel");
                        return;
                    }
                    
                    // Salvar no banco de dados via API
                    $scope.saving = true;
                    
                    $http({
                        url: window.baseUrl + "_inc/customer.php",
                        method: "POST",
                        data: $.param({
                            action_type: 'UPDATEMOBILE',
                            customer_id: $scope.parentCustomerId,
                            customer_mobile: phoneClean
                        }),
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'}
                    }).then(function(response) {
                        $scope.saving = false;
                        $("#customer-mobile-number").val(phoneClean);
                        $scope.customerMobileNumber = phoneClean;
                        window.toastr.success(response.data.msg || "Telefone atualizado com sucesso!", "Sucesso");
                        $uibModalInstance.dismiss("cancel");
                    }, function(response) {
                        $scope.saving = false;
                        window.toastr.error(response.data && response.data.errorMsg ? response.data.errorMsg : "Erro ao atualizar telefone", "Erro");
                    });
                };

                $scope.closeModal = function () {
                    $uibModalInstance.dismiss("cancel");
                };

                $scope.clear = function () {
                    if ($scope.saving) return;
                    
                    // Se não tem cliente selecionado, apenas limpa localmente
                    if (!$scope.parentCustomerId) {
                        $scope.mobileNumber = '';
                        $("#customer-mobile-number").val('');
                        $scope.customerMobileNumber = '';
                        $uibModalInstance.dismiss("cancel");
                        return;
                    }
                    
                    // Limpar no banco de dados via API
                    $scope.saving = true;
                    
                    $http({
                        url: window.baseUrl + "_inc/customer.php",
                        method: "POST",
                        data: $.param({
                            action_type: 'UPDATEMOBILE',
                            customer_id: $scope.parentCustomerId,
                            customer_mobile: ''
                        }),
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'}
                    }).then(function(response) {
                        $scope.saving = false;
                        $scope.mobileNumber = '';
                        $("#customer-mobile-number").val('');
                        $scope.customerMobileNumber = '';
                        window.toastr.success("Telefone removido com sucesso!", "Sucesso");
                        $uibModalInstance.dismiss("cancel");
                    }, function(response) {
                        $scope.saving = false;
                        window.toastr.error(response.data && response.data.errorMsg ? response.data.errorMsg : "Erro ao remover telefone", "Erro");
                    });
                };

                // Foco no campo
                setTimeout(function() {
                    $('#cnumber').focus();
                }, 300);
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
