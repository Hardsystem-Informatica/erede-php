<?php

namespace Rede;

use Rede\Auth\AccessToken;

class Store
{
    /**
     * Which environment will this store used for?
     * @var Environment
     */
    private Environment $environment;

    /**
     * OAuth 2.0 access token, cached in memory for the lifetime of this
     * instance (reused within the same request). Populated by the Authenticator.
     *
     * @var AccessToken|null
     */
    private ?AccessToken $accessToken = null;

    /**
     * Creates a store.
     *
     * Under OAuth 2.0 the arguments keep the same meaning: $filiation is the
     * clientId (PV) and $token is the clientSecret (Chave de Integração).
     *
     * @param string           $filiation
     * @param string           $token
     * @param Environment|null $environment if none provided, production will be used.
     */
    public function __construct(private string $filiation, private string $token, ?Environment $environment = null)
    {
        $this->environment = $environment ?? Environment::production();
    }

    /**
     * @return Environment
     */
    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    /**
     * @param Environment $environment
     *
     * @return $this
     */
    public function setEnvironment(Environment $environment): static
    {
        $this->environment = $environment;
        return $this;
    }

    /**
     * @return string
     */
    public function getFiliation(): string
    {
        return $this->filiation;
    }

    /**
     * @param string $filiation
     *
     * @return $this
     */
    public function setFiliation(string $filiation): static
    {
        $this->filiation = $filiation;
        return $this;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $token
     *
     * @return $this
     */
    public function setToken(string $token): static
    {
        $this->token = $token;
        return $this;
    }

    /**
     * OAuth 2.0 alias for the filiation (PV).
     *
     * @return string
     */
    public function getClientId(): string
    {
        return $this->filiation;
    }

    /**
     * OAuth 2.0 alias for the token (Chave de Integração / clientSecret).
     *
     * @return string
     */
    public function getClientSecret(): string
    {
        return $this->token;
    }

    /**
     * @return AccessToken|null The access token cached in memory, if any.
     */
    public function getAccessToken(): ?AccessToken
    {
        return $this->accessToken;
    }

    /**
     * @param AccessToken|null $accessToken
     *
     * @return $this
     */
    public function setAccessToken(?AccessToken $accessToken): static
    {
        $this->accessToken = $accessToken;
        return $this;
    }
}
