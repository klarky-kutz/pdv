# Sistema de Preferências de Gateway de Pagamento

## Visão Geral

O sistema agora respeita completamente as preferências configuradas na tabela `saas_payment_gateways`, incluindo:

- **default_card_gateway**: Qual gateway usar para cartão (ex: Stripe)
- **default_pix_gateway**: Qual gateway usar para PIX (ex: Asaas, Mercado Pago, PIX Manual)
- **default_boleto_gateway**: Qual gateway usar para Boleto (ex: Asaas, Mercado Pago)
- **stripe_billing_mode**: Modo de cobrança Stripe (`one_off` ou `subscription`)
- **Configurações específicas de cada gateway** (chaves API, ambiente, etc)

## Arquitetura

### Classe PaymentGatewayConfig

**Localização**: `conta/_inc/PaymentGatewayConfig.php`

Esta classe centraliza toda a lógica de carregamento de configurações de gateways e **respeita as preferências** definidas no banco de dados.

#### Métodos Principais:

```php
// Busca gateway específico
PaymentGatewayConfig::get($pdo, $tenantId, 'stripe');

// Busca gateway padrão para CARTÃO (respeita default_card_gateway)
PaymentGatewayConfig::getDefaultCardGateway($pdo, $tenantId);

// Busca gateway padrão para PIX (respeita default_pix_gateway)
PaymentGatewayConfig::getDefaultPixGateway($pdo, $tenantId);

// Busca gateway padrão para BOLETO (respeita default_boleto_gateway)
PaymentGatewayConfig::getDefaultBoletoGateway($pdo, $tenantId);

// Lista todos os gateways habilitados
PaymentGatewayConfig::getAllEnabled($pdo, $tenantId);
```

## Como as Preferências São Respeitadas

### 1. Seleção de Gateway no Checkout

Quando o usuário escolhe um método de pagamento (Cartão, PIX ou Boleto), o sistema:

1. **Verifica se existe coluna de preferência** (ex: `default_card_gateway`)
2. **Busca gateway marcado como padrão** (`WHERE default_card_gateway = 1`)
3. **Usa esse gateway prioritariamente**
4. **Fallback**: Se não houver preferência definida, busca primeiro gateway habilitado

**Exemplo - PIX**:
```sql
-- 1. Verifica preferência
SELECT gateway 
FROM saas_payment_gateways 
WHERE tenant_id = 1 
  AND is_enabled = 1
  AND default_pix_gateway = 1;

-- Se retornar 'asaas', usa Asaas
-- Se retornar 'mercado_pago', usa Mercado Pago
-- Se retornar 'pix_manual', usa PIX Manual
```

### 2. Configurações Específicas do Gateway

Cada gateway tem suas próprias configurações respeitadas:

#### Stripe
- `stripe_secret_key`: Chave secreta
- `stripe_publishable_key`: Chave pública
- `stripe_currency`: Moeda (padrão: BRL)
- **`stripe_billing_mode`**: **one_off** (pagamento único) ou **subscription** (assinatura recorrente)
- `environment`: sandbox ou production

#### Asaas
- `asaas_api_key`: Chave da API
- `asaas_wallet_id`: ID da carteira (opcional)
- `environment`: sandbox ou production

#### Mercado Pago
- `mp_access_token`: Access Token
- `mp_public_key`: Public Key
- `environment`: sandbox ou production

#### PIX Manual
- `pix_chave`: Chave PIX
- `pix_titular`: Nome do titular
- `pix_cidade`: Cidade do titular
- `whatsapp_support`: Número WhatsApp de suporte
- `whatsapp_message`: Mensagem padrão WhatsApp

## Estrutura da Tabela saas_payment_gateways

### Colunas Principais:
```sql
CREATE TABLE saas_payment_gateways (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    gateway VARCHAR(50) NOT NULL, -- 'stripe', 'asaas', 'mercado_pago', 'pix_manual'
    gateway_display_name VARCHAR(100),
    is_enabled TINYINT(1) DEFAULT 0,
    environment VARCHAR(20) DEFAULT 'sandbox', -- 'sandbox' ou 'production'
    
    -- Preferências (colunas opcionais, fallback se não existirem)
    default_card_gateway TINYINT(1) DEFAULT 0,
    default_pix_gateway TINYINT(1) DEFAULT 0,
    default_boleto_gateway TINYINT(1) DEFAULT 0,
    
    -- Stripe
    stripe_secret_key VARCHAR(255),
    stripe_publishable_key VARCHAR(255),
    stripe_currency VARCHAR(3) DEFAULT 'BRL',
    stripe_billing_mode VARCHAR(20) DEFAULT 'one_off', -- 'one_off' ou 'subscription'
    
    -- Asaas
    asaas_api_key VARCHAR(255),
    asaas_wallet_id VARCHAR(100),
    
    -- Mercado Pago
    mp_access_token VARCHAR(255),
    mp_public_key VARCHAR(255),
    
    -- PIX Manual
    pix_chave VARCHAR(255),
    pix_titular VARCHAR(255),
    pix_cidade VARCHAR(100),
    whatsapp_support VARCHAR(20),
    whatsapp_message TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Exemplos de Configuração

### Exemplo 1: Definir Stripe como Gateway Padrão de Cartão

```sql
-- Habilitar Stripe
UPDATE saas_payment_gateways 
SET is_enabled = 1,
    default_card_gateway = 1,
    stripe_secret_key = 'sk_test_...',
    stripe_publishable_key = 'pk_test_...',
    stripe_billing_mode = 'subscription', -- Assinatura recorrente
    environment = 'sandbox'
