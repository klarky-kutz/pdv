# Concierge IA — Roadmap de Implementação

> **Versão:** 1.0 · **Data:** 2026-03-26  
> **Projeto:** Módulo de Vendas Automatizadas por IA — ModernPOS  
> **Briefing completo:** [`CONCIERGE_IA_BRIEFING.md`](./CONCIERGE_IA_BRIEFING.md)

---

## Visão do Produto

```
WhatsApp (cliente)
      │
      ▼
[n8n Master Flow]
      │  loja_id
      ├──► buscar_produto / buscar_por_foto
      ├──► perfil_cliente (lembrar preferências)
      ├──► criar_pedido
      └──► confirmar_pagamento
             │
             ▼
     [ModernPOS Admin]
     Kanban: Pedido → Separação → Rota → Entregue
             │
             ▼
     [SaaS Painel]
     Controle de plano · MB usado · Chamadas usadas
```

---

## Fases de Implementação

---

### FASE 1 — Fundação: Banco de Dados
**Objetivo:** Criar toda a estrutura de tabelas antes de qualquer linha de código PHP.  
**Entregável:** 1 arquivo de migration SQL em `modernpos/migrations/`

#### Tabelas a criar

**`ai_catalogo_models`** — O Modelo (produto-raiz do catálogo IA)
```sql
CREATE TABLE ai_catalogo_models (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT NOT NULL,                     -- isolamento multi-tenant
    name          VARCHAR(150) NOT NULL,             -- ex: "Vestido Midi Floral"
    description   TEXT,
    tags          VARCHAR(500),                     -- ex: "midi,floral,feminino,casual"
    cover_webp    VARCHAR(300),                     -- caminho da foto principal (WebP)
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    demand_count  INT NOT NULL DEFAULT 0,           -- métrica de desejo (consultas IA)
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_active (tenant_id, is_active)
);
```

**`ai_catalogo_variants`** — A Variante (cor + tamanho + estoque + preço)
```sql
CREATE TABLE ai_catalogo_variants (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id     INT UNSIGNED NOT NULL,             -- FK → ai_catalogo_models.id
    tenant_id    INT NOT NULL,
    color        VARCHAR(80),                       -- ex: "Rosa", "Preto", "Off-White"
    size         VARCHAR(20),                       -- ex: "P", "M", "G", "GG", "34", "38"
    price        DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock_qty    INT NOT NULL DEFAULT 0,
    photo_webp   VARCHAR(300),                     -- foto específica da variante
    sku          VARCHAR(80),                       -- código interno opcional
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_model  (model_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_stock  (tenant_id, stock_qty)
);
```

**`ai_chat_profiles`** — Memória de perfil do cliente no WhatsApp
```sql
CREATE TABLE ai_chat_profiles (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           INT NOT NULL,
    whatsapp_phone      VARCHAR(30) NOT NULL,       -- +5511999999999
    name                VARCHAR(150),
    usual_size          VARCHAR(20),                -- tamanho habitual (P/M/G)
    preferences_json    JSON,                       -- {"cores": ["rose","pastel"], "estilos": ["midi"]}
    last_interaction    DATETIME,
    total_interactions  INT NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_phone_tenant (tenant_id, whatsapp_phone)
);
```

**`ai_orders`** — Pedidos originados via WhatsApp
```sql
CREATE TABLE ai_orders (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id        INT NOT NULL,
    profile_id       INT UNSIGNED,                  -- FK → ai_chat_profiles.id
    whatsapp_phone   VARCHAR(30) NOT NULL,
    status           ENUM('pendente','pago','separando','rota','entregue','cancelado')
                     NOT NULL DEFAULT 'pendente',
    total_amount     DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method   VARCHAR(50),                   -- pix, stripe, mercadopago
    payment_link     VARCHAR(500),                  -- link enviado ao cliente
    payment_ref      VARCHAR(200),                  -- ID de referência do gateway
    uber_proof_path  VARCHAR(300),                  -- foto do comprovante Uber Flash
    notes            TEXT,
    paid_at          DATETIME,
    delivered_at     DATETIME,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_tenant_date   (tenant_id, created_at)
);
```

