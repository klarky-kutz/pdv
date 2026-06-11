# PROMPT: Sistema de Dados de Exemplo Automáticos no ModernPOS

## Contexto do Sistema

**Sistema**: ModernPOS - Multi-tenant SaaS PDV (Ponto de Venda)
**Funcionalidade**: Criação automática de dados de exemplo quando uma nova loja é criada
**Objetivo**: Nova loja já vem com produtos, categorias, marcas e fornecedores cadastrados para o usuário começar a vender imediatamente

---

## 1. VISÃO GERAL

### O que são "Dados de Exemplo"?
São registros de demonstração criados automaticamente quando:
1. Um novo usuário se cadastra no sistema
2. Uma nova loja é criada pela modal de administração

### Por que isso é importante?
- ✅ **Onboarding rápido**: Usuário pode testar o sistema imediatamente
- ✅ **Aprendizado**: Vê exemplos reais de como cadastrar produtos
- ✅ **Demonstração**: Pode fazer vendas de teste sem precisar cadastrar nada
- ✅ **Experiência**: Interface não fica vazia, dá sensação de sistema "vivo"

### O que é criado automaticamente?
```
┌─────────────────────────────────────────────────────────┐
│         Dados Globais (compartilhados)                  │
├─────────────────────────────────────────────────────────┤
│ 1. Categoria: "Geral" ou "Produtos Gerais"            │
│ 2. Fornecedor: "Fornecedor Padrão"                    │
│ 3. Marca: "Sem Marca" ou "Genérico"                   │
│ 4. Conta Bancária: "Caixa Principal"                  │
│ 5. Templates de Recibo: Padrão 58mm e 80mm            │
└─────────────────────────────────────────────────────────┘
              ↓ (vinculados via *_to_store)
┌─────────────────────────────────────────────────────────┐
│      Dados Específicos da Loja (exclusivos)             │
├─────────────────────────────────────────────────────────┤
│ 1. Produto Demo 1: "Produto Exemplo A"                 │
│    - Preço: R$ 10,00                                   │
│    - Estoque: 100 unidades                             │
│    - Código de Barras: AUTO_GERADO                     │
│                                                         │
│ 2. Produto Demo 2: "Produto Exemplo B"                 │
│    - Preço: R$ 25,00                                   │
│    - Estoque: 50 unidades                              │
│    - Código de Barras: AUTO_GERADO                     │
└─────────────────────────────────────────────────────────┘
```

---

## 2. ARQUITETURA DO SISTEMA

### 2.1. Estrutura de Dados Globais vs Específicos

#### Dados Globais (Compartilhados)
**Características**:
- Criados UMA VEZ no banco de dados
- Compartilhados entre múltiplas lojas via tabelas `*_to_store`
- Exemplos: Categorias, Fornecedores, Marcas, Contas Bancárias

**Vantagens**:
- Economiza espaço no banco
- Dados consistentes entre lojas
- Facilita manutenção global

**Tabelas envolvidas**:
```
categorys (global)
    ↓ vinculada via
category_to_store (por loja)

suppliers (global)
    ↓ vinculada via
supplier_to_store (por loja)

brands (global)
    ↓ vinculada via
brand_to_store (por loja)

bank_accounts (global)
    ↓ vinculada via
bank_account_to_store (por loja)
```

#### Dados Específicos (Exclusivos)
**Características**:
- Criados PARA CADA LOJA individualmente
- Não compartilhados (cada loja tem os seus)
- Exemplos: Produtos demo

**Por que produtos são específicos?**
- Cada loja pode ter preços diferentes
- Estoque é individual por loja
- Permite personalização sem afetar outras lojas

**Tabelas envolvidas**:
```
products (específico - contém store_id)
    ↓ vinculada via
product_to_store (por loja)
```

---

## 3. FLUXO DE CRIAÇÃO

### Passo 1: Inicialização Global (uma única vez no sistema)
```
Script: modernpos/_inc/initialize_global_sample_data.php

┌────────────────────────────────────────────────┐
│  VERIFICAR se dados globais já existem         │
├────────────────────────────────────────────────┤
│ SELECT * FROM categorys WHERE is_sample = 1    │
│ Se JÁ EXISTE → Pula criação                    │
│ Se NÃO EXISTE → Cria:                          │
│                                                │
│ 1. INSERT INTO categorys                       │
│    (name, code_name, is_sample, status)       │
│    VALUES ('Geral', 'geral', 1, 1)            │
│                                                │
│ 2. INSERT INTO suppliers                       │
│    (name, mobile, email, is_sample, status)   │
│    VALUES ('Fornecedor Padrão', ...)          │
│                                                │
│ 3. INSERT INTO brands                          │
│    (name, code_name, is_sample, status)       │
│    VALUES ('Sem Marca', 'sem_marca', 1, 1)   │
│                                                │
│ 4. INSERT INTO bank_accounts                   │
│    (name, account_type, is_sample, status)    │
│    VALUES ('Caixa Principal', 'cash', 1, 1)   │
│                                                │
│ 5. INSERT INTO receipt_templates               │
│    (name, template_type, content, is_sample)  │
│    VALUES ('Padrão 58mm', '58mm', ...)        │
└────────────────────────────────────────────────┘
```

