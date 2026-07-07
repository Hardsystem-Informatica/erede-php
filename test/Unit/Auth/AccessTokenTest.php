<?php

namespace Rede\Tests\Auth;

use PHPUnit\Framework\TestCase;
use Rede\Auth\AccessToken;

/**
 * @testdox AccessToken value object
 */
class AccessTokenTest extends TestCase
{
    public function testAuthorizationHeaderUsesTheTokenType(): void
    {
        $token = new AccessToken('abc123', 1440, 1000);

        $this->assertSame('Bearer abc123', $token->authorizationHeader());
    }

    public function testAuthorizationHeaderRespectsACustomType(): void
    {
        $token = new AccessToken('abc123', 1440, 1000, 'MAC');

        $this->assertSame('MAC abc123', $token->authorizationHeader());
    }

    public function testIsNotExpiredRightAfterBeingIssued(): void
    {
        $token = new AccessToken('abc', 1440, 1000);

        $this->assertFalse($token->isExpired(1000, 60));
    }

    public function testIsExpiredOnceTheFullLifetimeHasPassed(): void
    {
        $token = new AccessToken('abc', 1440, 1000);

        $this->assertTrue($token->isExpired(1000 + 1440, 60));
    }

    /**
     * obtainedAt=1000, expiresIn=1440, skew=60 => threshold = 1000 + 1440 - 60 = 2380
     */
    public function testSkewBringsTheExpirationForward(): void
    {
        $token = new AccessToken('abc', 1440, 1000);

        $this->assertFalse($token->isExpired(2379, 60), 'one second before the skewed threshold is still valid');
        $this->assertTrue($token->isExpired(2380, 60), 'at the skewed threshold it is already expired');
    }

    public function testSurvivesSerializationKeepingObtainedAt(): void
    {
        $token = new AccessToken('abc', 1440, 1234, 'Bearer', 'read write');

        /** @var AccessToken $restored */
        $restored = unserialize(serialize($token));

        $this->assertSame('abc', $restored->token);
        $this->assertSame(1234, $restored->obtainedAt);
        $this->assertSame('read write', $restored->scope);
        $this->assertFalse($restored->isExpired(1234, 60));
    }
}