**`ai_order_items`** — Itens de cada pedido
```sql
CREATE TABLE ai_order_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,          -- FK → ai_orders.id
    variant_id      INT UNSIGNED NOT NULL,          -- FK → ai_catalogo_variants.id
    model_name      VARCHAR(150),                   -- snapshot do nome no momento da venda
    color           VARCHAR(80),
    size            VARCHAR(20),
    qty             INT NOT NULL DEFAULT 1,
    unit_price      DECIMAL(10,2) NOT NULL,         -- preço no momento da venda
    subtotal        DECIMAL(10,2) NOT NULL
);
```

**`ai_usage_log`** — Contador de uso por loja (para limites do plano SaaS)
```sql
CREATE TABLE ai_usage_log (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT NOT NULL,
    year_month      CHAR(7) NOT NULL,               -- "2026-03"
    webhook_calls   INT NOT NULL DEFAULT 0,         -- chamadas ao n8n neste mês
    storage_mb_used DECIMAL(8,2) NOT NULL DEFAULT 0, -- MB de fotos armazenadas
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_month (tenant_id, year_month)
);
```

**Arquivo de migration:** `modernpos/migrations/2026_03_26_create_ai_concierge_tables.sql`

---

### FASE 2 — Catálogo IA (Admin ModernPOS)
**Objetivo:** Interface para o lojista cadastrar e gerenciar seu catálogo curado.  
**Entregável:** Páginas PHP + JS no admin do ModernPOS.

#### Arquivos a criar

```
modernpos/
├── concierge_catalogo.php          ← listagem de modelos (cards visuais)
├── concierge_catalogo_form.php     ← criar/editar modelo + variantes
├── concierge_pedidos.php           ← Kanban de pedidos (Fase 4)
└── _inc/ajax/
    ├── ai_catalogo_salvar.php      ← POST: salvar modelo
    ├── ai_catalogo_variante.php    ← POST: salvar/excluir variante
    ├── ai_catalogo_foto.php        ← POST: upload foto → converte para WebP
    └── ai_demanda_ranking.php      ← GET: ranking de peças mais desejadas
```

#### Tela: Listagem de Modelos (`concierge_catalogo.php`)

- Grid de cards com: foto WebP, nome, nº de variantes, nº de cores, badge de estoque baixo.
- Filtros: ativo/inativo, categoria, busca por nome/tag.
- Botão "Novo Modelo" → abre drawer/modal com o formulário.
- Badge de **Demanda** (🔥 N consultas) em destaque nos cards mais procurados.
- Indicador de limite do plano: `X de Y produtos cadastrados`.

#### Tela: Formulário de Modelo + Variantes (`concierge_catalogo_form.php`)

- **Aba 1 — Informações do Modelo:**
  - Nome, Descrição, Tags (chips auto-complete), Foto de capa.
  - Upload: converte para WebP via `imagecreatefromjpeg/imagecreatefrompng + imagewebp()`.

- **Aba 2 — Variantes:**
  - Tabela inline editável: Cor | Tamanho | Preço | Estoque | Foto.
  - Botão "+ Adicionar Variante" duplica a última linha.
  - Ao zerar estoque de uma variante: badge "Esgotado" aparece no card.

#### Conversão WebP (helper PHP)

```php
// modernpos/_inc/helper/ai_image_webp.php
function ai_convert_to_webp(string $sourcePath, string $destPath, int $quality = 80): bool {
    $info = getimagesize($sourcePath);
    $mime = $info['mime'] ?? '';
    $img  = match($mime) {
        'image/jpeg' => imagecreatefromjpeg($sourcePath),
        'image/png'  => imagecreatefrompng($sourcePath),
        'image/webp' => imagecreatefromwebp($sourcePath),
        default      => false,
    };
    if (!$img) return false;
    $ok = imagewebp($img, $destPath, $quality);
    imagedestroy($img);
    return $ok;
}
```

#### Permissão nova a registrar

- Chave: `access_concierge_ia` → adicionar aos grupos de permissão padrão do SaaS.

---

### FASE 3 — API Webhook (n8n Bridge)
**Objetivo:** Conjunto de endpoints REST que o n8n chama para consultar e manipular dados.  
**Entregável:** Diretório `modernpos/api/concierge/` com endpoints JSON.

#### Endpoints

