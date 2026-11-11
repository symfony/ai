<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Controller;

use Mcp\Server;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpBundle\Http\MiddlewareFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;

class McpControllerTest extends TestCase
{
    public function testRejectsABodyOverTheConfiguredLimit()
    {
        $response = $this->createController(10)->handle($this->createRequest(str_repeat('a', 100)));

        $this->assertSame(413, $response->getStatusCode());
    }

    public function testAcceptsABodyWithinTheConfiguredLimit()
    {
        $response = $this->createController(1024)->handle($this->createRequest(str_repeat('a', 100)));

        $this->assertNotSame(413, $response->getStatusCode());
    }

    public function testKeepsTheSdkLimitWhenNoneIsConfigured()
    {
        $response = $this->createController(null)->handle($this->createRequest(str_repeat('a', 100)));

        $this->assertNotSame(413, $response->getStatusCode());
    }

    private function createController(?int $maxBodyBytes): McpController
    {
        $psr17Factory = new Psr17Factory();

        return new McpController(
            Server::builder()->build(),
            new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory),
            new HttpFoundationFactory(),
            $psr17Factory,
            $psr17Factory,
            new MiddlewareFactory(null),
            maxBodyBytes: $maxBodyBytes,
        );
    }

    private function createRequest(string $content): Request
    {
        return Request::create('http://localhost/mcp', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $content);
    }
}
