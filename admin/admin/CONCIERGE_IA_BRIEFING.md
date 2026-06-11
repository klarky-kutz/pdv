# Concierge IA — Briefing & Análise Técnica

> **Projeto:** Módulo de Vendas Automatizadas por IA para ModernPOS  
> **Nicho-alvo:** Lojas de Moda (roupas, acessórios)  
> **Stack:** PHP + MySQL (ModernPOS) · Node/n8n (IA) · WhatsApp Business API  
> **Documento relacionado:** [`CONCIERGE_IA_ROADMAP.md`](./CONCIERGE_IA_ROADMAP.md)

---

## Visão Geral

O módulo **Concierge IA** transforma o ModernPOS de um sistema de gestão em uma **máquina de vendas automatizada**, onde a inteligência artificial atende, convence e fecha pedidos via WhatsApp — sem intervenção humana nas etapas rotineiras.

O lojista continua no controle das operações físicas (separação, rota, entrega) enquanto a IA cuida do atendimento 24h, da memória de perfil de cada cliente e da apresentação do catálogo curado.

---

## Os 4 Pilares do Módulo

### Pilar 1 — Catálogo Inteligente (Alimentando a IA)

**O que é:** Um catálogo curado pelo lojista, separado do estoque operacional do ModernPOS, com hierarquia específica para moda.

| Entidade | Descrição | Exemplo |
|---|---|---|
| **Modelo** | O produto-raiz | "Vestido Midi Floral" |
| **Variante** | Combinação de cor + tamanho + estoque | "Rosa · P · 3 unidades" |

**Diferenciais técnicos:**
- **Visão Computacional:** o cliente envia uma foto no WhatsApp → o n8n encaminha para uma API de visão (GPT-4o Vision / Gemini) → a IA identifica características visuais → busca no catálogo da loja pelo endpoint `/api/concierge/buscar_por_foto.php`.
- **Métrica de Desejo:** toda vez que uma peça é consultada no chat (mesmo sem compra), o sistema incrementa `ai_demand_count` no banco. O lojista vê um ranking de peças mais desejadas — dado de mercado real e gratuito.
- **WebP automático:** todas as fotos enviadas são convertidas para WebP no servidor, reduzindo o consumo de MB do plano SaaS.

**Tabelas novas necessárias:**
```
ai_catalogo_models    → modelo pai (nome, descrição, tags, fotos, demand_count)
ai_catalogo_variants  → variante filha (cor, tamanho, preço, estoque, foto_webp)
```

---

### Pilar 2 — Atendimento e Venda (O Braço n8n)

**O que é:** A IA não é um FAQ; ela é uma vendedora consultiva com memória de longo prazo.

**Funcionalidades:**

1. **Memória de Perfil**
   - Tabela `ai_chat_profiles` armazena: `whatsapp_phone`, `nome`, `tamanho_habitual`, `preferencias_json`, `ultima_interacao`.
   - Quando a Maria volta após 2 meses, a IA puxa o perfil e já sabe que ela veste M e gosta de pastéis.

2. **Indicação de Similares**
   - Se o estoque de uma variante zera, a IA busca modelos com `tags` compatíveis (corte, estilo, proposta).
   - Query: `WHERE tags LIKE '%midi%' OR tags LIKE '%floral%' AND p_id != :esgotado`

3. **Fechamento via Gateway**
   - A IA envia o link de pagamento (Stripe/MP/Pix).
   - Um webhook do gateway dispara `confirmar_pagamento.php` → o pedido muda de status automaticamente.

**Tabelas novas necessárias:**
```
ai_chat_profiles      → memória de perfil por telefone + loja
ai_orders             → pedidos originados no WhatsApp (status: pendente → pago → separando → rota → entregue)
ai_order_items        → itens do pedido (variant_id, qty, price_snapshot)
```

---

### Pilar 3 — Logística Simplificada (Kanban de 4 Etapas)

**O que é:** Um painel Kanban no ModernPOS admin que orquestra o pedido do WhatsApp até a entrega.

**Fluxo:**
```
[Pedido] ──pagamento aprovado──► [Separação] ──lojista age──► [Rota] ──Uber Flash──► [Entregue]
```

**Detalhes técnicos:**

- **Kanban:** página `concierge_pedidos.php` no admin do ModernPOS com 4 colunas drag-and-drop.
- **Drawer de Detalhes:** ao clicar em um card de pedido, abre uma gaveta lateral (`<aside>`) com: dados do cliente, itens, histórico do chat, foto do comprovante Uber, timeline de status.
- **Uber Flash:** o cliente solicita o Uber e envia o print do comprovante no WhatsApp → n8n recebe → chama `api/concierge/registrar_comprovante_uber.php` → salva a imagem e notifica o lojista no painel.
- **Notificação push** (badge no menu lateral) quando um pedido muda de status.