**`GET /api/concierge/buscar_produto.php`**
- Parâmetros: `loja_id`, `q` (texto livre), `tamanho` (opcional), `cor` (opcional)
- Busca em `ai_catalogo_models.name` + `tags` + `ai_catalogo_variants.color/size`
- Retorna: lista de modelos com variantes disponíveis (stock_qty > 0), preço, foto WebP URL
- Registra +1 em `ai_demand_count` para cada modelo retornado

**`POST /api/concierge/buscar_por_foto.php`**
- Body: `loja_id`, `image_url` ou `image_base64`
- Encaminha para GPT-4o Vision / Gemini com prompt de identificação de moda
- Retorna: `tags_detectadas[]`, `modelos_similares[]` do catálogo da loja
- Registra na `ai_usage_log` (+1 webhook_call)

**`GET/POST /api/concierge/perfil_cliente.php`**
- GET: `loja_id`, `phone` → retorna perfil (tamanho, preferências, histórico)
- POST: `loja_id`, `phone`, `name`, `usual_size`, `preferences_json` → upsert perfil

**`POST /api/concierge/criar_pedido.php`**
- Body: `loja_id`, `phone`, `items[]` (variant_id + qty), `payment_method`
- Valida estoque de cada variante
- Cria registro em `ai_orders` + `ai_order_items`
- Gera link de pagamento (chama gateway configurado da loja)
- Retorna: `order_id`, `payment_link`, `total`

**`POST /api/concierge/confirmar_pagamento.php`**
- Webhook chamado pelo gateway após pagamento aprovado
- Atualiza `ai_orders.status = 'pago'`, registra `paid_at`
- Aciona notificação no painel do lojista (badge)
- Decrementa `stock_qty` nas variantes do pedido

**`POST /api/concierge/registrar_comprovante_uber.php`**
- Body: `loja_id`, `order_id`, `image_base64` (print do Uber)
- Salva imagem em `storage/concierge/uber/`
- Atualiza `ai_orders.uber_proof_path` + `status = 'rota'`
- Notifica lojista no painel

**`POST /api/concierge/webhook.php`** ← Entrada única do n8n
- Recebe todo payload do n8n com `loja_id` + `action`
- Roteador interno que delega para os endpoints acima
- Incrementa contador de chamadas em `ai_usage_log`
- Valida limite do plano antes de processar (`ai_webhook_calls`)

#### Autenticação dos endpoints

- Token estático por loja: `ai_webhook_token` armazenado em `stores` (gerado no cadastro).
- Header: `X-Concierge-Token: {token}`
- Validação: `hash_equals($storedToken, $receivedToken)`

---

### FASE 4 — Kanban de Pedidos (Admin ModernPOS)
**Objetivo:** Painel visual para o lojista acompanhar e gerenciar todos os pedidos WhatsApp.  
**Entregável:** `concierge_pedidos.php` + JS de drag-and-drop.

#### Layout

```
┌─────────────────────────────────────────────────────────────────┐
│  🤖 Pedidos Concierge IA          [Filtrar data] [Buscar]       │
├──────────────┬──────────────┬──────────────┬────────────────────┤
│   PEDIDO     │  SEPARAÇÃO   │    ROTA      │     ENTREGUE       │
│   (3)        │   (1)        │   (2)        │      (14)          │
│ ─────────    │ ─────────    │ ─────────    │ ──────────         │
│ [Card]       │ [Card]       │ [Card]       │ [Card]             │
│ [Card]       │              │ [Card]       │ [Card]             │
│ [Card]       │              │              │ ...                │
└──────────────┴──────────────┴──────────────┴────────────────────┘
```

#### Card de Pedido

```
┌─────────────────────────┐
│ #1042  •  há 12min      │
│ Maria Souza             │
│ 📱 11 99999-9999        │
│ ─────────────────────── │
│ Vestido Midi · Rosa · M │
│ 1x  R$ 89,90            │
│ ─────────────────────── │
│ 💰 R$ 89,90  [PIX ✓]   │
└─────────────────────────┘
```

#### Drawer de Detalhes (ao clicar no card)

Gaveta lateral com:
- Dados do cliente (nome, telefone, tamanho habitual, preferências)
- Lista de itens com fotos WebP
- Timeline: Pedido feito → Pago → Separando → Rota → Entregue
- Foto do comprovante Uber Flash (se existir)
- Botões de ação: "Marcar como Separado", "Iniciar Rota", "Confirmar Entrega"
- Link direto para o chat do WhatsApp

#### Drag-and-drop

