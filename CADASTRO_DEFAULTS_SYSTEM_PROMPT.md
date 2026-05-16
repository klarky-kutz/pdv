# PROMPT: Sistema de Defaults Automáticos no Cadastro ModernPOS

## Contexto do Sistema

**Sistema**: ModernPOS - Multi-tenant SaaS PDV (Ponto de Venda)
**Funcionalidade**: Aplicação automática de configurações padrão ao criar novos usuários/lojas
**Localização**: 
- Frontend: `http://localhost/modernpos/cadastro.php`
- Backend: `http://localhost/modernpos/process_register.php`
- Classe de Defaults: `http://localhost/saas/includes/ModernposDefaults.php`

---

## 1. VISÃO GERAL DO SISTEMA DE DEFAULTS

### O que são "Defaults"?
São configurações padrão que **DEVEM** ser aplicadas automaticamente quando:
1. Um novo usuário se cadastra no sistema
2. Uma nova loja é criada (via modal ou cadastro)

### Por que isso é importante?
Sem os defaults, uma loja nova ficaria **sem**:
- ❌ Métodos de pagamento (não poderia vender)
- ❌ Moeda (não conseguiria precificar)
- ❌ Unidades de medida (não cadastraria produtos)
- ❌ Boxes/Caixas (não abriria caixa)
- ❌ Cliente padrão "Balcão" (POS não funcionaria)

---

## 2. FLUXO DE CADASTRO ATUAL

### Passo 1: Usuário preenche formulário (cadastro.php)
```
┌─────────────────────────────────────┐
│   Formulário de Cadastro           │
├─────────────────────────────────────┤
│ Nome Completo                       │
│ Email                               │
│ WhatsApp                            │
│ CPF/CNPJ                           │
│ Nome da Loja                        │
│ Segmento (ex: Varejo)              │
└─────────────────────────────────────┘
         │
         ▼
```

### Passo 2: Dados são enviados para process_register.php
```php
// Arquivo: modernpos/process_register.php
// Linhas 79-131

// A. Criar usuário
INSERT INTO users (username, email, mobile, password, ...)

// B. Criar tenant (empresa)
INSERT INTO tenants (company_name, owner_user_id, ...)

// C. Criar primeira loja
INSERT INTO stores (name, code_name, country, currency, ...)

// D. APLICAR DEFAULTS (CRUCIAL!)
ModernposDefaults::applyDefaultsToStore($db, $store_id);
```

### Passo 3: Defaults são aplicados automaticamente
```
┌─────────────────────────────────────────────┐
│  ModernposDefaults::applyDefaultsToStore()  │
├─────────────────────────────────────────────┤
│ ✅ Currency ID 18 (BRL) vinculada          │
│ ✅ Payment Methods (7 métodos)              │
│    - Dinheiro (ativo)                       │
│    - Cartão Crédito (ativo)                │
│    - Cartão Débito (ativo)                 │
│    - PIX (ativo)                           │
│    - Crédito em Conta (inativo)           │
│    - Vale-Presente (inativo)              │
│    - Pagamento na Entrega (inativo)       │
│ ✅ Unidade padrão (uni.)                   │
│ ✅ Box/Caixa padrão                        │
│ ✅ Cliente "Balcão"                        │
│ ✅ Preferências da loja (serializadas)    │
└─────────────────────────────────────────────┘
```

---

## 3. ESTRUTURA DA CLASSE ModernposDefaults

### Localização
```
saas/includes/ModernposDefaults.php
```

### Métodos Principais

#### 3.1. `applyDefaultsToStore(PDO $pdo, int $storeId)`
**Propósito**: Aplicar TODOS os defaults em uma loja específica