**IMPORTANTE**: Este script deve rodar:
- ✅ Durante instalação do sistema
- ✅ Antes do primeiro cadastro de usuário
- ✅ Pode ser executado múltiplas vezes (verifica se já existe)

### Passo 2: Vincular Dados Globais à Nova Loja
```
Método: ModernposDefaults::applyGlobalSampleDataToStore($pdo, $storeId)

┌────────────────────────────────────────────────┐
│  1. Buscar IDs dos dados globais               │
├────────────────────────────────────────────────┤
│ $ids = ModernposDefaults::getGlobalSampleDataIds($pdo); │
│                                                │
│ Retorna:                                       │
│ [                                              │
│   'category_id' => 1,      // ID da categoria  │
│   'supplier_id' => 1,      // ID do fornecedor │
│   'brand_id' => 1,         // ID da marca      │
│   'bank_account_id' => 1   // ID da conta      │
│ ]                                              │
└────────────────────────────────────────────────┘
         ↓
┌────────────────────────────────────────────────┐
│  2. Vincular à loja via tabelas *_to_store     │
├────────────────────────────────────────────────┤
│ INSERT INTO category_to_store                  │
│ (ccategory_id, store_id, status, sort_order)  │
│ VALUES (1, $storeId, 1, 0)                     │
│                                                │
│ INSERT INTO supplier_to_store                  │
│ (sup_id, store_id, balance, status)           │
│ VALUES (1, $storeId, 0.0000, 1)               │
│                                                │
│ INSERT INTO brand_to_store                     │
│ (brand_id, store_id, status, sort_order)      │
│ VALUES (1, $storeId, 1, 0)                     │
│                                                │
│ INSERT INTO bank_account_to_store              │
│ (account_id, store_id, status, sort_order)    │
│ VALUES (1, $storeId, 1, 0)                     │
│                                                │
│ UPDATE stores                                  │
│ SET deposit_account_id = 1                     │
│ WHERE store_id = $storeId                      │
└────────────────────────────────────────────────┘
         ↓
┌────────────────────────────────────────────────┐
│  3. Vincular Templates de Recibo               │
├────────────────────────────────────────────────┤
│ ModernposDefaults::linkGlobalReceiptTemplatesToStore() │
│                                                │
│ INSERT INTO receipt_template_to_store          │
│ (template_id, store_id, is_default)           │
│ VALUES (1, $storeId, 1) -- Template 58mm      │
└────────────────────────────────────────────────┘
```

### Passo 3: Criar Produtos Demo Específicos da Loja
```
Método: ModernposDefaults::createDemoProductsForStore($pdo, $storeId, $ids)

┌────────────────────────────────────────────────┐
│  VERIFICAR se loja já tem produtos             │
├────────────────────────────────────────────────┤
│ SELECT COUNT(*) FROM product_to_store          │
│ WHERE store_id = $storeId                      │
│                                                │
│ Se COUNT > 0 → NÃO CRIA (loja já tem produtos)│
│ Se COUNT = 0 → CRIAR 2 produtos demo          │
└────────────────────────────────────────────────┘
         ↓
┌────────────────────────────────────────────────┐
│  PRODUTO DEMO 1: "Produto Exemplo A"           │
├────────────────────────────────────────────────┤
│ INSERT INTO products (                         │
│   item_name,                                   │
│   item_code,        -- AUTO: "PROD-" + timestamp │
│   barcode_symbology, -- "code128"             │
│   item_group_id,    -- $ids['category_id']    │
│   brand_id,         -- $ids['brand_id']       │
│   supplier_id,      -- $ids['supplier_id']    │
│   unit_id,          -- $ids['unit_id'] ou 1   │
│   box_id,           -- $ids['box_id'] ou 1    │
│   taxrate_id,       -- 3 (no_tax)             │
│   store_id,         -- $storeId               │
│   cost,             -- 5.00                   │
│   price,            -- 10.00 (margem 100%)    │
│   quantity,         -- 100                    │
│   alert_quantity,   -- 10                     │
│   is_sample,        -- 1                      │
│   status            -- 1                      │
│ ) VALUES (...)                                 │
│                                                │
│ $product1_id = lastInsertId()                  │
│                                                │
│ INSERT INTO product_to_store                   │
│ (product_id, store_id, quantity, status)      │
│ VALUES ($product1_id, $storeId, 100, 1)       │
└────────────────────────────────────────────────┘
         ↓
┌────────────────────────────────────────────────┐
│  PRODUTO DEMO 2: "Produto Exemplo B"           │
├────────────────────────────────────────────────┤
│ INSERT INTO products (...)                     │
│ Mesma estrutura do Produto 1, mas:            │
│   item_name = "Produto Exemplo B"             │
│   cost = 15.00                                │
│   price = 25.00                               │
│   quantity = 50                               │
│                                                │
│ INSERT INTO product_to_store (...)             │
└────────────────────────────────────────────────┘
```