- Biblioteca: SortableJS (leve, sem jQuery obrigatório)
- Ao arrastar card entre colunas → chamada AJAX para `_inc/ajax/ai_pedido_status.php`
- Animação de transição e confirmação visual

#### Notificações (badge no menu)

- Arquivo `_inc/ajax/ai_pedidos_badge.php` — retorna contagem de pedidos pendentes/pagos.
- Polling a cada 30s via JS no `left_sidebar.php`.
- Badge vermelho no item de menu "Concierge IA".

---

### FASE 5 — Integração SaaS (Limites, Bloqueio e Métricas)
**Objetivo:** Conectar os controles de plano do SaaS com o comportamento do módulo no ModernPOS.  
**Entregável:** Gate middleware + UI locked + dashboard de uso.

#### Gate Middleware (PHP)

```php
// modernpos/_inc/helper/ai_plan_gate.php

function ai_check_plan_gate(PDO $pdo, int $tenantId): array {
    // 1. Busca o plano ativo da loja no SaaS
    $plan = ai_get_active_plan($pdo, $tenantId);

    if (!$plan || !$plan['ai_concierge_enabled']) {
        return ['allowed' => false, 'reason' => 'plan_locked'];
    }

    // 2. Verifica limite de chamadas do mês atual
    $usage = ai_get_usage($pdo, $tenantId, date('Y-m'));
    $limit = (int)$plan['ai_webhook_calls'];  // 0 = ilimitado

    if ($limit > 0 && $usage['webhook_calls'] >= $limit) {
        return ['allowed' => false, 'reason' => 'calls_exceeded', 'used' => $usage['webhook_calls'], 'limit' => $limit];
    }

    // 3. Verifica limite de produtos no catálogo
    $catalogCount = ai_count_catalog($pdo, $tenantId);
    $catalogLimit = (int)$plan['ai_catalog_limit'];

    if ($catalogLimit > 0 && $catalogCount >= $catalogLimit) {
        return ['allowed' => false, 'reason' => 'catalog_limit', 'used' => $catalogCount, 'limit' => $catalogLimit];
    }

    return ['allowed' => true, 'plan' => $plan, 'usage' => $usage];
}
```

#### UI de Bloqueio (plano básico)

Quando `ai_concierge_enabled = 0` no plano da loja:
- Página `concierge_catalogo.php` exibe overlay com:
  - Ícone de cadeado 🔒 animado
  - Texto: "Módulo Concierge IA disponível a partir do Plano Profissional"
  - Botão "Ver Planos" → redireciona para `account_plans.php`
- Itens do menu relacionados aparecem em cinza (`opacity: 0.4; pointer-events: none`)

#### Dashboard de Uso (dentro do módulo)

Bloco na página `concierge_catalogo.php`:
```
┌────────────────────────────────────────────┐
│  📊 Uso do Concierge IA — Março 2026        │
│                                            │
│  Chamadas:   247 / 500/mês   [====--] 49%  │
│  Catálogo:    18 / 50 itens  [===---] 36%  │
│  Storage:    12,4 / 100 MB   [=----- ]12%  │
│                              [Upgrade ↑]   │
└────────────────────────────────────────────┘
```

#### Coluna nova em `stores` (migration)

```sql
ALTER TABLE stores
    ADD COLUMN ai_webhook_token VARCHAR(64) NULL
        COMMENT 'Token de autenticação para os endpoints Concierge IA'
        AFTER id;
```

---

### FASE 6 — Fluxo n8n Master
**Objetivo:** Criar o template do fluxo n8n que gerencia todas as lojas com um único webhook.  
**Entregável:** Arquivo JSON exportável do n8n + documentação de variáveis.

#### Estrutura do Fluxo

```
[Webhook Trigger]
      │
      ▼
[Switch: action]
      ├── "mensagem_texto"
      │       ├── [Classificar Intenção - LLM]
      │       │       ├── "buscar_produto" → [HTTP: buscar_produto.php] → [LLM: resposta]
      │       │       ├── "comprar"        → [HTTP: criar_pedido.php]   → [Enviar link]
      │       │       ├── "status_pedido"  → [HTTP: consultar_pedido]   → [Formatar status]
      │       │       └── "saudação"       → [LLM: cumprimento + perfil]
      │
      ├── "imagem_recebida"
      │       └── [HTTP: buscar_por_foto.php] → [LLM: apresentar matches]
      │
      ├── "pagamento_confirmado" (webhook do gateway)
      │       └── [HTTP: confirmar_pagamento.php] → [Enviar confirmação WA]
      │
      └── "comprovante_uber"
              └── [HTTP: registrar_comprovante_uber.php] → [Avisar lojista WA]
```

