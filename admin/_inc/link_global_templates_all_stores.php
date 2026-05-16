<?php
/**
 * Script para vincular templates globais em TODAS as lojas existentes
 * 
 * Use este script UMA VEZ para corrigir lojas que já existem
 * 
 * Acesse: http://localhost/modernpos/_inc/link_global_templates_all_stores.php
 */

session_start();
include("../_init.php");

// Verificar se usuário está logado e é admin
if (!is_loggedin() || user_group_id() != 1) {
    die('<h1>❌ Acesso Negado</h1><p>Apenas administradores podem acessar esta página.</p>');
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vincular Templates Globais</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
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
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid;
            border-radius: 4px;
        }
        .alert-info {
            background: #e3f2fd;
            border-left-color: #2196F3;
            color: #1976D2;
        }
        .alert-success {
            background: #e8f5e9;
            border-left-color: #4CAF50;
            color: #2E7D32;
        }
        .alert-warning {
            background: #fff3e0;
            border-left-color: #ff9800;
            color: #E65100;
        }
        button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 10px 5px;
        }
        button:hover {
            background: #45a049;
        }
        button.secondary {
            background: #2196F3;
        }
        button.secondary:hover {
            background: #1976D2;
        }
        .log {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 13px;
            max-height: 400px;
            overflow-y: auto;
        }
        .log-item {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .log-item:last-child {
            border-bottom: none;
        }
        .log-success {
            color: #2E7D32;
        }
        .log-info {
            color: #1976D2;
        }
        .log-error {
            color: #C62828;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔗 Vincular Templates Globais às Lojas</h1>
        
        <?php if (isset($_GET['action']) && $_GET['action'] == 'execute'): ?>
            <?php
            $log = [];
            $stores_updated = 0;
            $stores_skipped = 0;
            $templates_linked = 0;
            
            try {
                // Buscar todas as lojas
                $stmt = db()->prepare("SELECT store_id, name FROM stores ORDER BY store_id ASC");
                $stmt->execute();
                $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $log[] = ['type' => 'info', 'msg' => 'Total de lojas encontradas: ' . count($stores)];
                
                // Buscar templates globais
                $stmt = db()->prepare("
                    SELECT template_id, template_name 
                    FROM pos_templates 
                    WHERE template_name NOT LIKE '%(Personalizado)%'
                    ORDER BY template_id ASC
                ");
                $stmt->execute();
                $global_templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $log[] = ['type' => 'info', 'msg' => 'Templates globais encontrados: ' . count($global_templates)];
                
                if (empty($global_templates)) {
                    $log[] = ['type' => 'error', 'msg' => 'Nenhum template global encontrado!'];
                } else {
                    // Para cada loja
                    foreach ($stores as $store) {
                        $store_id = (int)$store['store_id'];
                        $store_name = $store['name'];
                        
                        $log[] = ['type' => 'info', 'msg' => '---'];
                        $log[] = ['type' => 'info', 'msg' => 'Processando: ' . $store_name . ' (ID: ' . $store_id . ')'];
                        
                        $linked_count = 0;
                        $first_template_id = null;
                        
                        foreach ($global_templates as $idx => $template) {
                            $template_id = (int)$template['template_id'];
                            $template_name = $template['template_name'];
                            
                            if ($first_template_id === null) {
                                $first_template_id = $template_id;
                            }
                            
                            // Verificar se já existe vínculo
                            $check = db()->prepare("
                                SELECT pt2s 
                                FROM pos_template_to_store 
                                WHERE store_id = ? AND ttemplate_id = ?
                            ");
                            $check->execute([$store_id, $template_id]);
                            $exists = $check->fetch(PDO::FETCH_ASSOC);
                            
                            if ($exists) {
                                $log[] = ['type' => 'info', 'msg' => '  ⚪ Já vinculado: ' . $template_name];
                            } else {
                                // Criar vínculo
                                $insert = db()->prepare("
                                    INSERT INTO pos_template_to_store 
                                    (store_id, ttemplate_id, is_active, status, sort_order) 
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                
                                $is_active = ($idx === 0) ? 1 : 0; // Primeiro ativo, outros não
                                
                                $insert->execute([
                                    $store_id,
                                    $template_id,
                                    $is_active,
                                    1,
                                    $idx + 1
                                ]);
                                
                                $linked_count++;
                                $templates_linked++;
                                $log[] = ['type' => 'success', 'msg' => '  ✅ Vinculado: ' . $template_name];
                            }
                        }
                        
                        if ($linked_count > 0) {
                            // Definir primeiro template como padrão na preferência
                            if ($first_template_id) {
                                $store_model = registry()->get('loader')->model('store');
                                $store_data = $store_model->getStore($store_id);
                                
                                if ($store_data) {
                                    $pref = valid_unserialize($store_data['preference']);
                                    if (!is_array($pref)) {
                                        $pref = [];
                                    }
                                    
                                    if (!isset($pref['receipt_template'])) {
                                        $pref['receipt_template'] = $first_template_id;
                                        
                                        $update = db()->prepare("
                                            UPDATE stores 
                                            SET preference = ? 
                                            WHERE store_id = ?
                                        ");
                                        $update->execute([serialize($pref), $store_id]);
                                        
                                        $log[] = ['type' => 'success', 'msg' => '  ✅ Preferência de template definida'];
                                    }
                                }
                            }
                            
                            $stores_updated++;
                            $log[] = ['type' => 'success', 'msg' => '✅ Loja atualizada: ' . $linked_count . ' template(s) vinculado(s)'];
                        } else {
                            $stores_skipped++;
                            $log[] = ['type' => 'info', 'msg' => '⚪ Loja já tinha todos os templates'];
                        }
                    }
                    
                    $log[] = ['type' => 'info', 'msg' => '---'];
                    $log[] = ['type' => 'success', 'msg' => '🎉 CONCLUÍDO!'];
                    $log[] = ['type' => 'success', 'msg' => 'Lojas atualizadas: ' . $stores_updated];
                    $log[] = ['type' => 'info', 'msg' => 'Lojas que já tinham templates: ' . $stores_skipped];
                    $log[] = ['type' => 'success', 'msg' => 'Total de vínculos criados: ' . $templates_linked];
                }
                
            } catch (Exception $e) {
                $log[] = ['type' => 'error', 'msg' => '❌ ERRO: ' . $e->getMessage()];
            }
            ?>
            
            <div class="alert alert-success">
                <strong>✅ Processo Executado!</strong><br>
                Veja o log abaixo para detalhes.
            </div>
            
            <h3>📋 Log de Execução</h3>
            <div class="log">
                <?php foreach ($log as $item): ?>
                    <div class="log-item log-<?php echo $item['type']; ?>">
                        <?php echo htmlspecialchars($item['msg']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="margin-top: 30px;">
                <a href="?"><button class="secondary">← Voltar</button></a>
                <a href="<?php echo root_url(); ?>admin/select_receipt_template.php">
                    <button>Ver Templates de Recibo</button>
                </a>
            </div>
            
        <?php else: ?>
            
            <div class="alert alert-info">
                <strong>ℹ️ Sobre Este Script</strong><br>
                Este script vincula automaticamente todos os <strong>templates globais de recibo</strong> 
                a TODAS as lojas existentes no sistema.<br><br>
                <strong>Quando usar:</strong>
                <ul>
                    <li>Após criar novos templates globais no painel SAAS</li>
                    <li>Quando lojas existentes não veem os modelos de recibo</li>
                    <li>Para corrigir lojas criadas antes desta funcionalidade</li>
                </ul>
            </div>
            
            <div class="alert alert-warning">
                <strong>⚠️ Atenção</strong><br>
                Este script é seguro e pode ser executado várias vezes.<br>
                Ele NÃO sobrescreve vínculos existentes, apenas adiciona os que faltam.
            </div>
            
            <h3>📦 O Que Será Feito:</h3>
            <ul>
                <li>✅ Buscar todas as lojas do sistema</li>
                <li>✅ Buscar todos os templates globais (sem "(Personalizado)")</li>
                <li>✅ Vincular templates que ainda não estão vinculados</li>
                <li>✅ Definir o primeiro template como padrão (se necessário)</li>
                <li>✅ Pular lojas que já têm todos os templates</li>
            </ul>
            
            <div style="margin-top: 30px;">
                <a href="?action=execute">
                    <button>🚀 Executar Agora</button>
                </a>
                <a href="<?php echo root_url(); ?>admin/dashboard.php">
                    <button class="secondary">← Cancelar</button>
                </a>
            </div>
            
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;">
            <strong>Nota:</strong> Novas lojas criadas após esta correção já receberão os templates automaticamente.
        </div>
    </div>
</body>
</html>
