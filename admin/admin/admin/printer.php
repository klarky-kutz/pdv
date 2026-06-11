<?php 
ob_start();
session_start();
include ("../_init.php");

if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

if (user_group_id() != 1 && !has_permission('access', 'read_printer')) {
  redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

$document->setTitle(trans('title_printer'));
$document->addScript('../assets/itsolution24/angular/controllers/PrinterController.js');

include("header.php"); 
include ("left_sidebar.php");
?>

<style>
.printer-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 30px;
    border-radius: 10px;
    margin-bottom: 25px;
}
.printer-hero h2 { margin: 0 0 10px 0; font-size: 24px; }
.printer-hero p { margin: 0; opacity: 0.9; }

.connection-cards { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
.connection-card {
    flex: 1;
    min-width: 200px;
    background: #fff;
    border-radius: 12px;
    padding: 25px 20px;
    text-align: center;
    cursor: pointer;
    border: 3px solid #e0e0e0;
    transition: all 0.3s ease;
}
.connection-card:hover { border-color: #667eea; transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
.connection-card.selected { border-color: #667eea; background: #f8f9ff; }
.connection-card .card-icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 15px;
    background: #f5f5f5;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    color: #667eea;
}
.connection-card.selected .card-icon { background: #667eea; color: #fff; }
.connection-card h4 { margin: 0 0 8px 0; font-size: 16px; color: #333; }
.connection-card p { margin: 0; font-size: 12px; color: #888; }
.connection-card .difficulty { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 10px; margin-top: 10px; }
.difficulty-easy { background: #d4edda; color: #155724; }
.difficulty-medium { background: #fff3cd; color: #856404; }

.setup-wizard { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
.wizard-step { display: none; }
.wizard-step.active { display: block; }
.step-header { display: flex; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
.step-number { width: 40px; height: 40px; background: #667eea; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px; }
.step-header h3 { margin: 0; color: #333; }
.step-header p { margin: 5px 0 0 0; color: #888; font-size: 13px; }

.help-box {
    background: #e8f4fd;
    border-left: 4px solid #2196f3;
    padding: 15px 20px;
    border-radius: 0 8px 8px 0;
    margin: 20px 0;
}
.help-box h5 { margin: 0 0 8px 0; color: #1976d2; }
.help-box p { margin: 0; font-size: 13px; color: #555; }
.help-box ul { margin: 10px 0 0 0; padding-left: 20px; font-size: 13px; color: #555; }

.example-box {
    background: #f8f9fa;
    border: 1px dashed #ccc;
    padding: 12px 15px;
    border-radius: 6px;
    font-family: monospace;
    font-size: 13px;
    margin: 10px 0;
    color: #333;
}

.visual-guide {
    background: #fffde7;
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    text-align: center;
}
.visual-guide img { max-width: 100%; margin: 10px 0; }
.visual-guide .guide-icon { font-size: 50px; color: #ffc107; margin-bottom: 10px; }

.form-section { margin-bottom: 25px; }
.form-section label { font-weight: 600; color: #333; margin-bottom: 8px; display: block; }
.form-section .form-control { border-radius: 8px; padding: 12px 15px; border: 2px solid #e0e0e0; }
.form-section .form-control:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
.form-section .help-text { font-size: 12px; color: #888; margin-top: 5px; }

.btn-wizard { padding: 12px 30px; border-radius: 8px; font-weight: 600; }
.btn-prev { background: #f5f5f5; color: #333; border: none; }
.btn-next { background: #667eea; color: #fff; border: none; }
.btn-next:hover { background: #5a6fd6; color: #fff; }

.printer-list-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.printer-list-card .printer-info { display: flex; align-items: center; }
.printer-list-card .printer-icon { width: 50px; height: 50px; background: #f5f5f5; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #667eea; margin-right: 15px; }
.printer-list-card .printer-details h4 { margin: 0 0 5px 0; font-size: 16px; }
.printer-list-card .printer-details p { margin: 0; font-size: 12px; color: #888; }
.printer-list-card .printer-status { padding: 5px 12px; border-radius: 15px; font-size: 11px; font-weight: 600; }
.status-active { background: #d4edda; color: #155724; }
.status-inactive { background: #f8d7da; color: #721c24; }

.no-printer-msg {
    text-align: center;
    padding: 50px 20px;
    color: #888;
}
.no-printer-msg .icon { font-size: 60px; color: #ddd; margin-bottom: 15px; }
</style>

<div class="content-wrapper" ng-controller="PrinterController">

  <section class="content-header">
    <h1><i class="fa fa-print"></i> Configuração de Impressora</h1>
    <ol class="breadcrumb">
      <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Painel</a></li>
      <li class="active">Impressoras</li>
    </ol>
  </section>
  
  <section class="content">

    <!-- Hero Section -->
    <div class="printer-hero">
      <h2><i class="fa fa-print"></i> Configure sua Impressora de Cupom</h2>
      <p>Siga o assistente abaixo para conectar sua impressora térmica de forma simples e rápida.</p>
    </div>

    <?php if (user_group_id() == 1 || has_permission('access', 'create_printer')) : ?>
    
    <!-- Step 1: Choose Connection Type -->
    <div class="setup-wizard" id="setup-wizard">
      
      <div class="wizard-step active" id="step-1">
        <div class="step-header">
          <div class="step-number">1</div>
          <div>
            <h3>Como sua impressora está conectada?</h3>
            <p>Selecione o tipo de conexão da sua impressora</p>
          </div>
        </div>

        <div class="connection-cards">
          <div class="connection-card" data-type="windows" onclick="selectConnectionType('windows')">
            <div class="card-icon"><i class="fa fa-windows"></i></div>
            <h4>Cabo USB</h4>
            <p>Impressora conectada diretamente no computador via cabo USB</p>
            <span class="difficulty difficulty-easy">Fácil</span>
          </div>
          
          <div class="connection-card" data-type="network" onclick="selectConnectionType('network')">
            <div class="card-icon"><i class="fa fa-wifi"></i></div>
            <h4>Rede Wi-Fi / Cabo de Rede</h4>
            <p>Impressora conectada na rede local via Wi-Fi ou cabo de rede (Ethernet)</p>
            <span class="difficulty difficulty-medium">Médio</span>
          </div>
        </div>

        <div class="help-box">
          <h5><i class="fa fa-lightbulb-o"></i> Não sabe qual escolher?</h5>
          <p>Olhe atrás da sua impressora:</p>
          <ul>
            <li><strong>Se tem apenas um cabo USB</strong> conectado ao computador → Escolha <strong>"Cabo USB"</strong></li>
            <li><strong>Se tem um cabo de rede (parecido com telefone, mas maior)</strong> ou antena Wi-Fi → Escolha <strong>"Rede"</strong></li>
          </ul>
        </div>
      </div>

      <!-- Step 2: Windows/USB Configuration -->
      <div class="wizard-step" id="step-windows">
        <div class="step-header">
          <div class="step-number">2</div>
          <div>
            <h3><i class="fa fa-windows text-blue"></i> Configuração USB (Windows)</h3>
            <p>Vamos encontrar sua impressora no Windows</p>
          </div>
        </div>

        <div class="visual-guide">
          <div class="guide-icon"><i class="fa fa-search"></i></div>
          <h4>Como encontrar o nome da sua impressora:</h4>
          <p><strong>1.</strong> Aperte as teclas <kbd>Win</kbd> + <kbd>R</kbd> no teclado<br>
             <strong>2.</strong> Digite: <code>control printers</code> e aperte Enter<br>
             <strong>3.</strong> Encontre sua impressora na lista e copie o nome exato</p>
        </div>

        <form id="form-usb" class="mt-20">
          <input type="hidden" name="action_type" value="CREATE">
          <input type="hidden" name="type" value="windows">
          
          <div class="form-section">
            <label><i class="fa fa-tag"></i> Dê um nome para identificar esta impressora</label>
            <input type="text" class="form-control" name="title" placeholder="Ex: Impressora do Caixa" required>
            <p class="help-text">Este nome é só para você identificar a impressora no sistema</p>
          </div>

          <div class="form-section">
            <label><i class="fa fa-print"></i> Nome da impressora no Windows</label>
            <input type="text" class="form-control" name="path" placeholder="Ex: POS-80" required>
            <p class="help-text">Digite exatamente como aparece em "Dispositivos e Impressoras"</p>
            <div class="example-box">
              <strong>Exemplos comuns:</strong> POS-80, EPSON TM-T20, Generic / Text Only
            </div>
          </div>

          <div class="form-section">
            <label><i class="fa fa-text-width"></i> Largura do papel (caracteres por linha)</label>
            <select class="form-control" name="char_per_line">
              <option value="32">58mm (32 caracteres) - Cupom pequeno</option>
              <option value="48" selected>80mm (48 caracteres) - Cupom padrão</option>
            </select>
          </div>

          <input type="hidden" name="ip_address" value="">
          <input type="hidden" name="port" value="">
          <input type="hidden" name="printer_store[]" value="<?php echo store_id(); ?>">
          <input type="hidden" name="status" value="1">
          <input type="hidden" name="sort_order" value="0">

          <div class="help-box">
            <h5><i class="fa fa-exclamation-triangle"></i> Importante!</h5>
            <p>A impressora deve estar instalada e funcionando no Windows. Faça um teste de impressão pelo Windows antes de configurar aqui.</p>
          </div>

          <div class="text-right mt-20">
            <button type="button" class="btn btn-wizard btn-prev" onclick="goToStep('step-1')"><i class="fa fa-arrow-left"></i> Voltar</button>
            <button type="submit" class="btn btn-wizard btn-next" id="btn-save-usb"><i class="fa fa-check"></i> Salvar Impressora</button>
          </div>
        </form>
      </div>

      <!-- Step 2: Network Configuration -->
      <div class="wizard-step" id="step-network">
        <div class="step-header">
          <div class="step-number">2</div>
          <div>
            <h3><i class="fa fa-wifi text-green"></i> Configuração de Rede</h3>
            <p>Configure sua impressora conectada via rede</p>
          </div>
        </div>

        <div class="visual-guide">
          <div class="guide-icon"><i class="fa fa-info-circle"></i></div>
          <h4>Como descobrir o IP da impressora:</h4>
          <p><strong>Opção 1:</strong> Imprima uma página de teste na própria impressora (geralmente segurando um botão por 3 segundos)<br>
             <strong>Opção 2:</strong> Acesse as configurações do seu roteador e veja os dispositivos conectados<br>
             <strong>Opção 3:</strong> Veja no manual da impressora</p>
        </div>

        <form id="form-network" class="mt-20">
          <input type="hidden" name="action_type" value="CREATE">
          <input type="hidden" name="type" value="network">
          
          <div class="form-section">
            <label><i class="fa fa-tag"></i> Dê um nome para identificar esta impressora</label>
            <input type="text" class="form-control" name="title" placeholder="Ex: Impressora da Cozinha" required>
          </div>

          <div class="row">
            <div class="col-md-8">
              <div class="form-section">
                <label><i class="fa fa-globe"></i> Endereço IP da impressora</label>
                <input type="text" class="form-control" name="ip_address" placeholder="Ex: 192.168.1.100" required>
                <p class="help-text">Geralmente começa com 192.168...</p>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-section">
                <label><i class="fa fa-plug"></i> Porta</label>
                <input type="text" class="form-control" name="port" value="9100" required>
                <p class="help-text">Padrão: 9100</p>
              </div>
            </div>
          </div>

          <div class="form-section">
            <label><i class="fa fa-text-width"></i> Largura do papel</label>
            <select class="form-control" name="char_per_line">
              <option value="32">58mm (32 caracteres)</option>
              <option value="48" selected>80mm (48 caracteres)</option>
            </select>
          </div>

          <input type="hidden" name="path" value="">
          <input type="hidden" name="printer_store[]" value="<?php echo store_id(); ?>">
          <input type="hidden" name="status" value="1">
          <input type="hidden" name="sort_order" value="0">

          <div class="help-box">
            <h5><i class="fa fa-check-circle"></i> Dica para testar a conexão</h5>
            <p>Abra o Prompt de Comando (CMD) e digite: <code>ping 192.168.1.100</code> (substitua pelo IP da sua impressora). Se aparecer "Resposta de...", a impressora está conectada!</p>
          </div>

          <div class="text-right mt-20">
            <button type="button" class="btn btn-wizard btn-prev" onclick="goToStep('step-1')"><i class="fa fa-arrow-left"></i> Voltar</button>
            <button type="submit" class="btn btn-wizard btn-next" id="btn-save-network"><i class="fa fa-check"></i> Salvar Impressora</button>
          </div>
        </form>
      </div>

    </div>
    <?php endif; ?>

    <!-- Printer List -->
    <div class="box box-success mt-25">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-list"></i> Impressoras Cadastradas</h3>
      </div>
      <div class="box-body">
        <?php
          $hide_colums = "";
          if (user_group_id() != 1) {
            if (!has_permission('access', 'update_printer')) { $hide_colums .= "7,"; }
            if (!has_permission('access', 'delete_printer')) { $hide_colums .= "8,"; }
          }
        ?> 
        <div class="table-responsive">  
          <table id="printer-printer-list" class="table table-bordered table-striped table-hover" data-hide-colums="<?php echo $hide_colums; ?>" style="display:none;">
            <thead>
              <tr class="bg-gray">
                <th class="w-5">#</th>
                <th class="w-20">Nome</th>
                <th class="w-10">Tipo</th>
                <th class="w-25">Caminho/Nome</th>
                <th class="w-15">IP</th>
                <th class="w-10">Porta</th>
                <th class="w-10">Status</th>
                <th class="w-5">Editar</th>
                <th class="w-5">Excluir</th>
              </tr>
            </thead>
            <tfoot>
              <tr class="bg-gray">
                <th>#</th>
                <th>Nome</th>
                <th>Tipo</th>
                <th>Caminho/Nome</th>
                <th>IP</th>
                <th>Porta</th>
                <th>Status</th>
                <th>Editar</th>
                <th>Excluir</th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- FAQ Section -->
    <div class="box box-default">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-question-circle"></i> Perguntas Frequentes</h3>
      </div>
      <div class="box-body">
        <div class="panel-group" id="faq-accordion">
          <div class="panel panel-default">
            <div class="panel-heading">
              <h4 class="panel-title">
                <a data-toggle="collapse" data-parent="#faq-accordion" href="#faq1">
                  <i class="fa fa-chevron-right"></i> Minha impressora não imprime, o que fazer?
                </a>
              </h4>
            </div>
            <div id="faq1" class="panel-collapse collapse">
              <div class="panel-body">
                <ol>
                  <li>Verifique se a impressora está ligada e com papel</li>
                  <li>Faça um teste de impressão pelo Windows (clique direito na impressora > Propriedades > Imprimir página de teste)</li>
                  <li>Verifique se o nome da impressora está escrito exatamente igual</li>
                  <li>Reinicie a impressora e tente novamente</li>
                </ol>
              </div>
            </div>
          </div>
          <div class="panel panel-default">
            <div class="panel-heading">
              <h4 class="panel-title">
                <a data-toggle="collapse" data-parent="#faq-accordion" href="#faq2">
                  <i class="fa fa-chevron-right"></i> Como descobrir o IP da minha impressora de rede?
                </a>
              </h4>
            </div>
            <div id="faq2" class="panel-collapse collapse">
              <div class="panel-body">
                <p>A maioria das impressoras térmicas imprime uma página de configuração ao segurar o botão FEED por 3-5 segundos com a impressora desligada, depois ligar. O IP aparecerá nessa folha.</p>
              </div>
            </div>
          </div>
          <div class="panel panel-default">
            <div class="panel-heading">
              <h4 class="panel-title">
                <a data-toggle="collapse" data-parent="#faq-accordion" href="#faq3">
                  <i class="fa fa-chevron-right"></i> Qual a diferença entre 58mm e 80mm?
                </a>
              </h4>
            </div>
            <div id="faq3" class="panel-collapse collapse">
              <div class="panel-body">
                <p><strong>58mm:</strong> Bobina menor, cupom mais estreito. Comum em maquininhas portáteis.<br>
                   <strong>80mm:</strong> Bobina padrão, cupom mais largo. Mais comum em caixas de loja.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </section>
</div>

<script>
function selectConnectionType(type) {
    $('.connection-card').removeClass('selected');
    $('.connection-card[data-type="'+type+'"]').addClass('selected');
    
    setTimeout(function() {
        if (type === 'windows') {
            goToStep('step-windows');
        } else if (type === 'network') {
            goToStep('step-network');
        }
    }, 300);
}

function goToStep(stepId) {
    $('.wizard-step').removeClass('active');
    $('#' + stepId).addClass('active');
    $('html, body').animate({ scrollTop: $('#setup-wizard').offset().top - 20 }, 300);
}

$(document).ready(function() {
    // Form USB submit
    $('#form-usb').on('submit', function(e) {
        e.preventDefault();
        savePrinter($(this));
    });
    
    // Form Network submit
    $('#form-network').on('submit', function(e) {
        e.preventDefault();
        savePrinter($(this));
    });
    
    function savePrinter(form) {
        var btn = form.find('button[type="submit"]');
        btn.html('<i class="fa fa-spinner fa-spin"></i> Salvando...').prop('disabled', true);
        
        $.ajax({
            url: 'printer.php',
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    toastr.success('Impressora cadastrada com sucesso!');
                    form[0].reset();
                    goToStep('step-1');
                    $('#printer-printer-list').DataTable().ajax.reload();
                } else {
                    toastr.error(response.message || 'Erro ao salvar');
                }
            },
            error: function() {
                // Fallback - submit normal
                form.off('submit').submit();
            },
            complete: function() {
                btn.html('<i class="fa fa-check"></i> Salvar Impressora').prop('disabled', false);
            }
        });
    }
    
    // Show datatable after load
    setTimeout(function() {
        $('#printer-printer-list').show();
    }, 500);
});
</script>

<?php include ("footer.php"); ?>
