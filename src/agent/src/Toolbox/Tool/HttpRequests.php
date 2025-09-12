<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('requests_get', 'Tool for making GET requests to API endpoints')]
#[AsTool('requests_post', 'Tool for making POST requests to API endpoints', method: 'post')]
#[AsTool('requests_put', 'Tool for making PUT requests to API endpoints', method: 'put')]
#[AsTool('requests_patch', 'Tool for making PATCH requests to API endpoints', method: 'patch')]
#[AsTool('requests_delete', 'Tool for making DELETE requests to API endpoints', method: 'delete')]
final readonly class HttpRequests
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private bool $allowDangerousRequests = false,
        private array $options = [],
    ) {
        if (!$this->allowDangerousRequests) {
            throw new \InvalidArgumentException('You must set allowDangerousRequests to true to use this tool. Requests can be dangerous and can lead to security vulnerabilities. For example, users can ask a server to make a request to an internal server. It\'s recommended to use requests through a proxy server and avoid accepting inputs from untrusted sources without proper sandboxing.');
        }
    }

    /**
     * Make a GET request to a URL.
     *
     * @param string $url The URL to make a GET request to
     *
     * @return array{status_code: int, headers: array<string, array<int, string>>, content: string}
     */
    public function __invoke(string $url): array
    {
        try {
            $response = $this->httpClient->request('GET', $url, $this->options);

            return [
                'status_code' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'content' => $response->getContent(),
            ];
        } catch (\Exception $e) {
            return [
                'status_code' => 0,
                'headers' => [],
                'content' => 'Error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Make a POST request to a URL.
     *
     * @param string               $url  The URL to make a POST request to
     * @param array<string, mixed> $data The data to send in the POST request
     *
     * @return array{status_code: int, headers: array<string, array<int, string>>, content: string}
     */
    public function post(string $url, array $data = []): array
    {
        try {
            $options = array_merge($this->options, [
                'json' => $data,
            ]);

            $response = $this->httpClient->request('POST', $url, $options);

            return [
                'status_code' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'content' => $response->getContent(),
            ];
        } catch (\Exception $e) {
            return [
                'status_code' => 0,
                'headers' => [],
                'content' => 'Error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Make a PUT request to a URL.
     *
     * @param string               $url  The URL to make a PUT request to
     * @param array<string, mixed> $data The data to send in the PUT request
     *
     * @return array{status_code: int, headers: array<string, array<int, string>>, content: string}
     */
    public function put(string $url, array $data = []): array
    {
        try {
            $options = array_merge($this->options, [
                'json' => $data,
            ]);

            $response = $this->httpClient->request('PUT', $url, $options);

            return [
                'status_code' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'content' => $response->getContent(),
            ];
        } catch (\Exception $e) {
            return [
                'status_code' => 0,
                'headers' => [],
                'content' => 'Error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Make a PATCH request to a URL.
     *
     * @param string               $url  The URL to make a PATCH request to
     * @param array<string, mixed> $data The data to send in the PATCH request
     *
     * @return array{status_code: int, headers: array<string, array<int, string>>, content: string}
     */
    public function patch(string $url, array $data = []): array
    {
        try {
            $options = array_merge($this->options, [
                'json' => $data,
            ]);

            $response = $this->httpClient->request('PATCH', $url, $options);

            return [
                'status_code' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'content' => $response->getContent(),
            ];
        } catch (\Exception $e) {
            return [
                'status_code' => 0,
                'headers' => [],
                'content' => 'Error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Make a DELETE request to a URL.
     *
     * @param string $url The URL to make a DELETE request to
     *
     * @return array{status_code: int, headers: array<string, array<int, string>>, content: string}
     */
    public function delete(string $url): array
    {
        try {
            $response = $this->httpClient->request('DELETE', $url, $this->options);

            return [
                'status_code' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'content' => $response->getContent(),
            ];
        } catch (\Exception $e) {
            return [
                'status_code' => 0,
                'headers' => [],
                'content' => 'Error: '.$e->getMessage(),
            ];
        }
    }
}
