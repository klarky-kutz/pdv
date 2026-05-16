window.angularApp.factory("ProductQuickCreateModal", ["API_URL", "window", "jQuery", "$http", "$uibModal", "$sce", "$rootScope", 
    function (API_URL, window, $, $http, $uibModal, $sce, $scope) {
        return function($scope) {
            var uibModalInstance = $uibModal.open({
                animation: true,
                ariaLabelledBy: "modal-title",
                ariaDescribedBy: "modal-body",
                template: 
                    '<style>' +
                        '.product-quick-modal .modal-dialog { width: 600px; max-width: 95%; }' +
                    '</style>' +
                    '<div class="modal-header" style="background:#3498db;border-radius:5px 5px 0 0;">' +
                        '<button ng-click="closeModal();" type="button" class="close" style="color:#fff;opacity:1;"><span>&times;</span></button>' +
                        '<h4 class="modal-title" style="color:#fff;"><i class="fa fa-cube"></i> Novo Produto</h4>' +
                    '</div>' +
                    '<div class="modal-body" style="padding:20px;">' +
                        '<div ng-if="loading" class="text-center" style="padding:40px;">' +
                            '<i class="fa fa-spinner fa-spin fa-2x text-primary"></i>' +
                            '<p style="margin-top:10px;">Carregando...</p>' +
                        '</div>' +
                        '<div ng-if="!loading">' +
                            '<form class="form-horizontal">' +
                                '<div class="panel panel-info">' +
                                    '<div class="panel-heading"><i class="fa fa-info-circle"></i> Informações Básicas</div>' +
                                    '<div class="panel-body">' +
                                        '<div class="form-group">' +
                                            '<label class="col-sm-3 control-label">Nome <span class="text-danger">*</span></label>' +
                                            '<div class="col-sm-9">' +
                                                '<input type="text" class="form-control" ng-model="form.productName" placeholder="Ex: Coca-Cola 350ml">' +
                                            '</div>' +
                                        '</div>' +
                                        '<div class="form-group">' +
                                            '<label class="col-sm-3 control-label">Código</label>' +
                                            '<div class="col-sm-9">' +
                                                '<div class="input-group">' +
                                                    '<input type="text" class="form-control" ng-model="form.productCode" placeholder="Código de barras ou SKU">' +
                                                    '<span class="input-group-btn">' +
                                                        '<button type="button" class="btn btn-info" ng-click="generateCode()"><i class="fa fa-random"></i></button>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                        '<div class="form-group" style="margin-bottom:0;">' +
                                            '<label class="col-sm-3 control-label">Categoria</label>' +
                                            '<div class="col-sm-9">' +
                                                '<select class="form-control" ng-model="form.categoryId">' +
                                                    '<option value="">Selecione uma categoria...</option>' +
                                                    '<option ng-repeat="c in categories" value="{{ c.category_id }}">{{ c.category_name }}</option>' +
                                                '</select>' +
                                            '</div>' +
                                        '</div>' +
                                    '</div>' +
                                '</div>' +
                                '<div class="panel panel-success">' +
                                    '<div class="panel-heading"><i class="fa fa-money"></i> Preços e Estoque</div>' +
                                    '<div class="panel-body">' +
                                        '<div class="form-group">' +
                                            '<label class="col-sm-3 control-label">Preço Custo</label>' +
                                            '<div class="col-sm-4">' +
                                                '<input type="number" step="0.01" min="0" class="form-control" ng-model="form.purchasePrice" placeholder="0.00">' +
                                            '</div>' +
                                            '<label class="col-sm-2 control-label">Venda <span class="text-danger">*</span></label>' +
                                            '<div class="col-sm-3">' +
                                                '<input type="number" step="0.01" min="0" class="form-control" ng-model="form.sellingPrice" placeholder="0.00">' +
                                            '</div>' +
                                        '</div>' +
                                        '<div class="form-group" style="margin-bottom:0;">' +
                                            '<label class="col-sm-3 control-label">Unidade</label>' +
                                            '<div class="col-sm-4">' +
                                                '<select class="form-control" ng-model="form.unitId">' +
                                                    '<option value="">Selecione...</option>' +
                                                    '<option ng-repeat="u in units" value="{{ u.unit_id }}">{{ u.unit_name }}</option>' +
                                                '</select>' +
                                            '</div>' +
                                            '<label class="col-sm-2 control-label">Fornecedor</label>' +
                                            '<div class="col-sm-3">' +
                                                '<select class="form-control" ng-model="form.supplierId">' +
                                                    '<option value="">Selecione...</option>' +
                                                    '<option ng-repeat="s in suppliers" value="{{ s.sup_id }}">{{ s.sup_name }}</option>' +
                                                '</select>' +
                                            '</div>' +
                                        '</div>' +
                                    '</div>' +
                                '</div>' +
                            '</form>' +
                        '</div>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button ng-click="closeModal();" type="button" class="btn btn-default"><i class="fa fa-times"></i> Cancelar</button>' +
                        '<button ng-click="saveProduct();" type="button" class="btn btn-primary" ng-disabled="saving">' +
                            '<i class="fa" ng-class="{\'fa-spinner fa-spin\': saving, \'fa-save\': !saving}"></i> ' +
                            '{{ saving ? "Salvando..." : "Criar Produto" }}' +
                        '</button>' +
                    '</div>',
                controller: function ($scope, $uibModalInstance) {
                    $scope.loading = true;
                    $scope.saving = false;
                    $scope.categories = [];
                    $scope.units = [];
                    $scope.suppliers = [];
                    
                    // Usar objeto para evitar problemas de escopo com ng-model
                    $scope.form = {
                        productName: '',
                        productCode: '',
                        categoryId: '',
                        purchasePrice: 0,
                        sellingPrice: 0,
                        unitId: '',
                        supplierId: ''
                    };
                    
                    $scope.generateCode = function() {
                        var result = 'PRD';
                        for (var i = 0; i < 8; i++) {
                            result += Math.floor(Math.random() * 10);
                        }
                        $scope.form.productCode = result;
                    };
                    
                    // Carregar categorias, unidades e fornecedores em paralelo
                    var loadCount = 0;
                    var totalLoads = 3;
                    
                    function checkLoadComplete() {
                        loadCount++;
                        if (loadCount >= totalLoads) {
                            $scope.loading = false;
                            $scope.generateCode();
                        }
                    }
                    
                    // Carregar categorias
                    $http({
                        url: window.baseUrl + "_inc/category.php?get_categories=1",
                        method: "GET"
                    }).then(function(response) {
                        if (response.data && Array.isArray(response.data)) {
                            $scope.categories = response.data;
                        }
                        checkLoadComplete();
                    }, function() {
                        checkLoadComplete();
                    });
                    
                    // Carregar unidades
                    $http({
                        url: window.baseUrl + "_inc/unit.php?get_units=1",
                        method: "GET"
                    }).then(function(response) {
                        if (response.data && Array.isArray(response.data)) {
                            $scope.units = response.data;
                        }
                        checkLoadComplete();
                    }, function() {
                        checkLoadComplete();
                    });
                    
                    // Carregar fornecedores
                    $http({
                        url: window.baseUrl + "_inc/supplier.php?get_suppliers=1",
                        method: "GET"
                    }).then(function(response) {
                        if (response.data) {
                            // Lidar com formato {success: true, data: []} ou array direto
                            if (response.data.data && Array.isArray(response.data.data)) {
                                $scope.suppliers = response.data.data;
                            } else if (Array.isArray(response.data)) {
                                $scope.suppliers = response.data;
                            }
                        }
                        checkLoadComplete();
                    }, function() {
                        checkLoadComplete();
                    });
                    
                    $scope.saveProduct = function() {
                        if ($scope.saving) return;
                        if (!$scope.form.productName || !$scope.form.productName.trim()) {
                            window.toastr.warning("Informe o nome do produto", "Atenção");
                            return;
                        }
                        if (!$scope.form.sellingPrice || $scope.form.sellingPrice <= 0) {
                            window.toastr.warning("Informe o preço de venda", "Atenção");
                            return;
                        }
                        
                        $scope.saving = true;
                        $http({
                            url: window.baseUrl + "_inc/product.php",
                            method: "POST",
                            data: $.param({
                                action_type: 'QUICKCREATE',
                                p_name: $scope.form.productName,
                                p_code: $scope.form.productCode,
                                category_id: $scope.form.categoryId || '',
                                purchase_price: $scope.form.purchasePrice || 0,
                                sell_price: $scope.form.sellingPrice,
                                unit_id: $scope.form.unitId || '',
                                sup_id: $scope.form.supplierId || ''
                            }),
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'}
                        }).then(function(response) {
                            $scope.saving = false;
                            window.toastr.success("Produto criado com sucesso!", "Sucesso");
                            $scope.closeModal();
                            if ($scope.ProductQuickCreateModalCallback) {
                                $scope.ProductQuickCreateModalCallback($scope);
                            }
                        }, function(response) {
                            $scope.saving = false;
                            window.toastr.error(response.data && response.data.errorMsg ? response.data.errorMsg : "Erro ao criar produto", "Erro");
                        });
                    };

                    $scope.closeModal = function () { $uibModalInstance.dismiss("cancel"); };
                },
                scope: $scope,
                size: "md",
                windowClass: "product-quick-modal",
                backdrop: "static",
                keyboard: true,
            });

            uibModalInstance.result.catch(function () { uibModalInstance.close(); });
        };
    }
]);
