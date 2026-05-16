<?php
/**
 * Script de teste para validar geração de código EAN-13
 * 
 * Acesse via navegador (faça login primeiro):
 * http://localhost/modernpos/_inc/test_generate_code.php
 */

session_start();
include("../_init.php");

// Verificar se usuário está logado
if (!is_loggedin()) {
    die('<h1>❌ Erro: Você precisa estar logado</h1><p>Faça login no sistema primeiro.</p>');
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Geração de Código EAN-13</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-left: 4px solid #2196F3;
            margin: 20px 0;
        }
        .success {
            background: #e8f5e9;
            border-left-color: #4CAF50;
        }
        .error {
            background: #ffebee;
            border-left-color: #f44336;
        }
        button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 10px 5px;
        }
        button:hover {
            background: #45a049;
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        #result {
            margin-top: 20px;
            padding: 20px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
        }
        .code-display {
            font-size: 24px;
            font-weight: bold;
            color: #1976D2;
            margin: 15px 0;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 4px;
            text-align: center;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        .stat-item {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
            border-left: 3px solid #4CAF50;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-top: 5px;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .loading.active {
            display: block;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #4CAF50;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Teste de Geração de Código EAN-13</h1>
        
        <div class="info">
            <strong>ℹ️ Informações do Sistema</strong><br>
            <strong>Store ID:</strong> <?php echo store_id(); ?><br>
            <strong>User ID:</strong> <?php echo user_id(); ?><br>
            <strong>Store Nome:</strong> <?php echo store('name'); ?>
        </div>

        <div style="margin: 20px 0;">
            <button onclick="generateCode()">🎲 Gerar Código EAN-13</button>
            <button onclick="generateMultiple()">📊 Gerar 5 Códigos</button>
            <button onclick="validateCheckDigit()">✅ Validar Último Código</button>
            <button onclick="clearResults()">🗑️ Limpar</button>
        </div>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Gerando código...</p>
        </div>

        <div id="result"></div>
    </div>

    <script>
        let lastCode = null;
        let generatedCodes = [];

        function showLoading(show) {
            document.getElementById('loading').classList.toggle('active', show);
        }

        function displayResult(html, type = 'info') {
            const result = document.getElementById('result');
            result.className = type;
            result.innerHTML = html;
            result.style.display = 'block';
        }

        async function generateCode() {
            showLoading(true);
            
            try {
                const response = await fetch('<?php echo root_url(); ?>_inc/generate_product_code.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                const data = await response.json();
                
                if (data.success) {
                    lastCode = data.code;
                    generatedCodes.push(data.code);
                    
                    displayResult(`
                        <div class="success">
                            <h3>✅ Código gerado com sucesso!</h3>
                            <div class="code-display">${data.code}</div>
                            <div class="stats">
                                <div class="stat-item">
                                    <div class="stat-label">Sequência</div>
                                    <div class="stat-value">${data.sequence || 'N/A'}</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Comprimento</div>
                                    <div class="stat-value">${data.code.length} dígitos</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Prefixo</div>
                                    <div class="stat-value">${data.code.substring(0, 4)}</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Verificador</div>
                                    <div class="stat-value">${data.code.charAt(12)}</div>
                                </div>
                            </div>
                        </div>
                    `, 'success');
                } else {
                    displayResult(`
                        <div class="error">
                            <h3>❌ Erro ao gerar código</h3>
                            <p><strong>Mensagem:</strong> ${data.error || 'Erro desconhecido'}</p>
                        </div>
                    `, 'error');
                }
            } catch (error) {
                displayResult(`
                    <div class="error">
                        <h3>❌ Erro de Conexão</h3>
                        <p><strong>Erro:</strong> ${error.message}</p>
                        <p>Verifique se o endpoint está acessível e se você está logado.</p>
                    </div>
                `, 'error');
            } finally {
                showLoading(false);
            }
        }

        async function generateMultiple() {
            showLoading(true);
            const codes = [];
            
            try {
                for (let i = 0; i < 5; i++) {
                    const response = await fetch('<?php echo root_url(); ?>_inc/generate_product_code.php', {
                        method: 'POST'
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        codes.push(data.code);
                        generatedCodes.push(data.code);
                    }
                }
                
                lastCode = codes[codes.length - 1];
                
                const codeList = codes.map((code, idx) => 
                    `<div style="padding: 8px; background: #f9f9f9; margin: 5px 0; border-radius: 4px;">
                        <strong>#${idx + 1}:</strong> ${code}
                    </div>`
                ).join('');
                
                displayResult(`
                    <div class="success">
                        <h3>✅ ${codes.length} códigos gerados</h3>
                        ${codeList}
                        <p style="margin-top: 15px;"><strong>Verificação:</strong> ${isSequential(codes) ? '✅ Códigos são sequenciais' : '❌ Códigos NÃO são sequenciais'}</p>
                    </div>
                `, 'success');
            } catch (error) {
                displayResult(`
                    <div class="error">
                        <h3>❌ Erro ao gerar múltiplos códigos</h3>
                        <p>${error.message}</p>
                    </div>
                `, 'error');
            } finally {
                showLoading(false);
            }
        }

        function isSequential(codes) {
            if (codes.length < 2) return true;
            
            for (let i = 1; i < codes.length; i++) {
                const prev = parseInt(codes[i-1].substring(4, 12));
                const curr = parseInt(codes[i].substring(4, 12));
                
                if (curr !== prev + 1) return false;
            }
            
            return true;
        }

        function validateCheckDigit() {
            if (!lastCode) {
                displayResult(`
                    <div class="error">
                        <h3>⚠️ Nenhum código para validar</h3>
                        <p>Gere um código primeiro.</p>
                    </div>
                `, 'error');
                return;
            }

            const code12 = lastCode.substring(0, 12);
            const providedCheckDigit = parseInt(lastCode.charAt(12));
            const calculatedCheckDigit = calculateEAN13CheckDigit(code12);
            
            const isValid = providedCheckDigit === calculatedCheckDigit;
            
            displayResult(`
                <div class="${isValid ? 'success' : 'error'}">
                    <h3>${isValid ? '✅' : '❌'} Validação do Dígito Verificador</h3>
                    <div class="code-display">${lastCode}</div>
                    <div class="stats">
                        <div class="stat-item">
                            <div class="stat-label">12 Primeiros Dígitos</div>
                            <div class="stat-value">${code12}</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Verificador Fornecido</div>
                            <div class="stat-value">${providedCheckDigit}</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Verificador Calculado</div>
                            <div class="stat-value">${calculatedCheckDigit}</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Status</div>
                            <div class="stat-value">${isValid ? '✅ VÁLIDO' : '❌ INVÁLIDO'}</div>
                        </div>
                    </div>
                </div>
            `, isValid ? 'success' : 'error');
        }

        function calculateEAN13CheckDigit(code12) {
            let sum = 0;
            for (let i = 0; i < 12; i++) {
                const digit = parseInt(code12[i]);
                sum += (i % 2 === 0) ? digit : digit * 3;
            }
            return (10 - (sum % 10)) % 10;
        }

        function clearResults() {
            document.getElementById('result').style.display = 'none';
            document.getElementById('result').innerHTML = '';
            lastCode = null;
        }
    </script>
</body>
</html>
