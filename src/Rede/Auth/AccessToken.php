<?php

namespace Rede\Auth;

/**
 * Immutable value object representing an OAuth 2.0 access token.
 *
 * It carries no notion of "now": both the current time and the issue time are
 * supplied from the outside so the object stays pure and easy to test. It is
 * plain-serializable (readonly promoted properties) so it can be stored in a
 * PSR-16 cache without losing {@see $obtainedAt}.
 */
final class AccessToken
{
    /**
     * @param string      $token      The access token itself (Bearer credential).
     * @param int         $expiresIn  Lifetime in seconds as returned by Rede (~1440).
     * @param int         $obtainedAt Unix timestamp when the token was issued.
     * @param string      $type       Token type; Rede returns "Bearer".
     * @param string|null $scope      Space separated list of granted scopes.
     */
    public function __construct(
        public readonly string $token,
        public readonly int $expiresIn,
        public readonly int $obtainedAt,
        public readonly string $type = 'Bearer',
        public readonly ?string $scope = null,
    ) {
    }

    /**
     * Whether the token is (or is about to become) expired.
     *
     * The $skew brings the expiration forward so the token is refreshed before
     * it actually dies mid-request. Rede recommends renewing between 15 and 23
     * minutes, so a generous skew (e.g. 300s) keeps requests safely authorized.
     *
     * @param int $now  Current unix timestamp.
     * @param int $skew Safety margin in seconds subtracted from the expiration.
     *
     * @return bool
     */
    public function isExpired(int $now, int $skew = 60): bool
    {
        return $now >= ($this->obtainedAt + $this->expiresIn - $skew);
    }

    /**
     * @return string The value for the Authorization header, e.g. "Bearer abc".
     */
    public function authorizationHeader(): string
    {
        return sprintf('%s %s', $this->type, $this->token);
    }
}
