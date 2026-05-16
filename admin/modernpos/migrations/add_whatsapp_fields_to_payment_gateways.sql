-- Migration: Adicionar campos de WhatsApp para PIX Manual
-- Data: 2026-02-03
-- Descrição: Adiciona campos whatsapp_support e whatsapp_message para suporte via WhatsApp no PIX Manual

-- Adiciona coluna whatsapp_support (número do WhatsApp)
ALTER TABLE `saas_payment_gateways` 
ADD COLUMN IF NOT EXISTS `whatsapp_support` VARCHAR(20) DEFAULT NULL COMMENT 'Número do WhatsApp para suporte (ex: 5511999999999)' AFTER `pix_cidade`;

-- Adiciona coluna whatsapp_message (mensagem padrão)
ALTER TABLE `saas_payment_gateways` 
ADD COLUMN IF NOT EXISTS `whatsapp_message` TEXT DEFAULT NULL COMMENT 'Mensagem padrão do WhatsApp' AFTER `whatsapp_support`;

-- Exemplo de configuração para PIX Manual (apenas se necessário)
-- UPDATE `saas_payment_gateways` 
-- SET `whatsapp_support` = '5511999999999',
--     `whatsapp_message` = 'Olá! Realizei um pagamento PIX e gostaria de confirmar a verificação.'
-- WHERE `gateway` = 'pix_manual';
