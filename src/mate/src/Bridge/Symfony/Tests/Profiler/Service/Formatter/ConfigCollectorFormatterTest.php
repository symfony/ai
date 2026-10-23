<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Profiler\Service\Formatter;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\ConfigCollectorFormatter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\ConfigDataCollector;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 */
final class ConfigCollectorFormatterTest extends TestCase
{
    public function testFormatCollectedProfile()
    {
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('test');
        $kernel->method('isDebug')->willReturn(false);
        $kernel->method('getBundles')->willReturn([]);

        $collector = new ConfigDataCollector();
        $collector->setKernel($kernel);
        $collector->collect(new Request(), new Response('', 200, ['X-Debug-Token' => 'profile-token']));
        $collector->lateCollect();

        $formatter = new ConfigCollectorFormatter();
        $this->assertSame([
            'symfony_version' => $collector->getSymfonyVersion(),
            'php_version' => $collector->getPhpVersion(),
            'environment' => 'test',
            'debug' => false,
        ], $formatter->getSummary($collector));
        $this->assertSame([
            'symfony_version' => $collector->getSymfonyVersion(),
            'php_version' => $collector->getPhpVersion(),
            'environment' => 'test',
            'debug' => false,
            'sapi' => $collector->getSapiName(),
            'timezone' => $collector->getPhpTimezone(),
            'xdebug_enabled' => $collector->hasXdebug(),
            'apcu_enabled' => $collector->hasApcu(),
            'opcache_enabled' => $collector->hasZendOpcache(),
        ], $formatter->format($collector));
    }

    public function testRegisteredInProfilerConfiguration()
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());
        $loader->load(\dirname(__DIR__, 4).'/config/config.php');

        $this->assertTrue($container->hasDefinition(ConfigCollectorFormatter::class));
        $this->assertTrue($container->getDefinition(ConfigCollectorFormatter::class)->hasTag('ai_mate.profiler_collector_formatter'));
    }

    public function testSummaryWithoutKernel()
    {
        $collector = new ConfigDataCollector();
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        $summary = (new ConfigCollectorFormatter())->getSummary($collector);

        $this->assertSame('n/a', $summary['environment']);
        $this->assertSame('n/a', $summary['debug']);
    }
}
