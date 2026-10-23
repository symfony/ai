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
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Bundle\FrameworkBundle\DataCollector\RouterDataCollector as FrameworkRouterDataCollector;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\RouterDataCollector;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Controller\RedirectController as RoutingRedirectController;

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
            'route' => null,
            'target_host' => null,
            'target_path' => null,
        ], (new RouterCollectorFormatter())->format($collector));
    }

    public function testFormatPreservesRedirectingRoute()
    {
        $request = Request::create('/old');
        $request->attributes->set('_route', 'old');
        if (class_exists(RoutingRedirectController::class)) {
            $controller = new RoutingRedirectController();
        } else {
            $controller = new RedirectController();
        }
        $action = [$controller, 'urlRedirectAction'];
        $this->assertIsCallable($action);
        $collector = new FrameworkRouterDataCollector();
        $collector->onKernelController(new ControllerEvent($this->createMock(HttpKernelInterface::class), $action, $request, HttpKernelInterface::MAIN_REQUEST));
        $response = $action($request, '/page?token=secret');
        $this->assertInstanceOf(Response::class, $response);
        $collector->collect($request, $response);

        $this->assertSame([
            'redirect' => true,
            'route' => 'old',
            'target_host' => 'localhost',
            'target_path' => '/page',
        ], (new RouterCollectorFormatter())->format($collector));
    }

    public function testFormatNormalizesUnknownRoute()
    {
        $request = Request::create('/source');
        $controller = static fn (): Response => new RedirectResponse('/login');
        $collector = new FrameworkRouterDataCollector();
        $collector->onKernelController(new ControllerEvent($this->createMock(HttpKernelInterface::class), $controller, $request, HttpKernelInterface::MAIN_REQUEST));
        $collector->collect($request, $controller());

        $this->assertSame('n/a', $collector->getTargetRoute());
        $this->assertNull((new RouterCollectorFormatter())->format($collector)['route']);
    }

    #[DataProvider('provideRedirectTargets')]
    public function testFormatOmitsSensitiveUrlComponents(string $target, ?string $expectedHost, ?string $expectedPath)
    {
        $collector = new RouterDataCollector();
        $collector->collect(Request::create('/source'), new RedirectResponse($target));

        $this->assertSame([
            'redirect' => true,
            'route' => null,
            'target_host' => $expectedHost,
            'target_path' => $expectedPath,
        ], (new RouterCollectorFormatter())->format($collector));
    }

    /**
     * @return iterable<string, array{string, string|null, string|null}>
     */
    public static function provideRedirectTargets(): iterable
    {
        yield 'absolute URL' => ['https://user:password@example.com:8443/reset/secret?token=secret#private', 'example.com', '/reset/secret'];
        yield 'relative URL' => ['/reset/secret?token=secret#private', null, '/reset/secret'];
        yield 'network-path URL' => ['//example.com/reset/secret?token=secret', 'example.com', '/reset/secret'];
        yield 'fragment' => ['#private', null, null];
        yield 'malformed authority' => ['https://example.com:invalid/reset/secret', null, null];
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
