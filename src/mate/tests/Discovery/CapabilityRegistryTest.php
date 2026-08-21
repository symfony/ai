<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Discovery;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Discovery\CapabilityRegistry;
use Symfony\AI\Mate\Discovery\ReflectionDiscoverer;
use Symfony\AI\Mate\Tests\Command\Fixtures\SampleResources;
use Symfony\AI\Mate\Tests\Command\Fixtures\SampleTool;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class CapabilityRegistryTest extends TestCase
{
    public function testFindToolReturnsDiscoveredTool()
    {
        $tool = $this->createRegistry()->findTool('sample-add');

        $this->assertNotNull($tool);
        $this->assertSame('sample-add', $tool->name);
        $this->assertSame(SampleTool::class, $tool->handlerClass);
        $this->assertSame('add', $tool->handlerMethod);
    }

    public function testFindToolReturnsNullForUnknownTool()
    {
        $this->assertNull($this->createRegistry()->findTool('does-not-exist'));
    }

    public function testDisabledFeatureIsFilteredOut()
    {
        $registry = $this->createRegistry([
            '_custom' => ['sample-add' => ['enabled' => false]],
        ]);

        $this->assertNull($registry->findTool('sample-add'));
    }

    public function testFindResourceReturnsStaticResource()
    {
        $resource = $this->createRegistry()->findResource('sample://greeting');

        $this->assertNotNull($resource);
        $this->assertSame(SampleResources::class, $resource->handlerClass);
        $this->assertSame('text/plain', $resource->mimeType);
    }

    public function testMatchResourceTemplateExtractsVariables()
    {
        $match = $this->createRegistry()->matchResourceTemplate('sample://echo/hello');

        $this->assertNotNull($match);
        $this->assertSame('sample://echo/{message}', $match['template']->uriTemplate);
        $this->assertSame(['message' => 'hello'], $match['variables']);
    }

    public function testMatchResourceTemplateReturnsNullWhenNoTemplateMatches()
    {
        $this->assertNull($this->createRegistry()->matchResourceTemplate('unknown://x'));
    }

    /**
     * @param array<string, array<string, array{enabled: bool}>> $disabledFeatures
     */
    private function createRegistry(array $disabledFeatures = []): CapabilityRegistry
    {
        $logger = new NullLogger();
        $extensions = [
            '_custom' => ['dirs' => ['tests/Command/Fixtures'], 'includes' => []],
        ];

        return new CapabilityRegistry(__DIR__.'/../..', $extensions, $disabledFeatures, new ReflectionDiscoverer($logger), $logger);
    }
}