---

## 4. ESTRUTURA DE TABELAS

### 4.1. Tabela: `categorys` (Global)
```sql
CREATE TABLE categorys (
  category_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(200) NOT NULL,
  code_name VARCHAR(100),
  image VARCHAR(255),
  parent_id INT DEFAULT 0,         -- Para subcategorias
  is_sample TINYINT DEFAULT 0,     -- 1 = dado de exemplo
  status TINYINT DEFAULT 1,        -- 1 = ativo
  sort_order INT DEFAULT 0,
  created_at DATETIME
);

-- Categoria de exemplo
INSERT INTO categorys 
(name, code_name, is_sample, status, created_at) 
VALUES 
('Geral', 'geral', 1, 1, NOW()),
('Produtos Gerais', 'produtos_gerais', 1, 1, NOW()),
('Alimentos', 'alimentos', 1, 1, NOW()),
('Bebidas', 'bebidas', 1, 1, NOW());
```

### 4.2. Tabela: `category_to_store`
```sql
CREATE TABLE category_to_store (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ccategory_id INT,                -- FK para categorys.category_id
  store_id INT,                    -- FK para stores.store_id
  status TINYINT DEFAULT 1,
  sort_order INT DEFAULT 0
);

-- Exemplo: Vincular categoria "Geral" à loja 123
INSERT INTO category_to_store 
(ccategory_id, store_id, status, sort_order) 
VALUES (1, 123, 1, 0);
```

### 4.3. Tabela: `suppliers` (Global)
```sql
CREATE TABLE suppliers (
  supplier_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(200) NOT NULL,
  mobile VARCHAR(50),
  email VARCHAR(100),
  address TEXT,
  city VARCHAR(100),
  country VARCHAR(100),
  is_sample TINYINT DEFAULT 0,
  status TINYINT DEFAULT 1,
  created_at DATETIME
);

-- Fornecedor de exemplo
INSERT INTO suppliers 
(name, mobile, email, is_sample, status, created_at) 
VALUES 
('Fornecedor Padrão', '(11) 0000-0000', 'contato@fornecedor.com', 1, 1, NOW()),
('Distribuidora Exemplo', '(11) 1111-1111', 'vendas@distribuidora.com', 1, 1, NOW());
```

### 4.4. Tabela: `supplier_to_store`
```sql
CREATE TABLE supplier_to_store (
  id INT PRIMARY KEY AUTO_INCREMENT,
  sup_id INT,                      -- FK para suppliers.supplier_id
  store_id INT,                    -- FK para stores.store_id
  balance DECIMAL(25,4) DEFAULT 0.0000,  -- Saldo devedor
  status TINYINT DEFAULT 1,
  sort_order INT DEFAULT 0
);

-- Exemplo
INSERT INTO supplier_to_store 
(sup_id, store_id, balance, status) 
VALUES (1, 123, 0.0000, 1);
```

### 4.5. Tabela: `brands` (Global)
```sql
CREATE TABLE brands (
  brand_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(200) NOT NULL,
  code_name VARCHAR(100),
  image VARCHAR(255),
  is_sample TINYINT DEFAULT 0,
  status TINYINT DEFAULT 1,
  sort_order INT DEFAULT 0,
  created_at DATETIME
);

-- Marcas de exemplo
INSERT INTO brands 
(name, code_name, is_sample, status, created_at) 
VALUES 
('Sem Marca', 'sem_marca', 1, 1, NOW()),
('Genérico', 'generico', 1, 1, NOW()),
('Marca Exemplo', 'marca_exemplo', 1, 1, NOW());
```

### 4.6. Tabela: `brand_to_store`
```sql
CREATE TABLE brand_to_store (
  id INT PRIMARY KEY AUTO_INCREMENT,
  brand_id INT,                    -- FK para brands.brand_id
  store_id INT,                    -- FK para stores.store_id
  status TINYINT DEFAULT 1,
  sort_order INT DEFAULT 0
);
```

