<?php

namespace Rede\Tests\Service;

use PHPUnit\Framework\TestCase;
use Rede\Auth\AccessToken;
use Rede\Environment;
use Rede\Exception\RedeException;
use Rede\Service\AuthenticationService;
use Rede\Store;

/**
 * @testdox AuthenticationService (OAuth token)
 */
class AuthenticationServiceTest extends TestCase
{
    private function service(): AuthenticationService
    {
        $store = new Store('client-id', 'client-secret', Environment::sandbox());

        return new AuthenticationService($store);
    }

    public function testBuildsBasicAuthorizationFromClientCredentials(): void
    {
        $expected = 'Basic ' . base64_encode('client-id:client-secret');

        $this->assertSame($expected, $this->service()->basicAuthorization());
    }

    public function testParsesASuccessfulTokenResponse(): void
    {
        $json = (string)json_encode([
            'access_token' => 'tok-123',
            'token_type' => 'Bearer',
            'expires_in' => 1440,
            'scope' => 'read write',
        ]);

        $token = $this->service()->parseTokenResponse($json, 200, 5000);

        $this->assertInstanceOf(AccessToken::class, $token);
        $this->assertSame('tok-123', $token->token);
        $this->assertSame(1440, $token->expiresIn);
        $this->assertSame(5000, $token->obtainedAt);
        $this->assertSame('Bearer', $token->type);
        $this->assertSame('read write', $token->scope);
        $this->assertSame('Bearer tok-123', $token->authorizationHeader());
    }

    public function testThrowsRedeExceptionOnHttpError(): void
    {
        $json = (string)json_encode([
            'error' => 'invalid_client',
            'error_description' => 'bad credentials',
        ]);

        $this->expectException(RedeException::class);
        $this->expectExceptionMessage('bad credentials');

        $this->service()->parseTokenResponse($json, 401, 5000);
    }

    public function testThrowsRedeExceptionWhenTokenIsMissing(): void
    {
        $this->expectException(RedeException::class);

        $this->service()->parseTokenResponse('{}', 200, 5000);
    }
}
