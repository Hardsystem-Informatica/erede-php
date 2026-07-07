<?php

namespace Rede\Tests\Support;

use Rede\Auth\AccessToken;
use Rede\Auth\Authenticator;

/**
 * Authenticator test double that replaces the network fetch with a canned token
 * and counts how many times a fresh token was requested.
 */
final class SpyAuthenticator extends Authenticator
{
    public int $fetchCount = 0;

    public int $tokenExpiresIn = 1440;

    protected function fetchToken(int $now): AccessToken
    {
        $this->fetchCount++;

        return new AccessToken('fresh-' . $this->fetchCount, $this->tokenExpiresIn, $now);
    }
}
