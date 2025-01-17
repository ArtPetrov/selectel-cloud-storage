<?php

namespace ArtPetrov\Selectel\CloudStorage\Api;

use RuntimeException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use ArtPetrov\Selectel\CloudStorage\Contracts\Api\ApiClientContract;
use ArtPetrov\Selectel\CloudStorage\Exceptions\AuthenticationFailedException;

class ApiClient implements ApiClientContract
{
    /**
     * API Auth.
     *
     * @var string
     */
    public $auth_url = 'https://auth.selcdn.ru';

    /**
     * API Username.
     *
     * @var string
     */
    protected $username;

    /**
     * API Password.
     *
     * @var string
     */
    protected $password;

    /**
     * Authorization token.
     *
     * @var string
     */
    protected $token;

    /**
     * Storage URL.
     *
     * @var string
     */
    protected $storageUrl;

    /**
     * HTTP Client.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $httpClient;

    /**
     * Creates new API Client instance.
     *
     * @param string $username
     * @param string $password
     * @param null $authUrl
     */
    public function __construct($username, $password, $authUrl = null)
    {
        $this->username = $username;
        $this->password = $password;

        if (!is_null($authUrl)) {
            $this->auth_url = $authUrl;
        }

    }

    /**
     * Replaces HTTP Client instance.
     *
     * @param \GuzzleHttp\ClientInterface $httpClient
     *
     * @return \ArtPetrov\Selectel\CloudStorage\Contracts\Api\ApiClientContract
     */
    public function setHttpClient(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * HTTP Client.
     *
     * @return \GuzzleHttp\ClientInterface|null
     */
    public function getHttpClient()
    {
        if (!is_null($this->httpClient)) {
            return $this->httpClient;
        }

        return $this->httpClient = new Client([
            'base_uri' => $this->storageUrl(),
            'headers' => [
                'X-Auth-Token' => $this->token(),
            ],
        ]);
    }

    /**
     * Authenticated user's token.
     *
     * @return string
     */
    public function token()
    {
        return $this->token;
    }

    /**
     * Storage URL.
     *
     * @return string
     */
    public function storageUrl()
    {
        return $this->storageUrl;
    }

    /**
     * Determines if user is authenticated.
     *
     * @return bool
     */
    public function authenticated()
    {
        return !is_null($this->token());
    }

    /**
     * Performs authentication request.
     *
     * @throws \ArtPetrov\Selectel\CloudStorage\Exceptions\AuthenticationFailedException
     * @throws \RuntimeException
     */
    public function authenticate()
    {
        if (!is_null($this->token)) {
            return;
        }

        $response = $this->authenticationResponse();

        if (!$response->hasHeader('X-Auth-Token')) {
            throw new AuthenticationFailedException('Given credentials are wrong.', 403);
        }

        if (!$response->hasHeader('X-Storage-Url')) {
            throw new RuntimeException('Storage URL is missing.', 500);
        }

        $this->token = $response->getHeaderLine('X-Auth-Token');
        $this->storageUrl = $response->getHeaderLine('X-Storage-Url');
    }

    /**
     * Performs authentication request and returns its response.
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \ArtPetrov\Selectel\CloudStorage\Exceptions\AuthenticationFailedException
     *
     */
    public function authenticationResponse()
    {
        $client = new Client();

        try {
            $response = $client->request('GET', $this->auth_url, [
                'headers' => [
                    'X-Auth-User' => $this->username,
                    'X-Auth-Key' => $this->password,
                ],
            ]);
        } catch (RequestException $e) {
            throw new AuthenticationFailedException('Given credentials are wrong.', 403);
        }

        return $response;
    }

    /**
     * Performs new API request. $params array will be passed to HTTP Client as is.
     *
     * @param string $method
     * @param string $url
     * @param array $params = []
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function request($method, $url, array $params = [])
    {
        if (!$this->authenticated()) {
            $this->authenticate();
        }

        if (!isset($params['query'])) {
            $params['query'] = [];
        }

        $params['query']['format'] = 'json';

        try {
            $response = $this->getHttpClient()->request($method, $url, $params);
        } catch (RequestException $e) {
            return $e->getResponse();
        }

        return $response;
    }
}
