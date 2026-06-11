# Migração de Tabelas de Vendas - ModernPOS SaaS

## 📋 Visão Geral

Este pacote contém scripts para migrar as tabelas de vendas do ModernPOS para suportar arquitetura SaaS multi-tenant. A migração adiciona a coluna `tenant_id` nas seguintes tabelas:

- **selling_info** - Informações de vendas
- **selling_item** - Itens vendidos
- **customers** - Clientes
- **products** - Produtos

## 🎯 O que a Migração Faz

### 1. Adiciona coluna `tenant_id`
- Insere a coluna `tenant_id` em todas as tabelas
- Cria índices para otimizar consultas multi-tenant
- Define `tenant_id = 1` para todos os registros existentes

### 2. Preserva todos os dados
- **NENHUM DADO É PERDIDO**
- Todos os registros existentes são mantidos
- Backup automático antes da execução

### 3. Melhora performance
- Adiciona índices compostos para queries eficientes
- Otimiza consultas por tenant

## 📁 Arquivos Incluídos

```
migrations/
├── README_MIGRATION.md                      # Este arquivo
├── migrate_sales_tables_add_tenant.sql     # Script SQL da migração
└── run_sales_migration.php                 # Script PHP automatizado
```

## 🚀 Como Executar a Migração

### Opção 1: Script PHP Automatizado (Recomendado)

O script PHP cria backup automático e verifica integridade dos dados.

#### 1. Configurar credenciais do banco
Edite o arquivo `run_sales_migration.php` (linhas 13-16):

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'modernpos');
```

#### 2. Executar via terminal
```bash
cd C:\xampp\htdocs\modernpos\migrations
php run_sales_migration.php
```

#### 3. Confirmar quando solicitado
```
Deseja continuar? (s/n): s
```

### Opção 2: SQL Manual

Se preferir executar manualmente:

#### 1. Criar backup primeiro
```bash
cd C:\xampp\mysql\bin
mysqldump -u root modernpos > C:\backup_modernpos_manual.sql
```

#### 2. Executar o SQL
- Abra phpMyAdmin (http://localhost/phpmyadmin)
- Selecione o banco `modernpos`
- Clique em "SQL"
- Abra e cole o conteúdo de `migrate_sales_tables_add_tenant.sql`
- Clique em "Executar"

## ✅ Verificação Pós-Migração

### Via SQL
Execute estas queries para verificar:

```sql
-- Verificar se coluna foi adicionada
SHOW COLUMNS FROM selling_info LIKE 'tenant_id';
SHOW COLUMNS FROM selling_item LIKE 'tenant_id';
SHOW COLUMNS FROM customers LIKE 'tenant_id';
SHOW COLUMNS FROM products LIKE 'tenant_id';

-- Verificar dados
SELECT COUNT(*) as total, COUNT(tenant_id) as com_tenant 
FROM selling_info;

SELECT COUNT(*) as total, COUNT(tenant_id) as com_tenant 
FROM selling_item;

SELECT COUNT(*) as total, COUNT(tenant_id) as com_tenant 
FROM customers;

SELECT COUNT(*) as total, COUNT(tenant_id) as com_tenant 
FROM products;
```

### O que esperar
Todas as queries devem retornar:
- `total` = número de registros existentes
- `com_tenant` = mesmo número (todos com tenant_id = 1)

## 🔄 Rollback (Em caso de problemas)

Se algo der errado, você pode restaurar o backup:

### Via linha de comando
```bash
cd C:\xampp\mysql\bin
mysql -u root modernpos < C:\xampp\htdocs\modernpos\backups\backup_modernpos_YYYYMMDD_HHMMSS.sql
```

### Via phpMyAdmin
1. Abra phpMyAdmin
2. Selecione o banco `modernpos`
3. Clique em "Importar"
4. Escolha o arquivo de backup
5. Clique em "Executar"

## 📊 Estatísticas da Migração

### Tabelas Afetadas
- `selling_info`: ~1.148 registros
- `selling_item`: ~milhares de itens
- `customers`: todos os clientes
- `products`: todos os produtos

### Tempo Estimado
- Pequeno banco (< 10k registros): ~5 segundos
- Médio banco (10k-100k registros): ~30 segundos
- Grande banco (> 100k registros): ~2 minutos

## ⚠️ Avisos Importantes

### ANTES de Executar

1. ✅ **FAÇA BACKUP!** 
   - O script PHP faz automaticamente
   - Mas tenha um backup manual também

2. ✅ **Teste em ambiente de desenvolvimento primeiro**
   - Se possível, teste com cópia do banco

3. ✅ **Garanta espaço em disco**
   - Backup precisa de espaço igual ao tamanho do banco

4. ✅ **Feche outras aplicações**
   - Evite uso do sistema durante migração

### DURANTE a Execução

- ⏸️ **NÃO feche o terminal**
- ⏸️ **NÃO desligue o servidor**
- ⏸️ **NÃO acesse o sistema**

### DEPOIS da Migração

1. ✅ Verifique integridade dos dados
2. ✅ Teste funcionalidades críticas:
   - Criar nova venda
   - Visualizar vendas antigas
   - Buscar clientes
   - Consultar produtos

## 🔧 Solução de Problemas

### Erro: "Table doesn't exist"
**Solução**: Verifique se o banco de dados está correto.

### Erro: "Duplicate column name"
**Solução**: A coluna já existe. Migração já foi executada.

### Erro: "Access denied"
**Solução**: Verifique credenciais do banco no script PHP.

### Backup não é criado
**Solução**: Verifique se `mysqldump` está no PATH do sistema.

### Script PHP não executa
**Solução**: 
```bash
# Windows
C:\xampp\php\php.exe run_sales_migration.php

# Ou adicione PHP ao PATH
```

## 📞 Suporte

Em caso de problemas:

1. **Restaure o backup imediatamente**
2. Verifique os logs de erro
3. Documente o erro exato
4. Entre em contato com suporte técnico

## 📝 Notas Técnicas

### Índices Criados

```sql
-- selling_info
INDEX idx_tenant_selling (tenant_id, store_id, created_at)

-- selling_item  
INDEX idx_tenant_selling_item (tenant_id, store_id, invoice_id)

-- customers
INDEX idx_tenant_customer (tenant_id, mobile)

-- products
INDEX idx_tenant_product (tenant_id, item_code)
```

### Chaves Estrangeiras (Opcional)

As chaves estrangeiras estão comentadas no SQL. Para ativá-las:
1. Certifique-se que a tabela `tenants` existe
2. Descomente as linhas no SQL
3. Execute novamente

## ✨ Próximos Passos

Após a migração bem-sucedida:

1. **Atualizar queries da aplicação**
   - Adicionar filtro `WHERE tenant_id = ?` nas consultas
   
2. **Implementar controle de acesso**
   - Garantir isolamento entre tenants
   
3. **Monitorar performance**
   - Verificar se índices estão sendo usados

## 📄 Licença

© 2026 ModernPOS SaaS. Todos os direitos reservados.

---

**Data de Criação**: 06/02/2026  
**Versão**: 1.0  
**Autor**: Sistema de Migração Automatizado
