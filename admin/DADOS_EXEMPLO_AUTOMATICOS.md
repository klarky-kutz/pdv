# Dados de Exemplo Automáticos

## O que foi implementado

Quando uma nova loja é criada no ModernPOS, os seguintes dados de exemplo são criados automaticamente:

### ✅ Implementado e Funcionando

1. **Templates de Recibo Globais**
   - Todos os templates que NÃO contêm "(Personalizado)" no nome são automaticamente vinculados à nova loja
   - O primeiro template é definido como ativo por padrão
   - Aparece em `select_receipt_template.php` imediatamente

2. **Categoria de Exemplo**
   - Nome: "Exemplo - Eletrônicos"
   - Slug: "exemplo-eletronicos"
   - Descrição: "Categoria de exemplo - Produtos eletrônicos diversos"
   - Vinculada à loja via `category_to_store`

3. **Fornecedor de Exemplo**
   - Nome: "Fornecedor Exemplo LTDA"
   - Código: "fornecedor-exemplo"
   - Telefone: "(11) 98765-4321"
   - Email: "contato@fornecedorexemplo.com.br"
   - Endereço: "Rua Exemplo, 123 - São Paulo, SP"
   - Vinculado à loja via `supplier_to_store`

4. **Marca de Exemplo**
   - Nome: "Marca Exemplo"
   - Código: "marca-exemplo"
   - Descrição: "Marca de exemplo para demonstração"
   - Vinculada à loja via `brand_to_store`

5. **Conta Bancária de Exemplo**
   - Nome: "Conta Corrente Exemplo"
   - Número: "12345-6"
   - Descrição: "Conta bancária de exemplo para demonstração"
   - Contato: "Responsável Financeiro"
   - Telefone: "(11) 98765-4321"
   - Saldo inicial: R$ 0,00
   - Vinculada à loja via `bank_account_to_store`

### ❌ NÃO Implementado

**Produtos de Exemplo** - Não foram implementados devido à complexidade da arquitetura multi-tenant:
- Produtos requerem IDs válidos de:
  - Unidade (unit_id)
  - Caixa/Embalagem (box_id)
  - Taxa de Imposto (taxrate_id)
- Também requerem método de imposto e preferências
- O usuário deve criar produtos manualmente após configurar unidades e impostos

## Arquitetura Multi-Tenant

O ModernPOS usa uma arquitetura complexa onde:

### Tabelas Principais (Dados Globais)
- `categorys` - Categorias
- `suppliers` - Fornecedores  
- `brands` - Marcas
- `products` - Produtos
- `bank_accounts` - Contas bancárias
- `pos_receipt_template` - Templates de recibo

### Tabelas de Vínculo (Relacionamento Loja-Dados)
- `category_to_store` - Liga categorias às lojas
- `supplier_to_store` - Liga fornecedores às lojas
- `brand_to_store` - Liga marcas às lojas
- `product_to_store` - Liga produtos às lojas (contém preço, estoque, etc)
- `bank_account_to_store` - Liga contas bancárias às lojas
- `pos_template_to_store` - Liga templates às lojas

Cada tabela de vínculo contém:
- ID do item global
- ID da loja
- Status (ativo/inativo)
- Sort order (ordem de exibição)

## Arquivos Modificados

### Criação e Helper
- `_inc/helper/sample_data.php` - Funções de criação de dados de exemplo
- `_inc/model/store.php` (linhas 24-32) - Chama criação automática ao criar loja

### Testes
- `_inc/test_sample_data.php` - Teste via navegador com `?store_id=X`
- `_inc/test_sample_cli.php` - Teste via CLI (não funciona devido a limitações do _init.php)

## Como Testar

### Teste Manual (Recomendado)
1. Criar uma nova loja pelo painel SAAS
2. Acessar a nova loja
3. Verificar:
   - Selecionar template de recibo: deve mostrar templates globais
   - Categorias: deve ter "Exemplo - Eletrônicos"
   - Fornecedores: deve ter "Fornecedor Exemplo LTDA"
   - Marcas: deve ter "Marca Exemplo"
   - Contas Bancárias: deve ter "Conta Corrente Exemplo" com saldo R$ 0,00

### Teste Via Script
Acesse pelo navegador:
```
http://localhost/modernpos/_inc/test_sample_data.php?store_id=X
```
Substituindo X pelo ID da loja

## Logs de Erro

Erros são registrados em `error_log` do PHP:
- "Sample data created successfully for store ID: X"
- "Failed to create sample data for store ID X: [mensagem]"

## Notas Importantes

1. **Templates Globais**: Apenas templates SEM "(Personalizado)" no nome são vinculados
2. **Painel SAAS**: `saas/painel/paginas/modernpos-receipts.php` também filtra para mostrar apenas templates globais
3. **Saldo Zero**: Conta bancária sempre começa com R$ 0,00 (não R$ 5.000)
4. **Produtos**: Usuário deve criar manualmente após configurar unidades e impostos
5. **Dados Editáveis**: Todos os dados de exemplo podem ser editados ou excluídos pelo usuário
