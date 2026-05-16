<?php include ("../../../_init.php");?>
<style>
.calculator-modal .calc-btn {
    width: 100%;
    height: 50px;
    font-size: 18px;
    font-weight: bold;
    margin: 2px 0;
    border-radius: 4px;
}
.calculator-modal .calc-btn:hover {
    transform: scale(1.02);
}
.calculator-modal .calc-display {
    font-size: 28px;
    text-align: right;
    padding: 15px;
    height: 60px;
    font-weight: bold;
    background: #222;
    color: #0f0;
    border: 2px solid #444;
}
.calculator-modal .btn-number {
    background: #f5f5f5;
    border: 1px solid #ddd;
}
.calculator-modal .btn-number:hover {
    background: #e0e0e0;
}
.calculator-modal .btn-operator {
    background: #ff9800;
    color: white;
    border: none;
}
.calculator-modal .btn-operator:hover {
    background: #f57c00;
    color: white;
}
.calculator-modal .btn-equals {
    background: #4caf50;
    color: white;
    border: none;
}
.calculator-modal .btn-equals:hover {
    background: #388e3c;
    color: white;
}
.calculator-modal .btn-clear {
    background: #f44336;
    color: white;
    border: none;
}
.calculator-modal .btn-clear:hover {
    background: #d32f2f;
    color: white;
}
.calculator-modal .btn-function {
    background: #607d8b;
    color: white;
    border: none;
}
.calculator-modal .btn-function:hover {
    background: #455a64;
    color: white;
}
.calculator-modal .history-list {
    max-height: 150px;
    overflow-y: auto;
}
.calculator-modal .history-list li {
    cursor: pointer;
    padding: 5px 10px;
}
.calculator-modal .history-list li:hover {
    background: #f0f0f0;
}
.calculator-modal .troco-section {
    background: #e3f2fd;
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}
.calculator-modal .troco-section label {
    font-weight: 600;
    color: #1565c0;
}
.calculator-modal .keyboard-hint {
    font-size: 11px;
    color: #999;
    margin-top: 10px;
}
</style>

<div class="calculator-modal">
    <div class="row">
        <!-- Calculadora Principal -->
        <div class="col-md-7">
            <div class="form-group">
                <input type="text" id="calc-display" class="form-control calc-display" value="0" readonly>
            </div>
            
            <div class="row">
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-clear" data-calc="C">C</button>
                </div>
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-function" data-calc="CE">CE</button>
                </div>
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-function" data-calc="%">%</button>
                </div>
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-operator" data-calc="÷">÷</button>
                </div>
            </div>
            
            <div class="row">
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-number" data-calc="7">7</button>
                </div>
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-number" data-calc="8">8</button>
                </div>
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-number" data-calc="9">9</button>
                </div>
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-operator" data-calc="×">×</button>
                </div>
            </div>
            
            <div class="row">
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-number" data-calc="4">4</button>
                </div>
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-number" data-calc="5">5</button>
                </div>
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-number" data-calc="6">6</button>
                </div>
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-operator" data-calc="-">−</button>
                </div>
            </div>
            
            <div class="row">
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-number" data-calc="1">1</button>
                </div>
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-number" data-calc="2">2</button>
                </div>
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-number" data-calc="3">3</button>
                </div>
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-operator" data-calc="+">+</button>
                </div>
            </div>
            
            <div class="row">
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-function" data-calc="±">±</button>
                </div>
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-number" data-calc="0">0</button>
                </div>
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-number" data-calc=".">.</button>
                </div>
                <div class="col-xs-3">
                    <button class="btn calc-btn btn-equals" data-calc="=">=</button>
                </div>
            </div>
            
            <div class="row mt-5">
                <div class="col-xs-6">
                    <button class="btn calc-btn btn-function" data-calc="√">√</button>
                </div>
                <div class="col-xs-6">
                    <button class="btn calc-btn btn-info" id="use-result-troco">
                        <i class="fa fa-arrow-right"></i> Usar no Troco
                    </button>
                </div>
            </div>
            
            <div class="keyboard-hint text-center">
                <i class="fa fa-keyboard-o"></i> Atalhos: números, +, -, *, /, Enter, Esc
            </div>
        </div>
        
        <!-- Painel Lateral -->
        <div class="col-md-5">
            <!-- Histórico -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-history"></i> <?php echo trans('text_history') ?? 'Histórico'; ?>
                </div>
                <div class="panel-body p-0">
                    <ul id="calc-history" class="list-group history-list mb-0">
                        <li class="list-group-item text-center text-muted small">
                            <i>Nenhum cálculo ainda</i>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Calculadora de Troco -->
            <div class="troco-section">
                <h5><i class="fa fa-money"></i> <?php echo trans('text_change_calculator') ?? 'Cálculo de Troco'; ?></h5>
                <div class="form-group">
                    <label><?php echo trans('label_total') ?? 'Total da Compra'; ?></label>
                    <div class="input-group">
                        <span class="input-group-addon"><?php echo get_currency_symbol();?></span>
                        <input type="number" id="troco-total" class="form-control" placeholder="0.00" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo trans('label_amount_paid') ?? 'Valor Pago'; ?></label>
                    <div class="input-group">
                        <span class="input-group-addon"><?php echo get_currency_symbol();?></span>
                        <input type="number" id="troco-pago" class="form-control" placeholder="0.00" step="0.01">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label><?php echo trans('label_change') ?? 'Troco'; ?></label>
                    <div class="input-group">
                        <span class="input-group-addon"><?php echo get_currency_symbol();?></span>
                        <input type="text" id="troco-result" class="form-control text-success font-bold" value="0.00" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
