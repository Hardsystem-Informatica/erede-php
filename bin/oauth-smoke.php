<?php

/**
 * Smoke test local do fluxo OAuth 2.0 (v2) contra o sandbox da Rede.
 *
 * NÃO é um teste automatizado — é uma ferramenta de verificação manual para
 * validar, ponta a ponta, que:
 *   1. o endpoint de token responde e o access_token é obtido (autenticação);
 *   2. uma transação real roda em v2 com Bearer (autorização + captura/cancel).
 *
 * Uso:
 *   export REDE_PV=<clientId-sandbox>
 *   export REDE_TOKEN=<clientSecret-sandbox>
 *   php bin/oauth-smoke.php            # autentica + autoriza (capture=false)
 *   php bin/oauth-smoke.php --capture  # + captura a transação autorizada
 *   php bin/oauth-smoke.php --cancel   # autoriza capturando e depois cancela
 *   REDE_ENV=production php bin/oauth-smoke.php   # aponta para produção
 *
 * Este arquivo é descartável: pode apagar ou colocar no .gitignore.
 */

require __DIR__ . '/../vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Rede\Environment;
use Rede\Transaction;
use Rede\eRede;
use Rede\Store;

$pv = getenv('REDE_PV');
$token = getenv('REDE_TOKEN');

if ($pv === false || $token === false || $pv === '' || $token === '') {
    fwrite(STDERR, "Defina REDE_PV (clientId) e REDE_TOKEN (clientSecret) de sandbox OAuth.\n");
    exit(1);
}

$env = getenv('REDE_ENV') === 'production' ? Environment::production() : Environment::sandbox();

// Log em stdout no nível DEBUG: mostra a request/response da Rede com os
// segredos (Authorization / access_token) já mascarados pela lib.
$logger = new Logger('oauth-smoke');
$logger->pushHandler(new StreamHandler('php://stdout', Level::Debug));

$store = new Store($pv, $token, $env);
$rede = new eRede($store, $logger);

echo "==> Ambiente: " . (getenv('REDE_ENV') === 'production' ? 'PRODUÇÃO' : 'SANDBOX') . "\n";
echo "==> Token endpoint: " . $env->getTokenEndpoint() . "\n\n";

// -------------------------------------------------------------------------
// Passo 1: autenticação (só o access_token). Isola falha de OAuth.
// -------------------------------------------------------------------------
echo "==> [1] Autenticando (grant_type=client_credentials)...\n";

try {
    $accessToken = $rede->authenticate();
} catch (Throwable $e) {
    fwrite(STDERR, "\nFALHA na autenticação OAuth: " . $e->getMessage() . "\n");
    exit(2);
}

printf(
    "    OK. token_type=%s expires_in=%ds token=%s...%s scope=%s\n\n",
    $accessToken->type,
    $accessToken->expiresIn,
    substr($accessToken->token, 0, 6),
    substr($accessToken->token, -4),
    $accessToken->scope ?? '(nenhum)'
);

// -------------------------------------------------------------------------
// Passo 2: autorização de uma transação (valida o payload v2 + Bearer).
// -------------------------------------------------------------------------
$capture = in_array('--capture', $argv, true) || in_array('--cancel', $argv, true);

echo "==> [2] Autorizando transação de teste (capture=" . ($capture ? 'true' : 'false') . ")...\n";

$transaction = (new Transaction(250.00, 'smoke' . time()))
    ->creditCard('5448280000000007', '235', '12', (int)date('Y') + 1, 'John Snow')
    ->capture($capture);

try {
    $authorized = $rede->create($transaction);
} catch (Throwable $e) {
    fwrite(STDERR, "\nFALHA na autorização: " . $e->getMessage() . "\n");
    exit(3);
}

printf(
    "    returnCode=%s message=%s tid=%s\n\n",
    $authorized->getReturnCode(),
    $authorized->getReturnMessage(),
    $authorized->getTid()
);

// -------------------------------------------------------------------------
// Passo 3 (opcional): captura ou cancelamento.
// -------------------------------------------------------------------------
if (in_array('--capture', $argv, true) && !in_array('--cancel', $argv, true)) {
    echo "==> [3] Capturando...\n";

    try {
        $captured = $rede->capture((new Transaction(250.00))->setTid((string)$authorized->getTid()));
        printf("    returnCode=%s message=%s\n", $captured->getReturnCode(), $captured->getReturnMessage());
    } catch (Throwable $e) {
        fwrite(STDERR, "FALHA na captura: " . $e->getMessage() . "\n");
        exit(4);
    }
}

if (in_array('--cancel', $argv, true)) {
    echo "==> [3] Cancelando...\n";

    try {
        $canceled = $rede->cancel((new Transaction(150.00))->setTid((string)$authorized->getTid()));
        printf("    returnCode=%s message=%s\n", $canceled->getReturnCode(), $canceled->getReturnMessage());
    } catch (Throwable $e) {
        fwrite(STDERR, "FALHA no cancelamento: " . $e->getMessage() . "\n");
        exit(5);
    }
}

echo "\n==> Fim.\n";
