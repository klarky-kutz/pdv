<?php
/**
 * Gerenciador de Backups - ModernPOS
 * Acesse via: http://localhost/modernpos/migrations/view_backups.php
 */

define('BACKUP_DIR', __DIR__ . '/../backups/');

// Processar ação
$action = $_GET['action'] ?? 'list';
$file = $_GET['file'] ?? '';

if ($action === 'download' && $file) {
    $filepath = BACKUP_DIR . basename($file);
    if (file_exists($filepath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}

if ($action === 'delete' && $file) {
    $filepath = BACKUP_DIR . basename($file);
    if (file_exists($filepath)) {
        unlink($filepath);
        header('Location: view_backups.php?msg=deleted');
        exit;
    }
}

// Listar backups
$backups = [];
if (is_dir(BACKUP_DIR)) {
    $files = glob(BACKUP_DIR . '*.sql');
    foreach ($files as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => filemtime($file),
            'path' => $file
        ];
    }
    // Ordenar por data (mais recente primeiro)
    usort($backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Backups - ModernPOS</title>
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
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .content {
            padding: 30px;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .info-box strong {
            color: #667eea;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .backups-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .backups-table th,
        .backups-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .backups-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .backups-table tr:hover {
            background: #f8f9fa;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            margin: 0 5px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💾 Gerenciador de Backups</h1>
            <p>ModernPOS - Backups do Banco de Dados</p>
        </div>
        
        <div class="content">
            <a href="web_migration.php" class="back-link">← Voltar para Migração</a>
            
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                <div class="alert alert-success">
                    ✓ Backup excluído com sucesso!
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <strong>📁 Localização:</strong> <?= realpath(BACKUP_DIR) ?><br>
                <strong>📊 Total de Backups:</strong> <?= count($backups) ?><br>
                <strong>💾 Espaço Total:</strong> <?= formatBytes(array_sum(array_column($backups, 'size'))) ?>
            </div>
            
            <?php if (empty($backups)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <h2>Nenhum backup encontrado</h2>
                    <p>Crie seu primeiro backup na página de migração.</p>
                    <br>
                    <a href="web_migration.php" class="btn btn-primary">Ir para Migração</a>
                </div>
            <?php else: ?>
                <table class="backups-table">
                    <thead>
                        <tr>
                            <th>Nome do Arquivo</th>
                            <th>Tamanho</th>
                            <th>Data de Criação</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($backup['name']) ?></strong>
                                </td>
                                <td><?= formatBytes($backup['size']) ?></td>
                                <td><?= date('d/m/Y H:i:s', $backup['date']) ?></td>
                                <td>
                                    <?php if ($backup['size'] > 100): ?>
                                        <span class="badge badge-success">✓ OK</span>
                                    <?php elseif ($backup['size'] > 0): ?>
                                        <span class="badge badge-warning">⚠ Pequeno</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">✗ Vazio</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?action=download&file=<?= urlencode($backup['name']) ?>" 
                                       class="btn btn-success">
                                        ⬇ Download
                                    </a>
                                    <a href="?action=delete&file=<?= urlencode($backup['name']) ?>" 
                                       class="btn btn-danger"
                                       onclick="return confirm('Tem certeza que deseja excluir este backup?')">
                                        🗑 Excluir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 10px; border-left: 4px solid #ffc107;">
                    <h3 style="margin-bottom: 10px;">⚠️ Importante sobre Backups</h3>
                    <ul style="margin-left: 20px; line-height: 1.8;">
                        <li><strong>Backups vazios (0 bytes):</strong> Foram criados com falha, podem ser excluídos.</li>
                        <li><strong>Backups válidos:</strong> Devem ter pelo menos 1 MB para conter todos os dados.</li>
                        <li><strong>Download:</strong> Baixe e guarde os backups em local seguro antes de grandes mudanças.</li>
                        <li><strong>Restauração:</strong> Use phpMyAdmin (Importar) ou comando mysql para restaurar.</li>
                    </ul>
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <a href="web_migration.php" class="btn btn-primary" style="padding: 15px 40px; font-size: 16px;">
                        ← Voltar para Migração
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