**O que faz**:
```php
public static function applyDefaultsToStore(PDO $pdo, int $storeId): void
{
    // 1. Busca configurações default
    $defaults = self::getDefaults($pdo);
    
    // 2. Atualiza stores.preference (serializado)
    UPDATE stores SET preference = serialize($defaults['preference'])
    
    // 3. Vincula métodos de pagamento
    INSERT INTO pmethod_to_store (ppmethod_id, store_id, status, sort_order)
    
    // 4. Vincula moeda (Currency ID 18 = BRL)
    INSERT INTO currency_to_store (currency_id, store_id, status, sort_order)
    
    // 5. Vincula unidade padrão
    INSERT INTO unit_to_store (uunit_id, store_id, status, sort_order)
    
    // 6. Vincula box/caixa padrão
    INSERT INTO box_to_store (box_id, store_id, status, sort_order)
    
    // 7. Cria cliente "Balcão"
    self::ensureDefaultCustomerForStore($pdo, $storeId);
}
```

#### 3.2. `getDefaults(PDO $pdo)`
**Propósito**: Retornar array com todas as configurações padrão

**Estratégia em 3 níveis**:
```
1. Tenta ler da tabela `modernpos_store_defaults` (cache de configurações)
   ↓ Se não existir
2. Tenta construir a partir dos dados da Loja 1 (seed)
   ↓ Se não existir
3. Usa hardcoded defaults (último recurso)
```

**Retorno**:
```php
[
    'preference' => [
        'tax' => 0,
        'tax_method' => 'exclusive',
        'timezone' => 'America/Sao_Paulo',
        'show_stock' => 1,
        'show_currency' => 1,
        // ... 50+ configurações
    ],
    'pmethods' => [
        1 => ['status' => 0, 'sort_order' => 7], // Pagamento na Entrega
        3 => ['status' => 0, 'sort_order' => 6], // Vale-presente
        4 => ['status' => 0, 'sort_order' => 5], // Crédito em Conta
        5 => ['status' => 1, 'sort_order' => 2], // Cartão Crédito (ativo)
        6 => ['status' => 1, 'sort_order' => 4], // PIX (ativo)
        7 => ['status' => 1, 'sort_order' => 1], // Dinheiro (ativo)
        9 => ['status' => 1, 'sort_order' => 3], // Cartão Débito (ativo)
    ],
    'currencies' => [
        18 => ['status' => 1, 'sort_order' => 0] // Real Brasileiro (BRL)
    ],
    'units' => [
        1 => ['status' => 1, 'sort_order' => 0] // Unidade padrão
    ],
    'boxes' => [
        1 => ['status' => 1, 'sort_order' => 0] // Caixa Principal
    ]
]
```

#### 3.3. `getSeedCurrencies(PDO $pdo)`
**Propósito**: Determinar qual moeda usar para novas lojas

**Lógica atualizada** (IMPORTANTE):
```php
protected static function getSeedCurrencies(PDO $pdo): array
{
    // Prioridade 1: Currency ID 18 (Real Brasileiro - BRL)
    // Este é o padrão FIXO para sistema brasileiro
    $stmt = $pdo->prepare('SELECT currency_id, code FROM currency WHERE currency_id = 18');
    $stmt->execute();
    $row = $stmt->fetch();
    
    if ($row && $row['code'] === 'BRL') {
        return [18 => ['status' => 1, 'sort_order' => 0]];
    }
    
    // Fallback 1: Busca qualquer BRL na tabela
    $stmt = $pdo->prepare('SELECT currency_id FROM currency WHERE code = "BRL"');
    // ...
    
    // Fallback 2: Busca da loja 1 (última opção)
    // ...
}
```

**Por que Currency ID 18?**
- É o Real Brasileiro cadastrado no banco de dados
- Código: `BRL`
- Símbolo: `R$`
- Sistema é brasileiro, então SEMPRE usa BRL para novas lojas

#### 3.4. `getHardcodedPmethodDefaults()`
**Propósito**: Definir quais métodos de pagamento vêm ativos/inativos

```php
protected static function getHardcodedPmethodDefaults(): array
{
    return [
        // INATIVOS por padrão (usuário ativa manualmente se precisar)
        1 => ['status' => 0, 'sort_order' => 7], // Pagamento na Entrega (cod)
        3 => ['status' => 0, 'sort_order' => 6], // Vale-presente (gift_card)
        4 => ['status' => 0, 'sort_order' => 5], // Crédito em Conta (credit)
        
        // ATIVOS por padrão (mais usados no Brasil)
        5 => ['status' => 1, 'sort_order' => 2], // Cartão de Crédito
        6 => ['status' => 1, 'sort_order' => 4], // Pix
        7 => ['status' => 1, 'sort_order' => 1], // Dinheiro
        9 => ['status' => 1, 'sort_order' => 3], // Cartão de Débito
    ];
}
```