---

### Pilar 4 — Modelo de Negócio SaaS (Monetização e Escala)

**O que é:** O Concierge IA é um **Upsell Premium** dentro do SaaS já existente.

**Controles já implementados na tabela `plans`:**
- `ai_concierge_enabled` → habilita/bloqueia o módulo por plano ✅ *(migration aplicada em 2026-03-26)*
- `ai_webhook_calls` → limite mensal de chamadas ao webhook n8n ✅
- `ai_catalog_limit` → limite de produtos no catálogo IA ✅

**O que falta implementar:**
- **UI de Bloqueio:** usuários de plano básico veem o módulo com ícones acinzentados + cadeado + badge "Upgrade" — gera curiosidade e desejo.
- **Contador de chamadas:** middleware que incrementa `ai_calls_used` na tabela `store_saas_usage` a cada requisição ao webhook.
- **Contador de MB:** ao fazer upload de foto WebP, o sistema soma o tamanho ao `storage_used_mb` da loja e valida contra o limite do plano.
- **Webhook único n8n:** todos os lojistas usam a mesma URL de entrada; o campo `loja_id` (= `tenant_id` do ModernPOS) faz o roteamento interno dentro do fluxo n8n.

---

## Estado Atual do Projeto

### O que já existe no ModernPOS/SaaS

| Item | Status |
|---|---|
| Tabela `products` com `tenant_id` | ✅ Existe |
| Sistema de permissões `has_permission()` | ✅ Existe |
| Sistema de planos no SaaS (`plans`) | ✅ Existe |
| Colunas `ai_concierge_enabled/calls/catalog` em `plans` | ✅ Migration aplicada (2026-03-26) |
| Upload de imagens (pasta `storage/products`) | ✅ Existe |
| API de ajuda ao n8n | ❌ Não existe |
| Tabelas do catálogo IA | ❌ Não existem |
| Tabelas de perfil/pedidos IA | ❌ Não existem |
| Kanban de pedidos WhatsApp | ❌ Não existe |
| UI de bloqueio por plano | ❌ Não existe |

### Dependências Externas

| Serviço | Papel |
|---|---|
| **n8n** (self-hosted ou cloud) | Orquestrador do fluxo de atendimento IA |
| **WhatsApp Business API** (Evolution/360dialog/Meta) | Canal de entrada das mensagens |
| **GPT-4o Vision / Gemini** | Visão computacional para reconhecer fotos |
| **Gateway de Pagamento** (Stripe / Mercado Pago / Asaas) | Geração e confirmação de links de pagamento |

---

## Roadmap de Implementação (Resumo)

O desenvolvimento está dividido em **6 Fases** detalhadas no arquivo [`CONCIERGE_IA_ROADMAP.md`](./CONCIERGE_IA_ROADMAP.md):

| Fase | Nome | Entregável Principal |
|---|---|---|
| **F1** | Fundação — Banco de Dados | Migrations das tabelas do módulo |
| **F2** | Catálogo IA (Admin ModernPOS) | CRUD de modelos e variantes + upload WebP |
| **F3** | API Webhook (n8n Bridge) | Endpoints REST para o n8n consultar |
| **F4** | Kanban de Pedidos | Painel visual de acompanhamento de entregas |
| **F5** | Integração SaaS (Limites e Bloqueio) | Gate de plano, contadores de uso, UI locked |
| **F6** | Fluxo n8n Master | Template de fluxo reutilizável por loja_id |

**Estimativa total:** ~8 a 12 semanas de desenvolvimento solo (full-stack PHP + n8n).

---

## Decisões de Arquitetura

1. **Separação de catálogos:** o catálogo IA (`ai_catalogo_*`) é independente da tabela `products`. Isso evita poluir o estoque operacional com dados voltados para IA e permite que o lojista cuide os dois separadamente.

2. **tenant_id em todas as tabelas:** todas as novas tabelas terão `tenant_id` (= `store_id` do ModernPOS) para garantir isolamento multi-tenant nativo.

3. **WebP obrigatório:** toda foto enviada via WhatsApp ou upada pelo lojista é convertida para WebP antes de persistir. Reduz em média 70% o tamanho vs JPEG, economizando storage do servidor SaaS.

4. **Webhook único + loja_id:** um único endpoint `/api/concierge/webhook.php?loja_id=X` recebe todas as chamadas n8n. O `loja_id` faz o roteamento. Elimina o custo de múltiplos fluxos n8n.

5. **Permissões via `has_permission()`:** o módulo usa a chave `access_concierge_ia` no sistema existente de permissões do ModernPOS, sem criar nova camada de auth.
