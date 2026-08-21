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
use Symfony\AI\Mate\Discovery\Model\DiscoveredCapabilities;
use Symfony\AI\Mate\Discovery\ReflectionDiscoverer;
use Symfony\AI\Mate\Tests\Command\Fixtures\SampleResources;
use Symfony\AI\Mate\Tests\Command\Fixtures\SampleTool;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ReflectionDiscovererTest extends TestCase
{
    public function testDiscoversToolsResourcesAndTemplates()
    {
        $capabilities = $this->discover(['tests/Command/Fixtures']);

        $tools = $capabilities->getTools();
        $this->assertArrayHasKey('sample-add', $tools);
        $this->assertSame(SampleTool::class, $tools['sample-add']->handlerClass);
        $this->assertSame('Add two integers', $tools['sample-add']->description);
        $this->assertSame('object', $tools['sample-add']->inputSchema['type']);

        $resources = $capabilities->getResources();
        $this->assertArrayHasKey('sample://greeting', $resources);
        $this->assertSame(SampleResources::class, $resources['sample://greeting']->handlerClass);

        $templates = $capabilities->getResourceTemplates();
        $this->assertArrayHasKey('sample://echo/{message}', $templates);
    }

    public function testEmptyDirectoriesYieldNothing()
    {
        $capabilities = $this->discover(['tests/Discovery/Fixtures/does-not-exist']);

        $this->assertSame([], $capabilities->getTools());
        $this->assertSame([], $capabilities->getResources());
        $this->assertSame([], $capabilities->getResourceTemplates());
    }

    /**
     * @param string[] $dirs
     */
    private function discover(array $dirs): DiscoveredCapabilities
    {
        $discoverer = new ReflectionDiscoverer(new NullLogger());

        return $discoverer->discover(__DIR__.'/../..', $dirs);
    }
}