**Justificativa**:
- Métodos ativos (5,6,7,9) = 95% dos pagamentos no varejo brasileiro
- Métodos inativos (1,3,4) = Casos específicos, ativados sob demanda

---

## 4. TABELAS DE BANCO DE DADOS ENVOLVIDAS

### 4.1. Tabela: `currency`
```sql
-- Contém todas as moedas disponíveis no sistema
CREATE TABLE currency (
  currency_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100),    -- Ex: "Real Brasileiro"
  code VARCHAR(10),     -- Ex: "BRL"
  symbol VARCHAR(10),   -- Ex: "R$"
  status TINYINT,
  sort_order INT
);

-- Registro CRUCIAL (Currency ID 18)
INSERT INTO currency VALUES (18, 'Real Brasileiro', 'BRL', 'R$', 1, 0);
```

### 4.2. Tabela: `currency_to_store`
```sql
-- Vincula moedas às lojas
CREATE TABLE currency_to_store (
  id INT PRIMARY KEY AUTO_INCREMENT,
  currency_id INT,      -- FK para currency.currency_id
  store_id INT,         -- FK para stores.store_id
  status TINYINT,       -- 1 = ativa, 0 = inativa
  sort_order INT
);

-- Exemplo: Loja 123 tem BRL ativa
INSERT INTO currency_to_store VALUES (NULL, 18, 123, 1, 0);
```

### 4.3. Tabela: `pmethods`
```sql
-- Métodos de pagamento globais
CREATE TABLE pmethods (
  pmethod_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100),
  code_name VARCHAR(50)
);

-- Registros padrão
INSERT INTO pmethods VALUES 
(1, 'Pagamento na Entrega', 'cod'),
(3, 'Vale-Presente', 'gift_card'),
(4, 'Crédito em Conta', 'credit'),
(5, 'Cartão de Crédito', 'card_credit'),
(6, 'PIX', 'pix'),
(7, 'Dinheiro', 'cash'),
(9, 'Cartão de Débito', 'card_debit');
```

### 4.4. Tabela: `pmethod_to_store`
```sql
-- Vincula métodos de pagamento às lojas
CREATE TABLE pmethod_to_store (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ppmethod_id INT,      -- FK para pmethods.pmethod_id
  store_id INT,         -- FK para stores.store_id
  status TINYINT,       -- 1 = ativo, 0 = inativo
  sort_order INT
);

-- Exemplo: Loja 123 tem PIX, Dinheiro e Cartões ativos
INSERT INTO pmethod_to_store VALUES 
(NULL, 5, 123, 1, 2),  -- Cartão Crédito
(NULL, 6, 123, 1, 4),  -- PIX
(NULL, 7, 123, 1, 1),  -- Dinheiro
(NULL, 9, 123, 1, 3);  -- Cartão Débito
```

### 4.5. Tabela: `stores`
```sql
-- Lojas do sistema
CREATE TABLE stores (
  store_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(200),
  code_name VARCHAR(100),    -- URL-friendly
  tenant_id INT,             -- FK para tenants.id (multi-tenancy)
  preference TEXT,           -- Serialized PHP array com configs
  status TINYINT,
  created_at DATETIME
);
```

### 4.6. Tabela: `modernpos_store_defaults`
```sql
-- Cache de configurações default (otimização)
CREATE TABLE modernpos_store_defaults (
  id INT PRIMARY KEY AUTO_INCREMENT,
  preference_json TEXT,      -- JSON com preferences
  pmethods_json TEXT,        -- JSON com pmethods, currencies, units, boxes
  created_at DATETIME,
  updated_at DATETIME
);

-- Estrutura do pmethods_json:
{
  "pmethods": {
    "1": {"status": 0, "sort_order": 7},
    "5": {"status": 1, "sort_order": 2},
    // ...
  },
  "currencies": {
    "18": {"status": 1, "sort_order": 0}
  },
  "units": {
    "1": {"status": 1, "sort_order": 0}
  },
  "boxes": {
    "1": {"status": 1, "sort_order": 0}
  }
}
```

