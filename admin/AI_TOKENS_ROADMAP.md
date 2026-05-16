# AI Tokens — Roadmap de Implementação
> **Versão:** 1.0 · **Data:** 2026-03-30
> **Pré-requisito:** `CONCIERGE_IA_ROADMAP.md` (Fases 1–4 concluídas ou em andamento)
> **Documento complementar:** `AI_TOKENS_ANALISE_SISTEMA.md`

---

## Visão Geral do que será construído

```
[Chamada n8n] → Gate PHP → OK → processa + incrementa contador
                          ↓
                     Limite cota? → verifica tokens extras
                          ↓
                    Sem tokens? → Modal Upgrade
                          │        ├── Comprar Tokens (mesmo gateway)
                          │        └── Ver Planos → conta/planos
                          ↓
                  Painel SaaS
                  └── Tokens (novo sub-menu)
                       ├── Pacotes disponíveis (admin define preço)
                       ├── Histórico de compras
                       └── Saldo por tenant
```

---

## FASE T1 — Migration: Banco de Dados dos Tokens

**Objetivo:** Criar todas as tabelas e colunas necessárias para o sistema de tokens.
**Entregável:** `migrations/2026_04_XX_ai_tokens_system.sql`

### Alterações em tabelas existentes

```sql
-- 1. Adicionar saldo de tokens extras no tenant
ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS ai_extra_tokens INT NOT NULL DEFAULT 0
        COMMENT 'Saldo de tokens extras comprados (não expira mensalmente)';

-- 2. Adicionar preço de token por plano
ALTER TABLE plans
    ADD COLUMN IF NOT EXISTS ai_token_price_per_100 DECIMAL(8,2) NOT NULL DEFAULT 14.90
        COMMENT 'Preço de 100 tokens extras para este plano';

-- 3. Expandir ai_usage_log com distinção de cota-base vs tokens extras
ALTER TABLE ai_usage_log
    ADD COLUMN IF NOT EXISTS base_calls_used   INT NOT NULL DEFAULT 0
        COMMENT 'Chamadas consumidas da cota-base do plano neste mês',
    ADD COLUMN IF NOT EXISTS tokens_consumed   INT NOT NULL DEFAULT 0
        COMMENT 'Tokens extras consumidos neste mês';
```

### Novas tabelas

```sql
-- 4. Pacotes de tokens disponíveis para compra
CREATE TABLE IF NOT EXISTS ai_token_packages (
    package_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,          -- ex: "Pacote 150 Tokens"
    tokens_qty  INT NOT NULL,                   -- quantidade de tokens no pacote
    price       DECIMAL(8,2) NOT NULL,          -- preço em BRL
    description VARCHAR(300) DEFAULT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (package_id),
    INDEX idx_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dados iniciais sugeridos
INSERT INTO ai_token_packages (name, tokens_qty, price, sort_order) VALUES
    ('Pacote Básico',    50,  9.90,  1),
    ('Pacote Médio',    150, 24.90,  2),
    ('Pacote Avançado', 500, 69.90,  3),
    ('Pacote Ilimitado',2000,199.90, 4);

-- 5. Histórico de compras de tokens por tenant
CREATE TABLE IF NOT EXISTS ai_token_purchases (
    purchase_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id      INT NOT NULL,
    package_id     INT UNSIGNED DEFAULT NULL,    -- NULL = compra personalizada
    tokens_qty     INT NOT NULL,
    amount_paid    DECIMAL(8,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,     -- pix, card, boleto
    payment_ref    VARCHAR(200) DEFAULT NULL,    -- ID do gateway (saas_orders ref)
    saas_order_id  INT UNSIGNED DEFAULT NULL,    -- FK -> saas_orders.order_id
    status         ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at        DATETIME DEFAULT NULL,
    PRIMARY KEY (purchase_id),
    INDEX idx_tenant    (tenant_id),
    INDEX idx_status    (tenant_id, status),
    INDEX idx_created   (tenant_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Log granular de demanda por produto
CREATE TABLE IF NOT EXISTS ai_demand_log (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id   INT NOT NULL,
    model_id    INT UNSIGNED NOT NULL,          -- FK -> ai_catalogo_models.id
    query_text  VARCHAR(300) DEFAULT NULL,      -- termo buscado pelo usuário final
    source      ENUM('webhook','catalog_search','photo_search') NOT NULL DEFAULT 'webhook',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_tenant_model (tenant_id, model_id),
    INDEX idx_tenant_date  (tenant_id, created_at DESC),
    INDEX idx_model_date   (model_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## FASE T2 — Helper PHP: Funções de Token

**Objetivo:** Criar o helper central para toda lógica de tokens.
**Entregável:** `_inc/helper/ai_tokens.php`

### Funções a implementar

```
ai_get_token_balance(int $tid): int
    → Retorna saldo atual de tokens extras do tenant (tenants.ai_extra_tokens)

