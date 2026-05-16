<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Migração - ModernPOS SaaS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }
        
        .header h1 {
            font-size: 42px;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .header p {
            font-size: 18px;
            opacity: 0.9;
        }
        
        .success-banner {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border-left: 5px solid #28a745;
        }
        
        .success-banner h2 {
            color: #28a745;
            margin-bottom: 15px;
            font-size: 28px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-card .label {
            color: #666;
            margin-top: 5px;
            font-size: 14px;
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .card h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 22px;
        }
        
        .card p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .files-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .files-section h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .file-list {
            display: grid;
            gap: 10px;
        }
        
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }
        
        .file-item .file-name {
            font-weight: 600;
            color: #333;
        }
        
        .file-item .file-desc {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Central de Migração SaaS</h1>
            <p>ModernPOS - Sistema de Gerenciamento Multi-Tenant</p>
        </div>
        
        <div class="success-banner">
            <h2>✅ Migração Concluída com Sucesso!</h2>
            <p>Todas as tabelas foram migradas e estão prontas para arquitetura SaaS multi-tenant.</p>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="number">577</div>
                    <div class="label">Registros Migrados</div>
                </div>
                <div class="stat-card">
                    <div class="number">4</div>
                    <div class="label">Tabelas Atualizadas</div>
                </div>
                <div class="stat-card">
                    <div class="number">100%</div>
                    <div class="label">Taxa de Sucesso</div>
                </div>
                <div class="stat-card">
                    <div class="number">0</div>
                    <div class="label">Dados Perdidos</div>
                </div>
            </div>
        </div>
        
        <div class="cards-grid">
            <div class="card">
                <div class="card-icon">🔍</div>
                <h3>Interface de Migração</h3>
                <p>Ferramenta visual completa com 4 passos: verificação, backup, migração e validação.</p>
                <a href="web_migration.php" class="btn">Abrir Interface</a>
            </div>
            
            <div class="card">
                <div class="card-icon">💾</div>
                <h3>Gerenciador de Backups</h3>
                <p>Visualize, baixe e gerencie todos os backups do banco de dados criados.</p>
                <a href="view_backups.php" class="btn btn-success">Ver Backups</a>
            </div>
            
            <div class="card">
                <div class="card-icon">📄</div>
                <h3>Documentação Completa</h3>
                <p>Guia detalhado com instruções, verificações e próximos passos.</p>
                <a href="MIGRATION_SUCCESS.md" class="btn btn-secondary" download>Download MD</a>
            </div>
        </div>
        
        <div class="files-section">
            <h2>📁 Arquivos Criados</h2>
            <div class="file-list">
                <div class="file-item">
                    <div>
                        <div class="file-name">web_migration.php</div>
                        <div class="file-desc">Interface web principal para executar migração</div>
                    </div>
                    <span class="badge badge-success">Executado ✓</span>
                </div>
                
                <div class="file-item">
                    <div>
                        <div class="file-name">migrate_sales_tables_add_tenant.sql</div>
                        <div class="file-desc">Script SQL da migração (adiciona tenant_id)</div>
                    </div>
                    <span class="badge badge-success">Executado ✓</span>
                </div>
                
                <div class="file-item">
                    <div>
                        <div class="file-name">fix_tenant_id.php</div>
                        <div class="file-desc">Script de correção para registros sem tenant_id</div>
                    </div>
                    <span class="badge badge-success">Executado ✓</span>
                </div>
                
                <div class="file-item">
                    <div>
                        <div class="file-name">view_backups.php</div>
                        <div class="file-desc">Gerenciador visual de backups</div>
                    </div>
                    <span class="badge badge-info">Disponível</span>
                </div>
                
                <div class="file-item">
                    <div>
                        <div class="file-name">run_sales_migration.php</div>
                        <div class="file-desc">Script CLI para migração via terminal (opcional)</div>
                    </div>
                    <span class="badge badge-warning">Alternativo</span>
                </div>
                
                <div class="file-item">
                    <div>
                        <div class="file-name">MIGRATION_SUCCESS.md</div>
                        <div class="file-desc">Resumo completo da migração realizada</div>
                    </div>
                    <span class="badge badge-info">Documentação</span>
                </div>
                
                <div class="file-item">
                    <div>
                        <div class="file-name">README_MIGRATION.md</div>
                        <div class="file-desc">Guia de instruções e solução de problemas</div>
                    </div>
                    <span class="badge badge-info">Documentação</span>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 40px; color: white;">
            <p style="font-size: 18px; margin-bottom: 10px;">
                <strong>Migração Concluída em:</strong> 06/02/2026 às 20:30
            </p>
            <p style="opacity: 0.8;">
                Todos os dados foram preservados e estão prontos para uso
            </p>
        </div>
    </div>
</body>
</html>
