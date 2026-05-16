/**
 * ProductSelectorModal - Modal reutilizável para seleção de produtos
 * 
 * Uso:
 * ProductSelectorModal({
 *   multiple: true,           // Permitir seleção múltipla
 *   supplierId: null,         // Filtrar por fornecedor
 *   categoryId: null,         // Filtrar por categoria
 *   onSelect: function(products) {} // Callback com produtos selecionados
 * });
 */
window.angularApp.factory("ProductSelectorModal", [
    "API_URL", 
    "window", 
    "jQuery", 
    "$http", 
    "$uibModal", 
    "$sce", 
    "$rootScope",
    function (API_URL, window, $, $http, $uibModal, $sce, $scope) {
        return function(options) {
            options = options || {};
            var onSelectCallback = options.onSelect || function() {};
            var multiple = options.multiple !== false;
            var supplierId = options.supplierId || null;
            var categoryId = options.categoryId || null;
            
            var uibModalInstance = $uibModal.open({
                animation: true,
                ariaLabelledBy: "modal-title",
                ariaDescribedBy: "modal-body",
                template: 
                    '<div class="modal-header product-selector-header">' +
                        '<button ng-click="closeModal();" type="button" class="close" aria-label="Close">' +
                            '<span aria-hidden="true">&times;</span>' +
                        '</button>' +
                        '<h3 class="modal-title" id="modal-title">' +
                            '<span class="fa fa-fw fa-cube"></span> Selecionar Produtos' +
                        '</h3>' +
                    '</div>' +
                    '<div class="modal-body product-selector-body" id="modal-body">' +
                        '<!-- Filtros -->' +
                        '<div class="product-selector-filters">' +
                            '<div class="row">' +
                                '<div class="col-md-6">' +
                                    '<div class="form-group">' +
                                        '<div class="input-group">' +
                                            '<span class="input-group-addon"><i class="fa fa-search"></i></span>' +
                                            '<input type="text" class="form-control" id="ps-search" ' +
                                                'ng-model="searchQuery" ng-change="searchProducts()" ' +
                                                'placeholder="Buscar por nome ou código...">' +
                                        '</div>' +
                                    '</div>' +
                                '</div>' +
                                '<div class="col-md-3">' +
                                    '<select class="form-control" id="ps-category" ng-model="filterCategory" ng-change="loadProducts()">' +
                                        '<option value="">Todas Categorias</option>' +
                                        '<option ng-repeat="cat in categories" value="{{ cat.category_id }}">{{ cat.category_name }}</option>' +
                                    '</select>' +
                                '</div>' +
                                '<div class="col-md-3">' +
                                    '<select class="form-control" id="ps-supplier" ng-model="filterSupplier" ng-change="loadProducts()">' +
                                        '<option value="">Todos Fornecedores</option>' +
                                        '<option ng-repeat="sup in suppliers" value="{{ sup.sup_id }}">{{ sup.sup_name }}</option>' +
                                    '</select>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<!-- Produtos Selecionados -->' +
                        '<div class="product-selector-selected" ng-show="selectedProducts.length > 0">' +
                            '<div class="selected-header">' +
                                '<span><i class="fa fa-check-circle"></i> {{ selectedProducts.length }} produto(s) selecionado(s)</span>' +
                                '<button type="button" class="btn btn-xs btn-default" ng-click="clearSelection()">' +
                                    '<i class="fa fa-times"></i> Limpar' +
                                '</button>' +
                            '</div>' +
                            '<div class="selected-tags">' +
                                '<span class="label label-info" ng-repeat="p in selectedProducts">' +
                                    '{{ p.p_name }} ' +
                                    '<i class="fa fa-times" ng-click="toggleProduct(p)"></i>' +
                                '</span>' +
                            '</div>' +
                        '</div>' +
                        '<!-- Loading -->' +
                        '<div class="text-center" ng-show="loading">' +
                            '<i class="fa fa-spinner fa-spin fa-2x"></i>' +
                            '<p>Carregando produtos...</p>' +
                        '</div>' +
                        '<!-- Lista de Produtos -->' +
                        '<div class="product-selector-grid" ng-hide="loading">' +
                            '<div class="product-selector-item" ng-repeat="product in filteredProducts" ' +
                                'ng-click="toggleProduct(product)" ' +
                                'ng-class="{selected: isSelected(product)}">' +
                                '<div class="ps-item-image">' +
                                    '<img ng-src="{{ product.p_image_url || defaultImage }}" alt="{{ product.p_name }}">' +
                                '</div>' +
                                '<div class="ps-item-info">' +
                                    '<div class="ps-item-name" title="{{ product.p_name }}">{{ product.p_name }}</div>' +
                                    '<div class="ps-item-code">{{ product.p_code }}</div>' +
                                    '<div class="ps-item-stock">' +
                                        '<span class="badge" ng-class="{\'badge-success\': product.quantity > 0, \'badge-danger\': product.quantity <= 0}">' +
                                            'Estoque: {{ product.quantity || 0 }}' +
                                        '</span>' +
                                    '</div>' +
                                    '<div class="ps-item-price">{{ product.sell_price | currency:currencySymbol }}</div>' +
                                '</div>' +
                                '<div class="ps-item-check" ng-show="isSelected(product)">' +
                                    '<i class="fa fa-check-circle"></i>' +
                                '</div>' +
                            '</div>' +
                            '<div class="text-center text-muted" ng-show="filteredProducts.length === 0 && !loading">' +
                                '<i class="fa fa-inbox fa-3x"></i>' +
                                '<p>Nenhum produto encontrado</p>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="modal-footer product-selector-footer">' +
                        '<button type="button" class="btn btn-default" ng-click="closeModal()">' +
                            '<i class="fa fa-times"></i> Cancelar' +
                        '</button>' +
                        '<button type="button" class="btn btn-success" ng-click="confirmSelection()" ng-disabled="selectedProducts.length === 0">' +
                            '<i class="fa fa-check"></i> Confirmar Seleção' +
                        '</button>' +
                    '</div>',
                controller: function ($scope, $uibModalInstance) {
                    // Inicialização
                    $scope.loading = true;
                    $scope.products = [];
                    $scope.filteredProducts = [];
                    $scope.selectedProducts = [];
                    $scope.categories = [];
                    $scope.suppliers = [];
                    $scope.searchQuery = '';
                    $scope.filterCategory = categoryId || '';
                    $scope.filterSupplier = supplierId || '';
                    $scope.defaultImage = window.baseUrl + '/assets/itsolution24/img/noimage.jpg';
                    $scope.currencySymbol = window.settings ? window.settings.currency_prefix : 'R$ ';
                    $scope.multiple = multiple;

                    // Carrega categorias e fornecedores
                    $scope.loadFilters = function() {
                        // Categorias
                        $http.get(window.baseUrl + '/_inc/category.php?get_categories=1')
                            .then(function(response) {
                                if (response.data && Array.isArray(response.data)) {
                                    $scope.categories = response.data;
                                }
                            });

                        // Fornecedores
                        $http.get(window.baseUrl + '/_inc/supplier.php?get_suppliers=1')
                            .then(function(response) {
                                if (response.data && Array.isArray(response.data.data)) {
                                    $scope.suppliers = response.data.data;
                                }
                            });
                    };

                    // Carrega produtos
                    $scope.loadProducts = function() {
                        $scope.loading = true;
                        
                        var params = {
                            get_products_for_selector: 1
                        };
                        
                        if ($scope.filterCategory) {
                            params.category_id = $scope.filterCategory;
                        }
                        if ($scope.filterSupplier) {
                            params.sup_id = $scope.filterSupplier;
                        }

                        $http({
                            method: 'GET',
                            url: window.baseUrl + '/_inc/product.php',
                            params: params
                        }).then(function(response) {
                            $scope.loading = false;
                            if (response.data && Array.isArray(response.data.data)) {
                                $scope.products = response.data.data.map(function(p) {
                                    return {
                                        p_id: p.p_id,
                                        p_code: p.p_code,
                                        p_name: p.p_name,
                                        p_image_url: p.p_image ? window.baseUrl + '/storage/products/' + p.p_image : null,
                                        quantity: parseFloat(p.quantity) || 0,
                                        sell_price: parseFloat(p.sell_price) || 0,
                                        purchase_price: parseFloat(p.purchase_price) || 0,
                                        category_id: p.category_id,
                                        sup_id: p.sup_id
                                    };
                                });
                                $scope.filterProducts();
                            }
                        }, function(error) {
                            $scope.loading = false;
                            console.error('Erro ao carregar produtos:', error);
                        });
                    };

                    // Filtra produtos pela busca
                    $scope.searchProducts = function() {
                        $scope.filterProducts();
                    };

                    $scope.filterProducts = function() {
                        var query = ($scope.searchQuery || '').toLowerCase();
                        
                        $scope.filteredProducts = $scope.products.filter(function(p) {
                            if (!query) return true;
                            return (p.p_name && p.p_name.toLowerCase().indexOf(query) !== -1) ||
                                   (p.p_code && p.p_code.toLowerCase().indexOf(query) !== -1);
                        });
                    };

                    // Toggle seleção de produto
                    $scope.toggleProduct = function(product) {
                        var index = $scope.findSelectedIndex(product);
                        
                        if (index > -1) {
                            $scope.selectedProducts.splice(index, 1);
                        } else {
                            if (!$scope.multiple) {
                                $scope.selectedProducts = [];
                            }
                            $scope.selectedProducts.push(product);
                        }
                    };

                    $scope.findSelectedIndex = function(product) {
                        for (var i = 0; i < $scope.selectedProducts.length; i++) {
                            if ($scope.selectedProducts[i].p_id === product.p_id) {
                                return i;
                            }
                        }
                        return -1;
                    };

                    $scope.isSelected = function(product) {
                        return $scope.findSelectedIndex(product) > -1;
                    };

                    $scope.clearSelection = function() {
                        $scope.selectedProducts = [];
                    };

                    // Confirma seleção
                    $scope.confirmSelection = function() {
                        onSelectCallback($scope.selectedProducts);
                        $uibModalInstance.close($scope.selectedProducts);
                    };

                    // Fecha modal
                    $scope.closeModal = function() {
                        $uibModalInstance.dismiss('cancel');
                    };

                    // Inicializa
                    $scope.loadFilters();
                    $scope.loadProducts();
                },
                scope: $scope,
                size: "lg",
                backdrop: "static",
                keyboard: true,
                windowClass: "product-selector-modal"
            });

            uibModalInstance.result.catch(function() {
                uibModalInstance.close();
            });

            return uibModalInstance;
        };
    }
]);
