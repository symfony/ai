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
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('openapi_get_spec', 'Tool that gets OpenAPI specification')]
#[AsTool('openapi_execute_operation', 'Tool that executes OpenAPI operations', method: 'executeOperation')]
#[AsTool('openapi_list_operations', 'Tool that lists OpenAPI operations', method: 'listOperations')]
#[AsTool('openapi_validate_request', 'Tool that validates OpenAPI requests', method: 'validateRequest')]
#[AsTool('openapi_generate_client', 'Tool that generates OpenAPI client code', method: 'generateClient')]
#[AsTool('openapi_test_endpoint', 'Tool that tests OpenAPI endpoints', method: 'testEndpoint')]
final readonly class OpenApiClient
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $specUrl = '',
        private string $baseUrl = '',
        private string $apiKey = '',
        private array $headers = [],
        private array $options = [],
    ) {
    }

    /**
     * Get OpenAPI specification.
     *
     * @param string $specUrl OpenAPI specification URL
     *
     * @return array{
     *     success: bool,
     *     spec: array<string, mixed>,
     *     info: array{
     *         title: string,
     *         version: string,
     *         description: string,
     *         contact: array<string, mixed>,
     *         license: array<string, mixed>,
     *     },
     *     servers: array<int, array{
     *         url: string,
     *         description: string,
     *         variables: array<string, mixed>,
     *     }>,
     *     paths: array<string, mixed>,
     *     components: array<string, mixed>,
     *     error: string,
     * }
     */
    public function __invoke(string $specUrl = ''): array
    {
        try {
            $url = $specUrl ?: $this->specUrl;
            if (!$url) {
                throw new \InvalidArgumentException('OpenAPI specification URL is required.');
            }

            $response = $this->httpClient->request('GET', $url, [
                'headers' => array_merge($this->options, $this->headers),
            ]);

            $spec = $response->toArray();

            return [
                'success' => true,
                'spec' => $spec,
                'info' => [
                    'title' => $spec['info']['title'] ?? '',
                    'version' => $spec['info']['version'] ?? '',
                    'description' => $spec['info']['description'] ?? '',
                    'contact' => $spec['info']['contact'] ?? [],
                    'license' => $spec['info']['license'] ?? [],
                ],
                'servers' => array_map(fn ($server) => [
                    'url' => $server['url'],
                    'description' => $server['description'] ?? '',
                    'variables' => $server['variables'] ?? [],
                ], $spec['servers'] ?? []),
                'paths' => $spec['paths'] ?? [],
                'components' => $spec['components'] ?? [],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'spec' => [],
                'info' => ['title' => '', 'version' => '', 'description' => '', 'contact' => [], 'license' => []],
                'servers' => [],
                'paths' => [],
                'components' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute OpenAPI operation.
     *
     * @param string                $path        API path
     * @param string                $method      HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param array<string, mixed>  $parameters  Path and query parameters
     * @param array<string, mixed>  $requestBody Request body for POST/PUT
     * @param array<string, string> $headers     Additional headers
     *
     * @return array{
     *     success: bool,
     *     statusCode: int,
     *     headers: array<string, string>,
     *     body: mixed,
     *     responseTime: float,
     *     error: string,
     * }
     */
    public function executeOperation(
        string $path,
        string $method = 'GET',
        array $parameters = [],
        array $requestBody = [],
        array $headers = [],
    ): array {
        try {
            $startTime = microtime(true);

            // Build full URL
            $url = $this->buildUrl($path, $parameters);

            // Prepare request options
            $requestOptions = [
                'headers' => array_merge($this->headers, $headers),
            ];

            // Add request body for POST/PUT methods
            if (\in_array(strtoupper($method), ['POST', 'PUT', 'PATCH']) && !empty($requestBody)) {
                $requestOptions['json'] = $requestBody;
            }

            // Add API key if available
            if ($this->apiKey) {
                $requestOptions['headers']['Authorization'] = "Bearer {$this->apiKey}";
            }

            $response = $this->httpClient->request(strtoupper($method), $url, $requestOptions);

            $responseTime = microtime(true) - $startTime;
            $statusCode = $response->getStatusCode();
            $responseHeaders = $response->getHeaders(false);

            try {
                $body = $response->toArray();
            } catch (\Exception $e) {
                $body = $response->getContent();
            }

            return [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'statusCode' => $statusCode,
                'headers' => array_map(fn ($values) => implode(', ', $values), $responseHeaders),
                'body' => $body,
                'responseTime' => $responseTime,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'statusCode' => 0,
                'headers' => [],
                'body' => null,
                'responseTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List OpenAPI operations.
     *
     * @param string $specUrl OpenAPI specification URL
     *
     * @return array{
     *     success: bool,
     *     operations: array<int, array{
     *         path: string,
     *         method: string,
     *         operationId: string,
     *         summary: string,
     *         description: string,
     *         parameters: array<int, array{
     *             name: string,
     *             in: string,
     *             required: bool,
     *             schema: array<string, mixed>,
     *         }>,
     *         requestBody: array<string, mixed>,
     *         responses: array<string, array{
     *             description: string,
     *             content: array<string, mixed>,
     *         }>,
     *     }>,
     *     error: string,
     * }
     */
    public function listOperations(string $specUrl = ''): array
    {
        try {
            $spec = $this->__invoke($specUrl);
            if (!$spec['success']) {
                return [
                    'success' => false,
                    'operations' => [],
                    'error' => $spec['error'],
                ];
            }

            $operations = [];
            foreach ($spec['paths'] as $path => $pathItem) {
                foreach ($pathItem as $method => $operation) {
                    if (\in_array(strtoupper($method), ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'])) {
                        $operations[] = [
                            'path' => $path,
                            'method' => strtoupper($method),
                            'operationId' => $operation['operationId'] ?? '',
                            'summary' => $operation['summary'] ?? '',
                            'description' => $operation['description'] ?? '',
                            'parameters' => array_map(fn ($param) => [
                                'name' => $param['name'],
                                'in' => $param['in'],
                                'required' => $param['required'] ?? false,
                                'schema' => $param['schema'] ?? [],
                            ], $operation['parameters'] ?? []),
                            'requestBody' => $operation['requestBody'] ?? [],
                            'responses' => array_map(fn ($response) => [
                                'description' => $response['description'] ?? '',
                                'content' => $response['content'] ?? [],
                            ], $operation['responses'] ?? []),
                        ];
                    }
                }
            }

            return [
                'success' => true,
                'operations' => $operations,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'operations' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate OpenAPI request.
     *
     * @param string               $path        API path
     * @param string               $method      HTTP method
     * @param array<string, mixed> $parameters  Request parameters
     * @param array<string, mixed> $requestBody Request body
     * @param string               $specUrl     OpenAPI specification URL
     *
     * @return array{
     *     success: bool,
     *     valid: bool,
     *     errors: array<int, string>,
     *     warnings: array<int, string>,
     *     validatedParameters: array<string, mixed>,
     *     validatedBody: array<string, mixed>,
     * }
     */
    public function validateRequest(
        string $path,
        string $method,
        array $parameters,
        array $requestBody = [],
        string $specUrl = '',
    ): array {
        try {
            $spec = $this->__invoke($specUrl);
            if (!$spec['success']) {
                return [
                    'success' => false,
                    'valid' => false,
                    'errors' => [$spec['error']],
                    'warnings' => [],
                    'validatedParameters' => [],
                    'validatedBody' => [],
                ];
            }

            $errors = [];
            $warnings = [];
            $validatedParameters = [];
            $validatedBody = [];

            // Find the operation in the spec
            $operation = $this->findOperation($spec['paths'], $path, $method);
            if (!$operation) {
                $errors[] = "Operation {$method} {$path} not found in specification";

                return [
                    'success' => true,
                    'valid' => false,
                    'errors' => $errors,
                    'warnings' => $warnings,
                    'validatedParameters' => $validatedParameters,
                    'validatedBody' => $validatedBody,
                ];
            }

            // Validate parameters
            foreach ($operation['parameters'] ?? [] as $paramSpec) {
                $paramName = $paramSpec['name'];
                $paramIn = $paramSpec['in'];
                $required = $paramSpec['required'] ?? false;

                if ($required && !isset($parameters[$paramName])) {
                    $errors[] = "Required parameter '{$paramName}' is missing";
                } elseif (isset($parameters[$paramName])) {
                    $validatedParameters[$paramName] = $parameters[$paramName];
                }
            }

            // Validate request body
            if (\in_array(strtoupper($method), ['POST', 'PUT', 'PATCH']) && isset($operation['requestBody'])) {
                $validatedBody = $requestBody;
            }

            return [
                'success' => true,
                'valid' => empty($errors),
                'errors' => $errors,
                'warnings' => $warnings,
                'validatedParameters' => $validatedParameters,
                'validatedBody' => $validatedBody,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'valid' => false,
                'errors' => [$e->getMessage()],
                'warnings' => [],
                'validatedParameters' => [],
                'validatedBody' => [],
            ];
        }
    }

    /**
     * Generate OpenAPI client code.
     *
     * @param string $specUrl   OpenAPI specification URL
     * @param string $language  Programming language (php, javascript, python, java)
     * @param string $outputDir Output directory
     *
     * @return array{
     *     success: bool,
     *     generatedFiles: array<int, string>,
     *     message: string,
     *     error: string,
     * }
     */
    public function generateClient(
        string $specUrl,
        string $language = 'php',
        string $outputDir = './generated',
    ): array {
        try {
            $spec = $this->__invoke($specUrl);
            if (!$spec['success']) {
                return [
                    'success' => false,
                    'generatedFiles' => [],
                    'message' => 'Failed to load OpenAPI specification',
                    'error' => $spec['error'],
                ];
            }

            $generatedFiles = [];

            // This is a simplified client generation
            // In reality, you would use tools like OpenAPI Generator
            switch (strtolower($language)) {
                case 'php':
                    $generatedFiles[] = $this->generatePhpClient($spec['spec'], $outputDir);
                    break;
                case 'javascript':
                    $generatedFiles[] = $this->generateJavaScriptClient($spec['spec'], $outputDir);
                    break;
                case 'python':
                    $generatedFiles[] = $this->generatePythonClient($spec['spec'], $outputDir);
                    break;
                case 'java':
                    $generatedFiles[] = $this->generateJavaClient($spec['spec'], $outputDir);
                    break;
                default:
                    throw new \InvalidArgumentException("Unsupported language: {$language}.");
            }

            return [
                'success' => true,
                'generatedFiles' => $generatedFiles,
                'message' => "Client code generated successfully in {$language}",
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'generatedFiles' => [],
                'message' => 'Failed to generate client code',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test OpenAPI endpoint.
     *
     * @param string               $path        API path
     * @param string               $method      HTTP method
     * @param array<string, mixed> $parameters  Request parameters
     * @param array<string, mixed> $requestBody Request body
     * @param int                  $timeout     Request timeout in seconds
     *
     * @return array{
     *     success: bool,
     *     testResults: array{
     *         statusCode: int,
     *         responseTime: float,
     *         responseSize: int,
     *         headers: array<string, string>,
     *         body: mixed,
     *         errors: array<int, string>,
     *     },
     *     performance: array{
     *         averageResponseTime: float,
     *         minResponseTime: float,
     *         maxResponseTime: float,
     *         totalRequests: int,
     *         successfulRequests: int,
     *         failedRequests: int,
     *     },
     *     error: string,
     * }
     */
    public function testEndpoint(
        string $path,
        string $method = 'GET',
        array $parameters = [],
        array $requestBody = [],
        int $timeout = 30,
    ): array {
        try {
            $testResults = [];
            $responseTimes = [];
            $successCount = 0;
            $failCount = 0;

            // Run multiple test requests
            for ($i = 0; $i < 5; ++$i) {
                $result = $this->executeOperation($path, $method, $parameters, $requestBody);

                $testResults[] = [
                    'statusCode' => $result['statusCode'],
                    'responseTime' => $result['responseTime'],
                    'responseSize' => \is_string($result['body']) ? \strlen($result['body']) : 0,
                    'headers' => $result['headers'],
                    'body' => $result['body'],
                    'errors' => $result['success'] ? [] : [$result['error']],
                ];

                $responseTimes[] = $result['responseTime'];
                if ($result['success']) {
                    ++$successCount;
                } else {
                    ++$failCount;
                }
            }

            $performance = [
                'averageResponseTime' => array_sum($responseTimes) / \count($responseTimes),
                'minResponseTime' => min($responseTimes),
                'maxResponseTime' => max($responseTimes),
                'totalRequests' => 5,
                'successfulRequests' => $successCount,
                'failedRequests' => $failCount,
            ];

            return [
                'success' => $successCount > 0,
                'testResults' => $testResults,
                'performance' => $performance,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'testResults' => [],
                'performance' => [
                    'averageResponseTime' => 0.0,
                    'minResponseTime' => 0.0,
                    'maxResponseTime' => 0.0,
                    'totalRequests' => 0,
                    'successfulRequests' => 0,
                    'failedRequests' => 0,
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build full URL from path and parameters.
     */
    private function buildUrl(string $path, array $parameters): string
    {
        $baseUrl = $this->baseUrl ?: 'http://localhost';
        $url = rtrim($baseUrl, '/').'/'.ltrim($path, '/');

        // Replace path parameters
        foreach ($parameters as $key => $value) {
            $url = str_replace("{{$key}}", $value, $url);
        }

        return $url;
    }

    /**
     * Find operation in OpenAPI paths.
     */
    private function findOperation(array $paths, string $path, string $method): ?array
    {
        foreach ($paths as $pathPattern => $pathItem) {
            if ($this->matchPath($pathPattern, $path)) {
                return $pathItem[strtolower($method)] ?? null;
            }
        }

        return null;
    }

    /**
     * Match path pattern with actual path.
     */
    private function matchPath(string $pattern, string $path): bool
    {
        // Simple pattern matching - in reality, you'd need more sophisticated matching
        return $pattern === $path || fnmatch($pattern, $path);
    }

    /**
     * Generate PHP client.
     */
    private function generatePhpClient(array $spec, string $outputDir): string
    {
        $className = $spec['info']['title'] ?? 'ApiClient';
        $filename = "{$outputDir}/{$className}.php";

        // Create directory if it doesn't exist
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $content = "<?php\n\nclass {$className}\n{\n    // Generated OpenAPI client\n}\n";
        file_put_contents($filename, $content);

        return $filename;
    }

    /**
     * Generate JavaScript client.
     */
    private function generateJavaScriptClient(array $spec, string $outputDir): string
    {
        $className = $spec['info']['title'] ?? 'ApiClient';
        $filename = "{$outputDir}/{$className}.js";

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $content = "class {$className} {\n    // Generated OpenAPI client\n}\n";
        file_put_contents($filename, $content);

        return $filename;
    }

    /**
     * Generate Python client.
     */
    private function generatePythonClient(array $spec, string $outputDir): string
    {
        $className = $spec['info']['title'] ?? 'api_client';
        $filename = "{$outputDir}/{$className}.py";

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $content = "class {$className}:\n    # Generated OpenAPI client\n    pass\n";
        file_put_contents($filename, $content);

        return $filename;
    }

    /**
     * Generate Java client.
     */
    private function generateJavaClient(array $spec, string $outputDir): string
    {
        $className = $spec['info']['title'] ?? 'ApiClient';
        $filename = "{$outputDir}/{$className}.java";

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $content = "public class {$className} {\n    // Generated OpenAPI client\n}\n";
        file_put_contents($filename, $content);

        return $filename;
    }
}
