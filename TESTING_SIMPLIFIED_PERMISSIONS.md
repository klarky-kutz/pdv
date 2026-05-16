# 🧪 Guia de Testes: Arquitetura Simplificada de Permissões

## 📋 Pré-requisitos
- ✅ Backup do banco: `backup_modernpos_20260129_161444.sql`
- ✅ Migração executada: `migration_simplify_permissions.sql`
- ✅ Coluna `is_owner` criada na tabela `users`
- ✅ Grupos antigos removidos (plan_*_owner)

---

## 🧪 Cenários de Teste

### Teste 1: Owner Herda Capabilities do Plano
**Objetivo:** Verificar que Owner tem acesso a TODAS as capabilities do plano sem precisar de grupo RBAC

**Passos:**
1. Fazer login como Owner (user_id = tenant_id)
2. Acessar `http://localhost/modernpos/conta/`
3. Tentar acessar cada seção:
   - `/conta/` (Overview)
   - `/conta/usuarios` (Usuários)
   - `/conta/planos` (Planos)
   - `/conta/relatorios` (Relatórios)

**Resultado Esperado:**
- ✅ Owner acessa todas as páginas do painel da conta
- ✅ Não aparece mensagem "Acesso Negado"

---

### Teste 2: Criar Grupo Customizado
**Objetivo:** Verificar que Owner pode criar grupos personalizados com permissões filtradas pelo plano

**Passos:**
1. Login como Owner
2. Acessar `/conta/usuarios?tab=permissoes`
3. Clicar em "Criar Novo Grupo"
4. Preencher:
   - Nome: "Gerente de Vendas"
   - Slug: deixar vazio (gerar automaticamente)
5. Selecionar permissões:
   - ✅ `sales.create`
   - ✅ `sales.view`
   - ✅ `products.view`
6. Clicar em "Criar Grupo"

**Resultado Esperado:**
- ✅ Modal abre corretamente
- ✅ Apenas permissões do plano aparecem na lista
- ✅ Grupo criado com sucesso
- ✅ Aparece na lista de grupos com "0 usuário(s)"

**Validação no Banco:**
```sql
SELECT group_id, tenant_id, name, slug, permission 
FROM user_group 
WHERE slug = 'gerente_de_vendas';
```

---

### Teste 3: Permissões Filtradas por Plano
**Objetivo:** Verificar que permissões NÃO disponíveis no plano ficam ocultas

**Passos:**
1. Login como Owner de um tenant com **Plano Básico** (sem `reports.financial`)
2. Clicar em "Criar Novo Grupo"
3. Procurar pela permissão `reports.financial`

**Resultado Esperado:**
- ✅ Permissão `reports.financial` **NÃO aparece** na lista
- ✅ Apenas permissões do Plano Básico aparecem

---

### Teste 4: Plano Permissivo (*)
**Objetivo:** Verificar que planos com `["*"]` liberam todas as permissões

**Passos:**
1. Login como Owner de um tenant com **Plano Pro** (features_json = `["*"]`)
2. Clicar em "Criar Novo Grupo"
3. Verificar permissões disponíveis

**Resultado Esperado:**
- ✅ Alerta mostra "Todas liberadas"
- ✅ Todas as categorias de permissões aparecem
- ✅ Total de permissões disponíveis = 37+ permissões

---

### Teste 5: Validação de Permissão Inexistente
**Objetivo:** Verificar que API bloqueia criação de grupo com permissão não permitida no plano

**Passos:**
1. Usar Postman ou curl para enviar request direta à API
2. Tentar criar grupo com permissão que NÃO está no plano:

```bash
curl -X POST http://localhost/modernpos/api/groups/create.php \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Teste Hack",
    "slug": "teste_hack",
    "permissions": {
      "access": {
        "admin.full_access": true
      }
    }
  }'
```

**Resultado Esperado:**
- ❌ HTTP 403 Forbidden
- ❌ Mensagem: "Permissão 'admin.full_access' não está disponível no seu plano"

---

### Teste 6: Usuário Não-Owner Tentando Criar Grupo
**Objetivo:** Verificar que apenas Owners podem criar grupos

**Passos:**
1. Criar usuário comum (não-owner) no tenant
2. Fazer login como este usuário
3. Acessar `/conta/usuarios?tab=permissoes`

**Resultado Esperado:**
- ❌ Botão "Criar Novo Grupo" não aparece ou está desabilitado
- ❌ Se tentar chamar API diretamente: HTTP 403 "Apenas Owners podem criar grupos"

---

### Teste 7: Verificar is_tenant_owner()
**Objetivo:** Testar função helper `is_tenant_owner()`

**Passos:**
1. Criar arquivo de teste `test_is_owner.php`:

```php
<?php
session_start();
require_once '_init.php';

if (!$user->isLogged()) {
    die('Faça login primeiro');
}

$userId = user_id();
$isOwner = is_tenant_owner($userId);

echo "<h1>Teste is_tenant_owner()</h1>";
echo "User ID: $userId<br>";
echo "Is Owner: " . ($isOwner ? 'TRUE ✅' : 'FALSE ❌') . "<br>";

// Verificar no banco
$stmt = db()->prepare("SELECT id, username, tenant_id, is_owner FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($userData);
echo "</pre>";
?>
```

