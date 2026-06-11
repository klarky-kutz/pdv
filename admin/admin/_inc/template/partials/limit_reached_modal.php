<?php
/**
 * Modal de Limite SaaS Atingido
 * Incluir este arquivo nas páginas que precisam de validação de limite
 */
?>
<!-- Modal SaaS - Limite Atingido -->
<div class="modal fade" id="saasLimitModal" tabindex="-1" role="dialog" aria-labelledby="saasLimitModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <!-- Header com gradiente vermelho -->
            <div class="modal-header" style="background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%); color: #fff; border-radius: 0;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar" style="color: #fff; opacity: 0.8; text-shadow: none;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="saasLimitModalLabel">
                    <i class="fa fa-exclamation-triangle"></i> 
                    Limite do Plano Atingido
                </h4>
            </div>
            
            <!-- Body -->
            <div class="modal-body text-center" style="padding: 30px;">
                <!-- Ícone grande -->
                <div id="saasLimitIconContainer" style="margin-bottom: 20px;">
                    <i id="saasLimitIcon" class="fa fa-ban" style="font-size: 72px; color: #e74c3c;"></i>
                </div>
                
                <!-- Título -->
                <h4 id="saasLimitTitle" style="margin-bottom: 15px; color: #333; font-weight: 600;">
                    Limite Atingido
                </h4>
                
                <!-- Mensagem -->
                <p id="saasLimitMessage" style="color: #666; font-size: 15px; line-height: 1.6;">
                    Você atingiu o limite permitido pelo seu plano atual.
                </p>
                
                <!-- Box de uso atual -->
                <div class="well well-sm" style="margin-top: 25px; background: #f9f9f9; border: 1px solid #eee;">
                    <p style="margin: 0; font-size: 14px;">
                        <i class="fa fa-bar-chart" style="color: #3498db;"></i>
                        <strong>Uso atual:</strong> 
                        <span id="saasLimitUsage" class="text-danger" style="font-weight: 600;"></span>
                    </p>
                    <!-- Barra de progresso -->
                    <div class="progress" style="margin: 15px 0 5px 0; height: 10px; border-radius: 5px;">
                        <div id="saasLimitProgressBar" class="progress-bar progress-bar-danger" role="progressbar" style="width: 100%; border-radius: 5px;"></div>
                    </div>
                </div>
                
                <!-- Dica -->
                <p style="margin-top: 20px; color: #888; font-size: 13px;">
                    <i class="fa fa-lightbulb-o"></i>
                    Faça upgrade do seu plano para aumentar os limites.
                </p>
            </div>
            
            <!-- Footer -->
            <div class="modal-footer" style="text-align: center; border-top: 1px solid #eee; padding: 20px;">
                <button type="button" class="btn btn-default btn-lg" data-dismiss="modal" style="min-width: 120px;">
                    <i class="fa fa-times"></i> Fechar
                </button>
                <a href="<?php echo root_url(); ?>conta/planos" class="btn btn-success btn-lg" style="min-width: 160px;">
                    <i class="fa fa-arrow-up"></i> Fazer Upgrade
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos adicionais para a modal */
#saasLimitModal .modal-content {
    border-radius: 4px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.2);
}
#saasLimitModal .modal-header {
    border-bottom: none;
}
#saasLimitModal .modal-header .close:hover {
    opacity: 1;
}
#saasLimitModal .progress {
    background-color: #ecf0f1;
    overflow: hidden;
}
</style>

<script>
/**
 * Exibe a modal de limite SaaS atingido
 * @param {string} type - Tipo: 'products', 'customers', 'users', 'stores'
 * @param {number} current - Uso atual
 * @param {number} limit - Limite do plano
 */
