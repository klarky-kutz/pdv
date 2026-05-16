# Implementação de Geração Sequencial de Código EAN-13

## 📋 Visão Geral
Sistema de geração automática de códigos de produto no formato EAN-13 brasileiro (7898XXXXXXXXXC).

## 🎯 Características

- **Formato**: 13 dígitos (7898 + 8 sequenciais + verificador)
- **Sequencial**: Cada código é único e incremental
- **Persistente**: Códigos não são reutilizados mesmo após deleção
- **Multi-tenant**: Cada loja (`store_id`) tem sua própria sequência
- **Valor inicial**: 789818057090X (onde X é o dígito verificador)

## 📁 Arquivos Criados/Modificados

### 1. SQL - Tabela de Controle
**Arquivo**: `_inc/sql/create_product_code_sequence_table.sql`

Execute este SQL manualmente no seu banco de dados para criar a tabela de controle.

### 2. Backend PHP - Endpoint de Geração
**Arquivo**: `_inc/generate_product_code.php`

Endpoint AJAX que:
- Verifica autenticação do usuário
- Gera código sequencial por loja
- Calcula dígito verificador EAN-13
- Valida unicidade do código
- Usa transações para evitar conflitos

### 3. Frontend JavaScript - Handler do Botão
**Arquivo**: `assets/itsolution24/js/main.js` (linha ~227)

Modificado para:
- Fazer chamada AJAX ao endpoint
- Mostrar spinner durante geração
- Exibir notificação de sucesso/erro
- Fallback para código aleatório em caso de falha

## 🚀 Passo a Passo de Instalação

### Passo 1: Criar a Tabela no Banco de Dados

1. Acesse phpMyAdmin ou seu cliente MySQL
2. Selecione o banco de dados do ModernPOS
3. Execute o SQL do arquivo: `_inc/sql/create_product_code_sequence_table.sql`

```sql
CREATE TABLE IF NOT EXISTS `product_code_sequence` (
  `store_id` INT UNSIGNED NOT NULL,
  `last_sequence` BIGINT UNSIGNED NOT NULL DEFAULT 18057090,
  `prefix` VARCHAR(4) NOT NULL DEFAULT '7898',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

4. Verifique se a tabela foi criada:
```sql
SHOW TABLES LIKE 'product_code_sequence';
```

### Passo 2: Verificar Permissões dos Arquivos

Os arquivos PHP já foram criados automaticamente:
- ✅ `_inc/generate_product_code.php`
- ✅ `assets/itsolution24/js/main.js` (modificado)

**Importante**: Certifique-se de que o PHP tem permissão de leitura nos arquivos.

### Passo 3: Limpar Cache do Navegador

Após modificar o JavaScript, limpe o cache:
- **Chrome/Edge**: Ctrl + Shift + Delete
- **Firefox**: Ctrl + Shift + Delete
- Ou acesse em modo anônimo para testar

### Passo 4: Testar a Funcionalidade

1. Acesse a página de cadastro/edição de produtos
2. Localize o campo "Código do Produto"
3. Clique no botão com ícone 🎲 (random)
4. O sistema deve:
   - Mostrar um spinner enquanto gera
   - Exibir notificação de sucesso
   - Preencher o campo com código EAN-13 válido

**Exemplo de código gerado**: `7898180570901`

## 🔍 Como Funciona

### Cálculo do Dígito Verificador EAN-13

O dígito verificador é calculado usando o algoritmo padrão EAN-13:

1. Somar os dígitos em posições ímpares (1º, 3º, 5º...) multiplicados por 1
2. Somar os dígitos em posições pares (2º, 4º, 6º...) multiplicados por 3
3. Somar os dois resultados
4. Calcular: (10 - (soma % 10)) % 10

**Exemplo**: Para `789818057090`
- Posições ímpares × 1: 7+9+1+0+7+9 = 33
- Posições pares × 3: (8+8+8+5+0+0) × 3 = 87
- Soma total: 33 + 87 = 120
- Verificador: (10 - (120 % 10)) % 10 = 0
- **Código final**: `7898180570900`

### Fluxo de Geração

```
Usuário clica no botão
    ↓
Frontend (main.js) faz AJAX POST
    ↓
Backend (generate_product_code.php) verifica autenticação
    ↓
Busca/cria sequência da loja no BD
    ↓
Incrementa sequência + calcula verificador
    ↓
Valida unicidade (não existe em products)
    ↓
Atualiza sequência no BD
    ↓
