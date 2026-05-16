# AI Tokens — Análise Técnica do Sistema
> **Versão:** 1.0 · **Data:** 2026-03-30
> **Escopo:** Mapeamento da arquitetura atual + base para implementar Contagem de Chamadas IA, Sistema de Créditos (Tokens) e Bloqueio com Modal de Upgrade

---

## 1. Estado Atual — O Que Já Existe

### 1.1 Banco de Dados (tabelas relevantes)

| Tabela | Coluna-chave | Função atual |
|---|---|---|
| `plans` | `ai_concierge_enabled` | Liga/desliga o módulo IA para o plano |
| `plans` | `ai_webhook_calls` | Limite mensal de chamadas ao n8n (int, 0 = ilimitado) |
| `plans` | `ai_catalog_limit` | Limite de produtos no catálogo IA (0 = ilimitado) |
| `plans` | `storage_mb` | Limite de MB de armazenamento |
| `tenants` | `tenant_id`, `plan_id` | Vínculo entre tenant e plano ativo |
| `ai_usage_log` | `webhook_calls`, `storage_mb_used` | Contador mensal de uso por loja |
| `ai_catalogo_models` | `demand_count` | Contagem de vezes que o produto foi consultado via IA |
| `stores` | `ai_webhook_token` | Token de autenticação das chamadas n8n |

### 1.2 Helpers PHP já implementados

**`_inc/helper/ai_plan_gate.php`**
- `ai_plan_is_enabled($tid)` → bool
- `ai_check_plan_gate($tid)` → array com `allowed`, `reason`, `limit`, `used`
- `ai_check_catalog_limit($tid)` → array com `ok`, `used`, `limit`
- `ai_render_plan_locked_overlay()` → HTML do overlay de bloqueio de plano

**`_inc/helper/ai_concierge.php`**
- `ai_tenant_id()` → resolve tenant_id da sessão
- `ai_get_active_plan($tid)` → retorna dados do plano ativo
- `ai_get_usage($tid)` → retorna uso do mês atual da `ai_usage_log`
- `ai_count_catalog($tid)` → conta modelos no catálogo
- `ai_get_storage_usage_mb($tid)` → uso real de storage em MB

### 1.3 Páginas já existentes

- **`admin/concierge_catalogo.php`** — listagem de modelos com barra de uso (`webhook_calls`, `catalog`, `storage`)
- **`conta/planos.php`** → roteador para `account/pages/plans.php` (checkout Stripe/Asaas/MP/Pix)
- **`account/pages/plans.php`** — grid de planos, histórico, checkout completo com cartão 3D e Pix

### 1.4 O que NÃO existe ainda (gap atual)

1. **Contador granular de chamadas IA** → `ai_usage_log.webhook_calls` existe mas **nenhum ponto do código o incrementa automaticamente** na chamada ao endpoint `/api/concierge/webhook.php`
2. **Contagem por produto pesquisado** → `demand_count` só registra popularidade, não tem data/hora para análise granular
3. **Bloqueio via Modal na UI** → existe `ai_render_plan_locked_overlay()` (overlay de página inteira), mas não existe **modal de bloqueio contextual** no estilo "pop-up de upgrade" com opção de comprar créditos
4. **Sistema de Créditos (Tokens)** → não existe. O modelo atual é apenas limite fixo por plano/mês
5. **Sub-menu Tokens no Painel SaaS** → não existe em `account/pages/`
6. **Tabela de controle de créditos comprados** → não existe
7. **Link "Ver Planos"** na modal → `account_plans.php` existe como referência interna; a rota pública é `http://localhost/modernpos/conta/planos`

---

## 2. Arquitetura Proposta — Sistema de Tokens/Créditos

### 2.1 Conceito

O sistema de **Tokens** (Créditos de IA) funciona como uma **moeda interna** independente do plano:

```
[Plano Mensal]  →  Inclui X chamadas gratuitas/mês (cota base)
[Tokens Extras] →  Pacotes avulsos compráveis a qualquer momento
                   (não expiram no mês, somam ao saldo)
```

