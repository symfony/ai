<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Http;

use Http\Discovery\ClassDiscovery;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Exception\LogicException;
use Symfony\AI\McpBundle\Http\Psr17Factory;

class Psr17FactoryTest extends TestCase
{
    public function testUsesTheDiscoveredImplementation()
    {
        $request = (new Psr17Factory())->createServerRequest('POST', 'http://localhost/mcp');

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/mcp', $request->getUri()->getPath());
    }

    public function testMissingImplementationFailsWithAnActionableMessage()
    {
        $strategies = ClassDiscovery::getStrategies();
        ClassDiscovery::setStrategies([]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Try running "composer require php-http/discovery psr/http-factory-implementation:*".');

        try {
            new Psr17Factory();
        } finally {
            ClassDiscovery::setStrategies([...$strategies]);
        }
    }
}