### 4.7. Tabela: `bank_accounts` (Global)
```sql
CREATE TABLE bank_accounts (
  account_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(200) NOT NULL,
  account_number VARCHAR(100),
  account_type VARCHAR(50),        -- cash, checking, savings
  bank_name VARCHAR(200),
  initial_balance DECIMAL(25,4) DEFAULT 0.0000,
  is_sample TINYINT DEFAULT 0,
  status TINYINT DEFAULT 1,
  created_at DATETIME
);

-- Conta de exemplo
INSERT INTO bank_accounts 
(name, account_type, initial_balance, is_sample, status, created_at) 
VALUES 
('Caixa Principal', 'cash', 0.0000, 1, 1, NOW()),
('Conta Corrente Exemplo', 'checking', 1000.0000, 1, 1, NOW());
```

### 4.8. Tabela: `bank_account_to_store`
```sql
CREATE TABLE bank_account_to_store (
  id INT PRIMARY KEY AUTO_INCREMENT,
  store_id INT,
  account_id INT,
  deposit DECIMAL(25,4) DEFAULT 0.0000,
  withdraw DECIMAL(25,4) DEFAULT 0.0000,
  transfer_in DECIMAL(25,4) DEFAULT 0.0000,
  transfer_out DECIMAL(25,4) DEFAULT 0.0000,
  status TINYINT DEFAULT 1,
  sort_order INT DEFAULT 0
);
```

### 4.9. Tabela: `products` (Específico por loja)
```sql
CREATE TABLE products (
  product_id INT PRIMARY KEY AUTO_INCREMENT,
  item_name VARCHAR(255) NOT NULL,
  item_code VARCHAR(100),          -- Código interno (SKU)
  barcode_symbology VARCHAR(50),   -- code128, ean13, etc
  item_group_id INT,               -- FK para categorys.category_id
  brand_id INT,                    -- FK para brands.brand_id
  supplier_id INT,                 -- FK para suppliers.supplier_id
  unit_id INT,                     -- FK para units.unit_id
  box_id INT,                      -- FK para boxes.box_id
  taxrate_id INT,                  -- FK para taxrates.taxrate_id
  store_id INT,                    -- FK para stores.store_id
  cost DECIMAL(25,4),              -- Custo de compra
  price DECIMAL(25,4),             -- Preço de venda
  quantity DECIMAL(15,2),          -- Estoque atual
  alert_quantity INT,              -- Alerta de estoque baixo
  description TEXT,
  image VARCHAR(255),
  is_sample TINYINT DEFAULT 0,     -- 1 = produto de exemplo
  status TINYINT DEFAULT 1,
  created_at DATETIME
);

-- Produtos de exemplo (ESPECÍFICOS DA LOJA!)
INSERT INTO products 
(item_name, item_code, barcode_symbology, item_group_id, brand_id, 
 supplier_id, unit_id, box_id, taxrate_id, store_id, cost, price, 
 quantity, alert_quantity, is_sample, status, created_at) 
VALUES 
('Produto Exemplo A', 'PROD-001', 'code128', 1, 1, 1, 1, 1, 3, 123, 
 5.00, 10.00, 100, 10, 1, 1, NOW()),
 
('Produto Exemplo B', 'PROD-002', 'code128', 1, 1, 1, 1, 1, 3, 123, 
 15.00, 25.00, 50, 10, 1, 1, NOW());
```

### 4.10. Tabela: `product_to_store`
```sql
CREATE TABLE product_to_store (
  id INT PRIMARY KEY AUTO_INCREMENT,
  product_id INT,                  -- FK para products.product_id
  store_id INT,                    -- FK para stores.store_id
  quantity DECIMAL(15,2),          -- Estoque específico desta loja
  status TINYINT DEFAULT 1
);
```

### 4.11. Tabela: `receipt_templates` (Global)
```sql
CREATE TABLE receipt_templates (
  template_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(200) NOT NULL,
  template_type VARCHAR(50),       -- 58mm, 80mm, a4
  content TEXT,                    -- HTML/Template do recibo
  is_sample TINYINT DEFAULT 0,
  status TINYINT DEFAULT 1,
  created_at DATETIME
);

-- Templates de exemplo
INSERT INTO receipt_templates 
(name, template_type, content, is_sample, status, created_at) 
VALUES 
('Padrão 58mm', '58mm', '<template HTML aqui>', 1, 1, NOW()),
('Padrão 80mm', '80mm', '<template HTML aqui>', 1, 1, NOW());
```

### 4.12. Tabela: `receipt_template_to_store`
```sql
CREATE TABLE receipt_template_to_store (
  id INT PRIMARY KEY AUTO_INCREMENT,
  template_id INT,
  store_id INT,
  is_default TINYINT DEFAULT 0     -- 1 = template padrão da loja
);
```

