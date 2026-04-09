# Increazy CheckoutV2 - Magento 2

Modulo de checkout headless que conecta o PWA da Increazy ao Magento 2. Fornece endpoints de API para gerenciamento completo do fluxo de compra: carrinho, frete, pagamento, pedidos e autenticacao.

Compativel com Magento 2.3.x ate 2.4.x.

## Instalacao

1. Copie a pasta `Increazy` para `app/code/`.
2. Execute:

```bash
php bin/magento module:enable Increazy_CheckoutV2
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

## Configuracao

No admin do Magento: **Stores > Configuration > Increazy > Geral**

| Campo | Descricao |
|-------|-----------|
| Hash | Hash de autenticacao gerado no painel Increazy |
| Id da aplicacao | AppId gerado no painel Increazy |
| Ambiente de homologacao | Ativar para usar o ambiente beta |
| Compatibilidade com Frete Rapido | Ativar caso utilize o carrier Frete Rapido |

## Endpoints

Rota base: `increazy_api2`

### Carrinho

| Endpoint | Descricao |
|----------|-----------|
| `cart/getorcreate` | Obter ou criar carrinho |
| `cart/add` | Adicionar produto ao carrinho |
| `cart/change` | Alterar quantidade de item |
| `cart/remove` | Remover item do carrinho |
| `cart/setdelivery` | Definir metodo de entrega |
| `cart/setmethod` | Definir metodo de pagamento |
| `cart/getfreight` | Cotar frete do carrinho |

### Endereco

| Endpoint | Descricao |
|----------|-----------|
| `address/all` | Listar enderecos do cliente |
| `address/create` | Criar endereco |
| `address/remove` | Remover endereco |
| `address/getfreight` | Cotar frete por endereco |

### Pagamento

| Endpoint | Descricao |
|----------|-----------|
| `payment/prepare` | Preparar pedido para pagamento |
| `payment/finish` | Finalizar pedido com dados de pagamento |
| `payment/process` | Processar pagamento |
| `payment/status` | Consultar status do pagamento |
| `payment/cancel` | Cancelar pagamento |

### Cupom

| Endpoint | Descricao |
|----------|-----------|
| `coupon/add` | Aplicar cupom |
| `coupon/remove` | Remover cupom |

### Autenticacao

| Endpoint | Descricao |
|----------|-----------|
| `auth/login` | Login do cliente |
| `auth/connect` | Conectar/autenticar |
| `auth/bridge` | Bridge de autenticacao |
| `auth/gettoken` | Obter token |
| `auth/recovery` | Recuperacao de senha |

### Cliente

| Endpoint | Descricao |
|----------|-----------|
| `customer/register` | Cadastro de cliente |
| `customer/update` | Atualizar dados |
| `customer/exists` | Verificar se cliente existe |
| `customer/newsletter` | Gerenciar newsletter |

### Pedidos

| Endpoint | Descricao |
|----------|-----------|
| `order/all` | Listar pedidos do cliente |
| `order/get` | Detalhe de um pedido |

### Status

| Endpoint | Descricao |
|----------|-----------|
| `status/index` | Health check |

## Metodos de pagamento

O modulo registra os seguintes metodos de pagamento no Magento:

- `increazy-creditcard` - Cartao de Credito
- `increazy-debitcard` - Cartao de Debito
- `increazy-billet` - Boleto
- `increazy-pix` - Pix
- `increazy-pix-installment` - Pix Parcelado
- `increazy-credit` - Credito
- `increazy-voucher` - Voucher
- `increazy-ted` - TED
- `increazy-onetap` - Compra com 1 clique
- `increazy-free` - Gratis
- `increazy-other` - Outro

## Compatibilidade com Frete Rapido

Para lojas que utilizam o carrier Frete Rapido, ative a opcao **Compatibilidade com Frete Rapido** nas configuracoes. Isso ajusta o fluxo de checkout (GetOrCreate, SetDelivery e CompleteQuote) para funcionar corretamente com os shipping rates do Frete Rapido.

Quando ativada, duas colunas sao utilizadas na tabela `quote`:
- `shipping_method_increazy` - carrier selecionado
- `shipping_method_option_increazy` - opcao/variante do frete

As colunas sao criadas automaticamente no `setup:upgrade` independente da flag estar ativa.

## Dependencias

- `Magento_Store`
- `Magento_Catalog`
- `Magento_Quote`
- `Magento_Sales`