ai_consume_call(int $tid): array
    → Lógica central de consumo:
       1. Verifica se cota-base tem saldo → decrementa ai_usage_log.base_calls_used
       2. Se cota-base esgotada, verifica tokens extras (tenants.ai_extra_tokens)
       3. Se tokens disponíveis → decrementa ai_extra_tokens + incrementa tokens_consumed
       4. Se ambos esgotados → retorna ['allowed'=>false, 'reason'=>'calls_exceeded']
    → Retorna ['allowed'=>true, 'source'=>'base|token', 'balance'=>N]

ai_add_tokens(int $tid, int $qty, int $purchaseId): bool
    → Adiciona tokens ao saldo do tenant (UPDATE tenants SET ai_extra_tokens += qty)
    → Registra na ai_token_purchases com status 'paid'

ai_get_token_packages(): array
    → Lista pacotes ativos ordenados por sort_order

ai_get_purchase_history(int $tid, int $limit = 20): array
    → Histórico de compras do tenant

ai_get_demand_ranking(int $tid, string $period = 'month', int $limit = 10): array
    → Ranking de produtos mais buscados no período usando ai_demand_log
    → $period: 'today' | 'week' | 'month' | 'all'

ai_log_demand(int $tid, int $modelId, string $queryText = '', string $source = 'webhook'): void
    → Insere em ai_demand_log + incrementa ai_catalogo_models.demand_count
```

---

## FASE T3 — Atualizar ai_plan_gate.php

**Objetivo:** Integrar verificação de tokens extras no gate existente.
**Entregável:** Modificação em `_inc/helper/ai_plan_gate.php`

### Lógica adicional na função `ai_check_plan_gate()`

A função atual retorna `['allowed'=>false, 'reason'=>'calls_exceeded']` quando a cota mensal é atingida.

**Nova lógica:** antes de retornar `calls_exceeded`, verificar saldo de tokens extras:

```
if ($callLimit > 0 && $usedCalls >= $callLimit) {
    $tokenBalance = ai_get_token_balance($tenantId);
    if ($tokenBalance > 0) {
        // Permite, mas sinaliza que usará token extra
        return ['allowed' => true, 'source' => 'token', 'token_balance' => $tokenBalance, ...];
    }
    // Sem tokens → bloqueia
    return ['allowed' => false, 'reason' => 'calls_exceeded', 'token_balance' => 0, ...];
}
```

---

## FASE T4 — Ativar Incremento de Contadores no Webhook

**Objetivo:** Garantir que cada chamada ao n8n realmente incremente os contadores.
**Entregável:** Modificação em `api/concierge/webhook.php` e `api/concierge/buscar_produto.php`

### Em webhook.php (entrada única do n8n)

```
1. Chamar ai_consume_call($tenantId)
   ├── Se 'allowed' == false → retornar HTTP 402 com JSON {blocked:true, reason:..., token_balance:0}
   └── Se 'allowed' == true → processar normalmente + registrar source (base/token)

2. Incrementar ai_usage_log com INSERT ... ON DUPLICATE KEY UPDATE:
   - Se source == 'base' → base_calls_used += 1
   - Se source == 'token' → tokens_consumed += 1
```

### Em buscar_produto.php

```
1. Para cada model_id retornado → chamar ai_log_demand($tid, $modelId, $query, 'webhook')
   → Incrementa demand_count no modelo
   → Insere linha em ai_demand_log com timestamp
