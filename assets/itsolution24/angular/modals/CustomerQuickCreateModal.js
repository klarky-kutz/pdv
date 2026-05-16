window.angularApp.factory("CustomerQuickCreateModal", ["API_URL", "window", "jQuery", "$http", "$uibModal", "$sce", "$rootScope",
    function (API_URL, window, $, $http, $uibModal, $sce, $scope) {
        return function($scope) {
            var uibModalInstance = $uibModal.open({
                animation: true,
                ariaLabelledBy: "modal-title",
                ariaDescribedBy: "modal-body",
                template: 
                    '<style>' +
                        '.customer-create-modal .modal-dialog { width: 500px; max-width: 95%; }' +
                    '</style>' +
                    '<div class="modal-header" style="background:#3498db;border-radius:5px 5px 0 0;">' +
                        '<button ng-click="closeModal();" type="button" class="close" style="color:#fff;opacity:1;"><span>&times;</span></button>' +
                        '<h4 class="modal-title" style="color:#fff;"><i class="fa fa-user-plus"></i> Novo Cliente</h4>' +
                    '</div>' +
                    '<div class="modal-body" style="padding:20px;">' +
                        '<form class="form-horizontal">' +
                            '<div class="form-group">' +
                                '<label class="col-sm-4 control-label">Nome <span class="text-danger">*</span></label>' +
                                '<div class="col-sm-8">' +
                                    '<input type="text" class="form-control" ng-model="customer.name" placeholder="Ex: João da Silva">' +
                                '</div>' +
                            '</div>' +
                            '<div class="form-group">' +
                                '<label class="col-sm-4 control-label">Telefone <span class="text-danger">*</span></label>' +
                                '<div class="col-sm-8">' +
                                    '<input type="tel" class="form-control" ng-model="customer.mobile" placeholder="(00) 00000-0000" ng-change="formatPhone()">' +
                                '</div>' +
                            '</div>' +
                            '<div class="form-group">' +
                                '<label class="col-sm-4 control-label">Data Nasc.</label>' +
                                '<div class="col-sm-8">' +
                                    '<input type="date" class="form-control" ng-model="customer.dob">' +
                                '</div>' +
                            '</div>' +
                            '<div class="form-group">' +
                                '<label class="col-sm-4 control-label">Sexo</label>' +
                                '<div class="col-sm-8">' +
                                    '<select class="form-control" ng-model="customer.sex">' +
                                        '<option value="1">Masculino</option>' +
                                        '<option value="2">Feminino</option>' +
                                        '<option value="3">Outro</option>' +
                                    '</select>' +
                                '</div>' +
                            '</div>' +
                            '<div class="form-group">' +
                                '<label class="col-sm-4 control-label">E-mail</label>' +
                                '<div class="col-sm-8">' +
                                    '<input type="email" class="form-control" ng-model="customer.email" placeholder="email@exemplo.com">' +
                                '</div>' +
                            '</div>' +
                        '</form>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button ng-click="closeModal();" type="button" class="btn btn-default"><i class="fa fa-times"></i> Cancelar</button>' +
                        '<button ng-click="saveCustomer();" type="button" class="btn btn-primary" ng-disabled="saving">' +
                            '<i class="fa" ng-class="{\'fa-spinner fa-spin\': saving, \'fa-check\': !saving}"></i> ' +
                            '{{ saving ? "Salvando..." : "Salvar Cliente" }}' +
                        '</button>' +
                    '</div>',
                controller: function ($scope, $uibModalInstance) {
                    $scope.customer = { name: '', mobile: '', email: '', dob: null, sex: '1' };
                    $scope.saving = false;

                    // Função para formatar Date para string YYYY-MM-DD
                    function formatDateToString(date) {
                        if (!date) return '';
                        if (!(date instanceof Date)) return String(date);
                        var year = date.getFullYear();
                        var month = ('0' + (date.getMonth() + 1)).slice(-2);
                        var day = ('0' + date.getDate()).slice(-2);
                        return year + '-' + month + '-' + day;
                    }

                    $scope.formatPhone = function() {
                        var phone = ($scope.customer.mobile || '').replace(/\D/g, '');
                        if (phone.length > 11) phone = phone.substring(0, 11);
                        if (phone.length > 0) {
                            if (phone.length <= 2) phone = '(' + phone;
                            else if (phone.length <= 7) phone = '(' + phone.substring(0, 2) + ') ' + phone.substring(2);
                            else phone = '(' + phone.substring(0, 2) + ') ' + phone.substring(2, 7) + '-' + phone.substring(7);
                        }
                        $scope.customer.mobile = phone;
                    };

                    $scope.saveCustomer = function() {
                        if (!$scope.customer.name || !$scope.customer.name.trim()) {
                            window.toastr.error('Informe o nome do cliente', 'Atenção');
                            return;
                        }
                        var phoneClean = ($scope.customer.mobile || '').replace(/\D/g, '');
                        if (!phoneClean || phoneClean.length < 10) {
                            window.toastr.error('Informe um telefone válido', 'Atenção');
                            return;
                        }

                        $scope.saving = true;
                        
                        var storeId = window.store ? window.store.store_id : 1;
                        var postData = 'action_type=QUICKCREATE' +
                            '&customer_name=' + encodeURIComponent($scope.customer.name.trim()) +
                            '&customer_mobile=' + encodeURIComponent(phoneClean) +
                            '&customer_email=' + encodeURIComponent($scope.customer.email || '') +
                            '&dob=' + encodeURIComponent(formatDateToString($scope.customer.dob)) +
                            '&customer_sex=' + encodeURIComponent($scope.customer.sex || '1') +
                            '&customer_country=Brazil' +
                            '&customer_store[]=' + storeId +
                            '&status=1';
                        
                        $http({
                            url: window.baseUrl + '_inc/customer.php',
                            method: 'POST',
                            data: postData,
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'}
                        }).then(function(response) {
                            $scope.saving = false;
                            window.toastr.success('Cliente cadastrado!', 'Sucesso');
                            
                            // Atualiza o scope do POS
                            if ($scope.$parent) {
                                $scope.$parent.customerName = response.data.customer_name + ' (' + response.data.customer_contact + ')';
                                $scope.$parent.customerMobileNumber = response.data.customer_contact;
                                $scope.$parent.customerId = response.data.id;
                                $scope.$parent.dueAmount = 0;
                            }
                            
                            $uibModalInstance.close(response.data);
                        }, function(response) {
                            $scope.saving = false;
                            var errorMsg = (response.data && response.data.errorMsg) ? response.data.errorMsg : 'Erro ao cadastrar';
                            window.toastr.error(errorMsg, 'Erro');
                        });
                    };

                    $scope.closeModal = function() { $uibModalInstance.dismiss('cancel'); };
                    setTimeout(function() { $('input[ng-model="customer.name"]').focus(); }, 300);
                },
                scope: $scope,
                size: "md",
                windowClass: "customer-create-modal",
                backdrop: "static",
                keyboard: true
            });
            uibModalInstance.result.catch(function() { uibModalInstance.close(); });
            return uibModalInstance;
        };
    }
]);