WHERE gateway = 'stripe' AND tenant_id = 1;
```

### Exemplo 2: Definir Asaas como Gateway Padrão de PIX

```sql
-- Habilitar Asaas para PIX
UPDATE saas_payment_gateways 
SET is_enabled = 1,
    default_pix_gateway = 1,
    asaas_api_key = '$aact_...',
    environment = 'sandbox'
WHERE gateway = 'asaas' AND tenant_id = 1;

-- Garantir que outros gateways PIX não sejam padrão
UPDATE saas_payment_gateways 
SET default_pix_gateway = 0
WHERE gateway IN ('mercado_pago', 'pix_manual') AND tenant_id = 1;
```

### Exemplo 3: Usar PIX Manual com WhatsApp

```sql
UPDATE saas_payment_gateways 
SET is_enabled = 1,
    default_pix_gateway = 1,
    pix_chave = 'seu@email.com',
    pix_titular = 'SUA EMPRESA LTDA',
    pix_cidade = 'SAO PAULO',
    whatsapp_support = '5511999999999',
    whatsapp_message = 'Olá! Realizei um pagamento PIX e gostaria de confirmar a verificação.'
WHERE gateway = 'pix_manual' AND tenant_id = 1;
```

### Exemplo 4: Múltiplos Gateways Habilitados, Com Preferências

```sql
-- Stripe para Cartão (PADRÃO)
UPDATE saas_payment_gateways 
SET is_enabled = 1, default_card_gateway = 1
WHERE gateway = 'stripe' AND tenant_id = 1;

-- Asaas para PIX e Boleto (PADRÃO)
UPDATE saas_payment_gateways 
SET is_enabled = 1, default_pix_gateway = 1, default_boleto_gateway = 1
WHERE gateway = 'asaas' AND tenant_id = 1;

-- Mercado Pago habilitado, mas NÃO é padrão (fallback)
UPDATE saas_payment_gateways 
SET is_enabled = 1, default_pix_gateway = 0, default_boleto_gateway = 0
WHERE gateway = 'mercado_pago' AND tenant_id = 1;

-- PIX Manual habilitado, mas NÃO é padrão (fallback)
UPDATE saas_payment_gateways 
SET is_enabled = 1, default_pix_gateway = 0
WHERE gateway = 'pix_manual' AND tenant_id = 1;
```

## Fluxo de Seleção de Gateway

```
Usuário escolhe método: CARTÃO
    ↓
Sistema verifica: existe default_card_gateway = 1?
    ↓
SIM → Usa gateway marcado (ex: Stripe)
NÃO → Busca primeiro gateway de cartão habilitado
    ↓
Carrega configurações específicas do gateway
    ↓
Processa pagamento com as configurações corretas
```

## Modo de Cobrança Stripe (stripe_billing_mode)

### one_off (Pagamento Único)
- Cria uma Checkout Session no modo `payment`
- Cobra apenas uma vez
- Ideal para: upgrades pontuais, recarga de créditos

### subscription (Assinatura Recorrente)
- Cria uma Checkout Session no modo `subscription`
- Cobra automaticamente a cada ciclo (mensal/anual)
- Ideal para: planos mensais/anuais recorrentes
- Stripe gerencia automaticamente as cobranças

**Configuração**:
```sql
-- Pagamento Único
UPDATE saas_payment_gateways 
SET stripe_billing_mode = 'one_off'
WHERE gateway = 'stripe';

