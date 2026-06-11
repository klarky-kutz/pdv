<?php
/**
 * Migration Runner — Moda IA (Concierge IA)
 * Arquivo: migrations/run_ai_concierge_migration.php
 * 
 * ACESSE: http://localhost/modernpos/migrations/run_ai_concierge_migration.php
 * Ou em produção: https://pdv.easysaascloud.com/migrations/run_ai_concierge_migration.php
 */

// Segurança: permitir acesso somente local ou com token
$allow_remote = false; // Altere para true apenas temporariamente em produção
$remote_token = 'moda_ia_mig_2026'; // Mude antes de usar em produção

$is_local = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost']);
$has_token = isset($_GET['token']) && $_GET['token'] === $remote_token;

if (!$is_local && !$has_token) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Acesse localmente ou use ?token=... em produção.</p>');
}

// Carregar config do banco
$config_path = realpath(__DIR__ . '/../config.php');
if (!file_exists($config_path)) {
    die('config.php não encontrado.');
}
require_once $config_path;

$migration_file = __DIR__ . '/2026_03_28_create_ai_concierge_tables.sql';

// Processar migração
$result = null;
$errors = [];
$executed_statements = 0;

if (isset($_POST['run'])) {
    try {
        $pdo = new PDO(
            "mysql:host={$sql_details['host']};port={$sql_details['port']};dbname={$sql_details['db']};charset=utf8",
            $sql_details['user'],
            $sql_details['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $sql = file_get_contents($migration_file);

        // Separar statements (ignorar comentários de linha)
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($s) {
                return strlen($s) > 5 && !preg_match('/^--/', $s);
            }
        );

        foreach ($statements as $stmt) {
            if (empty(trim($stmt))) continue;
            $pdo->exec($stmt);
            $executed_statements++;
        }

        // Criar diretórios de storage
        $dirs = [
            realpath(__DIR__ . '/../storage') . '/concierge',
            realpath(__DIR__ . '/../storage') . '/concierge/catalogo',
            realpath(__DIR__ . '/../storage') . '/concierge/uber',
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        $result = 'success';
    } catch (PDOException $e) {
        $result = 'error';
        $errors[] = $e->getMessage();
    }
}

// Verificar quais tabelas já existem
$tables_status = [];
$tables_expected = [
    'ai_catalogo_models', 'ai_catalogo_variants', 'ai_chat_profiles',
    'ai_orders', 'ai_order_items', 'ai_usage_log'
];

try {
    $check_pdo = new PDO(
        "mysql:host={$sql_details['host']};port={$sql_details['port']};dbname={$sql_details['db']};charset=utf8",
        $sql_details['user'],
        $sql_details['pass']
    );
    foreach ($tables_expected as $table) {
        $stmt = $check_pdo->query("SHOW TABLES LIKE '{$table}'");
        $tables_status[$table] = $stmt->rowCount() > 0;
    }
} catch (Exception $e) {
    // silencioso
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Migration — Moda IA Concierge</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#0f0722;min-height:100vh;padding:30px 20px}
.wrap{max-width:760px;margin:0 auto}
.hero{background:linear-gradient(135deg,#4c1d95,#7c3aed);border-radius:8px;padding:28px 30px;margin-bottom:24px;display:flex;align-items:center;gap:20px}
.hero-icon{font-size:40px}
.hero h1{font-size:22px;font-weight:800;color:#fff;margin-bottom:4px}
.hero p{font-size:13px;color:#c4b5fd}
.card{background:#fff;border-radius:8px;padding:24px;margin-bottom:18px;box-shadow:0 4px 20px rgba(0,0,0,.3)}
.card h2{font-size:15px;font-weight:700;color:#374151;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.table-check{width:100%;border-collapse:collapse}
.table-check th{background:#f9fafb;text-align:left;padding:8px 12px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.3px;border-bottom:1px solid #e5e7eb}
.table-check td{padding:10px 12px;font-size:13px;border-bottom:1px solid #f3f4f6;color:#374151}
.badge-ok{background:#d1fae5;color:#065f46;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700}
.badge-miss{background:#fee2e2;color:#991b1b;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700}
.alert{padding:14px 16px;border-radius:6px;font-size:13px;margin-bottom:18px;border-left:4px solid;display:flex;align-items:flex-start;gap:10px}
.alert-ok{background:#d1fae5;border-color:#059669;color:#065f46}
.alert-err{background:#fee2e2;border-color:#dc2626;color:#7f1d1d}
.alert-warn{background:#fef3c7;border-color:#d97706;color:#78350f}
.btn-run{width:100%;padding:14px;background:linear-gradient(135deg,#6d28d9,#7c3aed);color:#fff;border:none;border-radius:6px;font-size:15px;font-weight:700;cursor:pointer;letter-spacing:.3px;transition:all .2s}
.btn-run:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(109,40,217,.4)}
.count-row{display:flex;gap:12px;margin-bottom:18px}
.count-box{flex:1;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:14px;text-align:center}
.count-box .val{font-size:26px;font-weight:900;color:#4c1d95}
.count-box .lbl{font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-top:3px}
code{background:#1f2937;color:#a78bfa;padding:2px 7px;border-radius:4px;font-size:12px;font-family:monospace}
</style>
</head>
<body>
<div class="wrap">

  <div class="hero">
    <div class="hero-icon">✦</div>
    <div>
      <h1>Migration — Moda IA Concierge</h1>
      <p>Cria as 6 tabelas do módulo e altera <code>stores</code> e <code>plans</code></p>
    </div>
  </div>

  <?php if ($result === 'success'): ?>
  <div class="alert alert-ok">
    <span>✅</span>
    <div><strong>Migration executada com sucesso!</strong><br><?= $executed_statements ?> statements processados. Diretórios de storage criados em <code>storage/concierge/</code></div>
  </div>
  <?php elseif ($result === 'error'): ?>
  <div class="alert alert-err">
    <span>❌</span>
    <div><strong>Erro na migration:</strong><br><?= htmlspecialchars(implode('<br>', $errors)) ?></div>
  </div>
  <?php endif; ?>

  <!-- Status das tabelas -->
  <div class="card">
    <h2>📊 Status das Tabelas</h2>
    <?php
    $all_ok = !in_array(false, $tables_status, true);
    $count_ok = count(array_filter($tables_status));
    $count_total = count($tables_status);
    ?>
    <div class="count-row">
      <div class="count-box"><div class="val"><?= $count_ok ?>/<?= $count_total ?></div><div class="lbl">Tabelas prontas</div></div>
      <div class="count-box"><div class="val" style="color:<?= $all_ok ? '#059669' : '#dc2626' ?>"><?= $all_ok ? '✅' : '⚠️' ?></div><div class="lbl">Status geral</div></div>
    </div>
    <table class="table-check">
      <thead><tr><th>Tabela</th><th>Propósito</th><th>Status</th></tr></thead>
      <tbody>
        <?php
        $desc = [
            'ai_catalogo_models'   => 'Modelos do catálogo IA (produto-raiz)',
            'ai_catalogo_variants' => 'Variantes: cor + tamanho + estoque',
            'ai_chat_profiles'     => 'Memória de perfil WhatsApp dos clientes',
            'ai_orders'            => 'Pedidos originados via WhatsApp',
            'ai_order_items'       => 'Itens de cada pedido (snapshot)',
            'ai_usage_log'         => 'Contador de uso por plano/mês',
        ];
        foreach ($tables_status as $table => $exists): ?>
        <tr>
          <td><code><?= $table ?></code></td>
          <td style="color:#6b7280;font-size:12px"><?= $desc[$table] ?></td>
          <td><?php if ($exists): ?><span class="badge-ok">✓ Existe</span><?php else: ?><span class="badge-miss">✗ Falta</span><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (!$all_ok || $result === null): ?>
  <!-- Botão executar -->
  <div class="card">
    <h2>🚀 Executar Migration</h2>
    <div class="alert alert-warn">
      <span>⚠️</span>
      <div>Esta operação usa <code>CREATE TABLE IF NOT EXISTS</code> e <code>ADD COLUMN IF NOT EXISTS</code> — seguro para reexecutar. Mesmo assim, <strong>faça backup antes</strong>.</div>
    </div>
    <form method="POST">
      <input type="hidden" name="run" value="1">
      <button type="submit" class="btn-run">✦ Executar Migration do Moda IA</button>
    </form>
  </div>
  <?php elseif ($all_ok): ?>
  <div class="card">
    <h2>✅ Tudo pronto!</h2>
    <p style="color:#6b7280;font-size:13px;margin-bottom:14px">Todas as tabelas foram criadas. O módulo Moda IA está com a fundação de banco de dados completa.</p>
    <p style="font-size:13px;color:#374151"><strong>Próximos passos:</strong></p>
    <ol style="margin-top:8px;padding-left:20px;font-size:13px;color:#6b7280;line-height:2">
      <li>Verificar token gerado em <code>stores.ai_webhook_token</code></li>
      <li>Conferir colunas AI em <code>plans</code></li>
      <li>Acessar o módulo em <a href="../admin/concierge_catalogo.php" style="color:#6d28d9">admin/concierge_catalogo.php</a></li>
    </ol>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
