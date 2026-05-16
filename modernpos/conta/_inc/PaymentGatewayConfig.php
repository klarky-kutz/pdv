<?php
/**
 * PaymentGatewayConfig
 * 
 * Classe para carregar configurações de gateways de pagamento
 * Respeita as preferências configuradas em saas_payment_gateways:
 * - default_card_gateway
 * - default_pix_gateway  
 * - default_boleto_gateway
 * - stripe_billing_mode (one_off ou subscription)
 * - E demais configurações específicas de cada gateway
 */

class PaymentGatewayConfig
{
    /**
     * Chave base usada para criptografia de segredos de gateway.
     * IMPORTANTE: deve ser a mesma chave usada em pagamentos-salvar.php do SaaS.
     */
    private const ENC_KEY = 'CHANGE_ME_TO_A_STRONG_SECRET_KEY';

    /**
     * Decripta um segredo vindo do banco. Caso não pareça criptografado, devolve como está.
     */
    public static function decryptSecret(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        $method = 'AES-256-CBC';
        $key    = hash('sha256', self::ENC_KEY, true);
        $ivLen  = openssl_cipher_iv_length($method);

        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) <= $ivLen) {
            // Não parece base64 de IV+cipher; assume texto puro
            return $stored;
        }

        $iv        = substr($raw, 0, $ivLen);
        $cipherRaw = substr($raw, $ivLen);

        $plain = openssl_decrypt($cipherRaw, $method, $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            // Não conseguiu decriptar, assume que é texto puro legado
            return $stored;
        }

        return $plain;
    }

    /**
     * Resolve o ambiente efetivo (sandbox/production) para um tenant.
     * Por padrão usa 'sandbox' para segurança em ambiente de desenvolvimento.
     */
    public static function resolveEnvironment(PDO $pdo, int $tenantId, ?string $environment = null): string
    {
        $env = 'sandbox';

        if ($environment !== null && in_array($environment, ['sandbox', 'production'], true)) {
            return $environment;
        }

        try {
            $stmt = $pdo->prepare("SELECT payment_environment FROM tenants WHERE tenant_id = :tenant_id LIMIT 1");
            $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['payment_environment']) && in_array($row['payment_environment'], ['sandbox', 'production'], true)) {
                $env = (string)$row['payment_environment'];
            }
        } catch (Throwable $e) {
            // Se a coluna ainda não existir ou houver erro, mantém fallback em sandbox
        }

        return $env;
    }

    /**
     * Busca configuração de um gateway específico
     * 
     * @param PDO $pdo
     * @param int $tenantId
     * @param string $gatewayName (stripe, asaas, mercado_pago, pix_manual)
     * @param string|null $environment Ambiente específico (sandbox/production). Se null, usa sandbox.
     * @return array|null
     */
    public static function get(PDO $pdo, int $tenantId, string $gatewayName, ?string $environment = null): ?array
    {
        try {
            // Resolve o ambiente a usar
            $env = self::resolveEnvironment($pdo, $tenantId, $environment);

            // Primeiro tenta buscar com filtro de environment
            $stmt = $pdo->prepare("
                SELECT * 
                  FROM saas_payment_gateways 
                 WHERE tenant_id = :tenant_id 
                   AND gateway = :gateway
                   AND environment = :environment
                   AND is_enabled = 1
                 LIMIT 1
            ");
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':gateway' => $gatewayName,
                ':environment' => $env
            ]);
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Fallback: se não encontrou com environment específico, tenta sem filtro
            if (!$row) {
                $stmt = $pdo->prepare("
                    SELECT * 
                      FROM saas_payment_gateways 
                     WHERE tenant_id = :tenant_id 
                       AND gateway = :gateway
                       AND is_enabled = 1
                     ORDER BY environment = :environment DESC, id ASC
                     LIMIT 1
                ");
                $stmt->execute([
                    ':tenant_id' => $tenantId,
                    ':gateway' => $gatewayName,
                    ':environment' => $env
                ]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$row) {
                return null;
            }
            
            // Monta array de configuração normalizado
            $config = [
                'id' => (int)$row['id'],
                'tenant_id' => (int)$row['tenant_id'],
                'gateway' => (string)$row['gateway'],
                'gateway_code' => (string)$row['gateway'], // para compatibilidade
                'gateway_display_name' => (string)($row['gateway_display_name'] ?? ucfirst($row['gateway'])),
                'enabled' => (int)($row['is_enabled'] ?? 0) === 1,
                'is_enabled' => (int)($row['is_enabled'] ?? 0) === 1,
                'environment' => (string)($row['environment'] ?? 'sandbox'),
            ];
            
            // Stripe - DECRIPTA a secret_key
            if ($gatewayName === 'stripe') {
                $config['stripe_secret_key'] = self::decryptSecret($row['stripe_secret_key'] ?? null);
                $config['stripe_publishable_key'] = $row['stripe_publishable_key'] ?? null;
                $config['stripe_webhook_secret'] = $row['stripe_webhook_secret'] ?? null;
                $config['stripe_currency'] = $row['stripe_currency'] ?? 'BRL';
                // Tipo de cobrança: one_off (pagamento único) ou subscription (recorrente)
                $config['stripe_billing_mode'] = $row['stripe_billing_mode'] ?? 'one_off';
                // Fluxo visual: checkout (redireciona para Stripe) ou transparent (formulário embutido)
                $config['stripe_flow_mode'] = $row['stripe_flow_mode'] ?? 'checkout';
            }
            
            // Asaas - A chave é armazenada em claro atualmente (pagamentos-salvar.php linha 254)
            // mas chamamos decryptSecret por segurança caso alguém mude
            if ($gatewayName === 'asaas') {
                $config['asaas_api_key'] = self::decryptSecret($row['asaas_api_key'] ?? null);
                $config['asaas_wallet_id'] = $row['asaas_wallet_id'] ?? null;
                $config['asaas_subscription_enabled'] = !empty($row['asaas_subscription_enabled']);
                $config['asaas_default_description'] = $row['asaas_default_description'] ?? null;
                $config['asaas_max_installments'] = (int)($row['asaas_max_installments'] ?? 1);
                $config['asaas_webhook_url'] = $row['asaas_webhook_url'] ?? null;
            }
            
            // Mercado Pago - DECRIPTA o access_token
            if ($gatewayName === 'mercado_pago') {
                $config['mp_access_token'] = self::decryptSecret($row['mp_access_token'] ?? null);
                $config['mp_public_key'] = $row['mp_public_key'] ?? null;
            }
            
            // PIX Manual
            if ($gatewayName === 'pix_manual') {
                $config['pix_chave'] = $row['pix_chave'] ?? null;
                $config['pix_titular'] = $row['pix_titular'] ?? null;
                $config['pix_cidade'] = $row['pix_cidade'] ?? null;
                $config['whatsapp_support'] = $row['whatsapp_support'] ?? null;
                $config['whatsapp_message'] = $row['whatsapp_message'] ?? null;
            }
            
            return $config;
            
        } catch (Throwable $e) {
            error_log('[PaymentGatewayConfig] Erro ao buscar gateway ' . $gatewayName . ': ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Busca gateway padrão para cartão
     * Respeita a preferência default_card_gateway da tabela
     * 
     * @param PDO $pdo
     * @param int $tenantId
     * @return array|null
     */
    public static function getDefaultCardGateway(PDO $pdo, int $tenantId): ?array
    {
        try {
            // Verifica se existe coluna default_card_gateway
            $hasDefaultColumn = false;
            try {
                $stCol = $pdo->query("SHOW COLUMNS FROM saas_payment_gateways LIKE 'default_card_gateway'");
                $hasDefaultColumn = $stCol && $stCol->rowCount() > 0;
            } catch (Throwable $e) {
                $hasDefaultColumn = false;
            }
            
            if ($hasDefaultColumn) {
                // Busca pela preferência configurada
                $stmt = $pdo->prepare("
                    SELECT gateway 
                      FROM saas_payment_gateways 
                     WHERE tenant_id = :tenant_id 
                       AND is_enabled = 1
                       AND default_card_gateway = 1
                     ORDER BY id ASC
                     LIMIT 1
                ");
                $stmt->execute([':tenant_id' => $tenantId]);
                $gatewayName = $stmt->fetchColumn();
                
                if ($gatewayName) {
                    return self::get($pdo, $tenantId, $gatewayName);
                }
            }
            
            // Fallback: busca primeiro gateway de cartão habilitado
            // (ordem de preferência quando não há default_* configurado)
            $candidates = ['stripe', 'asaas', 'mercado_pago'];
            foreach ($candidates as $gateway) {
                $config = self::get($pdo, $tenantId, $gateway);
                if ($config && $config['enabled']) {
                    return $config;
                }
            }
            
            return null;
            
        } catch (Throwable $e) {
            error_log('[PaymentGatewayConfig] Erro ao buscar gateway padrão de cartão: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Busca gateway padrão para PIX
     * Respeita a preferência default_pix_gateway da tabela
     * 
     * @param PDO $pdo
     * @param int $tenantId
     * @return array|null
     */
    public static function getDefaultPixGateway(PDO $pdo, int $tenantId): ?array
    {
        try {
            // Verifica se existe coluna default_pix_gateway
            $hasDefaultColumn = false;
            try {
                $stCol = $pdo->query("SHOW COLUMNS FROM saas_payment_gateways LIKE 'default_pix_gateway'");
                $hasDefaultColumn = $stCol && $stCol->rowCount() > 0;
            } catch (Throwable $e) {
                $hasDefaultColumn = false;
            }
            
            if ($hasDefaultColumn) {
                // Busca pela preferência configurada
                $stmt = $pdo->prepare("
                    SELECT gateway 
                      FROM saas_payment_gateways 
                     WHERE tenant_id = :tenant_id 
                       AND is_enabled = 1
                       AND default_pix_gateway = 1
                     ORDER BY id ASC
                     LIMIT 1
                ");
                $stmt->execute([':tenant_id' => $tenantId]);
                $gatewayName = $stmt->fetchColumn();
                
                if ($gatewayName) {
                    return self::get($pdo, $tenantId, $gatewayName);
                }
            }
            
            // Fallback: busca primeiro gateway PIX habilitado
            // IMPORTANTE: pix_manual tem prioridade quando habilitado (não depende de API externa)
            $candidates = ['pix_manual', 'asaas', 'mercado_pago'];
            foreach ($candidates as $gateway) {
                $config = self::get($pdo, $tenantId, $gateway);
                if ($config && $config['enabled']) {
                    return $config;
                }
            }
            
            return null;
            
        } catch (Throwable $e) {
            error_log('[PaymentGatewayConfig] Erro ao buscar gateway padrão PIX: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Busca gateway padrão para Boleto
     * Respeita a preferência default_boleto_gateway da tabela
     * 
     * @param PDO $pdo
     * @param int $tenantId
     * @return array|null
     */
    public static function getDefaultBoletoGateway(PDO $pdo, int $tenantId): ?array
    {
        try {
            // Verifica se existe coluna default_boleto_gateway
            $hasDefaultColumn = false;
            try {
                $stCol = $pdo->query("SHOW COLUMNS FROM saas_payment_gateways LIKE 'default_boleto_gateway'");
                $hasDefaultColumn = $stCol && $stCol->rowCount() > 0;
            } catch (Throwable $e) {
                $hasDefaultColumn = false;
            }
            
            if ($hasDefaultColumn) {
                // Busca pela preferência configurada
                $stmt = $pdo->prepare("
                    SELECT gateway 
                      FROM saas_payment_gateways 
                     WHERE tenant_id = :tenant_id 
                       AND is_enabled = 1
                       AND default_boleto_gateway = 1
                     ORDER BY id ASC
                     LIMIT 1
                ");
                $stmt->execute([':tenant_id' => $tenantId]);
                $gatewayName = $stmt->fetchColumn();
                
                if ($gatewayName) {
                    return self::get($pdo, $tenantId, $gatewayName);
                }
            }
            
            // Fallback: busca primeiro gateway Boleto habilitado (prioriza Asaas > MP)
            $candidates = ['asaas', 'mercado_pago'];
            foreach ($candidates as $gateway) {
                $config = self::get($pdo, $tenantId, $gateway);
                if ($config && $config['enabled']) {
                    return $config;
                }
            }
            
            return null;
            
        } catch (Throwable $e) {
            error_log('[PaymentGatewayConfig] Erro ao buscar gateway padrão Boleto: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Lista todos os gateways habilitados para um tenant
     * 
     * @param PDO $pdo
     * @param int $tenantId
     * @return array
     */
    public static function getAllEnabled(PDO $pdo, int $tenantId): array
    {
        try {
            $stmt = $pdo->prepare("
                SELECT gateway 
                  FROM saas_payment_gateways 
                 WHERE tenant_id = :tenant_id 
                   AND is_enabled = 1
                 ORDER BY id ASC
            ");
            $stmt->execute([':tenant_id' => $tenantId]);
            $gateways = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $configs = [];
            foreach ($gateways as $gateway) {
                $config = self::get($pdo, $tenantId, $gateway);
                if ($config) {
                    $configs[] = $config;
                }
            }
            
            return $configs;
            
        } catch (Throwable $e) {
            error_log('[PaymentGatewayConfig] Erro ao listar gateways: ' . $e->getMessage());
            return [];
        }
    }
}
