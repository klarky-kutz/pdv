# Sistema de Dados de Exemplo Globais

## 🎯 Problema Resolvido

**Antes:** Cada loja nova criava seus próprios registros de exemplo, gerando lixo no banco de dados (registros órfãos sem `tenant_id`).

**Agora:** Dados de exemplo são criados UMA VEZ como registros globais e apenas VINCULADOS às novas lojas.

## ✅ Vantagens

1. **Sem Lixo no Banco**: Nenhum registro órfão é criado
2. **IDs Consistentes**: Todos os exemplos têm sempre os mesmos IDs
3. **Performance**: Apenas INSERTs nas tabelas `*_to_store` (muito mais rápido)
4. **Manutenção Fácil**: Atualizar dados globais afeta todas as lojas novas
5. **Reutilização**: Mesmos dados podem ser vinculados a múltiplas lojas

## 📋 Como Funciona

### 1️⃣ Inicialização (Uma Vez)

Acesse: `http://localhost/modernpos/_inc/initialize_global_sample_data.php`

Este script cria os seguintes registros GLOBAIS:

| Tipo | Nome | Identificação |
|------|------|---------------|
| Categoria | [Global] Eletrônicos | Prefixo [Global] |
| Fornecedor | [Global] Fornecedor Exemplo LTDA | Prefixo [Global] |
| Marca | [Global] Marca Exemplo | Prefixo [Global] |
| Conta Bancária | [Global] Conta Corrente Exemplo | Prefixo [Global] |

**Importante:** O prefixo `[Global]` identifica que são dados de exemplo globais.

### 2️⃣ Criação Automática de Lojas

Quando uma nova loja é criada:

1. Sistema busca os dados globais pelo prefixo `[Global]`
2. Cria vínculos nas tabelas:
   - `category_to_store`
   - `supplier_to_store`
   - `brand_to_store`
   - `bank_account_to_store`
   - `pos_template_to_store` (templates)
3. Dados aparecem imediatamente na nova loja

**Nenhum novo registro é criado nas tabelas principais!**

## 🗄️ Estrutura do Banco

### Tabelas Principais (Registros Globais)
```
categorys
  ├─ ccategory_id: X  ← Criado uma vez
  └─ category_name: "[Global] Eletrônicos"

suppliers
  ├─ sup_id: Y  ← Criado uma vez
  └─ sup_name: "[Global] Fornecedor Exemplo LTDA"

brands
  ├─ brand_id: Z  ← Criado uma vez
  └─ brand_name: "[Global] Marca Exemplo"

bank_accounts
  ├─ id: W  ← Criado uma vez
  └─ account_name: "[Global] Conta Corrente Exemplo"
```

### Tabelas de Vínculo (Um por Loja)
```
category_to_store
  ├─ ccategory_id: X  ← Referência ao global
  └─ store_id: 1, 2, 3...  ← Cada loja tem seu vínculo

supplier_to_store
  ├─ sup_id: Y  ← Referência ao global
  ├─ store_id: 1, 2, 3...
  ├─ status: 1
  └─ sort_order: 0

brand_to_store
  ├─ brand_id: Z  ← Referência ao global
  ├─ store_id: 1, 2, 3...
  ├─ status: 1
  └─ sort_order: 0

bank_account_to_store
  ├─ account_id: W  ← Referência ao global
  ├─ store_id: 1, 2, 3...
  ├─ status: 1
  └─ sort_order: 0
```

## 🔧 Arquivos Modificados

### Novos Arquivos
- `_inc/initialize_global_sample_data.php` - Script de inicialização
- `_inc/config/global_sample_data_ids.php` - IDs dos dados globais (auto-gerado)

### Arquivos Modificados
- `_inc/helper/sample_data.php`
  - Nova função: `link_global_sample_data_to_store()`
  - Modificada: `create_sample_data_for_store()` - agora vincula ao invés de criar
  - Mantidas (não usadas): `create_sample_category()`, `create_sample_supplier()`, etc.

- `_inc/model/store.php`
  - Linhas 24-39: Adicionado logging de debug
  - Chamada automática de `create_sample_data_for_store()` ao criar loja

## 📝 Passo a Passo de Uso

### Primeira Vez (Instalação)

1. **Inicializar dados globais:**
   ```
   http://localhost/modernpos/_inc/initialize_global_sample_data.php
   ```
   
2. **Clicar em "✨ Criar Dados Globais Agora"**

3. **Verificar criação:**
   - Categoria: [Global] Eletrônicos
   - Fornecedor: [Global] Fornecedor Exemplo LTDA
   - Marca: [Global] Marca Exemplo  
   - Conta Bancária: [Global] Conta Corrente Exemplo

### Uso Normal

1. **Criar nova loja** (pelo painel admin ou checkout)

2. **Sistema automaticamente:**
   - Vincula templates globais
   - Vincula categoria global
   - Vincula fornecedor global
   - Vincula marca global
   - Vincula conta bancária global

3. **Verificar na nova loja:**
   - Categorias: deve mostrar "[Global] Eletrônicos"
   - Fornecedores: deve mostrar "[Global] Fornecedor Exemplo LTDA"
   - Marcas: deve mostrar "[Global] Marca Exemplo"
   - Contas Bancárias: deve mostrar "[Global] Conta Corrente Exemplo"

