// Auto-registrar CalculatorModal quando Angular estiver pronto
(function() {
    'use strict';
    
    // Função que cria o modal
    function createCalculatorModal(API_URL, $http, $uibModal, $sce, $scope) {
        return function() {
            var uibModalInstance = $uibModal.open({
                animation: true,
                ariaLabelledBy: "modal-title",
                ariaDescribedBy: "modal-body",
                template: "<div class=\"modal-header bg-primary\">" +
                            "<button ng-click=\"cancel();\" type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-label=\"Close\"><span aria-hidden=\"true\">&times;</span></button>" +
                            "<h3 class=\"modal-title\" id=\"modal-title\"><span class=\"fa fa-fw fa-calculator\"></span> Calculadora</h3>" +
                          "</div>" +
                          "<div class=\"modal-body\" id=\"modal-body\">" +
                            "<div bind-html-compile=\"rawHtml\">Carregando...</div>" +
                          "</div>",
                controller: function ($scope, $uibModalInstance) {
                    $http({
                      url: API_URL + "_inc/template/partials/calculator_modal.php",
                      method: "GET"
                    })
                    .then(function(response, status, headers, config) {
                        $scope.rawHtml = $sce.trustAsHtml(response.data);
                        setTimeout(function() {
                            initCalculator();
                        }, 100);
                    }, function(data) {
                       window.swal("Oops!", "Erro ao carregar calculadora!", "error");
                    });
                    $scope.cancel = function () {
                        $uibModalInstance.dismiss("cancel");
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
    }
    
    // Registrar a factory
    if (window.angularApp) {
        window.angularApp.factory("CalculatorModal", ["API_URL", "$http", "$uibModal", "$sce", "$rootScope", createCalculatorModal]);
    }
    
    // Auto-registrar no $rootScope quando o DOM estiver pronto
    angular.element(document).ready(function() {
        setTimeout(function() {
            var $injector = angular.element(document.body).injector();
            if ($injector) {
                var $rootScope = $injector.get('$rootScope');
                if ($rootScope && !$rootScope.CalculatorModal) {
                    try {
                        var CalculatorModal = $injector.get('CalculatorModal');
                        $rootScope.CalculatorModal = CalculatorModal;
                    } catch(e) {
                        // Se a factory não existe, criar manualmente
                        var API_URL = $injector.get('API_URL');
                        var $http = $injector.get('$http');
                        var $uibModal = $injector.get('$uibModal');
                        var $sce = $injector.get('$sce');
                        $rootScope.CalculatorModal = createCalculatorModal(API_URL, $http, $uibModal, $sce, $rootScope);
                    }
                }
            }
        }, 500);
    });
})();

// Função global para inicializar a calculadora
function initCalculator() {
    var display = document.getElementById('calc-display');
    var historyList = document.getElementById('calc-history');
    var trocoTotal = document.getElementById('troco-total');
    var trocoPago = document.getElementById('troco-pago');
    var trocoResult = document.getElementById('troco-result');
    
    if (!display) return;
    
    var currentValue = '0';
    var previousValue = '';
    var operation = null;
    var shouldResetDisplay = false;
    var history = [];
    
    function updateDisplay() {
        display.value = currentValue;
    }
    
    function addToHistory(expression, result) {
        history.unshift({ expression: expression, result: result });
        if (history.length > 5) history.pop();
        renderHistory();
    }
    
    function renderHistory() {
        if (!historyList) return;
        historyList.innerHTML = '';
        history.forEach(function(item) {
            var li = document.createElement('li');
            li.className = 'list-group-item small';
            li.innerHTML = '<span class="text-muted">' + item.expression + '</span> = <strong>' + item.result + '</strong>';
            li.onclick = function() {
                currentValue = item.result;
                updateDisplay();
            };
            historyList.appendChild(li);
        });
    }
    
    function calculate() {
        if (!previousValue || !operation) return;
        
        var prev = parseFloat(previousValue);
        var current = parseFloat(currentValue);
        var result;
        var expression = previousValue + ' ' + operation + ' ' + currentValue;
        
        switch(operation) {
            case '+': result = prev + current; break;
            case '-': result = prev - current; break;
            case '×': result = prev * current; break;
            case '÷': result = current !== 0 ? prev / current : 'Erro'; break;
            case '%': result = prev * (current / 100); break;
        }
        
        if (result !== 'Erro') {
            result = Math.round(result * 100) / 100;
            addToHistory(expression, result.toString());
        }
        
        currentValue = result.toString();
        previousValue = '';
        operation = null;
        shouldResetDisplay = true;
        updateDisplay();
    }
    
    function calculateTroco() {
        if (!trocoTotal || !trocoPago || !trocoResult) return;
        var total = parseFloat(trocoTotal.value) || 0;
        var pago = parseFloat(trocoPago.value) || 0;
        var troco = pago - total;
        trocoResult.value = troco >= 0 ? troco.toFixed(2) : 'Valor insuficiente';
        if (troco >= 0) {
            trocoResult.className = 'form-control text-success font-bold';
        } else {
            trocoResult.className = 'form-control text-danger';
        }
    }
    
    // Event delegation para botões
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.calculator-modal')) return;
        
        var btn = e.target.closest('[data-calc]');
        if (!btn) return;
        
        var action = btn.getAttribute('data-calc');
        
        if (!isNaN(action) || action === '.') {
            if (shouldResetDisplay) {
                currentValue = '0';
                shouldResetDisplay = false;
            }
            if (action === '.' && currentValue.includes('.')) return;
            currentValue = currentValue === '0' && action !== '.' ? action : currentValue + action;
            updateDisplay();
        }
        else if (['+', '-', '×', '÷', '%'].includes(action)) {
            if (previousValue && operation) calculate();
            previousValue = currentValue;
            operation = action;
            shouldResetDisplay = true;
        }
        else if (action === '=') {
            calculate();
        }
        else if (action === 'C') {
            currentValue = '0';
            previousValue = '';
            operation = null;
            updateDisplay();
        }
        else if (action === 'CE') {
            currentValue = '0';
            updateDisplay();
        }
        else if (action === '±') {
            currentValue = (parseFloat(currentValue) * -1).toString();
            updateDisplay();
        }
        else if (action === '√') {
            var val = parseFloat(currentValue);
            if (val >= 0) {
                var result = Math.sqrt(val);
                addToHistory('√' + currentValue, result.toFixed(4));
                currentValue = result.toFixed(4);
                updateDisplay();
            }
        }
    });
    
    // Atalhos de teclado
    document.addEventListener('keydown', function(e) {
        if (!document.querySelector('.calculator-modal')) return;
        
        var key = e.key;
        
        if (!isNaN(key) || key === '.') {
            e.preventDefault();
            var btn = document.querySelector('[data-calc="' + key + '"]');
            if (btn) btn.click();
        }
        else if (key === '+' || key === '-') {
            e.preventDefault();
            var btn = document.querySelector('[data-calc="' + key + '"]');
            if (btn) btn.click();
        }
        else if (key === '*') {
            e.preventDefault();
            var btn = document.querySelector('[data-calc="×"]');
            if (btn) btn.click();
        }
        else if (key === '/') {
            e.preventDefault();
            var btn = document.querySelector('[data-calc="÷"]');
            if (btn) btn.click();
        }
        else if (key === 'Enter' || key === '=') {
            e.preventDefault();
            var btn = document.querySelector('[data-calc="="]');
            if (btn) btn.click();
        }
        else if (key === 'Escape' || key === 'c' || key === 'C') {
            e.preventDefault();
            var btn = document.querySelector('[data-calc="C"]');
            if (btn) btn.click();
        }
        else if (key === '%') {
            e.preventDefault();
            var btn = document.querySelector('[data-calc="%"]');
            if (btn) btn.click();
        }
    });
    
    // Cálculo de troco
    if (trocoTotal) trocoTotal.addEventListener('input', calculateTroco);
    if (trocoPago) trocoPago.addEventListener('input', calculateTroco);
    
    // Botão para usar resultado no troco
    var useResultBtn = document.getElementById('use-result-troco');
    if (useResultBtn) {
        useResultBtn.addEventListener('click', function() {
            if (trocoTotal) {
                trocoTotal.value = currentValue;
                calculateTroco();
            }
        });
    }
}