**Regra de consumo:**
1. Sempre que uma chamada IA é executada, o sistema:
   - Primeiro consome da **cota base do plano** (se ainda restante)
   - Quando a cota base zera, passa a consumir **tokens extras** (se existirem)
   - Se ambos zerarem → **bloqueia** e dispara a modal de upgrade

### 2.2 Novos campos no banco (resumo)

```
plans
  └── ai_token_price_per_100    DECIMAL(8,2)  ← preço de 100 tokens extras p/ plano

tenants
  └── ai_extra_tokens           INT           ← saldo de tokens extras comprados (não expira)

ai_usage_log
  └── tokens_consumed           INT           ← tokens extras consumidos no mês
  └── base_calls_used           INT           ← chamadas da cota-base do plano usadas

ai_token_packages               ← tabela nova: pacotes disponíveis para compra
  ├── package_id
  ├── name
  ├── tokens_qty
  ├── price
  └── is_active

ai_token_purchases              ← tabela nova: histórico de compras de tokens
  ├── purchase_id
  ├── tenant_id
  ├── package_id
  ├── tokens_qty
  ├── amount_paid
  ├── payment_method
  ├── payment_ref
  ├── status (pending/paid/failed)
  └── created_at

ai_demand_log                   ← tabela nova (opcional, granular)
  ├── id
  ├── tenant_id
  ├── model_id
  ├── query_text
  ├── source (webhook/catalog_search)
  └── created_at
```

---

## 3. Fluxo de Bloqueio e Modal de Upgrade

### 3.1 Pontos de bloqueio no sistema

Existem **dois tipos** de bloqueio:

| Tipo | Onde aciona | Comportamento |
|---|---|---|
| **Plano Bloqueado** | Módulo desabilitado no plano | Overlay de página (já implementado) |
| **Cota Esgotada** | Cota mensal atingida + sem tokens extras | **Modal de upgrade** (a implementar) |

### 3.2 Fluxo do gateway de bloqueio (gate)

```
[Chamada IA] → ai_check_plan_gate()
                    │
                    ├─ plan_locked?      → Overlay de página bloqueada
                    │
                    ├─ calls_exceeded?   → [Verificar tokens extras]
                    │                         ├─ extra_tokens > 0 → Consome token, permite
                    │                         └─ extra_tokens = 0 → Modal "Comprar Tokens"
                    │
                    └─ ok → Incrementa contador + executa ação
```

### 3.3 A Modal de Upgrade (nova)

A modal é **contextual** — aparece no momento exato do bloqueio, dentro da interface admin do ModernPOS (não é redirecionamento de página).

**Estrutura da modal:**
```
┌────────────────────────────────────────┐
│ 🔒 Limite de Chamadas IA Atingido      │
│                                        │
│  Você usou todas as chamadas do seu    │
│  plano neste mês.                      │
│                                        │
│  ● Comprar Tokens Extras               │
│    [50 tokens — R$ 9,90]  [COMPRAR]    │
│    [150 tokens — R$ 24,90] [COMPRAR]   │
│    [500 tokens — R$ 69,90] [COMPRAR]   │
│                                        │
│  ─── ou ───                            │
│                                        │
│  [Ver Planos →] (conta/planos)         │
│  [Cancelar]                            │
└────────────────────────────────────────┘
```

**Implementação:**
- HTML/CSS da modal: `admin/_modals/modal_ai_upgrade.php` (incluso no `header.php` do admin)
- JS: função `window.aiShowUpgradeModal(reason, used, limit)` disponível globalmente
- O gate PHP retorna JSON `{blocked: true, reason: 'calls_exceeded', ...}` → JS intercepta e abre a modal
- Compra de tokens: AJAX para `_inc/ai_token_purchase.php` → integra com o mesmo gateway do `conta/planos`

---

## 4. Painel SaaS — Sub-menu "Tokens"

### 4.1 Localização

**Arquivo:** `account/pages/tokens.php`
**Rota:** acessível via `account/index.php?section=tokens` ou futura rota `/conta/tokens`

**Inclusão no menu do painel SaaS** (`account/partials/sidebar.php` ou equivalente):
```
Planos e Assinatura
  ├── Planos         ← já existe
  ├── Histórico      ← já existe
  └── Tokens         ← NOVO
```

### 4.2 Conteúdo da página Tokens (admin SaaS)

