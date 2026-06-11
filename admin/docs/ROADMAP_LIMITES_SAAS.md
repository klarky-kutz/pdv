# 🚀 ROADMAP: Validação de Limites SaaS - Produtos e Clientes

> **Documento de Implementação para IA**
> 
> Este documento contém todas as especificações técnicas e o roadmap passo-a-passo para implementar a validação de limites de produtos e clientes no sistema ModernPOS.

---

## 📋 Índice

1. [Visão Geral](#-visão-geral)
2. [Stack Tecnológica](#-stack-tecnológica)
3. [Estrutura do Banco de Dados](#-estrutura-do-banco-de-dados)
4. [Lógica de Negócio](#-lógica-de-negócio)
5. [Roadmap de Implementação](#-roadmap-de-implementação)
6. [Código de Referência](#-código-de-referência)
7. [Testes](#-testes)
8. [Checklist Final](#-checklist-final)

---

## 🎯 Visão Geral

### Objetivo
Implementar validação de limites do plano SaaS nas páginas de criação de **Produtos** e **Clientes** dentro das lojas do ModernPOS.

### Regra Principal
O sistema deve verificar o **TOTAL CONSOLIDADO de todas as lojas do tenant** antes de permitir novos cadastros.

### Exemplo Prático
```
Tenant possui 2 lojas:
├── Loja A: 2 produtos
├── Loja B: 8 produtos
└── TOTAL: 10 produtos

Se o limite do plano é 10 produtos:
→ QUALQUER loja que tentar criar novo produto deve receber AVISO/BLOQUEIO
```

### Páginas Afetadas
| Recurso | URL | Arquivo |
|---------|-----|---------|
| Produtos | `http://localhost/modernpos/admin/product.php?box_state=open` | `admin/product.php` |
| Clientes | `http://localhost/modernpos/admin/customer.php?box_state=open` | `admin/customer.php` |

### Estilo Visual
A modal de aviso deve seguir o padrão do AdminLTE 2 usado no dashboard:
- `http://localhost/modernpos/admin/dashboard.php`

---

## 🛠 Stack Tecnológica

| Tecnologia | Versão | Uso |
|------------|--------|-----|
| PHP | 8+ | Backend, lógica de validação |
| MySQL | 5.7+ | Banco de dados (via PDO) |
| JavaScript | ES5+ | Validação client-side |
| jQuery | 2.x/3.x | Manipulação DOM, AJAX |
| AngularJS | 1.x | Controllers existentes |
| AdminLTE | 2.x | Framework CSS (Bootstrap 3) |
| Bootstrap | 3.x | Componentes UI (modals, alerts) |

### Localização do Projeto
```
C:\xampp\htdocs\modernpos
```

---

## 🗄 Estrutura do Banco de Dados

### Tabela `plans` - Limites do Plano
```sql
-- Colunas relevantes para limites
max_products       INT(11)    DEFAULT 500   -- Limite máximo de produtos
products_unlimited TINYINT(1) DEFAULT 0     -- Se 1, produtos ilimitados
clients_limit      INT(11)    DEFAULT 10    -- Limite máximo de clientes
max_users          INT(11)    DEFAULT 3     -- Limite de usuários (futuro)
max_stores         INT(11)    DEFAULT 1     -- Limite de lojas
```

### Tabela `tenants` - Vínculo Tenant → Plano
```sql
tenant_id INT PRIMARY KEY AUTO_INCREMENT
plan_id   INT  -- FK para plans.plan_id
```

### Tabela `stores` - Lojas do Tenant
```sql
store_id  INT PRIMARY KEY AUTO_INCREMENT
tenant_id INT  -- FK para tenants.tenant_id
name      VARCHAR(100)
```

### Tabela `product_to_store` - Produtos por Loja
```sql
id         INT PRIMARY KEY AUTO_INCREMENT
product_id INT      -- FK para products.p_id
store_id   INT      -- FK para stores.store_id
status     TINYINT(1) DEFAULT 1  -- 1 = ativo, 0 = inativo
```

### Tabela `customer_to_store` - Clientes por Loja
```sql
id          INT PRIMARY KEY AUTO_INCREMENT
customer_id INT  -- FK para customers.customer_id
store_id    INT  -- FK para stores.store_id
```

### Diagrama de Relacionamento
```
plans (1) ←──── (N) tenants (1) ←──── (N) stores (1) ←──── (N) product_to_store
                                                    └──── (N) customer_to_store
```

---

## 💡 Lógica de Negócio

### Query: Contar Produtos do Tenant
```sql
SELECT COUNT(DISTINCT product_id) 
FROM product_to_store 
WHERE store_id IN (
    SELECT store_id FROM stores WHERE tenant_id = :tenant_id
) AND status = 1
```

### Query: Contar Clientes do Tenant
```sql
SELECT COUNT(DISTINCT customer_id) 
FROM customer_to_store 
WHERE store_id IN (
    SELECT store_id FROM stores WHERE tenant_id = :tenant_id
)
```

### Fluxograma de Decisão
```
┌─────────────────────────────────┐
│ Usuário acessa página de criar │
│ produto/cliente                 │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│ Buscar tenant_id da sessão     │
│ ou do usuário logado           │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│ tenant_id > 0 ?                │
└──────────────┬──────────────────┘
               │
      ┌────────┴────────┐
      │ NÃO             │ SIM
      ▼                 ▼
┌───────────┐    ┌─────────────────────────────────┐
│ Permitir  │    │ Buscar limites do plano        │
│ criação   │    │ (plans via tenants)             │
└───────────┘    └──────────────┬──────────────────┘
                               │
                               ▼
                 ┌─────────────────────────────────┐
                 │ Contar uso atual (consolidado)  │
                 │ de todas as lojas do tenant     │
                 └──────────────┬──────────────────┘
                               │
                               ▼
                 ┌─────────────────────────────────┐
                 │ uso_atual >= limite ?           │
                 └──────────────┬──────────────────┘
                               │
                      ┌────────┴────────┐
                      │ SIM             │ NÃO
                      ▼                 ▼
               ┌───────────┐     ┌───────────┐
               │ BLOQUEAR  │     │ PERMITIR  │
               │ + Modal   │     │ criação   │
               └───────────┘     └───────────┘
```

---

## 📍 ROADMAP DE IMPLEMENTAÇÃO

### FASE 1: Criar Helper de Verificação de Limites
**Prioridade:** 🔴 Alta
**Arquivo:** `_inc/saas_limits_check.php`

#### Passo 1.1: Criar arquivo helper
```
Caminho: C:\xampp\htdocs\modernpos\_inc\saas_limits_check.php
```

#### Passo 1.2: Implementar funções
- `get_tenant_id()` - Obter tenant_id do usuário logado
- `get_tenant_plan_limits()` - Buscar limites do plano
- `get_tenant_usage()` - Contar uso atual (produtos, clientes)
- `can_create_product()` - Verificar se pode criar produto
- `can_create_customer()` - Verificar se pode criar cliente
- `get_limit_info($type)` - Retornar info completa para UI

---

### FASE 2: Criar Modal de Aviso
**Prioridade:** 🔴 Alta
**Arquivo:** `_inc/template/partials/limit_reached_modal.php`

#### Passo 2.1: Criar arquivo da modal
```
Caminho: C:\xampp\htdocs\modernpos\_inc\template\partials\limit_reached_modal.php
```

#### Passo 2.2: Implementar HTML da modal
- Seguir padrão AdminLTE 2 / Bootstrap 3
- Incluir ícone, título, mensagem, uso atual
- Botões: "Fechar" e "Fazer Upgrade"
- Função JavaScript `showSaasLimitModal(type, current, limit)`

---

### FASE 3: Modificar Página de Produtos
**Prioridade:** 🔴 Alta
**Arquivo:** `admin/product.php`

#### Passo 3.1: Incluir helper no início
```php
// Após include '_init.php' (linha ~4)
include('../_inc/saas_limits_check.php');
```

#### Passo 3.2: Obter info de limite
```php
$productLimitInfo = get_limit_info('products');
```

#### Passo 3.3: Condicional no formulário de criação
- Se `can_create = false`: Mostrar box de aviso (não o form)
- Se `can_create = true`: Mostrar form normalmente

#### Passo 3.4: Incluir modal no final
```php
<?php include('../_inc/template/partials/limit_reached_modal.php'); ?>
```

#### Passo 3.5: Adicionar variáveis JavaScript
```php
<script>
var saasProductLimit = <?php echo json_encode($productLimitInfo); ?>;
</script>
```

---

### FASE 4: Modificar Página de Clientes
**Prioridade:** 🔴 Alta
**Arquivo:** `admin/customer.php`

#### Passo 4.1: Incluir helper no início
```php
// Após include '_init.php' (linha ~4)
include('../_inc/saas_limits_check.php');
```

#### Passo 4.2: Obter info de limite
```php
$customerLimitInfo = get_limit_info('customers');
```

#### Passo 4.3: Condicional no formulário de criação
- Se `can_create = false`: Mostrar box de aviso
- Se `can_create = true`: Mostrar form normalmente

#### Passo 4.4: Incluir modal e variáveis JavaScript

---

### FASE 5: Criar Endpoint AJAX (Opcional)
**Prioridade:** 🟡 Média
**Arquivo:** `_inc/api/check_saas_limit.php`

#### Passo 5.1: Criar endpoint
```
Caminho: C:\xampp\htdocs\modernpos\_inc\api\check_saas_limit.php
```

#### Passo 5.2: Implementar lógica
- Receber parâmetro `type` (products/customers)
- Retornar JSON com `can_create`, `current`, `limit`, `unlimited`

---

### FASE 6: Validação JavaScript no Submit
**Prioridade:** 🟡 Média
**Arquivos:** Controllers AngularJS existentes

#### Passo 6.1: Interceptar submit do form de produto
```
Arquivo: assets/itsolution24/angular/controllers/ProductController.js
```

#### Passo 6.2: Interceptar submit do form de cliente
```
Arquivo: assets/itsolution24/angular/controllers/CustomerController.js
```

#### Passo 6.3: Implementar verificação antes do submit
- Verificar variável `saasProductLimit` ou `saasCustomerLimit`
- Se `can_create = false`: Prevenir submit e mostrar modal
- Se `can_create = true`: Continuar normalmente

---

### FASE 7: Validação Backend no Save
**Prioridade:** 🔴 Alta (Segurança)
**Arquivos:** Models/Controllers de salvamento

#### Passo 7.1: Localizar onde produtos são salvos
```
Possíveis locais:
- _inc/product.php
- model/product.php
- _inc/api/product_create.php
```

#### Passo 7.2: Localizar onde clientes são salvos
```
Possíveis locais:
- _inc/customer.php
- model/customer.php
- _inc/api/customer_create.php
```

#### Passo 7.3: Adicionar verificação de limite
- ANTES do INSERT, verificar `can_create_product()` ou `can_create_customer()`
- Se `false`: Retornar erro JSON/redirect com mensagem

---

### FASE 8: Testes e Ajustes
**Prioridade:** 🔴 Alta

#### Passo 8.1: Testar cenários
- Tenant no limite
- Tenant abaixo do limite
- Tenant com plano ilimitado
- Usuário não-SaaS (sem tenant_id)

#### Passo 8.2: Testar em múltiplas lojas
- Criar produtos em Loja A
- Verificar se limite é atingido em Loja B

#### Passo 8.3: Ajustar estilo visual se necessário

---

## 📝 Código de Referência

### Arquivo: `_inc/saas_limits_check.php`

```php
<?php
/**
 * SaaS Limits Check
 * Helper para verificar limites do plano antes de criar recursos
 * 
 * @package ModernPOS
 * @subpackage SaaS
 */

// Evitar acesso direto
if (!defined('ROOT')) {
    die('Acesso negado');
}

/**
 * Obtém o tenant_id do usuário logado
 * @return int
 */
function get_tenant_id_from_session() {
    // Primeiro tenta da sessão
    if (isset($_SESSION['tenant_id']) && $_SESSION['tenant_id'] > 0) {
        return (int)$_SESSION['tenant_id'];
    }
    
    // Fallback: buscar do usuário
    if (function_exists('user_id') && user_id() > 0) {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([user_id()]);
        $tenantId = (int)$stmt->fetchColumn();
        
        // Armazena na sessão para próximas consultas
        if ($tenantId > 0) {
            $_SESSION['tenant_id'] = $tenantId;
        }
        
        return $tenantId;
    }
    
    return 0;
}

/**
 * Obtém os limites do plano do tenant
 * @return array|null
 */
function get_tenant_plan_limits() {
    $tenantId = get_tenant_id_from_session();
    
    if ($tenantId <= 0) {
        return null; // Não é tenant SaaS
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT 
                p.max_products,
                p.products_unlimited,
                p.clients_limit,
                p.max_users,
                p.max_stores,
                p.name as plan_name
            FROM tenants t
            JOIN plans p ON p.plan_id = t.plan_id
            WHERE t.tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        $limits = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $limits ?: null;
    } catch (Exception $e) {
        error_log('[SaaS Limits] Erro ao buscar limites: ' . $e->getMessage());
        return null;
    }
}

/**
 * Obtém as store_ids do tenant
 * @return array
 */
function get_tenant_store_ids() {
    $tenantId = get_tenant_id_from_session();
    
    if ($tenantId <= 0) {
        return [];
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT store_id FROM stores WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log('[SaaS Limits] Erro ao buscar stores: ' . $e->getMessage());
        return [];
    }
}

/**
 * Conta o uso atual do tenant (produtos, clientes, usuários)
 * @return array
 */
function get_tenant_usage() {
    $storeIds = get_tenant_store_ids();
    $tenantId = get_tenant_id_from_session();
    
    $usage = [
        'products' => 0,
        'customers' => 0,
        'users' => 0,
        'stores' => count($storeIds)
    ];
    
    if (empty($storeIds)) {
        return $usage;
    }
    
    try {
        $pdo = db();
        $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
        
        // Contar produtos (distintos, ativos)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT product_id) 
            FROM product_to_store 
            WHERE store_id IN ($placeholders) AND status = 1
        ");
        $stmt->execute($storeIds);
        $usage['products'] = (int)$stmt->fetchColumn();
        
        // Contar clientes (distintos)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT customer_id) 
            FROM customer_to_store 
            WHERE store_id IN ($placeholders)
        ");
        $stmt->execute($storeIds);
        $usage['customers'] = (int)$stmt->fetchColumn();
        
        // Contar usuários do tenant
        if ($tenantId > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
            $usage['users'] = (int)$stmt->fetchColumn();
        }
        
    } catch (Exception $e) {
        error_log('[SaaS Limits] Erro ao contar uso: ' . $e->getMessage());
    }
    
    return $usage;
}

/**
 * Verifica se pode criar novo produto
 * @return bool
 */
function can_create_product() {
    $limits = get_tenant_plan_limits();
    
    // Se não é SaaS ou não encontrou limites, permite
    if (!$limits) {
        return true;
    }
    
    // Se produtos são ilimitados, permite
    if (!empty($limits['products_unlimited'])) {
        return true;
    }
    
    // Verifica limite
    $maxProducts = (int)($limits['max_products'] ?? 0);
    if ($maxProducts <= 0) {
        return true; // Sem limite definido = ilimitado
    }
    
    $usage = get_tenant_usage();
    return $usage['products'] < $maxProducts;
}

/**
 * Verifica se pode criar novo cliente
 * @return bool
 */
function can_create_customer() {
    $limits = get_tenant_plan_limits();
    
    // Se não é SaaS ou não encontrou limites, permite
    if (!$limits) {
        return true;
    }
    
    // Verifica limite de clientes
    $maxClients = (int)($limits['clients_limit'] ?? 0);
    if ($maxClients <= 0) {
        return true; // Sem limite definido = ilimitado
    }
    
    $usage = get_tenant_usage();
    return $usage['customers'] < $maxClients;
}

/**
 * Obtém informações completas de limite para exibição na UI
 * @param string $type 'products' ou 'customers'
 * @return array
 */
function get_limit_info($type) {
    $limits = get_tenant_plan_limits();
    $usage = get_tenant_usage();
    
    $info = [
        'is_saas' => ($limits !== null),
        'current' => 0,
        'limit' => 0,
        'unlimited' => true,
        'can_create' => true,
        'percentage' => 0,
        'plan_name' => $limits['plan_name'] ?? 'N/A'
    ];
    
    if (!$limits) {
        return $info;
    }
    
    switch ($type) {
        case 'products':
            $info['current'] = $usage['products'];
            $info['limit'] = (int)($limits['max_products'] ?? 0);
            $info['unlimited'] = !empty($limits['products_unlimited']) || $info['limit'] <= 0;
            $info['can_create'] = can_create_product();
            break;
            
        case 'customers':
            $info['current'] = $usage['customers'];
            $info['limit'] = (int)($limits['clients_limit'] ?? 0);
            $info['unlimited'] = $info['limit'] <= 0;
            $info['can_create'] = can_create_customer();
            break;
            
        case 'users':
            $info['current'] = $usage['users'];
            $info['limit'] = (int)($limits['max_users'] ?? 0);
            $info['unlimited'] = $info['limit'] <= 0;
            $info['can_create'] = $info['unlimited'] || $usage['users'] < $info['limit'];
            break;
            
        case 'stores':
            $info['current'] = $usage['stores'];
            $info['limit'] = (int)($limits['max_stores'] ?? 0);
            $info['unlimited'] = $info['limit'] <= 0;
            $info['can_create'] = $info['unlimited'] || $usage['stores'] < $info['limit'];
            break;
    }
    
    // Calcular porcentagem de uso
    if (!$info['unlimited'] && $info['limit'] > 0) {
        $info['percentage'] = round(($info['current'] / $info['limit']) * 100, 1);
    }
    
    return $info;
}
```

---

### Arquivo: `_inc/template/partials/limit_reached_modal.php`

```php
<?php
/**
 * Modal de Limite SaaS Atingido
 * Incluir este arquivo nas páginas que precisam de validação de limite
 */
?>
<!-- Modal SaaS - Limite Atingido -->
<div class="modal fade" id="saasLimitModal" tabindex="-1" role="dialog" aria-labelledby="saasLimitModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <!-- Header com gradiente vermelho -->
            <div class="modal-header" style="background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%); color: #fff; border-radius: 0;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar" style="color: #fff; opacity: 0.8; text-shadow: none;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="saasLimitModalLabel">
                    <i class="fa fa-exclamation-triangle"></i> 
                    Limite do Plano Atingido
                </h4>
            </div>
            
            <!-- Body -->
            <div class="modal-body text-center" style="padding: 30px;">
                <!-- Ícone grande -->
                <div id="saasLimitIconContainer" style="margin-bottom: 20px;">
                    <i id="saasLimitIcon" class="fa fa-ban" style="font-size: 72px; color: #e74c3c;"></i>
                </div>
                
                <!-- Título -->
                <h4 id="saasLimitTitle" style="margin-bottom: 15px; color: #333; font-weight: 600;">
                    Limite Atingido
                </h4>
                
                <!-- Mensagem -->
                <p id="saasLimitMessage" style="color: #666; font-size: 15px; line-height: 1.6;">
                    Você atingiu o limite permitido pelo seu plano atual.
                </p>
                
                <!-- Box de uso atual -->
                <div class="well well-sm" style="margin-top: 25px; background: #f9f9f9; border: 1px solid #eee;">
                    <p style="margin: 0; font-size: 14px;">
                        <i class="fa fa-bar-chart" style="color: #3498db;"></i>
                        <strong>Uso atual:</strong> 
                        <span id="saasLimitUsage" class="text-danger" style="font-weight: 600;"></span>
                    </p>
                    <!-- Barra de progresso -->
                    <div class="progress" style="margin: 15px 0 5px 0; height: 10px; border-radius: 5px;">
                        <div id="saasLimitProgressBar" class="progress-bar progress-bar-danger" role="progressbar" style="width: 100%; border-radius: 5px;"></div>
                    </div>
                </div>
                
                <!-- Dica -->
                <p style="margin-top: 20px; color: #888; font-size: 13px;">
                    <i class="fa fa-lightbulb-o"></i>
                    Faça upgrade do seu plano para aumentar os limites.
                </p>
            </div>
            
            <!-- Footer -->
            <div class="modal-footer" style="text-align: center; border-top: 1px solid #eee; padding: 20px;">
                <button type="button" class="btn btn-default btn-lg" data-dismiss="modal" style="min-width: 120px;">
                    <i class="fa fa-times"></i> Fechar
                </button>
                <a href="<?php echo root_url(); ?>conta/planos" class="btn btn-success btn-lg" style="min-width: 160px;">
                    <i class="fa fa-arrow-up"></i> Fazer Upgrade
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos adicionais para a modal */
#saasLimitModal .modal-content {
    border-radius: 4px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.2);
}
#saasLimitModal .modal-header {
    border-bottom: none;
}
#saasLimitModal .modal-header .close:hover {
    opacity: 1;
}
#saasLimitModal .progress {
    background-color: #ecf0f1;
    overflow: hidden;
}
</style>

<script>
/**
 * Exibe a modal de limite SaaS atingido
 * @param {string} type - Tipo: 'products', 'customers', 'users', 'stores'
 * @param {number} current - Uso atual
 * @param {number} limit - Limite do plano
 */
function showSaasLimitModal(type, current, limit) {
    var config = {
        products: {
            icon: 'fa-cubes',
            title: 'Limite de Produtos Atingido',
            message: 'Sua conta atingiu o limite de <strong>' + limit + ' produtos</strong> permitidos pelo seu plano atual. Para cadastrar novos produtos, é necessário fazer upgrade.',
            usageText: current + ' de ' + limit + ' produtos'
        },
        customers: {
            icon: 'fa-users',
            title: 'Limite de Clientes Atingido',
            message: 'Sua conta atingiu o limite de <strong>' + limit + ' clientes</strong> permitidos pelo seu plano atual. Para cadastrar novos clientes, é necessário fazer upgrade.',
            usageText: current + ' de ' + limit + ' clientes'
        },
        users: {
            icon: 'fa-user-plus',
            title: 'Limite de Usuários Atingido',
            message: 'Sua conta atingiu o limite de <strong>' + limit + ' usuários</strong> permitidos pelo seu plano atual.',
            usageText: current + ' de ' + limit + ' usuários'
        },
        stores: {
            icon: 'fa-building',
            title: 'Limite de Lojas Atingido',
            message: 'Sua conta atingiu o limite de <strong>' + limit + ' lojas</strong> permitidas pelo seu plano atual.',
            usageText: current + ' de ' + limit + ' lojas'
        }
    };
    
    var cfg = config[type] || config.products;
    var percentage = limit > 0 ? Math.min(100, (current / limit) * 100) : 100;
    
    // Atualizar conteúdo da modal
    $('#saasLimitIcon').removeClass().addClass('fa ' + cfg.icon);
    $('#saasLimitTitle').html(cfg.title);
    $('#saasLimitMessage').html(cfg.message);
    $('#saasLimitUsage').html(cfg.usageText);
    $('#saasLimitProgressBar').css('width', percentage + '%');
    
    // Exibir modal
    $('#saasLimitModal').modal('show');
}

/**
 * Verifica limite antes de abrir formulário de criação
 * @param {string} type - Tipo: 'products' ou 'customers'
 * @param {object} limitInfo - Objeto com informações de limite
 * @returns {boolean} - true se pode continuar, false se limite atingido
 */
function checkSaasLimitBeforeCreate(type, limitInfo) {
    if (!limitInfo || !limitInfo.is_saas) {
        return true; // Não é SaaS, permite
    }
    
    if (limitInfo.unlimited) {
        return true; // Ilimitado, permite
    }
    
    if (!limitInfo.can_create) {
        showSaasLimitModal(type, limitInfo.current, limitInfo.limit);
        return false; // Limite atingido, bloqueia
    }
    
    return true; // Pode criar
}
</script>
```

---

### Modificação: `admin/product.php`

```php
<?php 
ob_start();
session_start();
include '../_init.php';

// ========== INÍCIO: SaaS Limits Check ==========
include('../_inc/saas_limits_check.php');
$productLimitInfo = get_limit_info('products');
// ========== FIM: SaaS Limits Check ==========

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

// ... resto do código existente ...

// Na seção do formulário de criação (linha ~75), substituir por:
?>

<?php if (user_group_id() == 1 || has_permission('access', 'create_product')) : ?>
    
    <?php // ========== INÍCIO: Verificação de Limite SaaS ========== ?>
    <?php if ($productLimitInfo['is_saas'] && !$productLimitInfo['can_create'] && !$productLimitInfo['unlimited']): ?>
        
        <!-- Box de Limite Atingido -->
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <span class="fa fa-fw fa-ban"></span> 
                    Limite de Produtos Atingido
                </h3>
            </div>
            <div class="box-body text-center" style="padding: 40px;">
                <div style="font-size: 64px; color: #dd4b39; margin-bottom: 20px;">
                    <i class="fa fa-cubes"></i>
                </div>
                <h4>Você atingiu o limite do seu plano</h4>
                <p style="color: #666; margin: 15px 0;">
                    Seu plano <strong><?php echo htmlspecialchars($productLimitInfo['plan_name']); ?></strong> 
                    permite até <strong><?php echo $productLimitInfo['limit']; ?> produtos</strong>.
                </p>
                <div class="well well-sm" style="display: inline-block; margin: 20px 0;">
                    <span class="text-danger" style="font-size: 18px; font-weight: 600;">
                        <?php echo $productLimitInfo['current']; ?> / <?php echo $productLimitInfo['limit']; ?> produtos utilizados
                    </span>
                </div>
                <br>
                <a href="<?php echo root_url(); ?>conta/planos" class="btn btn-success btn-lg">
                    <i class="fa fa-arrow-up"></i> Fazer Upgrade do Plano
                </a>
            </div>
        </div>
        
    <?php else: ?>
        <?php // ========== FIM: Verificação de Limite SaaS ========== ?>
        
        <!-- Formulário normal de criação -->
        <div class="box box-info<?php echo create_box_state(); ?>">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <span class="fa fa-fw fa-plus"></span> <?php echo sprintf(trans('text_add_new'), trans('text_product')); ?>
                </h3>
                <button type="button" class="btn btn-box-tool add-new-btn" data-widget="collapse" data-collapse="true">
                    <i class="fa <?php echo !create_box_state() ? 'fa-minus' : 'fa-plus'; ?>"></i>
                </button>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <p><span class="fa fa-warning"></span> <?php echo $error_message; ?></p>
                </div>
            <?php elseif (isset($success_message)): ?>
                <div class="alert alert-success">
                    <p><span class="fa fa-check"></span> <?php echo $success_message; ?></p>
                </div>
            <?php endif; ?>

            <!-- Include Product Form -->
            <?php include('../_inc/template/product_create_form.php'); ?>
        </div>
        
    <?php endif; ?>
<?php endif; ?>

<?php 
// ... resto do código ...

// Antes do footer.php, adicionar:
?>

<!-- SaaS Limit Modal -->
<?php include('../_inc/template/partials/limit_reached_modal.php'); ?>

<!-- SaaS Limit Info para JavaScript -->
<script>
var saasProductLimit = <?php echo json_encode($productLimitInfo); ?>;
</script>

<?php include ("footer.php"); ?>
```

---

### Endpoint AJAX: `_inc/api/check_saas_limit.php`

```php
<?php
/**
 * API: Verificar Limite SaaS
 * GET /modernpos/_inc/api/check_saas_limit.php?type=products
 */

session_start();

// Carregar init
$initPath = realpath(__DIR__ . '/../../_init.php');
if (!file_exists($initPath)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Sistema não configurado']);
    exit;
}
include($initPath);

// Carregar helper de limites
include(__DIR__ . '/../saas_limits_check.php');

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Verificar autenticação
if (!function_exists('is_loggedin') || !is_loggedin()) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// Obter tipo
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

if (!in_array($type, ['products', 'customers', 'users', 'stores'])) {
    echo json_encode(['success' => false, 'error' => 'Tipo inválido. Use: products, customers, users, stores']);
    exit;
}

// Obter informações de limite
$info = get_limit_info($type);

// Retornar resposta
echo json_encode([
    'success' => true,
    'type' => $type,
    'is_saas' => $info['is_saas'],
    'can_create' => $info['can_create'],
    'current' => $info['current'],
    'limit' => $info['limit'],
    'unlimited' => $info['unlimited'],
    'percentage' => $info['percentage'],
    'plan_name' => $info['plan_name']
]);
```

---

## 🧪 Testes

### Cenário 1: Limite Atingido
```
Configuração:
- Plano: max_products = 10
- Loja A: 4 produtos
- Loja B: 6 produtos
- Total: 10 (limite atingido)

Resultado esperado:
✅ Modal de aviso ao acessar /admin/product.php?box_state=open
✅ Form de criação não é exibido
✅ Box vermelho com informação do limite
✅ Botão "Fazer Upgrade" visível
```

### Cenário 2: Abaixo do Limite
```
Configuração:
- Plano: max_products = 10
- Loja A: 3 produtos
- Loja B: 4 produtos
- Total: 7 (pode criar +3)

Resultado esperado:
✅ Form de criação exibido normalmente
✅ Nenhuma modal exibida
```

### Cenário 3: Plano Ilimitado
```
Configuração:
- Plano: products_unlimited = 1
- Total: qualquer quantidade

Resultado esperado:
✅ Form de criação sempre exibido
✅ Nenhum bloqueio
```

### Cenário 4: Não é Tenant SaaS
```
Configuração:
- Usuário sem tenant_id
- Instalação standalone

Resultado esperado:
✅ Comportamento normal (sem verificação)
✅ Nenhum bloqueio
```

### Cenário 5: Clientes
```
Configuração:
- Plano: clients_limit = 5
- Loja A: 3 clientes
- Loja B: 2 clientes
- Total: 5 (limite atingido)

Resultado esperado:
✅ Modal de aviso ao acessar /admin/customer.php?box_state=open
✅ Form de criação não é exibido
```

---

## ✅ Checklist Final

### Fase 1: Helper
- [ ] Criar arquivo `_inc/saas_limits_check.php`
- [ ] Implementar `get_tenant_id_from_session()`
- [ ] Implementar `get_tenant_plan_limits()`
- [ ] Implementar `get_tenant_store_ids()`
- [ ] Implementar `get_tenant_usage()`
- [ ] Implementar `can_create_product()`
- [ ] Implementar `can_create_customer()`
- [ ] Implementar `get_limit_info()`
- [ ] Testar funções isoladamente

### Fase 2: Modal
- [ ] Criar arquivo `_inc/template/partials/limit_reached_modal.php`
- [ ] Implementar HTML da modal (AdminLTE style)
- [ ] Implementar função `showSaasLimitModal()`
- [ ] Implementar função `checkSaasLimitBeforeCreate()`
- [ ] Testar modal manualmente

### Fase 3: Página de Produtos
- [ ] Modificar `admin/product.php`
- [ ] Incluir helper no início
- [ ] Adicionar condicional no form
- [ ] Incluir modal no final
- [ ] Adicionar variável JS `saasProductLimit`
- [ ] Testar com limite atingido
- [ ] Testar com limite disponível
- [ ] Testar com plano ilimitado

### Fase 4: Página de Clientes
- [ ] Modificar `admin/customer.php`
- [ ] Incluir helper no início
- [ ] Adicionar condicional no form
- [ ] Incluir modal no final
- [ ] Adicionar variável JS `saasCustomerLimit`
- [ ] Testar com limite atingido
- [ ] Testar com limite disponível

### Fase 5: Endpoint AJAX
- [ ] Criar `_inc/api/check_saas_limit.php`
- [ ] Testar endpoint via navegador
- [ ] Testar resposta JSON

### Fase 6: Validação JavaScript
- [ ] Identificar controllers AngularJS
- [ ] Adicionar verificação antes do submit
- [ ] Testar prevenção de submit

### Fase 7: Validação Backend
- [ ] Localizar função de save de produto
- [ ] Adicionar verificação `can_create_product()`
- [ ] Localizar função de save de cliente
- [ ] Adicionar verificação `can_create_customer()`
- [ ] Testar bloqueio via API/form direto

### Fase 8: Testes Finais
- [ ] Testar todos os cenários documentados
- [ ] Testar em múltiplas lojas
- [ ] Verificar estilo visual
- [ ] Verificar responsividade
- [ ] Corrigir bugs encontrados

---

## 📚 Referências

- **Dashboard (estilo):** `/admin/dashboard.php`
- **Formulário de Produto:** `/admin/product.php`
- **Formulário de Cliente:** `/admin/customer.php`
- **Estrutura de Modal:** Bootstrap 3 + AdminLTE 2
- **Tabela de Planos:** `plans`
- **Tabela de Tenants:** `tenants`
- **Tabela de Lojas:** `stores`
- **Tabela de Produtos por Loja:** `product_to_store`
- **Tabela de Clientes por Loja:** `customer_to_store`

---

> **Nota:** Este documento serve como guia completo para implementação. Cada fase pode ser executada sequencialmente, e o checklist deve ser atualizado conforme o progresso.
