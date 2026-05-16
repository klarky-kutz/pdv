# Plano de Implementação: Gestão de Grupos com IA (Moda IA)

Este documento detalha a arquitetura, estrutura de dados e o roteiro de implementação para o módulo de Gestão de Grupos com IA no ecossistema ModernPOS + SaaS.

---

## 1. Arquitetura e Fluxo de Dados

O módulo funciona como uma ponte entre o **ModernPOS (Admin)**, o **SaaS (Core)** e o **n8n (Automação)**.

### Diagrama Lógico
1. **PHP (Admin)**: Interface do usuário, agendamento no banco e controle de limites.
2. **SaaS Bridge**: Valida se o Tenant tem permissão e créditos para a ação.
3. **API Interna**: Endpoints que o n8n consome para buscar dados de produtos/grupos.
4. **n8n**: Processa a inteligência (IA), gera o conteúdo e realiza o disparo via Evolution API.
5. **Webhooks**: Notificam o PHP sobre o status do envio para atualização do dashboard.

---

## 2. Estrutura de Banco de Dados (SQL)

Devem ser criadas as tabelas no banco de dados principal para suportar o módulo.

```sql
-- 1. Tabela de Grupos (Sincronizados da Evolution API)
CREATE TABLE IF NOT EXISTS `concierge_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `remote_jid` varchar(100) NOT NULL, -- ID do grupo no WhatsApp
  `name` varchar(255) NOT NULL,
  `member_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `category` varchar(50) DEFAULT 'Geral', -- Ex: VIP, Promo, etc.
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_groups` (`tenant_id`, `store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabela de Campanhas (Planejamento da IA)
CREATE TABLE IF NOT EXISTS `concierge_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `media_url` varchar(255) DEFAULT NULL,
  `status` enum('draft', 'scheduled', 'sent', 'failed') DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabela de Disparos (Relação Campanha x Grupos)
CREATE TABLE IF NOT EXISTS `concierge_broadcasts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `status` enum('pending', 'sent', 'error') DEFAULT 'pending',
  `error_message` text,
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`campaign_id`) REFERENCES `concierge_campaigns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Extensão da tabela Plans (SaaS)
ALTER TABLE `plans` 
ADD COLUMN `ai_groups_enabled` tinyint(1) DEFAULT 0,
ADD COLUMN `ai_groups_limit` int(11) DEFAULT 0, -- Qtd de grupos permitidos
ADD COLUMN `ai_broadcast_monthly_limit` int(11) DEFAULT 0; -- Qtd de mensagens/mês
```

---

## 3. Integração SaaS (Multi-tenant)

### Chaves de Permissão
Adicionar em `PermissionCatalog.php` e `user_group_form.php`:
- `concierge_groups_access`: Acesso ao módulo.
- `concierge_groups_ai_create`: Permissão para usar IA na criação.
- `concierge_groups_manage`: Permissão para gerenciar grupos.

### Controle de Limites
No arquivo `concierge_grupos.php`, utilizar a `SaasLimitsBridge`:
```php
$limits = SaasLimitsBridge::getPlanLimits($pdo, $tenantId);
$canAccess = SaasLimitsBridge::isUserActiveInTenant($pdo, $tenantId, $userId);
// Lógica de bloqueio por UI se $limits['ai_groups_enabled'] == 0
```

---

## 4. APIs e Webhooks

### Endpoints Internos (PHP)
- `GET /api/concierge/groups.php`: Lista grupos ativos do tenant.
- `POST /api/concierge/campaigns.php`: Salva/Agenda uma nova campanha.
- `GET /api/concierge/products.php`: Serve dados do catálogo para a IA no n8n.

### Fluxo n8n
1. **Trigger**: Cron (para agendados) ou Webhook (para "Disparar Agora").
2. **Auth**: O n8n envia `X-Tenant-ID` e `X-Api-Key` nas requisições ao PHP.
3. **Ação**:
   - IA analisa o catálogo via API.
   - IA gera texto/imagem.
   - n8n dispara para a Evolution API.
   - n8n chama Webhook de retorno no PHP para marcar como "Enviado".

---

## 5. Roteiro de Implementação (Passo a Passo)

### Fase 1: Base e Dados
1. [ ] Executar o SQL de criação das tabelas.
2. [ ] Adicionar colunas de limite na tabela `plans`.
3. [ ] Atualizar o `PermissionCatalog.php` com as novas chaves.

### Fase 2: SaaS Core
1. [ ] Atualizar o painel de edição de planos em `saas/painel/paginas/adicionar/` para incluir os novos campos de limite.
2. [ ] Criar modal de bloqueio (bloqueio.png) em `concierge_grupos.php` para usuários sem o recurso ativo.

### Fase 3: Lógica Backend (ModernPOS)
1. [ ] Criar helper `ai_groups_helper.php` para centralizar consultas de grupos e campanhas.
2. [ ] Implementar a lógica de agendamento:
   - Criar um script `cron_broadcasts.php` que verifica campanhas `scheduled` e dispara o Webhook para o n8n.
3. [ ] Integrar o `filemanager.php` na modal de criação de campanha.

### Fase 4: Integração n8n
1. [ ] Criar Workflow no n8n dedicado a "Moda IA - Grupos".
2. [ ] Configurar a Evolution API para os disparos.
3. [ ] Testar o isolamento de dados entre diferentes `tenant_id`.

---

## 6. UI/UX e Recursos Avançados

- **File Manager**: Utilizar a mesma lógica de `concierge_catalogo.php` chamando o seletor de mídia do sistema.
- **Agenda**: O visual já foi implementado, agora os dados devem vir da tabela `concierge_campaigns` com status `scheduled`.
- **Monetização**: Sugere-se unificar o sistema de créditos com o "Chamadas IA" existente, onde cada disparo de campanha em grupo consome X créditos.

---
*Este plano foi gerado para servir de guia técnico na expansão do módulo Moda IA.*

---

## 7. Auditoria de Lacunas (Modelo vs. Real)

| Recurso | Status no PHP | Ação Necessária |
| :--- | :--- | :--- |
| **Lista de Campanhas** | Mock (estático) | Criar loop PHP com `SELECT` na tabela `concierge_campaigns`. |
| **Contagem de Grupos** | Mock (estático) | Integrar `COUNT` da tabela `concierge_groups` por `tenant_id`. |
| **Filtros (Aprovação, etc)** | Parcial (JS) | Refinar filtros no PHP para buscar por status real no banco. |
| **Drawer (Detalhes)** | Mock (estático) | Carregar dados dinâmicos da campanha via AJAX ao clicar no item. |
| **Ações (Sync Grupos)** | Interface apenas | Criar endpoint PHP que chame a Evolution API para listar e salvar grupos no banco. |
| **Ações (Disparar Agora)** | Interface apenas | Integrar com Webhook n8n enviando o `campaign_id` e `tenant_id`. |
| **Limites SaaS** | Não integrado | Inserir `SaasLimitsBridge` no topo do arquivo para validar planos e créditos. |
| **Gestão de Mídias** | Mock (estático) | Integrar com `filemanager.php` para upload/seleção de fotos reais. |