A página tem **dois contextos**:

**A) Visão do Lojista (tenant):**
- Saldo atual de tokens extras
- Histórico de compras
- Botões de compra dos pacotes disponíveis

**B) Visão do Admin SaaS:**
- Gerenciamento de pacotes (CRUD de `ai_token_packages`)
- Preço por 100 tokens por plano (`plans.ai_token_price_per_100`)
- Histórico global de compras
- Relatório de receita de tokens

### 4.3 Tabela de preços por plano

O admin SaaS define quanto custa **100 tokens** para cada plano:

| Plano | Cota Base/mês | Preço 100 tokens |
|---|---|---|
| Starter | 0 (IA bloqueada) | — |
| Profissional | 500 | R$ 14,90 |
| Enterprise | 2.000 | R$ 9,90 |

---

## 5. Contagem de Demanda por Produto

### 5.1 Campo atual: `ai_catalogo_models.demand_count`

**O que faz:** incrementa +1 a cada vez que um produto é retornado numa consulta IA (endpoint `/api/concierge/buscar_produto.php`).

**Limitação:** é um contador simples, sem histórico granular.

### 5.2 Registro granular com `ai_demand_log`

Para análise de tendências e relatórios de "o que os clientes mais buscam", criar a tabela `ai_demand_log`:

```sql
CREATE TABLE ai_demand_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT NOT NULL,
    model_id    INT UNSIGNED NOT NULL,
    query_text  VARCHAR(300),  -- termo buscado pelo cliente
    source      ENUM('webhook','catalog_page','photo_search') DEFAULT 'webhook',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_model (tenant_id, model_id),
    INDEX idx_tenant_date  (tenant_id, created_at)
);
```

**Vantagens:**
- Ranking por período (hoje, semana, mês)
- Identificar produtos que são buscados mas nunca comprados (oportunidade de restock)
- Relatório de "termos mais buscados" (inteligência de mercado para o lojista)

### 5.3 Onde incrementar

O `demand_count` e o `ai_demand_log` devem ser incrementados **somente** dentro do endpoint que retorna produtos para o n8n (`/api/concierge/buscar_produto.php`), garantindo que apenas buscas reais via IA sejam contadas.

---

## 6. Impacto em Arquivos Existentes

### 6.1 Arquivos a MODIFICAR

| Arquivo | Modificação |
|---|---|
| `_inc/helper/ai_plan_gate.php` | Adicionar verificação de tokens extras antes de retornar `calls_exceeded` |
| `api/concierge/webhook.php` | Chamar `ai_check_plan_gate()` antes de processar + incrementar contador |
| `api/concierge/buscar_produto.php` | Incrementar `demand_count` + inserir em `ai_demand_log` |
| `admin/header.php` | Incluir `modal_ai_upgrade.php` e o JS global da modal |
| `admin/left_sidebar.php` | (Opcional) Badge de saldo de tokens no menu Moda IA |
| `account/pages/plans.php` | Adicionar seção de compra de tokens no fluxo de checkout |

### 6.2 Arquivos a CRIAR

| Arquivo | Função |
|---|---|
| `account/pages/tokens.php` | Sub-menu Tokens no painel SaaS |
| `admin/_modals/modal_ai_upgrade.php` | Modal HTML de upgrade/compra de tokens |
| `_inc/ai_token_purchase.php` | AJAX: processar compra de tokens via gateway existente |
| `_inc/ai_token_balance.php` | AJAX: retornar saldo atual de tokens do tenant |
| `_inc/helper/ai_tokens.php` | Funções: consumir token, verificar saldo, listar pacotes |
| `migrations/2026_04_XX_ai_tokens_system.sql` | Migration do sistema de tokens |

---

## 7. Links de Referência

- **Página de planos (cliente):** `http://localhost/modernpos/conta/planos`
- **Checkout novo plano:** `http://localhost/modernpos/conta/planos?tab=checkout&plan={id}`
- **Catálogo IA (admin):** `http://localhost/modernpos/admin/concierge_catalogo.php`
- **Painel SaaS (planos):** via `store_select.php?section=plans`
- **Painel SaaS (tokens — novo):** via `store_select.php?section=tokens`