---

## 5. COMO O SISTEMA FUNCIONA NA PRÁTICA

### Cenário 1: Novo usuário se cadastra

```
Usuário acessa: http://localhost/modernpos/cadastro.php
         ↓
Preenche: Nome, Email, WhatsApp, CPF, Nome da Loja
         ↓
Clica em "Criar Conta"
         ↓
Requisição POST para: process_register.php
         ↓
┌────────────────────────────────────────────┐
│   process_register.php executa:           │
├────────────────────────────────────────────┤
│ 1. INSERT INTO users                       │
│ 2. INSERT INTO tenants                     │
│ 3. INSERT INTO stores                      │
│ 4. ModernposDefaults::applyDefaultsToStore │ ← AQUI!
└────────────────────────────────────────────┘
         ↓
┌────────────────────────────────────────────┐
│ ModernposDefaults::applyDefaultsToStore    │
│ executa:                                   │
├────────────────────────────────────────────┤
│ 1. getDefaults($pdo)                       │
│    ├─ getSeedCurrencies() → [18]          │
│    ├─ getHardcodedPmethodDefaults()       │
│    └─ getSeedUnits(), getSeedBoxes()      │
│                                            │
│ 2. INSERT INTO currency_to_store           │
│    → Vincula Currency 18 (BRL)            │
│                                            │
│ 3. INSERT INTO pmethod_to_store (7x)       │
│    → Vincula métodos 1,3,4,5,6,7,9        │
│                                            │
│ 4. INSERT INTO unit_to_store               │
│ 5. INSERT INTO box_to_store                │
│ 6. ensureDefaultCustomerForStore()         │
│    → Cria cliente "Balcão"                │
└────────────────────────────────────────────┘
         ↓
✅ Loja está pronta para uso!
   - Pode registrar vendas (tem pmethods)
   - Pode cadastrar produtos (tem units)
   - Pode abrir caixa (tem boxes)
   - POS funciona (tem cliente balcão)
```

### Cenário 2: Admin cria nova loja pela modal

```
Admin acessa: http://localhost/modernpos/conta/lojas
         ↓
Clica em "+ Nova Loja"
         ↓
Preenche modal: Nome, Endereço, etc.
         ↓
Clica em "Criar Loja"
         ↓
AJAX POST para: _inc/account_store.php
         ↓
┌────────────────────────────────────────────┐
│   account_store.php executa:               │
├────────────────────────────────────────────┤
│ 1. Validações                              │
│ 2. INSERT INTO stores                      │
│ 3. ModernposDefaults::applyDefaultsToStore │ ← MESMA LÓGICA!
└────────────────────────────────────────────┘
         ↓
✅ Loja criada com defaults automáticos
```

---

## 6. CORREÇÕES IMPLEMENTADAS RECENTEMENTE

### Problema 1: `bindValue()` não existia
**Sintoma**: Erro "Call to undefined method Database::bindValue()"

**Causa**: A classe `Database` (wrapper do PDO) não tinha o método `bindValue()`, mas `ModernposDefaults` estava tentando usar.

**Solução 1** (Tentada - não funcionou completamente):
```php
// modernpos/_inc/lib/database.php
public function bindValue($param, $value, $type = PDO::PARAM_STR) {
    if ($this->db) {
        return $this->db->bindValue($param, $value, $type);
    }
    return false;
}
```

**Problema**: O wrapper `Database` retorna um `PDOStatement` nativo quando você chama `prepare()`, não outro objeto `Database`. Então `bindValue()` ainda não funcionava.