```

---

## FASE T5 — Modal de Upgrade (UI)

**Objetivo:** Criar a modal contextual que aparece quando a cota é atingida.
**Entregáveis:**
- `admin/_modals/modal_ai_upgrade.php` — HTML da modal
- JS global injetado via `admin/header.php`

### Estrutura do HTML da modal

A modal usa o padrão visual já existente no sistema (`.mia-overlay` / `.mia-modal` do `concierge_catalogo.php`):

```html
<!-- Overlay escuro -->
<div id="aiUpgradeModal" class="mia-overlay hide">
  <div class="mia-modal modal-lg">
    <!-- Cabeçalho gradiente violeta (mesmo padrão do sistema) -->
    <div class="mh">
      <div class="mh-info">
        <div class="mt">🔒 Limite de Chamadas IA Atingido</div>
        <div class="ms" id="aiUpgradeModalSubtitle">
          Você usou todas as chamadas deste mês
        </div>
      </div>
      <button class="mh-close" onclick="aiCloseUpgradeModal()">✕</button>
    </div>

    <!-- Corpo: pacotes de tokens -->
    <div class="mb">
      <div id="aiUpgradePackagesList">
        <!-- Populado via JS ao abrir a modal -->
      </div>
      <div style="text-align:center;margin:16px 0;color:#9ca3af;font-size:13px;">— ou —</div>
      <div style="text-align:center;">
        <a href="/modernpos/conta/planos" class="btn btn-upgrade">
          <i class="fa fa-arrow-up"></i> Ver Planos Completos
        </a>
      </div>
    </div>

    <!-- Rodapé -->
    <div class="mf">
      <button class="btn btn-secondary" onclick="aiCloseUpgradeModal()">Cancelar</button>
    </div>
  </div>
</div>
```

### JS global (injetado em admin/header.php)

```javascript
// Abre a modal de upgrade com os pacotes carregados via AJAX
window.aiShowUpgradeModal = function(reason, used, limit) {
    fetch('/_inc/ai_token_packages_list.php')
        .then(r => r.json())
        .then(data => {
            // Preencher #aiUpgradePackagesList com botões dos pacotes
            // Cada botão dispara ai_buy_token_package(packageId)
        });
    document.getElementById('aiUpgradeModal').classList.remove('hide');
};

window.aiCloseUpgradeModal = function() {
    document.getElementById('aiUpgradeModal').classList.add('hide');
};
```

### Como a modal é disparada

No JavaScript das páginas do admin concierge, qualquer chamada AJAX que retornar:

```json
{"success": false, "blocked": true, "reason": "calls_exceeded", "token_balance": 0}
```

deve chamar `aiShowUpgradeModal('calls_exceeded', used, limit)`.

---

## FASE T6 — Fluxo de Compra de Tokens

**Objetivo:** Permitir compra de pacotes de tokens dentro da modal ou na página Tokens.
**Entregáveis:**
- `_inc/ai_token_purchase.php` — AJAX de criação do pedido
- `_inc/ai_token_confirm.php` — AJAX de confirmação (webhook do gateway)
- `_inc/ai_token_balance.php` — AJAX de consulta de saldo

### Fluxo de compra

```
1. Usuário clica "Comprar" em um pacote
   └── AJAX POST _inc/ai_token_purchase.php {package_id, payment_method}
        ├── Verifica autenticação (is_loggedin())
        ├── Busca dados do pacote em ai_token_packages
        ├── Cria registro em saas_orders (aproveita o sistema de checkout existente)
        │    └── payment_method = pix|card|boleto
        ├── Cria registro em ai_token_purchases (status='pending')
        └── Retorna {success:true, order_id, redirect_url OU pix_data}

2. Pagamento confirmado (webhook do gateway OU confirmação manual Pix)
   └── _inc/ai_token_confirm.php {order_id}
        ├── Valida que saas_orders.status = 'paid'
        ├── Chama ai_add_tokens($tenantId, $qty, $purchaseId)
        │    └── UPDATE tenants SET ai_extra_tokens += qty
        └── Retorna {success:true, new_balance: N}

