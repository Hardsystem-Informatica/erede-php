<?php

namespace Rede;

use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Rede\Auth\AccessToken;
use Rede\Auth\Authenticator;
use Rede\Service\CancelTransactionService;
use Rede\Service\CaptureTransactionService;
use Rede\Service\CreateTransactionService;
use Rede\Service\GetTransactionService;

/**
 * phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
 */
class eRede
{
    public const VERSION = '6.1.0';
    public const USER_AGENT = 'eRede/' . eRede::VERSION . ' (PHP %s; Store %s; %s %s) %s';

    /**
     * @var string|null
     */
    private ?string $platform = null;

    /**
     * @var string|null
     */
    private ?string $platformVersion = null;

    /**
     * Orchestrates the OAuth 2.0 token (memory + optional PSR-16 cache).
     *
     * @var Authenticator
     */
    private readonly Authenticator $authenticator;

    /**
     * eRede constructor.
     *
     * @param Store                $store
     * @param LoggerInterface|null $logger
     * @param CacheInterface|null  $cache  Optional PSR-16 backend to share the
     *                                     access token across requests. Without
     *                                     it the token is cached in memory only.
     */
    public function __construct(
        private readonly Store $store,
        private readonly ?LoggerInterface $logger = null,
        ?CacheInterface $cache = null,
    ) {
        $this->authenticator = new Authenticator($store, $cache, $logger);
    }

    /**
     * Resolves (and caches) a valid access token. Useful for warm-up/testing;
     * the transactional methods call it automatically before operating.
     *
     * @return AccessToken
     */
    public function authenticate(): AccessToken
    {
        return $this->authenticator->validToken();
    }

    /**
     * @param Transaction $transaction
     *
     * @return Transaction
     * @see    eRede::create()
     */
    public function authorize(Transaction $transaction): Transaction
    {
        return $this->create($transaction);
    }

    /**
     * @param Transaction $transaction
     *
     * @return Transaction
     */
    public function create(Transaction $transaction): Transaction
    {
        $this->authenticator->validToken();

        $service = new CreateTransactionService($this->store, $transaction, $this->logger);
        $service->platform($this->platform, $this->platformVersion);

        return $service->execute();
    }

    /**
     * @param string $platform
     * @param string $platformVersion
     *
     * @return $this
     */
    public function platform(string $platform, string $platformVersion): static
    {
        $this->platform = $platform;
        $this->platformVersion = $platformVersion;

        return $this;
    }

    /**
     * @param Transaction $transaction
     *
     * @return Transaction
     */
    public function cancel(Transaction $transaction): Transaction
    {
        $this->authenticator->validToken();

        $service = new CancelTransactionService($this->store, $transaction, $this->logger);
        $service->platform($this->platform, $this->platformVersion);

        return $service->execute();
    }

    /**
     * @param string $tid
     *
     * @return Transaction
     * @see    eRede::get()
     */
    public function getById(string $tid): Transaction
    {
        return $this->get($tid);
    }

    /**
     * @param string $tid
     *
     * @return Transaction
     */
    public function get(string $tid): Transaction
    {
        $this->authenticator->validToken();

        $service = new GetTransactionService(store: $this->store, logger: $this->logger);
        $service->platform($this->platform, $this->platformVersion);
        $service->setTid($tid);

        return $service->execute();
    }

    /**
     * @param string $reference
     *
     * @return Transaction
     */
    public function getByReference(string $reference): Transaction
    {
        $this->authenticator->validToken();

        $service = new GetTransactionService(store: $this->store, logger: $this->logger);
        $service->platform($this->platform, $this->platformVersion);
        $service->setReference($reference);

        return $service->execute();
    }

    /**
     * @param string $tid
     *
     * @return Transaction
     */
    public function getRefunds(string $tid): Transaction
    {
        $this->authenticator->validToken();

        $service = new GetTransactionService(
            store: $this->store,
            logger: $this->logger
        );
        $service->platform($this->platform, $this->platformVersion);
        $service->setTid($tid);
        $service->setRefund();

        return $service->execute();
    }

    /**
     * @param Transaction $transaction
     *
     * @return Transaction
     */
    public function zero(Transaction $transaction): Transaction
    {
        $amount = (int) $transaction->getAmount();
        $capture = (bool)$transaction->getCapture();

        $transaction->setAmount(0);
        $transaction->capture();

        $transaction = $this->create($transaction);

        $transaction->setAmount($amount);
        $transaction->capture($capture);

        return $transaction;
    }

    /**
     * @param Transaction $transaction
     *
     * @return Transaction
     */
    public function capture(Transaction $transaction): Transaction
    {
        $this->authenticator->validToken();

        $service = new CaptureTransactionService($this->store, $transaction, $this->logger);
        $service->platform($this->platform, $this->platformVersion);

        return $service->execute();
    }
}