**Solução 2** (IMPLEMENTADA - funciona):
```php
// ANTES (com bindValue)
$stmt = $pdo->prepare('INSERT INTO currency_to_store VALUES (:cid, :sid, :st, :so)');
$stmt->bindValue(':cid', $currencyId, PDO::PARAM_INT);
$stmt->bindValue(':sid', $storeId, PDO::PARAM_INT);
$stmt->bindValue(':st', $status, PDO::PARAM_INT);
$stmt->bindValue(':so', $sortOrder, PDO::PARAM_INT);
$stmt->execute();

// DEPOIS (com array no execute)
$stmt = $pdo->prepare('INSERT INTO currency_to_store VALUES (?, ?, ?, ?)');
$stmt->execute([$currencyId, $storeId, $status, $sortOrder]);
```

**Status**: ✅ Corrigido em todos os métodos principais (preference, pmethods, currencies, units, boxes)

### Problema 2: Currency não estava vindo da fonte correta
**Sintoma**: Lojas novas não tinham Currency ID 18 (BRL)

**Causa**: `getSeedCurrencies()` tentava buscar da Loja 1 primeiro, e se não encontrasse, buscava qualquer BRL. Não garantia que seria o ID 18.

**Solução**:
```php
// ANTES
$stmt = $pdo->prepare('SELECT currency_id FROM currency WHERE code = "BRL" LIMIT 1');
// Podia retornar ID 1, 5, 18... qualquer um

// DEPOIS
// Prioridade 1: SEMPRE tenta Currency ID 18 primeiro
$stmt = $pdo->prepare('SELECT currency_id FROM currency WHERE currency_id = 18');
$stmt->execute();
if ($row && $row['code'] === 'BRL') {
    return [18 => ['status' => 1, 'sort_order' => 0]];
}
```

**Status**: ✅ Corrigido

---

## 7. VALIDAÇÃO E TESTES

### Como verificar se os defaults foram aplicados corretamente?

#### Teste 1: Verificar Currency
```sql
-- Após criar loja ID 123
SELECT c.currency_id, c.name, c.code, c.symbol, c2s.status
FROM currency_to_store c2s
JOIN currency c ON c.currency_id = c2s.currency_id
WHERE c2s.store_id = 123;

-- Resultado esperado:
-- currency_id | name              | code | symbol | status
-- 18          | Real Brasileiro   | BRL  | R$     | 1
```

#### Teste 2: Verificar Payment Methods
```sql
SELECT pm.pmethod_id, pm.name, p2s.status, p2s.sort_order
FROM pmethod_to_store p2s
JOIN pmethods pm ON pm.pmethod_id = p2s.ppmethod_id
WHERE p2s.store_id = 123
ORDER BY p2s.sort_order;

-- Resultado esperado (7 linhas):
-- pmethod_id | name                  | status | sort_order
-- 7          | Dinheiro              | 1      | 1
-- 5          | Cartão de Crédito     | 1      | 2
-- 9          | Cartão de Débito      | 1      | 3
-- 6          | PIX                   | 1      | 4
-- 4          | Crédito em Conta      | 0      | 5
-- 3          | Vale-Presente         | 0      | 6
-- 1          | Pagamento na Entrega  | 0      | 7
```

#### Teste 3: Verificar Preferências
```sql
SELECT preference FROM stores WHERE store_id = 123;

-- Deve retornar um array serializado PHP
-- Deserialize com: unserialize($row['preference'])
-- Deve conter chaves como: tax, tax_method, timezone, show_stock, etc.
```

#### Teste 4: Verificar logs de erro
```bash
# Windows
Get-Content C:\xampp\apache\logs\error.log -Tail 20

# Buscar por:
# [ModernposDefaults] Falha ao...
# [account_store] AVISO: Loja X criada mas defaults incompletos

# Se aparecer "Métodos: 0, Moedas: 0" = PROBLEMA!
# Se aparecer "Métodos: 7, Moedas: 1" = OK!
```

---

## 8. CÓDIGO COMPLETO DE EXEMPLO