3. Pix Manual: mesma lógica do checkout de planos (comprovante → suporte confirma)
```

### Reutilização do gateway existente

O fluxo de pagamento **não precisa ser reconstruído** — usar a mesma infraestrutura de `conta/_ajax/process_upgrade.php` e `saas_orders`. A diferença é que ao confirmar, em vez de atualizar `tenants.plan_id`, o sistema incrementa `tenants.ai_extra_tokens`.

Criar um campo de distinção em `saas_orders`:
```sql
ALTER TABLE saas_orders
    ADD COLUMN IF NOT EXISTS order_type ENUM('plan','tokens') NOT NULL DEFAULT 'plan'
        COMMENT 'Tipo do pedido: upgrade de plano ou compra de tokens';
```

---

## FASE T7 — Sub-menu Tokens no Painel SaaS

**Objetivo:** Criar a página de gerenciamento de tokens no painel SaaS.
**Entregável:** `account/pages/tokens.php`

### Integração com o roteador existente

O painel SaaS usa `store_select.php` como roteador via `$_GET['section']`.

**Passos:**
1. Adicionar `'tokens'` ao array de seções válidas em `store_select.php` (ou equivalente)
2. Criar `account/pages/tokens.php`
3. Adicionar link no menu lateral do painel SaaS

### Layout da página Tokens (visão tenant/lojista)

```
┌─────────────────────────────────────────────────────┐
│  Tokens de IA                                       │
│  Gerencie seus créditos de chamadas IA              │
├──────────────┬──────────────────────────────────────┤
│ Saldo Atual  │  🪙 247 Tokens extras disponíveis    │
│              │  ℹ️  Cota base do plano: 12/500/mês  │
├──────────────┴──────────────────────────────────────┤
│  Comprar Tokens                                     │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐          │
│  │ 50 tok.  │  │ 150 tok. │  │ 500 tok. │          │
│  │  R$9,90  │  │ R$24,90  │  │ R$69,90  │          │
│  │[Comprar] │  │[Comprar] │  │[Comprar] │          │
│  └──────────┘  └──────────┘  └──────────┘          │
├─────────────────────────────────────────────────────┤
│  Histórico de Compras                               │
│  Data         Pacote          Valor    Status       │
│  2026-03-28   150 Tokens      R$24,90  ✅ Pago     │
└─────────────────────────────────────────────────────┘
```

### Layout da página Tokens (visão admin SaaS)

Acesso apenas para o dono do SaaS (user_group_id == 1):

```
┌─────────────────────────────────────────────────────┐
│  Tokens — Administração                             │
│  ┌────────────────────────────────────────────────┐ │
│  │ PACOTES DISPONÍVEIS        [+ Novo Pacote]     │ │
│  │ Nome          Tokens  Preço   Status  Ações    │ │
│  │ Pacote Básico    50   R$9,90  Ativo   ✏️ 🗑️   │ │
│  │ Pacote Médio    150  R$24,90  Ativo   ✏️ 🗑️   │ │
│  └────────────────────────────────────────────────┘ │
│  ┌────────────────────────────────────────────────┐ │
│  │ PREÇO POR 100 TOKENS (por plano)               │ │
│  │ Profissional: R$ [14,90]  Enterprise: R$ [9,90]│ │
│  └────────────────────────────────────────────────┘ │
│  ┌────────────────────────────────────────────────┐ │
│  │ TODAS AS COMPRAS                               │ │
│  │ Tenant  Data     Pacote   Valor  Status        │ │
│  └────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
```

---

## FASE T8 — Exibir Saldo e Contagem no Admin ModernPOS

**Objetivo:** Mostrar o saldo de tokens e a contagem de demanda na interface do lojista.
**Entregável:** Ajustes em `admin/concierge_catalogo.php`

### Barra de uso atualizada

Adicionar à barra de uso existente:

```
┌───────────────────────────────────────────────────────────────┐
│ 📊 Uso Concierge IA — Março 2026                              │
│ Chamadas Base:  247/500  [====-----] 49%                      │
│ Tokens Extras:  🪙 150 disponíveis        [Comprar Mais]      │
│ Catálogo:        18/50   [===------] 36%                      │
│ Storage:       12.4/100MB[=--------] 12%                      │
└───────────────────────────────────────────────────────────────┘
```

Quando `tokens_extras > 0` e `cota_base` for esgotada, a barra mostra:
```
Chamadas Base: 500/500  [==========] 100%  ⚠️ usando tokens extras
```

### Ranking de demanda na tabela de modelos

A coluna "Demanda" já existe com `demand_count`. Adicionar tooltip com "tendência":
- Comparar `demand_count` do período atual vs mês anterior (via `ai_demand_log`)
- Badge `📈 +23%` quando a demanda cresceu no período

---

## Cronograma Estimado

```
Semana  1    │  FASE T1: Migration SQL (banco de tokens)
Semana  2    │  FASE T2: Helper ai_tokens.php
Semana  3    │  FASE T3: Atualizar ai_plan_gate.php
Semana  3    │  FASE T4: Ativar incremento no webhook + buscar_produto
Semana  4    │  FASE T5: Modal de Upgrade (HTML + JS)
Semana  5-6  │  FASE T6: Fluxo de compra de tokens (gateway)
Semana  7    │  FASE T7: Sub-menu Tokens no painel SaaS
Semana  8    │  FASE T8: Ajustes visuais no admin (barra de uso + ranking)
```

**Estimativa total:** 6 a 8 semanas (trabalhando em paralelo com as Fases 3–6 do Concierge IA).

---

## Ordem de Implementação Recomendada

1. **FASE T1 primeiro** — sem a migration, nada funciona.
2. **FASE T2 + T3 juntas** — o helper de tokens e a atualização do gate são interdependentes.
3. **FASE T4** — ativar os contadores é crítico para que os dados sejam reais desde o início.
4. **FASE T5** — a modal é o que o usuário vê; deve ser feita antes de T6 para testar o fluxo.
5. **FASE T6** — o fluxo de compra integra com o gateway já existente (reutilização máxima).
6. **FASE T7 + T8** — podem ser feitas em paralelo; são melhorias de UI independentes.

---

## Decisões de Arquitetura

1. **Tokens não expiram mensalmente** — diferente da cota-base do plano, o saldo de tokens acumulado persiste indefinidamente em `tenants.ai_extra_tokens`. Isso é vantagem competitiva: o cliente compra com confiança.

2. **Cota-base é zerada todo mês** — um `cron.php` ou verificação inline reseta `ai_usage_log.base_calls_used = 0` no início de cada mês. A tabela `ai_usage_log` já usa `year_month` como chave — simplesmente criar uma nova linha para o novo mês.

3. **Mesmo gateway de pagamento** — a compra de tokens usa a mesma infraestrutura de `saas_orders` e `conta/_ajax/process_upgrade.php`. Não duplicar código de pagamento.

4. **Modal reutiliza o CSS do sistema** — usar as classes `.mia-overlay`, `.mia-modal`, `.mh`, `.mb`, `.mf` já definidas em `admin/concierge_catalogo.php`. Manter consistência visual.

5. **Link para planos** — sempre usar `http://localhost/modernpos/conta/planos` (ambiente local) ou `root_url() . 'conta/planos'` (dinâmico para produção). Não hardcodar URLs.

6. **Segurança da compra** — a confirmação de tokens (`ai_add_tokens`) só executa após verificar que `saas_orders.status = 'paid'`, prevenindo fraudes.

---

## Arquivos do Projeto (Resumo Final)

```
modernpos/
├── AI_TOKENS_ANALISE_SISTEMA.md          ← Este levantamento
├── AI_TOKENS_ROADMAP.md                  ← Este arquivo
│
├── migrations/
│   └── 2026_04_XX_ai_tokens_system.sql  ← FASE T1
│
├── _inc/
│   ├── helper/
│   │   └── ai_tokens.php                ← FASE T2
│   ├── ai_token_purchase.php            ← FASE T6 (AJAX: iniciar compra)
│   ├── ai_token_confirm.php             ← FASE T6 (AJAX: confirmar compra)
│   ├── ai_token_balance.php             ← FASE T8 (AJAX: saldo)
│   └── ai_token_packages_list.php       ← FASE T5 (AJAX: listar pacotes p/ modal)
│
├── admin/
│   ├── _modals/
│   │   └── modal_ai_upgrade.php         ← FASE T5
│   └── header.php                       ← FASE T5 (incluir modal + JS global)
│
└── account/
    └── pages/
        └── tokens.php                   ← FASE T7
```
