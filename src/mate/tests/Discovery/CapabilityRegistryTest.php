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
use Symfony\AI\Mate\Tests\Discovery\Fixtures\Shadow\ShadowingTool;

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

    public function testDisabledResourceIsFilteredOut()
    {
        $registry = $this->createRegistry([
            '_custom' => ['sample://greeting' => ['enabled' => false]],
        ]);

        $this->assertNull($registry->findResource('sample://greeting'));
    }

    public function testDisabledResourceTemplateIsFilteredOut()
    {
        $registry = $this->createRegistry([
            '_custom' => ['sample://echo/{message}' => ['enabled' => false]],
        ]);

        $this->assertNull($registry->matchResourceTemplate('sample://echo/hello'));
    }

    /**
     * `tools:list` and `tools:inspect` build their maps last-wins, so the lookup used by
     * `tools:call` has to resolve a shadowed name to the same handler they describe.
     */
    public function testACollidingNameResolvesToTheLastExtension()
    {
        $logger = new NullLogger();
        $extensions = [
            'vendor/first' => ['dirs' => ['tests/Command/Fixtures'], 'includes' => []],
            '_custom' => ['dirs' => ['tests/Discovery/Fixtures/Shadow'], 'includes' => []],
        ];

        $registry = new CapabilityRegistry(__DIR__.'/../..', $extensions, [], new ReflectionDiscoverer($logger), $logger);

        $tool = $registry->findTool('sample-add');
        $this->assertNotNull($tool);
        $this->assertSame(ShadowingTool::class, $tool->handlerClass);
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