## 🧹 Limpeza do Banco

### Remover Dados Órfãos Antigos

Se você tinha dados de exemplo criados pela abordagem antiga:

```
http://localhost/modernpos/_inc/cleanup_orphan_records.php
```

Este script remove:
- Categorias sem vínculo
- Fornecedores sem vínculo
- Marcas sem vínculo
- Produtos sem vínculo
- Contas bancárias sem vínculo
- Templates sem vínculo

**Importante:** NÃO remove os dados globais com prefixo `[Global]`!

## 🔍 Verificação e Debug

### Testar Vinculação Manual
```
http://localhost/modernpos/_inc/test_sample_data.php?store_id=123
```

### Logs de Debug

Verifique o `error_log` do PHP para mensagens:
```
[SAMPLE_DATA_DEBUG] Checking if create_sample_data_for_store exists...
[SAMPLE_DATA_DEBUG] Function exists, calling for store ID: 123
[SAMPLE_DATA_DEBUG] Function returned: true
Sample data created successfully for store ID: 123
```

### Consultas SQL para Verificar

```sql
-- Ver dados globais criados
SELECT * FROM categorys WHERE category_name LIKE '[Global]%';
SELECT * FROM suppliers WHERE sup_name LIKE '[Global]%';
SELECT * FROM brands WHERE brand_name LIKE '[Global]%';
SELECT * FROM bank_accounts WHERE account_name LIKE '[Global]%';

-- Ver vínculos de uma loja específica
SELECT * FROM category_to_store WHERE store_id = 123;
SELECT * FROM supplier_to_store WHERE store_id = 123;
SELECT * FROM brand_to_store WHERE store_id = 123;
SELECT * FROM bank_account_to_store WHERE store_id = 123;

-- Contar registros órfãos (deve ser 0)
SELECT COUNT(*) FROM categorys c 
LEFT JOIN category_to_store cts ON c.ccategory_id = cts.ccategory_id 
WHERE cts.ccategory_id IS NULL 
AND c.category_name NOT LIKE '[Global]%';
```

## ⚙️ Configuração

### Personalizar Dados Globais

Para alterar os dados de exemplo padrão, edite:
`_inc/initialize_global_sample_data.php`

Linhas 136-199 contêm os dados que serão criados.

### Desabilitar Dados de Exemplo

Para desabilitar completamente, comente em `_inc/model/store.php`:
```php
// if (function_exists('create_sample_data_for_store')) {
//     ...
// }
```

## 🚀 Migração de Sistema Antigo

Se você já tem lojas criadas com a abordagem antiga:

1. **Execute inicialização dos dados globais**
2. **Execute limpeza de órfãos** (remove duplicatas antigas)
3. **Novas lojas usarão automaticamente dados globais**
4. **Lojas antigas mantêm seus dados** (não são afetadas)

## ❓ FAQ

**P: Os dados globais podem ser editados?**
R: Sim, mas isso afetará todas as lojas que compartilham esses dados. Recomendamos que os usuários criem suas próprias categorias/fornecedores/marcas personalizadas.

**P: E se eu deletar um dado global?**
R: Os vínculos nas tabelas `*_to_store` ficarão órfãos. Use o script de limpeza para removê-los.

**P: Como identifico dados globais?**
R: Pelo prefixo `[Global]` no nome.

**P: Posso ter múltiplos conjuntos de dados globais?**
R: Sim, basta criar com prefixos diferentes e modificar a função `link_global_sample_data_to_store()`.

**P: E os templates de recibo?**
R: Templates sem "(Personalizado)" no nome são considerados globais e automaticamente vinculados.

## 📊 Comparação: Antes vs Depois

### ANTES (Problemático)
```
Criar Loja 1:
  → INSERT categorys (1 registro órfão)
  → INSERT category_to_store
  → INSERT suppliers (1 registro órfão)
  → INSERT supplier_to_store
  ... etc
  Total: 8 INSERTs (4 órfãos + 4 vínculos)

Criar Loja 2:
  → INSERT categorys (2 registro órfão)
  → INSERT category_to_store
  ... etc
  Total: 8 INSERTs (4 órfãos + 4 vínculos)

Resultado: 8 registros órfãos no banco!
```

### DEPOIS (Otimizado)
```
Inicialização (uma vez):
  → INSERT categorys (1 registro global)
  → INSERT suppliers (1 registro global)
  → INSERT brands (1 registro global)
  → INSERT bank_accounts (1 registro global)
  Total: 4 INSERTs (uma vez apenas)

Criar Loja 1:
  → INSERT category_to_store
  → INSERT supplier_to_store
  → INSERT brand_to_store
  → INSERT bank_account_to_store
  Total: 4 INSERTs (só vínculos)

Criar Loja 2:
  → 4 INSERTs (só vínculos)

Resultado: 0 registros órfãos! 🎉
```

## 🎓 Conclusão

A nova abordagem resolve completamente o problema de registros órfãos, mantém o banco limpo, melhora a performance e facilita a manutenção do sistema.

Todos os dados de exemplo são reutilizados, não duplicados!
