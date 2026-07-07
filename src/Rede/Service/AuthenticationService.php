<?php

namespace Rede\Service;

use CurlHandle;
use Psr\Log\LoggerInterface;
use Rede\Auth\AccessToken;
use Rede\Exception\RedeException;
use Rede\Store;
use RuntimeException;

/**
 * Fetches an OAuth 2.0 access token from Rede using the client_credentials
 * grant. Standalone on purpose: unlike {@see AbstractService} it talks to the
 * token host (not the transaction host) and returns an {@see AccessToken}
 * instead of a Transaction.
 */
class AuthenticationService
{
    public const GRANT_TYPE = 'client_credentials';

    /**
     * @param Store                $store
     * @param LoggerInterface|null $logger
     */
    public function __construct(private readonly Store $store, private readonly ?LoggerInterface $logger = null)
    {
    }

    /**
     * Requests a fresh access token from Rede.
     *
     * @param int|null $now Unix timestamp used as the token issue time; defaults
     *                      to the current time. Injectable to keep it testable.
     *
     * @return AccessToken
     * @throws RedeException  When Rede answers with an error (HTTP >= 400) or an
     *                        unexpected payload.
     * @throws RuntimeException When the transport itself fails.
     */
    public function authenticate(?int $now = null): AccessToken
    {
        $now ??= time();
        $endpoint = $this->store->getEnvironment()->getTokenEndpoint();
        $body = http_build_query(['grant_type' => self::GRANT_TYPE]);

        $headers = [
            'Authorization: ' . $this->basicAuthorization(),
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ];

        $curl = curl_init($endpoint);

        if (!$curl instanceof CurlHandle) {
            throw new RuntimeException('Was not possible to create a curl instance.');
        }

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

        $this->logger?->debug(
            sprintf(
                "Request Rede OAuth\nPOST %s\n%s\n\n%s",
                $endpoint,
                implode("\n", self::maskSecrets($headers)),
                $body
            )
        );

        $response = curl_exec($curl);
        $httpInfo = curl_getinfo($curl);
        $statusCode = (int)$httpInfo['http_code'];

        if (curl_errno($curl)) {
            $error = sprintf('Curl error[%s]: %s', curl_errno($curl), curl_error($curl));
            curl_close($curl);
            throw new RuntimeException($error);
        }

        curl_close($curl);

        if (!is_string($response)) {
            throw new RuntimeException('Error obtaining a response from the OAuth endpoint');
        }

        $this->logger?->debug(
            sprintf(
                "Response Rede OAuth\nStatus Code: %s\n\n%s",
                $statusCode,
                self::maskSecret($response)
            )
        );

        return $this->parseTokenResponse($response, $statusCode, $now);
    }

    /**
     * @return string The Basic authorization header value, base64(clientId:clientSecret).
     */
    public function basicAuthorization(): string
    {
        return 'Basic ' . base64_encode(
            sprintf('%s:%s', $this->store->getClientId(), $this->store->getClientSecret())
        );
    }

    /**
     * Parses the OAuth token endpoint response into an {@see AccessToken}.
     *
     * Kept separate from the transport so it can be unit tested without network.
     *
     * @param string $response   Raw response body.
     * @param int    $statusCode HTTP status code.
     * @param int    $now        Issue timestamp to stamp on the token.
     *
     * @return AccessToken
     * @throws RedeException
     */
    public function parseTokenResponse(string $response, int $statusCode, int $now): AccessToken
    {
        $data = json_decode($response, true);

        if ($statusCode >= 400) {
            $message = is_array($data)
                ? (string)($data['error_description'] ?? $data['error'] ?? 'OAuth authentication failed')
                : 'OAuth authentication failed';

            throw new RedeException($message, $statusCode);
        }

        if (!is_array($data) || !isset($data['access_token'])) {
            throw new RedeException('Unexpected response from the OAuth endpoint', $statusCode);
        }

        return new AccessToken(
            (string)$data['access_token'],
            (int)($data['expires_in'] ?? 0),
            $now,
            (string)($data['token_type'] ?? 'Bearer'),
            isset($data['scope']) ? (string)$data['scope'] : null,
        );
    }

    /**
     * Masks the Authorization header on a list of header lines.
     *
     * @param array<int, string> $headers
     *
     * @return array<int, string>
     */
    private static function maskSecrets(array $headers): array
    {
        return array_map(
            static fn(string $header): string => (string)preg_replace(
                '/^(Authorization:\s*\S+\s+).+$/i',
                '$1***',
                $header
            ),
            $headers
        );
    }

    /**
     * Masks the access_token value inside a JSON payload.
     *
     * @param string $payload
     *
     * @return string
     */
    private static function maskSecret(string $payload): string
    {
        return (string)preg_replace('/"access_token"\s*:\s*"[^"]+"/i', '"access_token":"***"', $payload);
    }
}
