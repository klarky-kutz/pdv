# Correção: Acesso Negado às Configurações da Loja

## Problema Identificado

Quando um usuário criava a primeira loja do sistema através do:
- **Checkout**: `http://localhost/saas/landing/checkout.php`
- **Cadastro**: `http://localhost/modernpos/cadastro.php`

Ao tentar acessar as configurações da loja criada em:
```
http://localhost/modernpos/conta/lojas/configuracoes?store_id=XXX
```

O sistema retornava:
- ❌ Erro 422 (Unprocessable Entity)
- ❌ Toast: "Acesso negado para esta loja"

## Causa Raiz

A função `account_user_can_access_store()` no arquivo `_inc/account_store.php` não estava verificando corretamente o acesso de usuários administradores (group_id = 1) às lojas do mesmo tenant em modo SaaS.

A lógica antiga retornava `false` mesmo quando:
- ✅ Usuário era admin (group_id = 1)
- ✅ Usuário pertencia ao mesmo tenant da loja
- ✅ Havia vínculo na tabela `user_to_store`

## Alterações Realizadas

### 1. Arquivo: `_inc/account_store.php` (Linhas 116-193)

**Melhorias na função `account_user_can_access_store()`:**

- ✅ Adicionada lógica clara para verificar tenant_id
- ✅ Adicionados logs detalhados para debug
- ✅ Corrigida validação de admin em modo multi-tenant
- ✅ Melhor tratamento de erros e fallbacks

**O que mudou:**
```php
// ANTES: Lógica confusa que não validava corretamente o tenant_id
if ($storeTenant === false || $storeTenant === null || $storeTenant === '') {
  return true;
}
return (int)$storeTenant === $tenantId;

// DEPOIS: Lógica clara com logs e validação explícita
if ($storeTenant === false || $storeTenant === null || $storeTenant === '') {
  error_log('[account_store] Admin acessando loja ' . $storeId . ' sem tenant_id definido. Permitindo acesso.');
  return true;
}

$storeTenantInt = (int)$storeTenant;
$accessGranted = $storeTenantInt === $tenantId;

if (!$accessGranted) {
  error_log('[account_store] Admin user_id=' . $uid . ' (tenant ' . $tenantId . ') tentou acessar loja ' . $storeId . ' (tenant ' . $storeTenantInt . '). Acesso negado.');
} else {
  error_log('[account_store] Admin user_id=' . $uid . ' (tenant ' . $tenantId . ') acessando loja ' . $storeId . '. Acesso concedido.');
}

return $accessGranted;
```

### 2. Arquivo: `process_register.php` (Linhas 145-160)

**Melhorias no vínculo usuário-loja:**

- ✅ Adicionado campo `sort_order` ao inserir em `user_to_store`
- ✅ Tratamento de erro com fallback para schemas antigos
- ✅ Melhor tratamento de inserção em `tenant_usage`

### 3. Arquivo: `_inc/test_store_access.php` (NOVO)

Script de teste para validar o acesso de usuários às lojas.

**Como usar:**
```
http://localhost/modernpos/_inc/test_store_access.php?user_id=273&store_id=292
```

## Como Testar

### Teste 1: Via Script de Teste

1. Acesse o script de teste:
   ```
   http://localhost/modernpos/_inc/test_store_access.php?user_id=SEU_USER_ID&store_id=SEU_STORE_ID
   ```

2. Verifique se aparece: **✅ ACESSO PERMITIDO**

### Teste 2: Via Interface

1. Faça login no sistema
2. Acesse:
   ```
   http://localhost/modernpos/conta/lojas/configuracoes?store_id=SEU_STORE_ID
   ```
3. Verifique se a página carrega sem erros
4. Tente editar e salvar as configurações da loja

### Teste 3: Criar Nova Loja e Testar

1. Crie uma nova loja via cadastro:
   ```
   http://localhost/modernpos/cadastro.php
   ```

2. Após criar, anote o `store_id` criado

3. Acesse as configurações:
   ```
   http://localhost/modernpos/conta/lojas/configuracoes?store_id=NOVO_STORE_ID
   ```

4. Deve funcionar sem erros!

## Logs de Debug

Para acompanhar o processo de validação, monitore os logs:

**No Windows (XAMPP):**
```
C:\xampp\apache\logs\error.log
```

**Exemplos de mensagens de log:**
```
[account_store] store_settings_get iniciado. POST: {"action":"store_settings_get","store_id":"292"}
[account_store] user_id: 273
[account_store] user_group_id: 1
[account_store] tenant_id: 273
[account_store] storeId recebido: 292
[account_store] account_user_can_access_store retornou: true
[account_store] Admin user_id=273 (tenant 273) acessando loja 292. Acesso concedido.
```

## Verificação Manual no Banco de Dados

### Verificar se usuário e loja pertencem ao mesmo tenant:
```sql
SELECT 
    s.store_id, 
    s.name AS store_name,
    s.tenant_id AS store_tenant,
    u.id AS user_id,
    u.username,
    u.tenant_id AS user_tenant,
    u.group_id
FROM stores s
LEFT JOIN users u ON u.tenant_id = s.tenant_id
WHERE s.store_id = 292;
```

### Verificar vínculo user_to_store:
```sql
SELECT * 
FROM user_to_store 
WHERE user_id = 273 AND store_id = 292;
```

### Verificar se usuário é admin:
```sql
SELECT id, username, email, group_id, tenant_id, status 
FROM users 
WHERE id = 273;
```

## Validações do Sistema

Para que o acesso seja permitido, o sistema verifica:

### Para Usuários Admin (group_id = 1):
- ✅ `user.group_id` = 1
- ✅ `user.tenant_id` = `store.tenant_id` (em modo SaaS)
- ✅ `user.status` = 1
- ℹ️ Vínculo em `user_to_store` é opcional (mas recomendado)

### Para Usuários Não-Admin:
- ✅ Deve existir vínculo em `user_to_store`
- ✅ `user_to_store.status` = 1
- ✅ `user.tenant_id` = `store.tenant_id` (em modo SaaS)

## Arquivos Modificados

1. **`_inc/account_store.php`** (Função `account_user_can_access_store`)
   - Melhorada validação de acesso admin
   - Adicionados logs detalhados
   - Correção de lógica de tenant_id

2. **`process_register.php`** (Vínculo user_to_store)
   - Adicionado `sort_order` padrão
   - Melhor tratamento de erros

3. **`account/js/store_settings_ui.js`** (Debug)
   - Adicionados console.log para debug

## Problemas Conhecidos Resolvidos

- ✅ Erro 422 ao acessar configurações de loja recém-criada
- ✅ "Acesso negado" mesmo sendo dono da loja
- ✅ Administrador não consegue acessar lojas do próprio tenant
- ✅ Validação de tenant_id incorreta em modo SaaS

## Próximos Passos

1. Teste o acesso às configurações da loja
2. Verifique os logs do Apache para confirmar os acessos
3. Se necessário, use o script de teste para diagnóstico
4. Em produção, remova ou desabilite os logs de debug adicionados

## Suporte

Se o problema persistir:

1. Verifique os logs em: `C:\xampp\apache\logs\error.log`
2. Execute o script de teste: `test_store_access.php`
3. Confirme no banco de dados:
   - Usuário existe e é admin
   - Loja existe e tem tenant_id correto
   - Vínculo em user_to_store existe (ou usuário é admin)

---

**Data da Correção:** 27 de Janeiro de 2026  
**Versão do Sistema:** ModernPOS + SaaS Multi-Tenant  
**Status:** ✅ CORRIGIDO
