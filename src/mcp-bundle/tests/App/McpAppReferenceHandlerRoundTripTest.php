<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\App;

use Mcp\Capability\Registry;
use Mcp\Capability\Registry\ReferenceHandler;
use Mcp\Server\Builder;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\AI\McpBundle\App\McpAppReferenceHandler;
use Symfony\AI\McpBundle\App\McpAppRenderer;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Round-trips a template-bound app tool through the *real* SDK stack — Builder, Registry,
 * ReflectedElementLoader, HandlerResolver and ReferenceHandler — with our decorator wrapping the SDK
 * handler, rather than the hand-rolled inner handler used by {@see McpAppReferenceHandlerTest}.
 *
 * The point is to pin the contract our keying silently depends on: registering a tool the way
 * {@see \Symfony\AI\McpBundle\DependencyInjection\McpAppPass} does — `addTool([$serviceId, $method], …)`
 * — must yield a {@see Registry\ToolReference} whose `->handler` is exactly
 * `[$serviceId, $method]`, the string on which {@see McpAppReferenceHandler} looks up the template. If a
 * future SDK bump normalizes handlers (to an instance, a closure, …), the template map would silently
 * miss and the iframe would render blank; these assertions turn that into a red test instead.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpAppReferenceHandlerRoundTripTest extends TestCase
{
    public function testSdkBuiltToolReferenceKeysAndRendersThroughTheDecorator()
    {
        $app = new RoundTripApp();
        $container = $this->containerFor($app);

        // Register the tool exactly as McpAppPass does: handler [$serviceId, $method], real SDK loaders.
        $registry = new Registry();
        (new Builder())
            ->setContainer($container)
            ->setRegistry($registry)
            ->addTool([RoundTripApp::class, 'render'], 'movie_search', null, 'Search movies')
            ->build();

        $toolReference = $registry->getTool('movie_search');

        // The contract McpAppReferenceHandler keys on (see McpAppPass::addTool()): if this shape ever
        // drifts, the decorator below can no longer find its template.
        $this->assertSame([RoundTripApp::class, 'render'], $toolReference->handler);

        $decorator = new McpAppReferenceHandler(
            new ReferenceHandler($container),
            new McpAppRenderer(new Environment(new ArrayLoader(['grid.html.twig' => '<ul><li>{{ query }}</li><li>{{ count }}</li></ul>']))),
            [RoundTripApp::class.'::render' => 'grid.html.twig'],
        );

        $result = $decorator->handle($toolReference, ['query' => 'Nolan', '_session' => $this->createStub(SessionInterface::class)]);

        $this->assertIsArray($result);
        $this->assertSame('<ul><li>Nolan</li><li>2</li></ul>', $result['html']); // rendered from the method's context
        $this->assertSame('Nolan', $result['query']); // original context preserved alongside the html
        $this->assertSame(2, $result['count']);
    }

    public function testSdkBuiltToolWithoutTemplatePassesThrough()
    {
        $app = new RoundTripApp();
        $container = $this->containerFor($app);

        $registry = new Registry();
        (new Builder())
            ->setContainer($container)
            ->setRegistry($registry)
            ->addTool([RoundTripApp::class, 'plainData'], 'plain_data')
            ->build();

        $decorator = new McpAppReferenceHandler(
            new ReferenceHandler($container),
            new McpAppRenderer(new Environment(new ArrayLoader([]))),
            [], // no templates registered
        );

        $result = $decorator->handle($registry->getTool('plain_data'), ['_session' => $this->createStub(SessionInterface::class)]);

        $this->assertSame(['ok' => true], $result); // untouched: no html injected
    }

    private function containerFor(RoundTripApp $app): ContainerInterface
    {
        return new class($app) implements ContainerInterface {
            public function __construct(private readonly RoundTripApp $app)
            {
            }

            public function get(string $id): RoundTripApp
            {
                if (RoundTripApp::class === $id) {
                    return $this->app;
                }

                throw new class(\sprintf('Service "%s" not found.', $id)) extends \RuntimeException implements NotFoundExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return RoundTripApp::class === $id;
            }
        };
    }
}

final class RoundTripApp
{
    /**
     * @return array<string, mixed>
     */
    public function render(string $query = ''): array
    {
        return ['query' => $query, 'count' => 2];
    }

    /**
     * @return array<string, mixed>
     */
    public function plainData(): array
    {
        return ['ok' => true];
    }
}
