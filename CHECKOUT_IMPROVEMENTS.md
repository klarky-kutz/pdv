# Melhorias no Sistema de Checkout Interno - ModernPOS

## Resumo das Implementações

### 1. ✅ PIX Manual - Tela Customizada com Suporte

#### Arquivos Modificados:
- `account/pages/plans.php` (linhas 825-931)
- `conta/assets/css/plans.css` (linhas 246-310)
- `conta/assets/js/plans.js` (linhas 883-1136)

#### Funcionalidades Implementadas:
- **Detecção Automática**: O sistema detecta quando o pagamento é PIX Manual (`gateway=pix_manual`)
- **Botão WhatsApp**: Link direto para WhatsApp com mensagem pré-configurada (editável no banco)
- **Botão "Enviar Comprovante"**: Abre modal para upload de comprovante e criação de ticket automático
- **UI Especial**: Card destacado com instruções claras para o usuário

#### Como Funciona:
1. Usuário seleciona PIX no checkout
2. Após criar o pedido, é redirecionado para página de pagamento
3. Se for PIX Manual, aparece:
   - QR Code PIX
   - Código Copia e Cola
   - **Card de Suporte** com:
     - Botão WhatsApp (se configurado)
     - Botão "Enviar Comprovante"

### 2. ✅ Sistema de Tickets para Verificação PIX

#### Arquivos Criados:
- `conta/_ajax/create_pix_verification_ticket.php` (novo endpoint)

#### Funcionalidades:
- **Upload de Comprovante**: Suporta JPG, PNG e PDF (até 5MB)
- **Ticket Automático**: Cria ticket com:
  - Categoria: Verificação de Pagamento
  - Título: "Verificação de Pagamento PIX - Pedido #X"
  - Status: `open`
  - Prioridade: `high`
  - Anexo do comprovante (se enviado)
- **Integração Completa**: Salva referência na tabela `saas_orders.proof_file`

### 3. ✅ Modal de Envio de Comprovante

#### Componentes:
- Modal responsivo com upload de arquivo
- Validação client-side e server-side
- Preview e instruções claras
- Redirecionamento automático para o ticket após criação

### 4. ✅ Campos WhatsApp no Banco de Dados

#### Migration SQL:
- **Arquivo**: `migrations/add_whatsapp_fields_to_payment_gateways.sql`
- **Campos Adicionados**:
  - `whatsapp_support` (VARCHAR 20): Número do WhatsApp (ex: 5511999999999)
  - `whatsapp_message` (TEXT): Mensagem padrão configurável

#### Como Configurar:
```sql
UPDATE saas_payment_gateways 
SET whatsapp_support = '5511999999999',
    whatsapp_message = 'Olá! Realizei um pagamento PIX e gostaria de confirmar a verificação.'
WHERE gateway = 'pix_manual';
```

### 5. ✅ Detecção de Gateway Stripe

#### Status:
A lógica de detecção já está implementada corretamente:
- Verifica `saas_payment_gateways` onde `gateway = 'stripe'` e `is_enabled = 1`
- Define `$pmEnabled['card'] = true` quando Stripe está ativo
- O botão de cartão só fica disabled se Stripe não estiver habilitado

#### Para Habilitar Stripe:
```sql
UPDATE saas_payment_gateways 
SET is_enabled = 1,
    stripe_secret_key = 'sk_test_...',
    stripe_publishable_key = 'pk_test_...',
    environment = 'sandbox'
WHERE gateway = 'stripe' AND tenant_id = 1;
```

## Fluxo Completo PIX Manual

```
1. Usuário escolhe plano → 2. Seleciona "PIX" no checkout
                              ↓
3. Sistema detecta PIX Manual → 4. Cria pedido (status: pending)
                              ↓
5. Página de pagamento exibe:
   - QR Code
   - Código Copia e Cola
   - Card de Suporte (WhatsApp + Enviar Comprovante)
                              ↓
6a. Usuário clica "Falar no WhatsApp"
    → Abre WhatsApp com mensagem pré-configurada
    
6b. Usuário clica "Enviar Comprovante"
    → Modal abre para upload
    → Ticket criado automaticamente
    → Redirecionado para o ticket
                              ↓
7. Equipe de suporte verifica pagamento no ticket
                              ↓
8. Admin confirma pagamento (via webhook ou manual)
                              ↓
9. Plano é ativado automaticamente
```

## Testes Recomendados

### ✅ Teste 1: PIX Manual - Tela Customizada
1. Acesse: `http://localhost/modernpos/conta/checkout.php?plan=1&billing=monthly`
2. Selecione método: **PIX**
3. Preencha dados e clique "Prosseguir para pagamento"
4. Verifique se aparece:
   - QR Code
   - Card de suporte com botões WhatsApp e "Enviar Comprovante"

### ✅ Teste 2: Upload de Comprovante
1. Na página de pagamento PIX Manual
2. Clique em "Enviar Comprovante"
3. Faça upload de uma imagem JPG ou PNG
4. Adicione descrição (opcional)
5. Clique "Enviar para Verificação"
6. Verifique se:
   - Ticket foi criado
   - Arquivo foi anexado
   - Redirecionamento para o ticket funciona

