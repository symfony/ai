<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Profiler\Tests\Service\Formatter;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Profiler\Service\Formatter\RequestCollectorFormatter;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RequestCollectorFormatterTest extends TestCase
{
    private RequestCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new RequestCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('request', $this->formatter->getName());
    }

    public function testSanitizesCookies()
    {
        $collector = $this->createCollectorWithData([
            'request_cookies' => [
                'session_id' => 'abc123secret',
                'admin_token' => 'xyz789secret',
            ],
            'response_cookies' => [
                'csrf_token' => 'csrf_secret',
            ],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame('***REDACTED***', $result['request_cookies']['session_id']);
        $this->assertSame('***REDACTED***', $result['request_cookies']['admin_token']);
        $this->assertSame('***REDACTED***', $result['response_cookies']['csrf_token']);
    }

    public function testSanitizesSensitiveHeaders()
    {
        $collector = $this->createCollectorWithData([
            'request_headers' => [
                'authorization' => 'Bearer secret-token',
                'cookie' => 'session=abc123',
                'x-api-key' => 'api-secret-key',
                'user-agent' => 'Mozilla/5.0',
                'accept' => 'text/html',
            ],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame('***REDACTED***', $result['request_headers']['authorization']);
        $this->assertSame('***REDACTED***', $result['request_headers']['cookie']);
        $this->assertSame('***REDACTED***', $result['request_headers']['x-api-key']);
        $this->assertSame('Mozilla/5.0', $result['request_headers']['user-agent']);
        $this->assertSame('text/html', $result['request_headers']['accept']);
    }

    public function testSanitizesEnvironmentVariables()
    {
        $collector = $this->createCollectorWithData([
            'request_server' => [
                'APP_SECRET' => 'my-secret-value',
                'OPENAI_API_KEY' => 'sk-1234567890',
                'DATABASE_PASSWORD' => 'db-password',
                'JWT_TOKEN' => 'jwt-token-value',
                'HTTP_HOST' => '127.0.0.1',
                'REQUEST_METHOD' => 'GET',
                'PATH' => '/usr/bin:/bin',
            ],
            'dotenv_vars' => [
                'APP_SECRET' => 'my-secret-value',
                'HUGGINGFACE_API_KEY' => 'hf-key',
                'APP_ENV' => 'dev',
            ],
        ]);

        $result = $this->formatter->format($collector);

        // Sensitive vars should be redacted
        $this->assertSame('***REDACTED***', $result['request_server']['APP_SECRET']);
        $this->assertSame('***REDACTED***', $result['request_server']['OPENAI_API_KEY']);
        $this->assertSame('***REDACTED***', $result['request_server']['DATABASE_PASSWORD']);
        $this->assertSame('***REDACTED***', $result['request_server']['JWT_TOKEN']);
        $this->assertSame('***REDACTED***', $result['dotenv_vars']['APP_SECRET']);
        $this->assertSame('***REDACTED***', $result['dotenv_vars']['HUGGINGFACE_API_KEY']);

        // Non-sensitive vars should be preserved
        $this->assertSame('127.0.0.1', $result['request_server']['HTTP_HOST']);
        $this->assertSame('GET', $result['request_server']['REQUEST_METHOD']);
        $this->assertSame('/usr/bin:/bin', $result['request_server']['PATH']);
        $this->assertSame('dev', $result['dotenv_vars']['APP_ENV']);
    }

    public function testSanitizesSessionData()
    {
        $collector = $this->createCollectorWithData([
            'session_attributes' => [
                'user_id' => 123,
                'auth_token' => 'session-token',
            ],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame('***REDACTED***', $result['session_attributes']['user_id']);
        $this->assertSame('***REDACTED***', $result['session_attributes']['auth_token']);
    }

    public function testPreservesNonSensitiveData()
    {
        $collector = $this->createCollectorWithData([
            'method' => 'GET',
            'path_info' => '/api/users',
            'route' => 'api_users_list',
            'status_code' => 200,
            'content_type' => 'application/json',
            'request_query' => ['page' => 1, 'limit' => 10],
            'request_request' => [],
            'request_attributes' => ['_route' => 'api_users_list'],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame('GET', $result['method']);
        $this->assertSame('/api/users', $result['path_info']);
        $this->assertSame('api_users_list', $result['route']);
        $this->assertSame(200, $result['status_code']);
        $this->assertSame('application/json', $result['content_type']);
        $this->assertSame(['page' => 1, 'limit' => 10], $result['request_query']);
    }

    public function testGetSummaryReturnsBasicInfo()
    {
        $collector = $this->createMock(DataCollectorInterface::class);
        $collector->method('getName')->willReturn('request');

        $collectorObj = new class {
            public function getMethod(): string
            {
                return 'POST';
            }

            public function getPathInfo(): string
            {
                return '/api/users';
            }

            public function getRoute(): string
            {
                return 'api_users_create';
            }

            public function getStatusCode(): int
            {
                return 201;
            }

            public function getContentType(): string
            {
                return 'application/json';
            }
        };

        $summary = $this->formatter->getSummary($collectorObj);

        $this->assertArrayHasKey('method', $summary);
        $this->assertArrayHasKey('path', $summary);
        $this->assertArrayHasKey('route', $summary);
        $this->assertArrayHasKey('status_code', $summary);
        $this->assertArrayHasKey('content_type', $summary);
        $this->assertSame('POST', $summary['method']);
        $this->assertSame('/api/users', $summary['path']);
        $this->assertSame('api_users_create', $summary['route']);
        $this->assertSame(201, $summary['status_code']);
        $this->assertSame('application/json', $summary['content_type']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createCollectorWithData(array $data): RequestDataCollector
    {
        $collector = new RequestDataCollector();

        $class = new \ReflectionClass($collector);
        $property = $class->getProperty('data');

        $mockData = $this->createMock(Data::class);
        $mockData->method('getValue')
            ->with(true)
            ->willReturn($data);

        $property->setValue($collector, $mockData);

        return $collector;
    }
}
