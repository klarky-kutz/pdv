window.angularApp.factory("CustomerEditModal", ["API_URL", "window", "jQuery", "$http", "$uibModal", "$sce", "$rootScope", function (API_URL, window, $, $http, $uibModal, $sce, $scope) {
    return function(customer, walkinCustomerId) {
        var isWalkinCustomer = walkinCustomerId && (parseInt(customer.customer_id) === parseInt(walkinCustomerId));
        
        var uibModalInstance = $uibModal.open({
            animation: true,
            ariaLabelledBy: "modal-title",
            ariaDescribedBy: "modal-body",
            template: 
                '<style>' +
                    '.customer-edit-modal .modal-dialog { width: 800px; max-width: 95%; }' +
                    '.customer-edit-modal .modal-body { max-height: calc(100vh - 200px); overflow-y: auto; }' +
                    '.customer-edit-modal .panel { margin-bottom: 15px; }' +
                    '.customer-edit-modal .panel-heading { padding: 8px 15px; }' +
                    '.customer-edit-modal .form-group { margin-bottom: 12px; }' +
                '</style>' +
                '<div class="modal-header" style="border-radius:5px 5px 0 0;background:#3498db;">' +
                    '<button ng-click="closeModal();" type="button" class="close" style="color:#fff;opacity:1;text-shadow:none;"><span>&times;</span></button>' +
                    '<h4 class="modal-title" style="color:#fff;">' +
                        '<i class="fa fa-user"></i> {{ isBlocked ? "Visualizar Cliente" : "Editar Cliente" }}' +
                    '</h4>' +
                '</div>' +
                '<div class="modal-body" style="padding:20px;">' +
                    '<div ng-if="isBlocked" class="alert alert-warning" style="margin-bottom:15px;">' +
                        '<i class="fa fa-lock"></i> <strong>Cliente Balcão</strong> é o cliente padrão do sistema e não pode ser alterado.' +
                    '</div>' +
                    '<div ng-if="loading" class="text-center" style="padding:50px;">' +
                        '<i class="fa fa-spinner fa-spin fa-3x text-primary"></i>' +
                        '<p style="margin-top:15px;color:#666;">Carregando dados...</p>' +
                    '</div>' +
                    '<div ng-if="!loading">' +
                        '<div class="row">' +
                            '<div class="col-md-6">' +
                                '<div class="panel panel-info">' +
                                    '<div class="panel-heading"><strong><i class="fa fa-user"></i> Dados Pessoais</strong></div>' +
                                    '<div class="panel-body">' +
                                        '<div class="form-group">' +
                                            '<label>Nome Completo <span class="text-danger">*</span></label>' +
                                            '<input type="text" class="form-control input-sm" ng-model="form.customer_name" ng-disabled="isBlocked" placeholder="Nome do cliente">' +
                                        '</div>' +
                                        '<div class="form-group">' +
                                            '<label>Telefone</label>' +
                                            '<input type="text" class="form-control input-sm" ng-model="form.customer_mobile" ng-disabled="isBlocked" placeholder="(00) 00000-0000">' +
                                        '</div>' +
                                        '<div class="form-group">' +
                                            '<label>E-mail</label>' +
                                            '<input type="email" class="form-control input-sm" ng-model="form.customer_email" ng-disabled="isBlocked" placeholder="email@exemplo.com">' +
                                        '</div>' +
                                        '<div class="row">' +
                                            '<div class="col-xs-6">' +
                                                '<div class="form-group">' +
                                                    '<label>Data Nascimento</label>' +
                                                    '<input type="text" class="form-control input-sm" ng-model="form.dob" ng-disabled="isBlocked" placeholder="AAAA-MM-DD">' +
                                                '</div>' +
                                            '</div>' +
                                            '<div class="col-xs-6">' +
                                                '<div class="form-group">' +
                                                    '<label>Idade</label>' +
                                                    '<input type="number" class="form-control input-sm" ng-model="form.customer_age" ng-disabled="isBlocked" min="0" max="140">' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                        '<div class="form-group">' +
                                            '<label>Sexo</label>' +
                                            '<select class="form-control input-sm" ng-model="form.customer_sex" ng-disabled="isBlocked">' +
                                                '<option value="1">Masculino</option>' +
                                                '<option value="2">Feminino</option>' +
                                            '</select>' +
                                        '</div>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                            '<div class="col-md-6">' +
                                '<div class="panel panel-success">' +
                                    '<div class="panel-heading"><strong><i class="fa fa-map-marker"></i> Endereço</strong></div>' +
                                    '<div class="panel-body">' +
                                        '<div class="form-group">' +
                                            '<label>Endereço</label>' +
                                            '<textarea class="form-control input-sm" ng-model="form.customer_address" ng-disabled="isBlocked" rows="2" placeholder="Rua, número, bairro..."></textarea>' +
                                        '</div>' +
                                        '<div class="row">' +
                                            '<div class="col-xs-6">' +
                                                '<div class="form-group">' +
                                                    '<label>Cidade</label>' +
                                                    '<input type="text" class="form-control input-sm" ng-model="form.customer_city" ng-disabled="isBlocked" placeholder="Cidade">' +
                                                '</div>' +
                                            '</div>' +
                                            '<div class="col-xs-6">' +
                                                '<div class="form-group">' +
                                                    '<label>Estado</label>' +
                                                    '<input type="text" class="form-control input-sm" ng-model="form.customer_state" ng-disabled="isBlocked" placeholder="Estado">' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                        '<div class="form-group">' +
                                            '<label>País</label>' +
                                            '<input type="text" class="form-control input-sm" ng-model="form.customer_country" ng-disabled="isBlocked" placeholder="País">' +
                                        '</div>' +
                                        '<div class="row">' +
                                            '<div class="col-xs-6">' +
                                                '<div class="form-group">' +
                                                    '<label>Status</label>' +
                                                    '<select class="form-control input-sm" ng-model="form.status" ng-disabled="isBlocked">' +
                                                        '<option value="1">Ativo</option>' +
                                                        '<option value="0">Inativo</option>' +
                                                    '</select>' +
                                                '</div>' +
                                            '</div>' +
                                            '<div class="col-xs-6">' +
                                                '<div class="form-group">' +
                                                    '<label>Ordem</label>' +
                                                    '<input type="number" class="form-control input-sm" ng-model="form.sort_order" ng-disabled="isBlocked" min="0">' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="modal-footer" style="border-radius:0 0 5px 5px;">' +
                    '<button ng-click="closeModal();" type="button" class="btn btn-default">' +
                        '<i class="fa fa-times"></i> {{ isBlocked ? "Fechar" : "Cancelar" }}' +
                    '</button>' +
                    '<button ng-if="!isBlocked" ng-click="updateCustomer();" type="button" class="btn btn-primary" ng-disabled="saving">' +
                        '<i class="fa" ng-class="{\'fa-spinner fa-spin\': saving, \'fa-save\': !saving}"></i> ' +
                        '{{ saving ? "Salvando..." : "Salvar Alterações" }}' +
                    '</button>' +
                '</div>',
            controller: function ($scope, $uibModalInstance) {
                $scope.loading = true;
                $scope.saving = false;
                $scope.isBlocked = isWalkinCustomer;
                $scope.customerId = customer.customer_id;
                $scope.customerName = customer.customer_name;
                $scope.currentStoreId = window.store && window.store.store_id ? window.store.store_id : 1;
                
                $scope.form = {
                    customer_name: '',
                    customer_mobile: '',
                    customer_email: '',
                    dob: '',
                    customer_age: 0,
                    customer_sex: '1',
                    customer_address: '',
                    customer_city: '',
                    customer_state: '',
                    customer_country: 'Brazil',
                    status: '1',
                    sort_order: 0
                };
                
                // Função auxiliar para converter string de data em Date object
                function parseDate(dateStr) {
                    if (!dateStr) return null;
                    // Se já for um Date, retorna
                    if (dateStr instanceof Date) return dateStr;
                    // Tenta parsear a string (formato YYYY-MM-DD)
                    var parts = String(dateStr).split('-');
                    if (parts.length === 3) {
                        return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                    }
                    return null;
                }
                
                // Função auxiliar para formatar Date em string YYYY-MM-DD
                function formatDateToString(date) {
                    if (!date) return '';
                    if (!(date instanceof Date)) return String(date);
                    var year = date.getFullYear();
                    var month = ('0' + (date.getMonth() + 1)).slice(-2);
                    var day = ('0' + date.getDate()).slice(-2);
                    return year + '-' + month + '-' + day;
                }
                
                // Carregar dados do cliente
                $http({
                    url: window.baseUrl + "_inc/customer.php?customer_id=" + customer.customer_id + "&action_type=GETDATA",
                    method: "GET"
                }).then(function(response) {
                    $scope.loading = false;
                    if (response.data) {
                        var c = response.data;
                        $scope.form.customer_name = c.customer_name || '';
                        $scope.form.customer_mobile = c.customer_mobile || '';
                        $scope.form.customer_email = c.customer_email || '';
                        // Converter string de data para Date object para evitar erro ngModel:datefmt
                        $scope.form.dob = parseDate(c.dob);
                        $scope.form.customer_age = parseInt(c.customer_age) || 0;
                        $scope.form.customer_sex = String(c.customer_sex || '1');
                        $scope.form.customer_address = c.customer_address || '';
                        $scope.form.customer_city = c.customer_city || '';
                        $scope.form.customer_state = c.customer_state || '';
                        $scope.form.customer_country = c.customer_country || 'Brazil';
                        $scope.form.status = String(c.status || '1');
                        $scope.form.sort_order = parseInt(c.sort_order) || 0;
                    }
                }).catch(function(err) {
                    $scope.loading = false;
                    window.toastr.error("Erro ao carregar dados do cliente", "Erro");
                });
                
                $scope.updateCustomer = function() {
                    if ($scope.saving || $scope.isBlocked) return;
                    
                    if (!$scope.form.customer_name) {
                        window.toastr.warning("O nome do cliente é obrigatório", "Atenção");
                        return;
                    }
                    
                    $scope.saving = true;
                    
                    var params = {
                        action_type: 'UPDATE',
                        customer_id: $scope.customerId,
                        customer_name: $scope.form.customer_name,
                        customer_mobile: $scope.form.customer_mobile,
                        customer_email: $scope.form.customer_email,
                        dob: formatDateToString($scope.form.dob),
                        customer_age: $scope.form.customer_age,
                        customer_sex: $scope.form.customer_sex,
                        customer_address: $scope.form.customer_address,
                        customer_city: $scope.form.customer_city,
                        customer_state: $scope.form.customer_state,
                        customer_country: $scope.form.customer_country,
                        status: $scope.form.status,
                        sort_order: $scope.form.sort_order
                    };
                    
                    params['customer_store[0]'] = $scope.currentStoreId;
                    
                    $http({
                        url: window.baseUrl + "_inc/customer.php",
                        method: "POST",
                        data: $.param(params),
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'}
                    }).then(function(response) {
                        $scope.saving = false;
                        window.toastr.success(response.data.msg || "Cliente atualizado!", "Sucesso");
                        $scope.closeModal();
                    }, function(response) {
                        $scope.saving = false;
                        window.toastr.error(response.data.errorMsg || "Erro ao atualizar", "Erro");
                    });
                };

                $scope.closeModal = function () {
                    $uibModalInstance.dismiss("cancel");
                };
            },
            scope: $scope,
            size: "lg",
            windowClass: "customer-edit-modal",
            backdrop: "static",
            keyboard: true,
        });

        uibModalInstance.result.catch(function () { 
            uibModalInstance.close(); 
        });
    };
}]);
