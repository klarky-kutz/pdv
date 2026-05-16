<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '_init.php';

if (!$user->isLogged()) {
    die("❌ Não está logado");
}

echo "<h1>🔍 Rastreamento de Acesso</h1>";
echo "<style>body{font-family:monospace;padding:20px;} .ok{color:green;} .error{color:red;}</style>";

$userId = user_id();
$groupId = user_group_id();

echo "<h2>Informações Básicas</h2>";
echo "User ID: $userId<br>";
echo "Group ID: $groupId<br>";
echo "Sessão tenant_id: " . (isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : 'N/A') . "<br><br>";

// Verificar permissões específicas
$permissions = [
    'account.view_overview',
    'account.view_stores',
    'account.view_users',
    'account.view_plans',
    'account.view_reports',
    'switch_store',
];

echo "<h2>Teste de Permissões has_permission()</h2>";
foreach ($permissions as $perm) {
    $result = has_permission('access', $perm);
    $status = $result ? '<span class="ok">✅ TRUE</span>' : '<span class="error">❌ FALSE</span>';
    echo "$perm: $status<br>";
}

echo "<h2>Teste can_access_account_section()</h2>";
require_once 'account/includes/account_access.php';

$sections = ['overview', 'stores', 'users', 'plans', 'reports'];
foreach ($sections as $sec) {
    $result = can_access_account_section($sec);
    $status = $result ? '<span class="ok">✅ TRUE</span>' : '<span class="error">❌ FALSE</span>';
    echo "$sec: $status<br>";
}

echo "<h2>Simulação de Acesso à Página</h2>";

// Simular o que store_select.php faz
echo "<strong>1. Verificação store_select.php (linha 46):</strong><br>";
$canAccessPanel = ($groupId == 1 || has_permission('access', 'account.view_overview'));
echo "group_id == 1 || has_permission('access', 'account.view_overview'): ";
echo $canAccessPanel ? '<span class="ok">✅ TRUE (PODE ACESSAR PAINEL)</span>' : '<span class="error">❌ FALSE (BLOQUEADO)</span>';
echo "<br><br>";

if (!$canAccessPanel) {
    echo '<span class="error">❌ BLOQUEIO ENCONTRADO: A verificação no store_select.php está bloqueando!</span><br>';
    echo "Solução: Adicionar permissão 'account.view_overview' ao grupo $groupId<br>";
}

// Simular o que store_select_admin.php faz
echo "<strong>2. Verificação store_select_admin.php (linha 470):</strong><br>";
$section = 'users';
$canAccessSection = can_access_account_section($section);
echo "can_access_account_section('users'): ";
echo $canAccessSection ? '<span class="ok">✅ TRUE (PODE ACESSAR SEÇÃO)</span>' : '<span class="error">❌ FALSE (BLOQUEADO)</span>';
echo "<br><br>";

// Verificar RBAC diretamente no banco
echo "<h2>Verificação RBAC (Banco de Dados)</h2>";
$stmt = db()->prepare("SELECT name, permission FROM user_group WHERE group_id = ?");
$stmt->execute([$groupId]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Grupo: {$group['name']}<br>";

$perms = @unserialize($group['permission']);
if (is_array($perms) && isset($perms['access'])) {
    $checkPerms = ['account.view_overview', 'account.view_users', 'switch_store'];
    foreach ($checkPerms as $p) {
        $has = isset($perms['access'][$p]) ? $perms['access'][$p] : 'NÃO EXISTE';
        echo "$p: $has<br>";
    }
} else {
    echo '<span class="error">❌ ERRO: Não foi possível decodificar as permissões!</span><br>';
}

echo "<hr>";
echo "<h2>Conclusão</h2>";
if ($canAccessPanel && $canAccessSection) {
    echo '<span class="ok">✅ TODAS as verificações passaram. O acesso deveria estar LIBERADO.</span><br>';
    echo "Se ainda está bloqueado, o problema pode ser:<br>";
    echo "1. Cache do navegador (Ctrl+Shift+R para recarregar forçado)<br>";
    echo "2. Outro arquivo fazendo verificação adicional<br>";
    echo "3. JavaScript bloqueando a interface<br>";
} else {
    echo '<span class="error">❌ BLOQUEIO IDENTIFICADO nas verificações acima.</span><br>';
}
?>
