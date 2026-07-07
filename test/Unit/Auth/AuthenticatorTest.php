<?php

namespace Rede\Tests\Auth;

use PHPUnit\Framework\TestCase;
use Rede\Auth\AccessToken;
use Rede\Auth\Authenticator;
use Rede\Environment;
use Rede\Store;
use Rede\Tests\Support\ArrayCache;
use Rede\Tests\Support\SpyAuthenticator;

/**
 * @testdox Authenticator (token orchestration)
 */
class AuthenticatorTest extends TestCase
{
    private function store(): Store
    {
        return new Store('client-id', 'client-secret', Environment::sandbox());
    }

    public function testReusesTheInMemoryTokenWithoutFetching(): void
    {
        $store = $this->store();
        $store->setAccessToken(new AccessToken('mem', 1440, 1000));
        $authenticator = new SpyAuthenticator($store, null);

        $token = $authenticator->validToken(1000);

        $this->assertSame('mem', $token->token);
        $this->assertSame(0, $authenticator->fetchCount);
    }

    public function testFallsBackToTheCacheWhenMemoryIsEmpty(): void
    {
        $store = $this->store();
        $cache = new ArrayCache();
        $authenticator = new SpyAuthenticator($store, $cache);
        $cache->set($authenticator->cacheKey(), new AccessToken('cached', 1440, 1000));

        $token = $authenticator->validToken(1000);

        $this->assertSame('cached', $token->token);
        $this->assertSame(0, $authenticator->fetchCount);
        $this->assertSame('cached', $store->getAccessToken()?->token, 'the cached token is promoted to memory');
    }

    public function testFetchesAndStoresWhenMemoryAndCacheAreEmpty(): void
    {
        $store = $this->store();
        $cache = new ArrayCache();
        $authenticator = new SpyAuthenticator($store, $cache);

        $token = $authenticator->validToken(1000);

        $this->assertSame('fresh-1', $token->token);
        $this->assertSame(1, $authenticator->fetchCount);
        $this->assertSame('fresh-1', $store->getAccessToken()?->token);
        $this->assertInstanceOf(AccessToken::class, $cache->get($authenticator->cacheKey()));
    }

    public function testWritesTheCacheWithATtlThatAccountsForSkew(): void
    {
        $store = $this->store();
        $cache = new ArrayCache();
        $authenticator = new SpyAuthenticator($store, $cache);
        $authenticator->tokenExpiresIn = 1440;

        $authenticator->validToken(1000);

        $this->assertSame(1440 - Authenticator::REFRESH_SKEW, $cache->ttls[$authenticator->cacheKey()] ?? null);
    }

    public function testTheCacheTtlIsFlooredAtSixtySeconds(): void
    {
        $store = $this->store();
        $cache = new ArrayCache();
        $authenticator = new SpyAuthenticator($store, $cache);
        $authenticator->tokenExpiresIn = 100; // 100 - 300 would be negative, so it is floored

        $authenticator->validToken(1000);

        $this->assertSame(60, $cache->ttls[$authenticator->cacheKey()] ?? null);
    }

    public function testIgnoresAnExpiredMemoryTokenAndUsesTheCache(): void
    {
        $store = $this->store();
        $cache = new ArrayCache();
        $authenticator = new SpyAuthenticator($store, $cache);
        $store->setAccessToken(new AccessToken('old', 1440, 0));
        $cache->set($authenticator->cacheKey(), new AccessToken('cached', 1440, 1000));

        $token = $authenticator->validToken(2000);

        $this->assertSame('cached', $token->token);
        $this->assertSame(0, $authenticator->fetchCount);
    }

    public function testWorksWithoutACacheAndReusesTheMemoryToken(): void
    {
        $store = $this->store();
        $authenticator = new SpyAuthenticator($store, null);

        $first = $authenticator->validToken(1000);
        $second = $authenticator->validToken(1001);

        $this->assertSame('fresh-1', $first->token);
        $this->assertSame('fresh-1', $second->token);
        $this->assertSame(1, $authenticator->fetchCount);
    }
}