---

## 5. IMPLEMENTAÇÃO - CÓDIGO PHP

### 5.1. Script de Inicialização Global
```php
<?php
// Arquivo: modernpos/_inc/initialize_global_sample_data.php

require_once __DIR__ . '/../config.php';

function initializeGlobalSampleData(PDO $pdo): array
{
    $ids = [];
    
    try {
        // 1. CATEGORIA
        $stmt = $pdo->query("SELECT category_id FROM categorys WHERE is_sample = 1 LIMIT 1");
        $categoryId = $stmt ? (int)$stmt->fetchColumn() : 0;
        
        if ($categoryId === 0) {
            $pdo->prepare("INSERT INTO categorys (name, code_name, is_sample, status, created_at) VALUES (?, ?, 1, 1, NOW())")
                ->execute(['Geral', 'geral']);
            $categoryId = $pdo->lastInsertId();
        }
        $ids['category_id'] = $categoryId;
        
        // 2. FORNECEDOR
        $stmt = $pdo->query("SELECT supplier_id FROM suppliers WHERE is_sample = 1 LIMIT 1");
        $supplierId = $stmt ? (int)$stmt->fetchColumn() : 0;
        
        if ($supplierId === 0) {
            $pdo->prepare("INSERT INTO suppliers (name, mobile, email, is_sample, status, created_at) VALUES (?, ?, ?, 1, 1, NOW())")
                ->execute(['Fornecedor Padrão', '(11) 0000-0000', 'contato@fornecedor.com']);
            $supplierId = $pdo->lastInsertId();
        }
        $ids['supplier_id'] = $supplierId;
        
        // 3. MARCA
        $stmt = $pdo->query("SELECT brand_id FROM brands WHERE is_sample = 1 LIMIT 1");
        $brandId = $stmt ? (int)$stmt->fetchColumn() : 0;
        
        if ($brandId === 0) {
            $pdo->prepare("INSERT INTO brands (name, code_name, is_sample, status, created_at) VALUES (?, ?, 1, 1, NOW())")
                ->execute(['Sem Marca', 'sem_marca']);
            $brandId = $pdo->lastInsertId();
        }
        $ids['brand_id'] = $brandId;
        
        // 4. CONTA BANCÁRIA
        $stmt = $pdo->query("SELECT account_id FROM bank_accounts WHERE is_sample = 1 LIMIT 1");
        $accountId = $stmt ? (int)$stmt->fetchColumn() : 0;
        
        if ($accountId === 0) {
            $pdo->prepare("INSERT INTO bank_accounts (name, account_type, initial_balance, is_sample, status, created_at) VALUES (?, 'cash', 0.0000, 1, 1, NOW())")
                ->execute(['Caixa Principal']);
            $accountId = $pdo->lastInsertId();
        }
        $ids['bank_account_id'] = $accountId;
        
        return $ids;
        
    } catch (Exception $e) {
        error_log("Erro ao inicializar dados globais: " . $e->getMessage());
        return [];
    }
}

// Executar se chamado diretamente
if (php_sapi_name() === 'cli') {
    $pdo = db();
    $ids = initializeGlobalSampleData($pdo);
    echo "✅ Dados globais inicializados:\n";
    print_r($ids);
}
```

