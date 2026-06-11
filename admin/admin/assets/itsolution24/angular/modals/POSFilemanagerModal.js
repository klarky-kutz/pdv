/**
 * POSFilemanagerModal - Modal moderna para seleção de imagens/arquivos
 * Usa o design moderno do FileManager
 */
window.angularApp.factory("POSFilemanagerModal", ["API_URL", "window", "jQuery", "$http", "$uibModal", "$sce", "$rootScope", function (API_URL, window, $, $http, $uibModal, $sce, $scope) {
    return function (data) {
        var uibModalInstance = $uibModal.open({
            animation: true,
            ariaLabelledBy: "modal-title",
            ariaDescribedBy: "modal-body",
            template: 
                '<div class="modal-header pos-filemanager-header">' +
                    '<button ng-click="closeFileManager()" type="button" id="close-filemanger" class="close" aria-label="Close">' +
                        '<span aria-hidden="true">&times;</span>' +
                    '</button>' +
                    '<h3 class="modal-title" id="modal-title">' +
                        '<i class="fa fa-image"></i> Selecionar Imagem' +
                    '</h3>' +
                '</div>' +
                '<div class="modal-body pos-filemanager-body" id="filemanager">' +
                    '<div bind-html-compile="rawHtml">' +
                        '<div class="pos-fm-loading">' +
                            '<i class="fa fa-spinner fa-spin fa-3x"></i>' +
                            '<p>Carregando biblioteca de mídia...</p>' +
                        '</div>' +
                    '</div>' +
                '</div>',
            controller: function ($scope, $uibModalInstance) {
                $scope.modal_title = 'Selecionar Imagem';
                
                $http({
                  url: window.baseUrl+window.adminDir+"/filemanager.php?ajax=1&target=" + data.target + "&thumb=" + data.thumb,
                  dataType: "html",
                  method: "GET"
                })
                .then(function (response, status, headers, config) {
                    $scope.rawHtml = $sce.trustAsHtml(response.data);
                    $(".modal:first").addClass("filemanager-open pos-filemanager-modal");
                    
                    // Inicializa FileManager depois de carregar
                    setTimeout(function() {
                        if (typeof window.FileManagerModern !== 'undefined') {
                            window.FileManagerModern.refresh();
                        }
                    }, 100);
                }, function (response) {
                    window.swal("Oops!", response.data.errorMsg, "error").then(function() {
                        $scope.closeFileManager();
                    });
                });

                $scope.closeFileManager = function () {
                    $uibModalInstance.dismiss("cancel");
                    setTimeout(function() {
                        if ($(document).find('.modal').length) {
                            $("body").addClass("modal-open");
                        }
                    }, 500);
                };
            },
            scope: $scope,
            size: "lg",
            backdrop: "static",
            keyboard: false,
            windowClass: "pos-filemanager-modal-wrapper"
        });
        
        uibModalInstance.result.catch(function () { 
            setTimeout(function() {
                $("body").removeClass("modal-open");
                $("body").removeClass("filemanager-open");
            }, 500);
            uibModalInstance.close(); 
        });
    };
}]);