2. Acessar `http://localhost/modernpos/test_is_owner.php`

**Resultado Esperado (Owner):**
- ✅ Is Owner: TRUE
- ✅ `is_owner = 1` OU `user_id == tenant_id`

**Resultado Esperado (Não-Owner):**
- ❌ Is Owner: FALSE
- ❌ `is_owner = 0` E `user_id != tenant_id`

---

### Teste 8: has_permission() Após Simplificação
**Objetivo:** Verificar que nova lógica de permissões funciona corretamente

**Passos:**
1. Login como Owner
2. Testar permissão presente no plano:
   ```php
   var_dump(has_permission('access', 'sales.create'));
   ```
3. Testar permissão NÃO presente no plano:
   ```php
   var_dump(has_permission('access', 'inexistente.permissao'));
   ```

**Resultado Esperado:**
- ✅ Permissão do plano: `TRUE`
- ❌ Permissão inexistente: `FALSE`

---

### Teste 9: Verificar Remoção de Grupos Antigos
**Objetivo:** Confirmar que grupos automáticos foram removidos

**Passos:**
```sql
-- Grupos que devem ter sido removidos
SELECT * FROM user_group WHERE slug LIKE 'plan_%';

-- Grupos que devem existir
SELECT * FROM user_group WHERE group_id = 1 OR tenant_id IS NOT NULL;

-- Backup criado
SELECT COUNT(*) as total FROM user_group_backup_20260129;
```

**Resultado Esperado:**
- ✅ 0 grupos com `slug LIKE 'plan_%'`
- ✅ Apenas Admin (group_id = 1) e grupos customizados do tenant
- ✅ Backup contém 3 registros (plan_1_owner, plan_2_owner, plan_3_owner removidos)

---

### Teste 10: Painel SaaS Não Cria Mais Grupos
**Objetivo:** Verificar que criar/editar plano no SaaS não cria grupos automaticamente

**Passos:**
1. Acessar `http://localhost/saas/painel/index.php?pagina=adicionar`
2. Criar novo plano "Plano Teste"
3. Selecionar algumas capabilities
4. Salvar

**Validação no Banco:**
```sql
-- Verificar que nenhum grupo foi criado para o plano novo
SELECT * FROM user_group WHERE name LIKE '%Plano Teste%';
```

**Resultado Esperado:**
- ✅ Plano criado com sucesso
- ✅ Mensagem: "Plano criado. Grupos serão criados pelos Owners."
- ✅ **Nenhum grupo** foi criado automaticamente

---

## 🐛 Problemas Conhecidos e Soluções

### Problema: Modal não abre
**Causa:** Bootstrap JS não carregado  
**Solução:** Verificar se `bootstrap.min.js` está incluído no HTML

### Problema: API retorna 401
**Causa:** Sessão expirada  
**Solução:** Fazer logout e login novamente

### Problema: Permissões não aparecem
**Causa:** `SaasLimitsBridge` não encontrado  
**Solução:** Verificar caminho em `require_once '../../saas/includes/SaasLimitsBridge.php'`

---

## ✅ Checklist Final

Após executar todos os testes, confirme:

- [ ] Owner acessa todas as páginas `/conta/*`
- [ ] Modal de criar grupo funciona
- [ ] Permissões são filtradas pelo plano
- [ ] API valida permissões corretamente
- [ ] Apenas Owners podem criar grupos
- [ ] `is_tenant_owner()` funciona
- [ ] `has_permission()` simplificado funciona
- [ ] Grupos antigos foram removidos
- [ ] Painel SaaS não cria grupos automaticamente
- [ ] Backup do banco está seguro

---

## 🔄 Rollback (se necessário)

Para reverter todas as mudanças:

```sql
-- 1. Restaurar coluna is_owner
ALTER TABLE users DROP COLUMN is_owner;

-- 2. Restaurar grupos antigos
INSERT INTO user_group SELECT * FROM user_group_backup_20260129;

-- 3. Remover backup
DROP TABLE user_group_backup_20260129;
```

Ou restaurar backup completo:
```bash
Get-Content "C:\xampp\htdocs\backup_modernpos_20260129_161444.sql" | C:\xampp\mysql\bin\mysql.exe -u root modernpos
```

---

## 📊 Métricas de Sucesso

- ✅ **Redução de Complexidade:** De 3 camadas para 1 (Capabilities ∩ Roles)
- ✅ **Grupos Automáticos:** 0 (antes eram 3 por plano)
- ✅ **Tempo para Criar Grupo:** <30 segundos
- ✅ **Permissões Filtradas:** 100% alinhadas com o plano
- ✅ **Owners:** Acesso total às capabilities do plano

---

**Data dos Testes:** `______/______/______`  
**Testado por:** `______________________`  
**Status:** ⬜ Aprovado | ⬜ Reprovado | ⬜ Parcial