### 5.2. Método em ModernposDefaults (já existe)
```php
<?php
// Arquivo: saas/includes/ModernposDefaults.php

class ModernposDefaults 
{
    /**
     * Obtém IDs dos dados globais de exemplo
     */
    protected static function getGlobalSampleDataIds(PDO $pdo): array
    {
        $ids = [];
        
        try {
            // Categoria
            $stmt = $pdo->query("SELECT category_id FROM categorys WHERE is_sample = 1 ORDER BY category_id ASC LIMIT 1");
            $ids['category_id'] = $stmt ? (int)$stmt->fetchColumn() : 0;
            
            // Fornecedor
            $stmt = $pdo->query("SELECT supplier_id FROM suppliers WHERE is_sample = 1 ORDER BY supplier_id ASC LIMIT 1");
            $ids['supplier_id'] = $stmt ? (int)$stmt->fetchColumn() : 0;
            
            // Marca
            $stmt = $pdo->query("SELECT brand_id FROM brands WHERE is_sample = 1 ORDER BY brand_id ASC LIMIT 1");
            $ids['brand_id'] = $stmt ? (int)$stmt->fetchColumn() : 0;
            
            // Conta bancária
            $stmt = $pdo->query("SELECT account_id FROM bank_accounts WHERE is_sample = 1 ORDER BY account_id ASC LIMIT 1");
            $ids['bank_account_id'] = $stmt ? (int)$stmt->fetchColumn() : 0;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar IDs globais: " . $e->getMessage());
        }
        
        return $ids;
    }
    
    /**
     * Vincula dados globais à loja e cria produtos demo
     */
    public static function applyGlobalSampleDataToStore(PDO $pdo, int $storeId): void
    {
        if ($storeId <= 0) return;
        
        try {
            $ids = self::getGlobalSampleDataIds($pdo);
            
            // 1) Categoria
            if (!empty($ids['category_id'])) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM category_to_store WHERE store_id = ? AND ccategory_id = ?');
                $stmt->execute([$storeId, $ids['category_id']]);
                
                if ((int)$stmt->fetchColumn() === 0) {
                    $pdo->prepare('INSERT INTO category_to_store (ccategory_id, store_id, status, sort_order) VALUES (?, ?, 1, 0)')
                        ->execute([$ids['category_id'], $storeId]);
                }
            }
            
            // 2) Fornecedor
            if (!empty($ids['supplier_id'])) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM supplier_to_store WHERE store_id = ? AND sup_id = ?');
                $stmt->execute([$storeId, $ids['supplier_id']]);
                
                if ((int)$stmt->fetchColumn() === 0) {
                    $pdo->prepare('INSERT INTO supplier_to_store (sup_id, store_id, balance, status, sort_order) VALUES (?, ?, 0.0000, 1, 0)')
                        ->execute([$ids['supplier_id'], $storeId]);
                }
            }
            
            // 3) Marca
            if (!empty($ids['brand_id'])) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM brand_to_store WHERE store_id = ? AND brand_id = ?');
                $stmt->execute([$storeId, $ids['brand_id']]);
                
                if ((int)$stmt->fetchColumn() === 0) {
                    $pdo->prepare('INSERT INTO brand_to_store (brand_id, store_id, status, sort_order) VALUES (?, ?, 1, 0)')
                        ->execute([$ids['brand_id'], $storeId]);
                }
            }
            
            // 4) Conta bancária + definir como padrão
            if (!empty($ids['bank_account_id'])) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM bank_account_to_store WHERE store_id = ? AND account_id = ?');
                $stmt->execute([$storeId, $ids['bank_account_id']]);
                
                if ((int)$stmt->fetchColumn() === 0) {
                    $pdo->prepare('INSERT INTO bank_account_to_store (store_id, account_id, status, sort_order) VALUES (?, ?, 1, 0)')
                        ->execute([$storeId, $ids['bank_account_id']]);
                        
                    // Definir como conta de depósito padrão
                    $pdo->prepare('UPDATE stores SET deposit_account_id = ? WHERE store_id = ?')
                        ->execute([$ids['bank_account_id'], $storeId]);
                }
            }
            
            // 5) Templates de recibo
            self::linkGlobalReceiptTemplatesToStore($pdo, $storeId);
            
            // 6) Produtos demo (exclusivos da loja)
            self::createDemoProductsForStore($pdo, $storeId, $ids);
            
        } catch (Exception $e) {
            error_log('[ModernposDefaults] Erro ao vincular sample data: ' . $e->getMessage());
        }
    }
    
    /**
     * Cria 2 produtos demo para a loja
     */
    protected static function createDemoProductsForStore(PDO $pdo, int $storeId, array $ids): void
    {
        if ($storeId <= 0) return;
        
        // Verifica se loja já tem produtos
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM product_to_store WHERE store_id = ?');
        $stmt->execute([$storeId]);
        if ((int)$stmt->fetchColumn() > 0) {
            return; // Já tem produtos, não cria
        }
        
        try {
            $categoryId = !empty($ids['category_id']) ? $ids['category_id'] : 1;
            $supplierId = !empty($ids['supplier_id']) ? $ids['supplier_id'] : 1;
            $brandId = !empty($ids['brand_id']) ? $ids['brand_id'] : 1;
            
            // Buscar unit_id e box_id padrão
            $unitId = 1;
            $boxId = 1;
            $taxrateId = 3; // no_tax
            
            try {
                $stmt = $pdo->query('SELECT unit_id FROM units ORDER BY unit_id ASC LIMIT 1');
                if ($stmt && $tmp = $stmt->fetchColumn()) $unitId = (int)$tmp;
            } catch (Exception $e) {}
            
            try {
                $stmt = $pdo->query('SELECT box_id FROM boxes ORDER BY box_id ASC LIMIT 1');
                if ($stmt && $tmp = $stmt->fetchColumn()) $boxId = (int)$tmp;
            } catch (Exception $e) {}
            
            // PRODUTO 1
            $itemCode1 = 'PROD-' . time() . '-A';
            $pdo->prepare("
                INSERT INTO products 
                (item_name, item_code, barcode_symbology, item_group_id, brand_id, supplier_id, 
                 unit_id, box_id, taxrate_id, store_id, cost, price, quantity, alert_quantity, 
                 is_sample, status, created_at) 
                VALUES (?, ?, 'code128', ?, ?, ?, ?, ?, ?, ?, 5.00, 10.00, 100, 10, 1, 1, NOW())
            ")->execute([
                'Produto Exemplo A',
                $itemCode1,
                $categoryId,
                $brandId,
                $supplierId,
                $unitId,
                $boxId,
                $taxrateId,
                $storeId
            ]);
            $product1Id = $pdo->lastInsertId();
            
            $pdo->prepare('INSERT INTO product_to_store (product_id, store_id, quantity, status) VALUES (?, ?, 100, 1)')
                ->execute([$product1Id, $storeId]);
            
            // PRODUTO 2
            $itemCode2 = 'PROD-' . time() . '-B';
            $pdo->prepare("
                INSERT INTO products 
                (item_name, item_code, barcode_symbology, item_group_id, brand_id, supplier_id, 
                 unit_id, box_id, taxrate_id, store_id, cost, price, quantity, alert_quantity, 
                 is_sample, status, created_at) 
                VALUES (?, ?, 'code128', ?, ?, ?, ?, ?, ?, ?, 15.00, 25.00, 50, 10, 1, 1, NOW())
            ")->execute([
                'Produto Exemplo B',
                $itemCode2,
                $categoryId,
                $brandId,
                $supplierId,
                $unitId,
                $boxId,
                $taxrateId,
                $storeId
            ]);
            $product2Id = $pdo->lastInsertId();
            
            $pdo->prepare('INSERT INTO product_to_store (product_id, store_id, quantity, status) VALUES (?, ?, 50, 1)')
                ->execute([$product2Id, $storeId]);
                
        } catch (Exception $e) {
            error_log('[ModernposDefaults] Erro ao criar produtos demo: ' . $e->getMessage());
        }
    }
    
    /**
     * Vincula templates de recibo globais à loja
     */
    protected static function linkGlobalReceiptTemplatesToStore(PDO $pdo, int $storeId): void
    {
        try {
            $stmt = $pdo->query("SELECT template_id FROM receipt_templates WHERE is_sample = 1 ORDER BY template_id ASC LIMIT 1");
            $templateId = $stmt ? (int)$stmt->fetchColumn() : 0;
            
            if ($templateId > 0) {
                $check = $pdo->prepare('SELECT COUNT(*) FROM receipt_template_to_store WHERE store_id = ? AND template_id = ?');
                $check->execute([$storeId, $templateId]);
                
                if ((int)$check->fetchColumn() === 0) {
                    $pdo->prepare('INSERT INTO receipt_template_to_store (template_id, store_id, is_default) VALUES (?, ?, 1)')
                        ->execute([$templateId, $storeId]);
                }
            }
        } catch (Exception $e) {
            error_log('[ModernposDefaults] Erro ao vincular templates: ' . $e->getMessage());
        }
    }
}
```

