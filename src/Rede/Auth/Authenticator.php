<?php

namespace Rede\Auth;

use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Rede\Service\AuthenticationService;
use Rede\Store;

/**
 * Resolves a valid OAuth 2.0 access token, orchestrating three layers:
 *
 *   1. In-memory token on the Store   — reused within the same request.
 *   2. PSR-16 cache (optional)        — shared across requests, keyed by clientId.
 *   3. Fresh fetch from Rede          — stored back into both layers above.
 *
 * Without a cache backend only layers 1 and 3 apply: the token is reused within
 * a request and, in the worst case, one token request is made per HTTP request.
 */
class Authenticator
{
    /**
     * Safety margin (seconds) subtracted from the token lifetime. Rede tokens
     * last ~24 min and should be renewed between 15-23 min, so refreshing ~5 min
     * early keeps requests comfortably authorized.
     */
    public const REFRESH_SKEW = 300;

    /**
     * @param Store               $store
     * @param CacheInterface|null $cache
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        private readonly Store $store,
        private readonly ?CacheInterface $cache = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Returns a non-expired access token, fetching and caching a new one when
     * needed. Always leaves the resolved token on the Store for the services to
     * read.
     *
     * @param int|null $now Current unix timestamp; injectable for testing.
     *
     * @return AccessToken
     */
    public function validToken(?int $now = null): AccessToken
    {
        $now ??= time();

        // 1) In-memory token still valid for this request.
        $memory = $this->store->getAccessToken();

        if ($memory !== null && !$memory->isExpired($now, self::REFRESH_SKEW)) {
            return $memory;
        }

        // 2) Shared PSR-16 cache (keyed per clientId, since each entity has its PV).
        $key = $this->cacheKey();

        if ($this->cache !== null) {
            $cached = $this->cache->get($key);

            if ($cached instanceof AccessToken && !$cached->isExpired($now, self::REFRESH_SKEW)) {
                $this->store->setAccessToken($cached);
                return $cached;
            }
        }

        // 3) Fetch a fresh token and store it in memory + cache.
        $fresh = $this->fetchToken($now);
        $this->store->setAccessToken($fresh);

        $this->cache?->set($key, $fresh, max(60, $fresh->expiresIn - self::REFRESH_SKEW));

        return $fresh;
    }

    /**
     * @return string The cache key for this store's client.
     */
    public function cacheKey(): string
    {
        return 'erede_token_' . sha1($this->store->getClientId());
    }

    /**
     * Performs the actual token request. Isolated so it can be overridden in tests.
     *
     * @param int $now
     *
     * @return AccessToken
     */
    protected function fetchToken(int $now): AccessToken
    {
        return (new AuthenticationService($this->store, $this->logger))->authenticate($now);
    }
}
