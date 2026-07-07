<?php

namespace Rede;

use stdClass;

class Environment implements RedeSerializable
{
    public const PRODUCTION = 'https://api.userede.com.br/erede';
    public const SANDBOX = 'https://sandbox-erede.useredecloud.com.br';
    public const VERSION = 'v2';

    /**
     * OAuth 2.0 token endpoints. The token host differs from the transaction
     * host (especially in sandbox), so it is kept apart from {@see $endpoint}.
     */
    public const TOKEN_URL_PRODUCTION = 'https://api.userede.com.br/redelabs/oauth2/token';
    public const TOKEN_URL_SANDBOX = 'https://rl7-sandbox-api.useredecloud.com.br/oauth2/token';

    /**
     * @var string|null
     */
    private ?string $ip = null;

    /**
     * @var string|null
     */
    private ?string $sessionId = null;

    /**
     * @var string
     */
    private string $endpoint;

    /**
     * @var string
     */
    private string $tokenEndpoint;

    /**
     * Creates an environment with its base url, version and OAuth token endpoint
     *
     * @param string $baseUrl
     * @param string $tokenEndpoint
     */
    private function __construct(string $baseUrl, string $tokenEndpoint)
    {
        $this->endpoint = sprintf('%s/%s/', $baseUrl, Environment::VERSION);
        $this->tokenEndpoint = $tokenEndpoint;
    }

    /**
     * @return Environment A preconfigured production environment
     */
    public static function production(): Environment
    {
        return new Environment(Environment::PRODUCTION, Environment::TOKEN_URL_PRODUCTION);
    }

    /**
     * @return Environment A preconfigured sandbox environment
     */
    public static function sandbox(): Environment
    {
        return new Environment(Environment::SANDBOX, Environment::TOKEN_URL_SANDBOX);
    }

    /**
     * @param string $service
     *
     * @return string Gets the environment endpoint
     */
    public function getEndpoint(string $service): string
    {
        return $this->endpoint . $service;
    }

    /**
     * @return string Gets the OAuth 2.0 token endpoint for this environment
     */
    public function getTokenEndpoint(): string
    {
        return $this->tokenEndpoint;
    }

    /**
     * @return string|null
     */
    public function getIp(): ?string
    {
        return $this->ip;
    }

    /**
     * @param string $ip
     *
     * @return $this
     */
    public function setIp(string $ip): static
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * @param string $sessionId
     *
     * @return $this
     */
    public function setSessionId(string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * @return mixed
     * @noinspection PhpMixedReturnTypeCanBeReducedInspection
     */
    public function jsonSerialize(): mixed
    {
        $consumer = new stdClass();
        $consumer->ip = $this->ip;
        $consumer->sessionId = $this->sessionId;

        return ['consumer' => $consumer];
    }
}