#### Variáveis de ambiente do n8n

```
MODERNPOS_BASE_URL     = https://pdv.seudominio.com
CONCIERGE_TOKEN        = {gerado por loja, injetado via $json.loja_id lookup}
OPENAI_API_KEY         = {sua key}
WHATSAPP_INSTANCE_URL  = {URL da Evolution API ou 360dialog}
```

#### System Prompt da Vendedora IA

O prompt é armazenado em `ai_catalogo_models.tags` (contexto de produtos) e em um campo `ai_system_prompt` na tabela `stores`:

```
Você é a vendedora virtual da loja {nome_loja}. Seu nome é {nome_ia}.
Você é especialista em moda feminina e conhece cada peça do catálogo.
Seja calorosa, consultiva e use emojis com moderação.
Regras:
- Nunca invente preços ou disponibilidade; consulte sempre a API.
- Se uma peça estiver esgotada, sugira similares imediatamente.
- Lembre das preferências da cliente e use-as nas sugestões.
- Ao fechar pedido, confirme os itens antes de gerar o link.
```

---

## Cronograma Estimado

```
Semana  1-2   │  FASE 1: Migration SQL + revisão de tabelas
Semana  3-4   │  FASE 2: Catálogo Admin (listagem + formulário + WebP)
Semana  5-6   │  FASE 3: API Endpoints (buscar, criar pedido, confirmar pagamento)
Semana  7-8   │  FASE 4: Kanban de Pedidos + Drawer + Drag-and-drop
Semana  9-10  │  FASE 5: Gate de Plano + UI Locked + Dashboard de Uso
Semana 11-12  │  FASE 6: Fluxo n8n Master + testes end-to-end + ajustes
```

---

## Ordem de Implementação Recomendada

1. **Comece pela Fase 1** (migration SQL) — tudo depende das tabelas existirem.
2. **Fase 2 em seguida** — o catálogo populado é a base de teste para todas as outras fases.
3. **Fase 3 junto com Fase 2** — os endpoints podem ser testados com Postman/Insomnia enquanto a UI está em construção.
4. **Fase 4** — o Kanban depende de pedidos existindo (Fase 3).
5. **Fase 5** — pode ser desenvolvida em paralelo com a Fase 4, pois é independente.
6. **Fase 6 por último** — o n8n só faz sentido quando toda a API está estável.

---

## Arquivos do Projeto (Resumo Final)

```
modernpos/
├── CONCIERGE_IA_BRIEFING.md                      ← Análise da ideia (este projeto)
├── CONCIERGE_IA_ROADMAP.md                       ← Este arquivo
│
├── migrations/
│   ├── 2026_03_26_create_ai_concierge_tables.sql ← FASE 1
│   └── 2026_03_26_add_ai_token_to_stores.sql     ← FASE 5
│
├── concierge_catalogo.php                        ← FASE 2
├── concierge_catalogo_form.php                   ← FASE 2
├── concierge_pedidos.php                         ← FASE 4
│
├── _inc/
│   ├── helper/
│   │   ├── ai_image_webp.php                     ← FASE 2
│   │   └── ai_plan_gate.php                      ← FASE 5
│   └── ajax/
│       ├── ai_catalogo_salvar.php                ← FASE 2
│       ├── ai_catalogo_variante.php              ← FASE 2
│       ├── ai_catalogo_foto.php                  ← FASE 2
│       ├── ai_pedido_status.php                  ← FASE 4
│       └── ai_pedidos_badge.php                  ← FASE 4
│
└── api/
    └── concierge/
        ├── webhook.php                           ← FASE 3 (entrada única n8n)
        ├── buscar_produto.php                    ← FASE 3
        ├── buscar_por_foto.php                   ← FASE 3
        ├── perfil_cliente.php                    ← FASE 3
        ├── criar_pedido.php                      ← FASE 3
        ├── confirmar_pagamento.php               ← FASE 3
        └── registrar_comprovante_uber.php        ← FASE 3
```
