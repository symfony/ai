<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Router;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Router\ChainRouter;
use Symfony\AI\Agent\Router\Result\RoutingResult;
use Symfony\AI\Agent\Router\RouterContext;
use Symfony\AI\Agent\Router\RouterInterface;
use Symfony\AI\Agent\Router\SimpleRouter;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

final class ChainRouterTest extends TestCase
{
    public function testReturnsFirstMatchingResult(): void
    {
        $router1 = new SimpleRouter(fn () => null);
        $router2 = new SimpleRouter(fn () => new RoutingResult('gpt-4-vision'));
        $router3 = new SimpleRouter(fn () => new RoutingResult('gpt-4'));

        $chainRouter = new ChainRouter([$router1, $router2, $router3]);

        $platform = $this->createMock(PlatformInterface::class);
        $context = new RouterContext($platform);

        $input = new Input(
            'gpt-4',
            new MessageBag(Message::ofUser('test')),
            []
        );

        $result = $chainRouter->route($input, $context);
        $this->assertInstanceOf(RoutingResult::class, $result);
        $this->assertSame('gpt-4-vision', $result->getModelName());
    }

    public function testReturnsNullWhenNoRouterMatches(): void
    {
        $router1 = new SimpleRouter(fn () => null);
        $router2 = new SimpleRouter(fn () => null);

        $chainRouter = new ChainRouter([$router1, $router2]);

        $platform = $this->createMock(PlatformInterface::class);
        $context = new RouterContext($platform);

        $input = new Input(
            'gpt-4',
            new MessageBag(Message::ofUser('test')),
            []
        );

        $result = $chainRouter->route($input, $context);
        $this->assertNull($result);
    }

    public function testSkipsNullReturningRouters(): void
    {
        $router1 = new SimpleRouter(fn () => null);
        $router2 = new SimpleRouter(fn () => null);
        $router3 = new SimpleRouter(fn () => new RoutingResult('gpt-4'));

        $chainRouter = new ChainRouter([$router1, $router2, $router3]);

        $platform = $this->createMock(PlatformInterface::class);
        $context = new RouterContext($platform);

        $input = new Input(
            'gpt-4',
            new MessageBag(Message::ofUser('test')),
            []
        );

        $result = $chainRouter->route($input, $context);
        $this->assertInstanceOf(RoutingResult::class, $result);
        $this->assertSame('gpt-4', $result->getModelName());
    }

    public function testWorksWithDifferentRouterImplementations(): void
    {
        $mockRouter = $this->createMock(RouterInterface::class);
        $mockRouter->expects($this->once())
            ->method('route')
            ->willReturn(new RoutingResult('custom-model'));

        $chainRouter = new ChainRouter([$mockRouter]);

        $platform = $this->createMock(PlatformInterface::class);
        $context = new RouterContext($platform);

        $input = new Input(
            'gpt-4',
            new MessageBag(Message::ofUser('test')),
            []
        );

        $result = $chainRouter->route($input, $context);
        $this->assertInstanceOf(RoutingResult::class, $result);
        $this->assertSame('custom-model', $result->getModelName());
    }

    public function testAcceptsIterableOfRouters(): void
    {
        $routers = new \ArrayObject([
            new SimpleRouter(fn () => null),
            new SimpleRouter(fn () => new RoutingResult('gpt-4-vision')),
        ]);

        $chainRouter = new ChainRouter($routers);

        $platform = $this->createMock(PlatformInterface::class);
        $context = new RouterContext($platform);

        $input = new Input(
            'gpt-4',
            new MessageBag(Message::ofUser('test')),
            []
        );

        $result = $chainRouter->route($input, $context);
        $this->assertInstanceOf(RoutingResult::class, $result);
        $this->assertSame('gpt-4-vision', $result->getModelName());
    }
}