### 5.3. Integração no Cadastro
```php
<?php
// Arquivo: modernpos/process_register.php

// ... após criar loja ...

$store_id = (int)$db->lastInsertId();

// 1. Aplicar defaults básicos (currency, pmethods, etc)
ModernposDefaults::applyDefaultsToStore($db, $store_id);

// 2. Aplicar dados de exemplo (produtos, categorias, etc)
ModernposDefaults::applyGlobalSampleDataToStore($db, $store_id);

// ... continua ...
```

---

## 6. VALIDAÇÃO E TESTES

### Teste 1: Verificar Categoria Vinculada
```sql
SELECT c.name, c2s.status
FROM category_to_store c2s
JOIN categorys c ON c.category_id = c2s.ccategory_id
WHERE c2s.store_id = 123;

-- Esperado: "Geral" ou "Produtos Gerais" com status = 1
```

### Teste 2: Verificar Fornecedor Vinculado
```sql
SELECT s.name, s2s.balance
FROM supplier_to_store s2s
JOIN suppliers s ON s.supplier_id = s2s.sup_id
WHERE s2s.store_id = 123;

-- Esperado: "Fornecedor Padrão" com balance = 0.0000
```

### Teste 3: Verificar Produtos Demo
```sql
SELECT p.item_name, p.item_code, p.cost, p.price, p.quantity, p.is_sample
FROM products p
WHERE p.store_id = 123 AND p.is_sample = 1;

-- Esperado: 2 produtos
-- "Produto Exemplo A" - R$ 10,00 - 100 unidades
-- "Produto Exemplo B" - R$ 25,00 - 50 unidades
```