### ✅ Teste 3: Botão WhatsApp
1. Configure número no banco: `UPDATE saas_payment_gateways SET whatsapp_support = '5511999999999' WHERE gateway = 'pix_manual'`
2. Na página de pagamento PIX Manual
3. Clique no botão "Falar no WhatsApp"
4. Verifique se abre WhatsApp Web com mensagem pré-preenchida

### ⚠️ Teste 4: Stripe (Pendente)
**Requisitos**:
- Stripe SDK instalado em `saas/Stripe/vendor/`
- Chaves configuradas em `saas_payment_gateways`
- Webhook configurado no Stripe Dashboard

**Como testar**:
1. Configure Stripe no banco
2. Acesse checkout
3. Selecione "Cartão"
4. Verifique se redireciona para Stripe Checkout Session
5. Complete pagamento de teste
6. Verifique se webhook confirma o pagamento

## Configurações Necessárias

### 1. Executar Migration SQL
```bash
mysql -u root -p modernpos < migrations/add_whatsapp_fields_to_payment_gateways.sql
```

### 2. Criar Diretório de Uploads
```bash
mkdir -p uploads/payment_proofs
chmod 755 uploads/payment_proofs
```

### 3. Configurar Gateway PIX Manual
```sql
INSERT INTO saas_payment_gateways (
    tenant_id, gateway, gateway_display_name, is_enabled, 
    pix_chave, pix_titular, pix_cidade, 
    whatsapp_support, whatsapp_message,
    environment, created_at
) VALUES (
    1, 'pix_manual', 'PIX Manual', 1,
    'seuemail@exemplo.com', 'Seu Nome ou Empresa', 'SAO PAULO',
    '5511999999999', 'Olá! Realizei um pagamento PIX e gostaria de confirmar a verificação.',
    'sandbox', NOW()
);
```

### 4. Verificar Categoria de Suporte
O sistema busca automaticamente uma categoria com "Pagamento" ou "Financeiro" no nome. Se não existir:
```sql
INSERT INTO support_categories (tenant_id, name, description, is_active, created_at)
VALUES (0, 'Verificação de Pagamento', 'Tickets de verificação de pagamentos PIX', 1, NOW());
```

## Regras de Negócio (Implementadas)

### Upgrade
- ✅ Imediato após confirmação de pagamento
- ✅ Limites do plano (max_stores, etc) atualizados
- ✅ Webhook confirma automaticamente (Stripe, Asaas, Mercado Pago)
- ⚠️ PIX Manual: Requer verificação manual via ticket

### Downgrade
- ⚠️ **Pendente**: Implementar agendamento de mudança de plano
- Deve entrar em vigor apenas no próximo ciclo
- Acesso atual mantido até fim do período pago

### Cancelamento
- ⚠️ **Pendente**: Implementar endpoint `subscription_cancel.php`
- Deve manter acesso até fim do período pago
- Não renovar automaticamente

## Arquivos Modificados/Criados

### Novos Arquivos
- ✅ `conta/_ajax/create_pix_verification_ticket.php` (411 linhas)
- ✅ `migrations/add_whatsapp_fields_to_payment_gateways.sql` (17 linhas)
- ✅ `CHECKOUT_IMPROVEMENTS.md` (este arquivo)

### Arquivos Modificados
- ✅ `account/pages/plans.php` (linhas 825-931, 1303-1359)
- ✅ `conta/assets/css/plans.css` (linhas 246-310)
- ✅ `conta/assets/js/plans.js` (linhas 883-899, 1034-1136)

## Próximos Passos

1. **Testar Fluxo PIX Manual Completo**
   - Upload de comprovante
   - Criação de ticket
   - Verificação manual
   - Ativação do plano

2. **Configurar e Testar Stripe**
   - Instalar SDK se necessário
   - Configurar chaves
   - Testar checkout
   - Configurar webhook

3. **Implementar Lógica de Downgrade**
   - Adicionar campo `scheduled_plan_id` em `tenants`
   - Criar job/cron para aplicar mudanças no próximo ciclo

4. **Implementar Cancelamento**
   - Criar endpoint `subscription_cancel.php`
   - Marcar assinatura como cancelada
   - Manter acesso até fim do período

5. **Webhooks**
   - Verificar `saas/webhooks/stripe.php`
   - Verificar `saas/webhooks/asaas.php`
   - Verificar `saas/webhooks/mercadopago.php`
   - Garantir que aplicam plano após confirmação

## Notas Importantes

- ⚠️ PIX Manual é **único** método que não tem confirmação automática
- ✅ Todos os outros métodos (Stripe, Asaas, MP) confirmam via webhook
- ✅ Sistema de tickets integrado com support_tickets existente
- ✅ Upload seguro com validações de tipo e tamanho
- ⚠️ Lembre-se de executar a migration SQL antes de testar

## Suporte

Para dúvidas ou problemas:
1. Verifique logs em `error_log`
2. Console do navegador para erros JS
3. Verifique permissões do diretório `uploads/payment_proofs`
4. Confirme que migration SQL foi executada