function showSaasLimitModal(type, current, limit) {
    var config = {
        products: {
            icon: 'fa-cubes',
            title: 'Limite de Produtos Atingido',
            message: 'Sua conta atingiu o limite de <strong>' + limit + ' produtos</strong> permitidos pelo seu plano atual. Para cadastrar novos produtos, é necessário fazer upgrade.',
            usageText: current + ' de ' + limit + ' produtos'
        },
        customers: {
            icon: 'fa-users',
            title: 'Limite de Clientes Atingido',
            message: 'Sua conta atingiu o limite de <strong>' + limit + ' clientes</strong> permitidos pelo seu plano atual. Para cadastrar novos clientes, é necessário fazer upgrade.',
            usageText: current + ' de ' + limit + ' clientes'
        },
        users: {
            icon: 'fa-user-plus',
            title: 'Limite de Usuários Atingido',
            message: 'Sua conta atingiu o limite de <strong>' + limit + ' usuários</strong> permitidos pelo seu plano atual.',
            usageText: current + ' de ' + limit + ' usuários'
        },
        stores: {
            icon: 'fa-building',
            title: 'Limite de Lojas Atingido',
            message: 'Sua conta atingiu o limite de <strong>' + limit + ' lojas</strong> permitidas pelo seu plano atual.',
            usageText: current + ' de ' + limit + ' lojas'
        }
    };
    
    var cfg = config[type] || config.products;
    var percentage = limit > 0 ? Math.min(100, (current / limit) * 100) : 100;
    
    // Atualizar conteúdo da modal
    $('#saasLimitIcon').removeClass().addClass('fa ' + cfg.icon);
    $('#saasLimitTitle').html(cfg.title);
    $('#saasLimitMessage').html(cfg.message);
    $('#saasLimitUsage').html(cfg.usageText);
    $('#saasLimitProgressBar').css('width', percentage + '%');
    
    // Exibir modal
    $('#saasLimitModal').modal('show');
}

/**
 * Verifica limite antes de abrir formulário de criação
 * @param {string} type - Tipo: 'products' ou 'customers'
 * @param {object} limitInfo - Objeto com informações de limite
 * @returns {boolean} - true se pode continuar, false se limite atingido
 */
function checkSaasLimitBeforeCreate(type, limitInfo) {
    if (!limitInfo || !limitInfo.is_saas) {
        return true; // Não é SaaS, permite
    }
    
    if (limitInfo.unlimited) {
        return true; // Ilimitado, permite
    }
    
    if (!limitInfo.can_create) {
        showSaasLimitModal(type, limitInfo.current, limitInfo.limit);
        return false; // Limite atingido, bloqueia
    }
    
    return true; // Pode criar
}

/**
 * Atualiza dinamicamente os limites SaaS após exclusão de produto/cliente
 * @param {string} type - Tipo: 'products' ou 'customers'
 * @param {function} callback - Função de callback opcional
 */
function refreshSaasLimit(type, callback) {
    var apiUrl = window.baseUrl + '_inc/api/check_saas_limit.php?type=' + type;
    
    $.ajax({
        url: apiUrl,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Atualizar variável global
                if (type === 'products' && typeof saasProductLimit !== 'undefined') {
                    saasProductLimit = response;
                    // Se agora pode criar, mostrar o formulário
                    if (response.can_create) {
                        showProductCreateForm();
                    }
                } else if (type === 'customers' && typeof saasCustomerLimit !== 'undefined') {
                    saasCustomerLimit = response;
                    // Se agora pode criar, mostrar o formulário
                    if (response.can_create) {
                        showCustomerCreateForm();
                    }
                }
                
                if (typeof callback === 'function') {
                    callback(response);
                }
            }
        },
        error: function() {
            console.log('Erro ao verificar limite SaaS');
        }
    });
}

/**
 * Mostra o formulário de criação de produtos (substitui box de limite)
 */
function showProductCreateForm() {
    var $limitBox = $('.box-danger:has(.fa-cubes)');
    var $createBox = $('.box-info:has(#create-product-form)');
    
    if ($limitBox.length && $createBox.length === 0) {
        // Recarregar a página para mostrar o formulário
        // (mais seguro que manipular DOM complexo)
        location.reload();
    } else if ($limitBox.length) {
        $limitBox.slideUp(300, function() {
            $createBox.slideDown(300);
        });
    }
}

/**
 * Mostra o formulário de criação de clientes (substitui box de limite)
 */
function showCustomerCreateForm() {
    var $limitBox = $('.box-danger:has(.fa-users)');
    var $createBox = $('.box-info:has(#create-customer-form)');
    
    if ($limitBox.length && $createBox.length === 0) {
        // Recarregar a página para mostrar o formulário
        location.reload();
    } else if ($limitBox.length) {
        $limitBox.slideUp(300, function() {
            $createBox.slideDown(300);
        });
    }
}
</script>