### Aplicar defaults manualmente (PHP)
```php
<?php
require_once __DIR__ . '/../saas/includes/ModernposDefaults.php';
require_once __DIR__ . '/config.php';

// Conectar ao banco
$pdo = new PDO(
    "mysql:host={$sql_details['host']};dbname={$sql_details['db']}",
    $sql_details['user'],
    $sql_details['pass']
);

// ID da loja que precisa de defaults
$storeId = 123;

// Aplicar defaults
try {
    ModernposDefaults::applyDefaultsToStore($pdo, $storeId);
    echo "✅ Defaults aplicados com sucesso na loja {$storeId}!\n";
    
    // Verificar
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM pmethod_to_store WHERE store_id = ?');
    $stmt->execute([$storeId]);
    echo "Métodos de pagamento vinculados: " . $stmt->fetchColumn() . "\n";
    
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM currency_to_store WHERE store_id = ?');
    $stmt->execute([$storeId]);
    echo "Moedas vinculadas: " . $stmt->fetchColumn() . "\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
```

---

## 9. CHECKLIST DE VERIFICAÇÃO

Ao implementar ou debugar o sistema de defaults, use esta checklist:

### Backend
- [ ] `ModernposDefaults::applyDefaultsToStore()` é chamado em `process_register.php`?
- [ ] `ModernposDefaults::applyDefaultsToStore()` é chamado em `_inc/account_store.php` (action=create)?
- [ ] `getSeedCurrencies()` prioriza Currency ID 18?
- [ ] Todos os `bindValue()` foram substituídos por `execute([...])`?
- [ ] Tabela `modernpos_store_defaults` existe?

### Database
- [ ] Currency ID 18 existe e é BRL?
- [ ] Pmethods IDs 1,3,4,5,6,7,9 existem?
- [ ] Units tem pelo menos 1 registro?
- [ ] Boxes tem pelo menos 1 registro?

### Testes
- [ ] Cadastrar novo usuário → verificar currency_to_store
- [ ] Cadastrar novo usuário → verificar pmethod_to_store (7 registros)
- [ ] Criar loja pela modal → mesmos resultados
- [ ] Logs de erro NÃO mostram "defaults incompletos"

---

## 10. TROUBLESHOOTING

### Problema: Loja criada mas sem currency
**Diagnóstico**:
```sql
SELECT * FROM currency_to_store WHERE store_id = [ID_DA_LOJA];
-- Se retornar 0 linhas = defaults não aplicados
```

**Soluções**:
1. Verificar logs: `C:\xampp\apache\logs\error.log`
2. Aplicar manualmente: `ModernposDefaults::applyDefaultsToStore($pdo, $storeId)`
3. Verificar se Currency ID 18 existe: `SELECT * FROM currency WHERE currency_id = 18`

### Problema: Loja criada mas sem payment methods
**Diagnóstico**:
```sql
SELECT * FROM pmethod_to_store WHERE store_id = [ID_DA_LOJA];
-- Deve retornar 7 linhas
```

**Soluções**:
1. Verificar se pmethods existem: `SELECT * FROM pmethods WHERE pmethod_id IN (1,3,4,5,6,7,9)`
2. Aplicar manualmente defaults
3. Verificar logs de erro

### Problema: Erro "bindValue() não existe"
**Solução**: Trocar todas as chamadas para formato array:
```php
// ❌ NÃO FUNCIONA
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();

// ✅ FUNCIONA
$stmt->execute([$id]);
```

---

## 11. ARQUITETURA E BOAS PRÁTICAS

### Por que usar uma classe centralizada?
```
ANTES (sem ModernposDefaults):
- Cada arquivo tinha sua própria lógica de defaults
- Inconsistências entre cadastro.php e modal de lojas
- Difícil de manter e atualizar

DEPOIS (com ModernposDefaults):
✅ Uma única fonte de verdade
✅ Defaults consistentes em todo o sistema
✅ Fácil de atualizar (muda em 1 lugar)
✅ Testável e auditável
```

### Por que cachear em `modernpos_store_defaults`?
```
Performance:
- Sem cache: 5-10 queries para montar defaults
- Com cache: 1 query (ler JSON)

Flexibilidade:
- Admin pode alterar defaults via interface
- Mudanças refletem em novas lojas instantaneamente
```

---

