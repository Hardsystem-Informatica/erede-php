# SDK PHP

SDK de integração eRede

> **Novidade na versão `0.3.0`:** a autenticação foi migrada para **OAuth 2.0** e os
> endpoints **v2** da Rede (o antigo HTTP Basic/v1 foi desligado pela Rede em
> **05/01/2026**). A fachada (`Store`/`eRede`) continua a mesma — detalhes em
> **[Autenticação (OAuth 2.0)](#autenticação-oauth-20)**.

# Funcionalidades

Este SDK possui as seguintes funcionalidades:
* Autorização
* Captura
* Consultas
* Cancelamento
* 3DS2
* Zero dollar
* iata
* MCC dinâmico.

# Autenticação (OAuth 2.0)

> **A partir da versão `0.3.0`** a biblioteca usa **OAuth 2.0** e os endpoints **v2**
> da Rede. O HTTP Basic (v1) foi desligado pela Rede em **05/01/2026**.

A mudança é **retrocompatível na fachada**: continue criando `new Store($pv, $chave, $env)`
e `new eRede($store)` como antes. Internamente a biblioteca passou a:

1. obter um `access_token` no endpoint OAuth (`grant_type=client_credentials`);
2. reutilizá-lo (dura ~24 min) e renová-lo com folga (~5 min antes de expirar);
3. enviar `Authorization: Bearer {access_token}` em todas as transações v2.

## Credenciais

As credenciais são **as mesmas** do Portal Use Rede, apenas renomeadas pelo OAuth 2.0.
Quem já transacionava no Basic **não precisa recadastrar nada** — a Chave de Integração
existente continua válida.

| Portal Use Rede      | OAuth 2.0      | Argumento do `Store`  |
|----------------------|----------------|-----------------------|
| PV                   | `clientId`     | 1º — `filiation`      |
| Chave de Integração  | `clientSecret` | 2º — `token`          |
| Token de acesso      | `access_token` | — (gerado em runtime) |

Os aliases `Store::getClientId()` e `Store::getClientSecret()` estão disponíveis; os
getters antigos (`getFiliation()` / `getToken()`) continuam funcionando.

## Cache do token (opcional, PSR-16)

Por padrão o `access_token` é reutilizado **em memória** durante a mesma request. Para
compartilhá-lo entre requests (recomendado em produção), injete um backend **PSR-16**
(`Psr\SimpleCache\CacheInterface`) — o cache do Laravel já implementa essa interface:

```php
<?php
use Rede\Store;
use Rede\Environment;
use Rede\eRede;

$store = new Store('PV', 'CHAVE_INTEGRACAO', Environment::production());

// Sem cache: token reutilizado apenas dentro da request (fallback em memória)
$rede = new eRede($store);

// Com cache PSR-16 compartilhado entre requests (chave por clientId)
$rede = new eRede($store, logger: null, cache: Cache::store()); // Laravel

// Opcional: aquece/valida o token manualmente (útil em warm-up ou testes)
$accessToken = $rede->authenticate();
```

Sem backend injetado, no pior caso é feita **uma** chamada ao endpoint de token por
request HTTP. Com o backend, o token é guardado como value object serializável
(`Rede\Auth\AccessToken`), com TTL alinhado à expiração informada pela Rede.

## Endpoints por ambiente

|                     | Sandbox                                                     | Produção                                              |
|---------------------|------------------------------------------------------------|-------------------------------------------------------|
| **Token**           | `https://rl7-sandbox-api.useredecloud.com.br/oauth2/token` | `https://api.userede.com.br/redelabs/oauth2/token`    |
| **Transações (v2)** | `https://sandbox-erede.useredecloud.com.br/v2/transactions`| `https://api.userede.com.br/erede/v2/transactions`    |

Escolhidos automaticamente por `Environment::sandbox()` / `Environment::production()`.

# Instalação

## Dependências

* PHP >= 8.1

## Instalando o SDK

Se já possui um arquivo `composer.json`, basta adicionar a seguinte dependência ao seu projeto:

```json
{
"require": {
    "hardsystem/erede-php": "^0.3"
}
}

```

Com a dependência adicionada ao `composer.json`, basta executar:

```
composer install
```

Alternativamente, você pode executar diretamente em seu terminal:

```
composer require "hardsystem/erede-php:^0.3"
```

# Testes

O SDK utiliza PHPUnit com TestDox para os testes. Para executá-los em ambiente local, você precisa exportar
as variáveis de ambiente `REDE_PV` e `REDE_TOKEN` com suas credenciais da API. Feito isso, basta rodar:

```
export REDE_PV=1234
export REDE_TOKEN=5678

./tests
```

## Testes unitários vs. integração

A partir da `0.3.0` os testes estão divididos em dois _suites_ (`phpunit.xml.dist`):

- **`unit`** — não acessam a rede nem exigem credenciais (ex.: `AccessToken`,
  `AuthenticationService`, `Authenticator`). Podem rodar a qualquer momento:

  ```
  ./vendor/bin/phpunit --testsuite unit
  ```

- **`integration`** — batem no sandbox v2 e exigem `REDE_PV` (clientId) e `REDE_TOKEN`
  (clientSecret) de OAuth válidos:

  ```
  export REDE_PV=<clientId>
  export REDE_TOKEN=<clientSecret>
  ./vendor/bin/phpunit --testsuite integration
  ```

Os testes também podem ser executados através de um container com a configuração ideal para o projeto. Para isso, basta
fazer:

```
docker build . -t erede-docker
docker run -e REDE_PV='1234' -e REDE_TOKEN='5678' erede-docker
```
````
Caso necessário, o SDK possui a possibilidade de logs de depuração que podem ser utilizados ao executar os testes. Para isso, 
basta exportar a variável de ambiente `REDE_DEBUG` com o valor 1:

```
export REDE_DEBUG=1
```

# Utilizando

## Autorizando uma transação

```php
<?php
// Configuração da loja em modo produção
$store = new Store('PV', 'TOKEN', Environment::production());

// Configuração da loja em modo sandbox
// $store = new \Rede\Store('PV', 'TOKEN', \Rede\Environment::sandbox());

// Transação que será autorizada
$transaction = (new Transaction(20.99, 'pedido' . time()))->creditCard(
    '5448280000000007',
    '235',
    '12',
    '2020',
    'John Snow'
);

// Autoriza a transação
$transaction = (new eRede($store))->create($transaction);

if ($transaction->getReturnCode() == '00') {
    printf("Transação autorizada com sucesso; tid=%s\n", $transaction->getTid());
}
```

Por padrão, a transação é capturada automaticamente; caso seja necessário apenas autorizar a transação, o método `Transaction::capture()` deverá ser chamado com o parâmetro `false`:

```php
<?php
// Configuração da loja em modo produção
$store = new Store('PV', 'TOKEN', Environment::production());

// Configuração da loja em modo sandbox
// $store = new \Rede\Store('PV', 'TOKEN', \Rede\Environment::sandbox());

// Transação que será autorizada
$transaction = (new Transaction(20.99, 'pedido' . time()))->creditCard(
    '5448280000000007',
    '235',
    '12',
    '2020',
    'John Snow'
)->capture(false);

// Autoriza a transação
$transaction = (new eRede($store))->create($transaction);

if ($transaction->getReturnCode() == '00') {
    printf("Transação autorizada com sucesso; tid=%s\n", $transaction->getTid());
}
//...
```

## Adiciona configuração de parcelamento
```php
<?php
// Configuração da loja em modo produção
$store = new Store('PV', 'TOKEN', Environment::production());

// Configuração da loja em modo sandbox
// $store = new \Rede\Store('PV', 'TOKEN', \Rede\Environment::sandbox());

// Transação que será autorizada
$transaction = (new Transaction(20.99, 'pedido' . time()))->creditCard(
    '5448280000000007',
    '235',
    '12',
    '2020',
    'John Snow'
);

// Configuração de parcelamento
$transaction->setInstallments(3);

// Autoriza a transação
$transaction = (new eRede($store))->create($transaction);

if ($transaction->getReturnCode() == '00') {
    printf("Transação autorizada com sucesso; tid=%s\n", $transaction->getTid());
}
```

## Adiciona informação adicional de gateway e módulo

```php
<?php
// Configuração da loja em modo produção
$store = new Store('PV', 'TOKEN', Environment::production());

// Configuração da loja em modo sandbox
// $store = new \Rede\Store('PV', 'TOKEN', \Rede\Environment::sandbox());

// Transação que será autorizada
$transaction = (new Transaction(20.99, 'pedido' . time()))->creditCard(
    '5448280000000007',
    '235',
    '12',
    '2020',
    'John Snow'
)->additional(1234, 56);

// Autoriza a transação
$transaction = (new eRede($store))->create($transaction);

if ($transaction->getReturnCode() == '00') {
    printf("Transação autorizada com sucesso; tid=%s\n", $transaction->getTid());
}
```

## Autorizando uma transação com MCC dinâmico

```php
<?php
// Configuração da loja em modo produção
$store = new Store('PV', 'TOKEN', Environment::production());

// Configuração da loja em modo sandbox
// $store = new \Rede\Store('PV', 'TOKEN', \Rede\Environment::sandbox());

// Transação que será autorizada
$transaction = (new Transaction(20.99, 'pedido' . time()))->creditCard(
    '5448280000000007',
    '235',
    '12',
    '2020',
    'John Snow'
)->mcc(
    'LOJADOZE',
    '22349202212',
    new SubMerchant(
       '1234',
       'São Paulo',
       'Brasil'
    )
);

// Autoriza a transação
$transaction = (new eRede($store))->create($transaction);

if ($transaction->getReturnCode() == '00') {
    printf("Transação autorizada com sucesso; tid=%s\n", $transaction->getTid());
}
//...
```

## Autorizando uma transação IATA

```php
<?php
// Configuração da loja em modo produção
$store = new Store('PV', 'TOKEN', Environment::production());

// Configuração da loja em modo sandbox
// $store = new \Rede\Store('PV', 'TOKEN', \Rede\Environment::sandbox());

// Transação que será autorizada
$transaction = (new Transaction(20.99, 'pedido' . time()))->creditCard(
    '5448280000000007',
    '235',
    '12',
    '2020',
    'John Snow'
)->iata('code123', '250');

// Autoriza a transação
$transaction = (new eRede($store))->create($transaction);

if ($transaction->getReturnCode() == '00') {
    printf("Transação autorizada com sucesso; tid=%s\n", $transaction->getTid());
}
```

## Capturando uma transação

```php
<?php
// Configuração da loja em modo produção
$store = new Store('PV', 'TOKEN', Environment::production());

// Configuração da loja em modo sandbox
// $store = new \Rede\Store('PV', 'TOKEN', \Rede\Environment::sandbox());

// Transação que será capturada
$transaction =  (new eRede($store))->capture((new Transaction(20.99))->setTid('TID123'));

if ($transaction->getReturnCode() == '00') {
    printf("Transação capturada com sucesso; tid=%s\n", $transaction->getTid());
}
```

## Cancelando uma transação

```php
<?php
// Configuração da loja em modo produção
$store = new Store('PV', 'TOKEN', Environment::production());

// Configuração da loja em modo sandbox
// $store = new \Rede\Store('PV', 'TOKEN', \Rede\Environment::sandbox());

// Transação que será cancelada
$transaction = (new eRede($store))->cancel((new Transaction(20.99))->setTid('TID123'));

if ($transaction->getReturnCode() == '359') {
    printf("Transação cancelada com sucesso; tid=%s\n", $transaction->getTid());
}
```

## Consultando uma transação pelo ID

```php
<?php
// Configuração da loja em modo produção
$store = new Store('PV', 'TOKEN', Environment::production());

// Configuração da loja em modo sandbox
// $store = new \Rede\Store('PV', 'TOKEN', \Rede\Environment::sandbox());

$transaction = (new eRede($store))->get('TID123');

printf("O status atual da autorização é %s\n", $transaction->getAuthorization()->getStatus());
```

## Consultando uma transação pela referência

```php
<?php
// Configuração da loja em modo produção
$store = new Store('PV', 'TOKEN', Environment::production());

// Configuração da loja em modo sandbox
// $store = new \Rede\Store('PV', 'TOKEN', \Rede\Environment::sandbox());

$transaction = (new eRede($store))->getByReference('pedido123');

printf("O status atual da autorização é %s\n", $transaction->getAuthorization()->getStatus());
```

## Consultando cancelamentos de uma transação

```php
<?php
// Configuração da loja em modo produção
$store = new Store('PV', 'TOKEN', Environment::production());

// Configuração da loja em modo sandbox
// $store = new \Rede\Store('PV', 'TOKEN', \Rede\Environment::sandbox());

$transaction = (new eRede($store))->getRefunds('TID123');

printf("O status atual da autorização é %s\n", $transaction->getAuthorization()->getStatus());
```

## Transação com autenticação

```php
<?php
// Configuração da loja em modo produção
$store = new Store('PV', 'TOKEN', Environment::production());

// Configuração da loja em modo sandbox
// $store = new \Rede\Store('PV', 'TOKEN', \Rede\Environment::sandbox());

// Configura a transação que será autorizada após a autenticação
$transaction = (new Transaction(25, 'pedido' . time()))->debitCard(
    '5277696455399733',
    '123',
    '01',
    '2020',
    'John Snow'
);

// Configura o 3dSecure para autenticação
$transaction->threeDSecure(
    new Device(
        ColorDepth: 1,
        DeviceType3ds: 'BROWSER',
        JavaEnabled: false,
        Language: 'BR',
        ScreenHeight: 500,
        ScreenWidth: 500,
        TimeZoneOffset: 3
    )
);
$transaction->addUrl('https://redirecturl.com/3ds/success', Url::THREE_D_SECURE_SUCCESS);
$transaction->addUrl('https://redirecturl.com/3ds/failure', Url::THREE_D_SECURE_FAILURE);

$transaction = (new eRede($store))->create($transaction);

if ($transaction->getReturnCode() == '220') {
    printf("Redirecione o cliente para \"%s\" para autenticação\n", $transaction->getThreeDSecure()->getUrl());
}
```
