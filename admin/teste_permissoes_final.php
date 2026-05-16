<?php 
ob_start();
session_start();
include("../_init.php");

// Este é um arquivo DO MODERNPOS para testar as permissões

// Set Document Title
$document->setTitle('Teste de Permissões RBAC');

// Include Header and Footer
include("header.php"); 
include("left_sidebar.php");
?>

<!-- Content Wrapper Start -->
<div class="content-wrapper">
  <section class="content-header">
    <h1>🧪 Teste Final de Permissões RBAC</h1>
  </section>

  <section class="content">
    <div class="box box-success">
      <div class="box-header with-border">
        <h3 class="box-title">📊 Informações do Usuário Atual</h3>
      </div>
      <div class="box-body">
        <?php
        global $user;
        
        echo '<table class="table table-bordered">';
        echo '<tr><th width="30%">Campo</th><th>Valor</th></tr>';
        echo '<tr><td><strong>User ID</strong></td><td>' . user_id() . '</td></tr>';
        echo '<tr><td><strong>Username</strong></td><td>' . user('username') . '</td></tr>';
        echo '<tr><td><strong>Email</strong></td><td>' . user('email') . '</td></tr>';
        echo '<tr><td><strong>Group ID</strong></td><td><span class="label label-danger" style="font-size: 14px;">' . user_group_id() . '</span></td></tr>';
        
        // Buscar nome do grupo
        $stmt = db()->prepare("SELECT name, slug FROM user_group WHERE group_id = ?");
        $stmt->execute([user_group_id()]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($group) {
            echo '<tr><td><strong>Nome do Grupo</strong></td><td>' . htmlspecialchars($group['name']) . '</td></tr>';
            echo '<tr><td><strong>Slug do Grupo</strong></td><td><code>' . htmlspecialchars($group['slug']) . '</code></td></tr>';
        }
        
        // Verificar tenant_id
        $userRow = get_the_user(user_id());
        if ($userRow && isset($userRow['tenant_id'])) {
            echo '<tr><td><strong>Tenant ID</strong></td><td>' . $userRow['tenant_id'] . '</td></tr>';
        }
        
        echo '</table>';
        ?>
      </div>
    </div>

    <div class="box box-info">
      <div class="box-header with-border">
        <h3 class="box-title">🔑 Permissões do Grupo</h3>
      </div>
      <div class="box-body">
        <?php
        // Buscar permissões do grupo
        $stmt = db()->prepare("SELECT permission FROM user_group WHERE group_id = ?");
        $stmt->execute([user_group_id()]);
        $groupData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($groupData) {
            $permRaw = $groupData['permission'];
            $unserialized = @unserialize($permRaw);
            
            if (is_array($unserialized) && isset($unserialized['access']) && is_array($unserialized['access'])) {
                $permCount = count($unserialized['access']);
                echo '<div class="alert alert-success">';
                echo '<strong>✅ Formato Correto!</strong><br>';
                echo 'Total de permissões: <strong>' . $permCount . '</strong>';
                echo '</div>';
                
                // Mostrar algumas permissões
                echo '<h4>Primeiras 20 permissões:</h4>';
                echo '<div class="row">';
                $count = 0;
                foreach ($unserialized['access'] as $key => $val) {
                    if ($count++ >= 20) break;
                    echo '<div class="col-md-6">';
                    echo '<code>' . htmlspecialchars($key) . '</code> = ' . htmlspecialchars($val);
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<div class="alert alert-danger">';
                echo '<strong>❌ Formato Incorreto!</strong><br>';
                echo 'As permissões não estão no formato esperado pelo ModernPOS.';
                echo '</div>';
            }
        }
        ?>
      </div>
    </div>

    <div class="box box-warning">
      <div class="box-header with-border">
        <h3 class="box-title">🧪 Teste de has_permission()</h3>
      </div>
      <div class="box-body">
        <?php
        $testPermissions = [
            'read_dashboard' => 'Dashboard',
            'read_customer' => 'Clientes',
            'read_product' => 'Produtos',
            'read_selling' => 'Vendas',
            'read_pos' => 'PDV',
            'read_user' => 'Usuários',
            'read_settings' => 'Configurações',
            'create_customer' => 'Criar Cliente',
            'update_customer' => 'Editar Cliente',
            'delete_customer' => 'Deletar Cliente',
            'read_supplier' => 'Fornecedores',
            'read_purchase' => 'Compras',
            'read_stock' => 'Estoque',
            'read_report' => 'Relatórios',
        ];
        
        echo '<table class="table table-bordered table-striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Permissão</th>';
        echo '<th>Descrição</th>';
        echo '<th width="15%" class="text-center">Resultado</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $allowed = 0;
        $blocked = 0;
        
        foreach ($testPermissions as $perm => $desc) {
            $result = has_permission('access', $perm);
            
            if ($result) {
                $allowed++;
                $badge = '<span class="label label-success"><i class="fa fa-check"></i> PERMITE</span>';
            } else {
                $blocked++;
                $badge = '<span class="label label-danger"><i class="fa fa-times"></i> BLOQUEIA</span>';
            }
            
            echo '<tr>';
            echo '<td><code>' . htmlspecialchars($perm) . '</code></td>';
            echo '<td>' . htmlspecialchars($desc) . '</td>';
            echo '<td class="text-center">' . $badge . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '<tfoot>';
        echo '<tr class="bg-gray">';
        echo '<td colspan="2"><strong>RESUMO:</strong></td>';
        echo '<td class="text-center">';
        echo '<span class="label label-success">Permitidas: ' . $allowed . '</span> ';
        echo '<span class="label label-danger">Bloqueadas: ' . $blocked . '</span>';
        echo '</td>';
        echo '</tr>';
        echo '</tfoot>';
        echo '</table>';
        ?>
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title">📋 Conclusão</h3>
      </div>
      <div class="box-body">
        <?php
        if (user_group_id() == 1) {
            echo '<div class="alert alert-info">';
            echo '<i class="fa fa-info-circle"></i> <strong>Você está usando o Grupo Admin (ID 1)</strong><br>';
            echo 'Este grupo tem privilégios especiais e sempre terá acesso total.';
            echo '</div>';
        } else {
            if ($blocked > 0) {
                echo '<div class="alert alert-danger">';
                echo '<i class="fa fa-exclamation-triangle"></i> <strong>PROBLEMA DETECTADO!</strong><br>';
                echo 'Você está usando o Grupo ID <strong>' . user_group_id() . '</strong>, mas <strong>' . $blocked . '</strong> permissões estão bloqueadas.<br>';
                echo '<br><strong>Possíveis causas:</strong>';
                echo '<ol>';
                echo '<li>As permissões não foram salvas corretamente no grupo</li>';
                echo '<li>O formato das permissões está incorreto</li>';
                echo '<li>Você precisa fazer LOGOUT e LOGIN novamente</li>';
                echo '<li>O cache de sessão está desatualizado</li>';
                echo '</ol>';
                echo '</div>';
            } else {
                echo '<div class="alert alert-success">';
                echo '<i class="fa fa-check-circle"></i> <strong>TUDO FUNCIONANDO!</strong><br>';
                echo 'Todas as permissões testadas estão permitidas para o seu grupo.';
                echo '</div>';
            }
        }
        ?>
        
        <div class="alert alert-warning">
          <strong>⚠️ IMPORTANTE:</strong>
          <p>Se você acabou de fazer alterações nas permissões do grupo:</p>
          <ol>
            <li>Faça <strong>LOGOUT COMPLETO</strong></li>
            <li>Feche <strong>TODOS OS NAVEGADORES</strong></li>
            <li>Limpe cache e cookies</li>
            <li>Abra em <strong>MODO ANÔNIMO</strong></li>
            <li>Faça login novamente</li>
          </ol>
        </div>
      </div>
    </div>

  </section>
</div>

<?php include("footer.php"); ?>
