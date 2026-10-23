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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorRegistry;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\RouterCollectorFormatter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\RouterDataCollector;

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 */
final class RouterCollectorFormatterTest extends TestCase
{
    public function testGetName()
    {
        $this->assertSame('router', (new RouterCollectorFormatter())->getName());
    }

    public function testFormatNonRedirectResponse()
    {
        $collector = new RouterDataCollector();
        $collector->collect(Request::create('/page'), new Response());

        $this->assertSame([
            'redirect' => false,
            'target_route' => null,
            'target_host' => null,
        ], (new RouterCollectorFormatter())->format($collector));
    }

    public function testFormatPreservesTargetRoute()
    {
        $collector = $this->createMock(RouterDataCollector::class);
        $collector->method('getRedirect')->willReturn(true);
        $collector->method('getTargetRoute')->willReturn('app_home');
        $collector->method('getTargetUrl')->willReturn('/home?token=secret');

        $this->assertSame([
            'redirect' => true,
            'target_route' => 'app_home',
            'target_host' => null,
        ], (new RouterCollectorFormatter())->format($collector));
    }

    #[DataProvider('provideRedirectTargets')]
    public function testFormatOmitsSensitiveUrlComponents(string $target, ?string $expectedHost)
    {
        $collector = new RouterDataCollector();
        $collector->collect(Request::create('/source'), new RedirectResponse($target));

        $this->assertSame([
            'redirect' => true,
            'target_route' => null,
            'target_host' => $expectedHost,
        ], (new RouterCollectorFormatter())->format($collector));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function provideRedirectTargets(): iterable
    {
        yield 'absolute URL' => ['https://user:password@example.com:8443/reset/secret?token=secret#private', 'example.com'];
        yield 'relative URL' => ['/reset/secret?token=secret#private', null];
        yield 'network-path URL' => ['//example.com/reset/secret?token=secret', 'example.com'];
        yield 'fragment' => ['#private', null];
        yield 'malformed authority' => ['https://example.com:invalid/reset/secret', null];
    }

    public function testSummaryOnlyReadsRedirectFlag()
    {
        $collector = $this->createMock(RouterDataCollector::class);
        $collector->method('getRedirect')->willReturn(true);
        $collector->expects($this->never())->method('getTargetUrl');
        $collector->expects($this->never())->method('getTargetRoute');

        $this->assertSame(['redirect' => true], (new RouterCollectorFormatter())->getSummary($collector));
    }

    public function testFormatterIsRegisteredBySymfonyExtension()
    {
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', __DIR__);
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../../../config'));
        $loader->load('config.php');
        $container->getDefinition(CollectorRegistry::class)->setPublic(true);
        $container->compile();

        $registry = $container->get(CollectorRegistry::class);
        $this->assertInstanceOf(CollectorRegistry::class, $registry);
        $this->assertInstanceOf(RouterCollectorFormatter::class, $registry->get('router'));
    }
}