-- Assinatura Recorrente
UPDATE saas_payment_gateways 
SET stripe_billing_mode = 'subscription'
WHERE gateway = 'stripe';
```

## Arquivos que Respeitam as Preferências

### 1. process_upgrade.php
**Localização**: `conta/_ajax/process_upgrade.php`

**Linhas 208-261**: Carrega PaymentGatewayConfig e seleciona gateway baseado nas preferências

```php
// Usa métodos que respeitam preferências
if ($paymentMethod === 'card') {
    $gw = PaymentGatewayConfig::getDefaultCardGateway($pdo, $gatewayTenantId);
} elseif ($paymentMethod === 'pix') {
    $gw = PaymentGatewayConfig::getDefaultPixGateway($pdo, $gatewayTenantId);
} elseif ($paymentMethod === 'boleto') {
    $gw = PaymentGatewayConfig::getDefaultBoletoGateway($pdo, $gatewayTenantId);
}
```

### 2. plans.php (Página de Pagamento)
**Localização**: `account/pages/plans.php`

**Linhas 605-710**: Carrega configurações do gateway selecionado para exibir QR Code, Boleto, etc.

```php
// Carrega PaymentGatewayConfig
$pgcPath = realpath(__DIR__ . '/../../conta/_inc/PaymentGatewayConfig.php');
if ($pgcPath && file_exists($pgcPath)) {
    require_once $pgcPath;
}

// Usa configurações específicas do gateway
$gw = PaymentGatewayConfig::get($pdoPay, $gatewayTenantId, 'asaas');
```

### 3. plans.php (Checkout - Detecção de Gateways)
**Localização**: `account/pages/plans.php`

**Linhas 191-225**: Detecta quais gateways estão habilitados para exibir opções corretas

```php
$stmt = $pdoCheckout->prepare("
    SELECT gateway 
    FROM saas_payment_gateways 
    WHERE tenant_id = :tid 
      AND is_enabled = 1
");
```

## Migration para Adicionar Campos de Preferências

Se a tabela ainda não tiver as colunas de preferências:

```sql
-- Adiciona colunas de preferências
ALTER TABLE saas_payment_gateways
ADD COLUMN IF NOT EXISTS default_card_gateway TINYINT(1) DEFAULT 0 AFTER is_enabled,
ADD COLUMN IF NOT EXISTS default_pix_gateway TINYINT(1) DEFAULT 0 AFTER default_card_gateway,
ADD COLUMN IF NOT EXISTS default_boleto_gateway TINYINT(1) DEFAULT 0 AFTER default_pix_gateway;

-- Adiciona stripe_billing_mode se não existir
ALTER TABLE saas_payment_gateways
ADD COLUMN IF NOT EXISTS stripe_billing_mode VARCHAR(20) DEFAULT 'one_off' AFTER stripe_currency;

-- Adiciona campos WhatsApp se não existir
ALTER TABLE saas_payment_gateways
ADD COLUMN IF NOT EXISTS whatsapp_support VARCHAR(20) DEFAULT NULL AFTER pix_cidade,
ADD COLUMN IF NOT EXISTS whatsapp_message TEXT DEFAULT NULL AFTER whatsapp_support;
```

## Testes

### Teste 1: Verificar Preferência Ativa
```sql
-- Ver qual gateway está marcado como padrão para PIX
SELECT gateway, default_pix_gateway, is_enabled
FROM saas_payment_gateways
WHERE tenant_id = 1
  AND is_enabled = 1
ORDER BY default_pix_gateway DESC;
```

### Teste 2: Verificar Configurações do Gateway
```sql
-- Ver configurações do Stripe
SELECT 
    gateway,
    is_enabled,
    stripe_billing_mode,
    environment,
    SUBSTRING(stripe_secret_key, 1, 10) as key_preview
FROM saas_payment_gateways
WHERE gateway = 'stripe' AND tenant_id = 1;
```

### Teste 3: Simular Seleção de Gateway
```php
require_once 'conta/_inc/PaymentGatewayConfig.php';
$pdo = db();

// Testa PIX
$pixGateway = PaymentGatewayConfig::getDefaultPixGateway($pdo, 1);
echo "Gateway PIX padrão: " . ($pixGateway['gateway'] ?? 'Nenhum') . "\n";

// Testa Cartão
$cardGateway = PaymentGatewayConfig::getDefaultCardGateway($pdo, 1);
echo "Gateway Cartão padrão: " . ($cardGateway['gateway'] ?? 'Nenhum') . "\n";
```

## Resumo

✅ **Sistema totalmente integrado com preferências**  
✅ **Respeita configurações de `saas_payment_gateways`**  
✅ **Suporta múltiplos gateways com priorização**  
✅ **Configurações específicas por gateway (Stripe billing mode, WhatsApp, etc)**  
✅ **Fallback inteligente se preferência não for definida**  
✅ **Compatível com colunas opcionais (não quebra se campos não existirem)**

**Próximos Passos**:
1. Configurar preferências no banco de dados
2. Testar checkout com diferentes gateways
3. Verificar webhooks de confirmação