Retorna código JSON para frontend
    ↓
Frontend preenche campo e mostra notificação
```

## 🔒 Segurança e Validações

### Backend
- ✅ Verificação de autenticação (`is_loggedin()`)
- ✅ Isolamento multi-tenant por `store_id`
- ✅ Transações de banco de dados (evita conflitos)
- ✅ Validação de unicidade de código
- ✅ Tratamento de exceções

### Frontend
- ✅ Feedback visual (spinner)
- ✅ Fallback para código aleatório em caso de erro
- ✅ Timeout de 10 segundos
- ✅ Notificações de sucesso/erro

## 🧪 Testes Recomendados

### Teste 1: Geração Básica
1. Clique no botão de gerar código
2. Verifique se código tem 13 dígitos
3. Verifique se começa com `7898`

### Teste 2: Sequencialidade
1. Gere 3 códigos consecutivos
2. Verifique se são sequenciais (ex: ...0901, ...0902, ...0903)

### Teste 3: Persistência
1. Gere um código e salve o produto
2. Delete o produto
3. Gere novo código
4. Verifique se não reutilizou o código anterior

### Teste 4: Multi-tenant
1. Troque de loja (`store_id`)
2. Gere código na loja A
3. Troque para loja B
4. Gere código na loja B
5. Verifique que cada loja tem sequência independente

### Teste 5: Validação EAN-13
Use um validador online (ex: https://www.gs1.org/services/check-digit-calculator):
- Insira os 12 primeiros dígitos
- Verifique se o último dígito calculado é igual ao gerado

## 📊 Consultas Úteis

### Ver sequência atual de uma loja
```sql
SELECT * FROM product_code_sequence WHERE store_id = 1;
```

### Reiniciar sequência (se necessário)
```sql
UPDATE product_code_sequence 
SET last_sequence = 18057090 
WHERE store_id = 1;
```

### Ver últimos códigos gerados
```sql
SELECT p_id, p_code, p_name, created_at 
FROM products 
WHERE p_code LIKE '7898%' 
ORDER BY p_id DESC 
LIMIT 10;
```

### Verificar códigos duplicados (não deve haver)
```sql
SELECT p_code, COUNT(*) as total 
FROM products 
WHERE p_code LIKE '7898%' 
GROUP BY p_code 
HAVING total > 1;
```

## 🐛 Troubleshooting

### Erro: "Tabela não existe"
**Solução**: Execute o SQL de criação da tabela novamente.

### Erro: "Não autorizado"
**Solução**: Faça login no sistema antes de usar.

### Botão não gera código
**Solução**: 
1. Abra o Console do navegador (F12)
2. Verifique se há erros JavaScript
3. Limpe cache do navegador
4. Verifique se `baseUrl` está definido

### Código gerado é aleatório (não sequencial)
**Solução**:
1. Verifique se endpoint existe: `_inc/generate_product_code.php`
2. Teste endpoint diretamente no navegador
3. Verifique logs do servidor PHP

### Dígito verificador incorreto
**Solução**: A função `calculateEAN13CheckDigit()` segue o padrão EAN-13. Valide em: https://www.gs1.org/services/check-digit-calculator

## 📝 Customização

### Alterar prefixo (ex: 789)
Edite `generate_product_code.php` e `create_product_code_sequence_table.sql`:
```php
// Linha 50 e 55
$prefix = '789'; // 3 dígitos ao invés de 4
```

### Alterar valor inicial
```sql
UPDATE product_code_sequence 
SET last_sequence = 10000000 
WHERE store_id = 1;
```

### Mudar mensagens de notificação
Edite `main.js` (linhas 247, 253, 265):
```javascript
window.toastr.success("Sua mensagem aqui");
```

## 📞 Suporte

Para dúvidas ou problemas:
1. Verifique os logs do PHP em: `_inc/storage/logs/`
2. Verifique console do navegador (F12)
3. Execute consultas SQL de diagnóstico acima

## ✅ Checklist de Implementação

- [ ] Executar SQL de criação da tabela
- [ ] Verificar que arquivos PHP/JS foram criados/modificados
- [ ] Limpar cache do navegador
- [ ] Testar geração de código
- [ ] Verificar sequencialidade
- [ ] Validar dígito verificador
- [ ] Testar multi-tenant (se aplicável)
- [ ] Documentar customizações feitas

---

**Versão**: 1.0  
**Data**: 22/01/2026  
**Sistema**: ModernPOS Multi-tenant