## 12. EXEMPLO COMPLETO: CRIAR USUÁRIO COM DEFAULTS

### Frontend (cadastro.php)
```html
<form id="register-form" method="POST" action="process_register.php">
  <input type="text" name="nome" placeholder="Nome Completo" required>
  <input type="email" name="email" placeholder="Email" required>
  <input type="tel" name="whatsapp" placeholder="WhatsApp" required>
  <input type="text" name="documento" placeholder="CPF ou CNPJ" required>
  <input type="text" name="nome_loja" placeholder="Nome da Loja" required>
  <select name="segmento">
    <option value="Varejo">Varejo</option>
    <option value="Serviços">Serviços</option>
  </select>
  <button type="submit">Criar Conta</button>
</form>
```

### Backend (process_register.php)
```php
<?php
session_start();
require_once("config.php");
require_once __DIR__ . '/../saas/includes/ModernposDefaults.php';

// Conecta ao banco
$db = new PDO($dsn, $user, $pass);
$db->beginTransaction();

// 1. Criar usuário
$stmt = $db->prepare("INSERT INTO users (username, email, mobile, password) VALUES (?, ?, ?, ?)");
$stmt->execute([
    $_POST['nome'],
    $_POST['email'],
    $_POST['whatsapp'],
    password_hash('123456', PASSWORD_DEFAULT)
]);
$user_id = $db->lastInsertId();

// 2. Criar tenant
$stmt = $db->prepare("INSERT INTO tenants (company_name, owner_user_id, plan_id, subscription_status) VALUES (?, ?, 1, 'trial')");
$stmt->execute([$_POST['nome_loja'], $user_id]);
$tenant_id = $db->lastInsertId();

// 3. Atualizar usuário com tenant
$db->prepare("UPDATE users SET tenant_id = ? WHERE id = ?")->execute([$tenant_id, $user_id]);

// 4. Criar loja
$code_name = strtolower(preg_replace('/[^a-z0-9]/', '_', $_POST['nome_loja']));
$stmt = $db->prepare("INSERT INTO stores (name, code_name, tenant_id, country, status) VALUES (?, ?, ?, 'BR', 1)");
$stmt->execute([$_POST['nome_loja'], $code_name, $tenant_id]);
$store_id = $db->lastInsertId();

// 5. 🔥 APLICAR DEFAULTS (CRUCIAL!)
try {
    ModernposDefaults::applyDefaultsToStore($db, $store_id);
    
    // Verificação (opcional mas recomendado)
    $verify = $db->prepare('SELECT COUNT(*) FROM currency_to_store WHERE store_id = ?');
    $verify->execute([$store_id]);
    $currencyCount = $verify->fetchColumn();
    
    if ($currencyCount === 0) {
        error_log("AVISO: Loja {$store_id} sem currency!");
    }
} catch (Exception $e) {
    error_log("Erro ao aplicar defaults: " . $e->getMessage());
    // Continua mesmo com erro (loja fica criada)
}

// 6. Vincular usuário à loja
$db->prepare("INSERT INTO user_to_store (user_id, store_id) VALUES (?, ?)")->execute([$user_id, $store_id]);

$db->commit();

// Login automático
$_SESSION['user_id'] = $user_id;
$_SESSION['store_id'] = $store_id;

echo json_encode(['status' => 'success', 'redirect' => 'store_select.php']);
```

---

## CONCLUSÃO

O sistema de defaults do ModernPOS garante que **toda loja nova** seja criada com:
- ✅ Currency ID 18 (Real Brasileiro)
- ✅ 7 métodos de pagamento configurados
- ✅ Unidades e boxes padrão
- ✅ Cliente "Balcão"
- ✅ Preferências iniciais

**Pontos críticos**:
1. SEMPRE chamar `ModernposDefaults::applyDefaultsToStore()` após criar loja
2. Currency ID 18 DEVE existir no banco como BRL
3. Usar `execute([...])` ao invés de `bindValue()` para compatibilidade
4. Verificar logs após cadastro para detectar falhas

**Arquivo principal**: `saas/includes/ModernposDefaults.php`