### Teste 4: Verificar Conta Bancária
```sql
SELECT ba.name, ba.account_type, s.deposit_account_id
FROM stores s
LEFT JOIN bank_account_to_store ba2s ON ba2s.store_id = s.store_id
LEFT JOIN bank_accounts ba ON ba.account_id = ba2s.account_id
WHERE s.store_id = 123;

-- Esperado: "Caixa Principal" vinculada e definida como deposit_account_id
```

---

## 7. LIMPEZA DE DADOS DE EXEMPLO

### Função para remover dados de exemplo
```php
<?php
/**
 * Remove todos os dados de exemplo de uma loja
 */
function removeSampleDataFromStore(PDO $pdo, int $storeId): void
{
    try {
        $pdo->beginTransaction();
        
        // 1. Deletar produtos de exemplo
        $pdo->prepare('DELETE FROM product_to_store WHERE store_id = ? AND product_id IN (SELECT product_id FROM products WHERE is_sample = 1)')
            ->execute([$storeId]);
            
        $pdo->prepare('DELETE FROM products WHERE store_id = ? AND is_sample = 1')
            ->execute([$storeId]);
        
        // 2. Desvincular categorias de exemplo (não deleta global)
        $pdo->prepare('DELETE FROM category_to_store WHERE store_id = ? AND ccategory_id IN (SELECT category_id FROM categorys WHERE is_sample = 1)')
            ->execute([$storeId]);
        
        // 3. Desvincular fornecedores de exemplo
        $pdo->prepare('DELETE FROM supplier_to_store WHERE store_id = ? AND sup_id IN (SELECT supplier_id FROM suppliers WHERE is_sample = 1)')
            ->execute([$storeId]);
        
        // 4. Desvincular marcas de exemplo
        $pdo->prepare('DELETE FROM brand_to_store WHERE store_id = ? AND brand_id IN (SELECT brand_id FROM brands WHERE is_sample = 1)')
            ->execute([$storeId]);
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
```

---

## 8. CHECKLIST DE IMPLEMENTAÇÃO

### Preparação Inicial
- [ ] Executar script `initialize_global_sample_data.php` UMA VEZ
- [ ] Verificar que tabelas têm coluna `is_sample`
- [ ] Verificar que dados globais foram criados

### Implementação
- [ ] Adicionar `applyGlobalSampleDataToStore()` em `ModernposDefaults`
- [ ] Adicionar chamada em `process_register.php` após criar loja
- [ ] Adicionar chamada em `_inc/account_store.php` (action=create)
- [ ] Testar criação de usuário novo
- [ ] Testar criação de loja pela modal

### Validação
- [ ] Nova loja tem categoria vinculada
- [ ] Nova loja tem fornecedor vinculado
- [ ] Nova loja tem marca vinculada
- [ ] Nova loja tem 2 produtos demo
- [ ] Nova loja tem conta bancária definida como padrão
- [ ] Produtos aparecem no PDV
- [ ] Produtos aparecem em Produtos > Listar

---

## 9. OBSERVAÇÕES IMPORTANTES

### 1. Dados Globais vs Específicos
- **Globais** (categorias, fornecedores, marcas): Criados UMA VEZ, compartilhados
- **Específicos** (produtos): Criados PARA CADA LOJA

### 2. Campo `is_sample`
- Usar em TODAS as tabelas que terão dados de exemplo
- Permite identificar e remover facilmente
- Útil para "Limpar Dados de Exemplo" na interface

### 3. Multi-tenancy
- Dados globais NÃO têm `tenant_id` (compartilhados entre todos)
- Produtos TÊM `store_id` (isolados por loja)
- Vínculos em `*_to_store` respeitam isolamento

### 4. Performance
- Criar dados globais UMA VEZ (não a cada cadastro)
- Verificar existência antes de criar (evita duplicação)
- Usar transações para garantir consistência

---

## CONCLUSÃO

Sistema de dados de exemplo garante que **toda loja nova** já vem com:
- ✅ Categoria "Geral" vinculada
- ✅ Fornecedor "Padrão" vinculado
- ✅ Marca "Sem Marca" vinculada
- ✅ 2 Produtos demo cadastrados e em estoque
- ✅ Conta bancária "Caixa Principal" como padrão
- ✅ Templates de recibo configurados

**Benefícios**:
- Onboarding imediato (pode testar venda sem cadastrar nada)
- Exemplos visuais de como funciona o sistema
- Reduz fricção na experiência inicial
- Usuário vê interface "povoada" ao invés de vazia

**Implementação**:
```php
// Sempre após criar loja
ModernposDefaults::applyDefaultsToStore($pdo, $storeId);
ModernposDefaults::applyGlobalSampleDataToStore($pdo, $storeId);
```
