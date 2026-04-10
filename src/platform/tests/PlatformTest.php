<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Event\ModelRoutingEvent;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\ModelCatalog\CompositeModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class PlatformTest extends TestCase
{
    public function testConstructorThrowsWithNoProviders()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one provider');

        new Platform([]);
    }

    public function testInvokeRoutesToCorrectProvider()
    {
        $deferredResult = new DeferredResult(new PlainConverter(new TextResult('Hello')), $this->createStub(RawResultInterface::class));

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('invoke')
            ->with('gpt-4o', 'Hello', [])
            ->willReturn($deferredResult);

        $router = $this->createStub(ModelRouterInterface::class);
        $router->method('resolve')->willReturn($provider);

        $platform = new Platform([$provider], $router);

        $result = $platform->invoke('gpt-4o', 'Hello');

        $this->assertSame($deferredResult, $result);
    }

    public function testInvokeDispatchesModelRoutingEvent()
    {
        $deferredResult = new DeferredResult(new PlainConverter(new TextResult('Hello')), $this->createStub(RawResultInterface::class));

        $provider = $this->createStub(ProviderInterface::class);
        $provider->method('invoke')->willReturn($deferredResult);

        $router = $this->createStub(ModelRouterInterface::class);
        $router->method('resolve')->willReturn($provider);

        $dispatchedEvent = null;
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$dispatchedEvent) {
                $dispatchedEvent = $event;

                return $event;
            });

        $platform = new Platform([$provider], $router, null, $eventDispatcher);

        $platform->invoke('gpt-4o', 'Hello', ['temperature' => 0.7]);

        $this->assertInstanceOf(ModelRoutingEvent::class, $dispatchedEvent);
        $this->assertSame('gpt-4o', $dispatchedEvent->getModel());
        $this->assertSame('Hello', $dispatchedEvent->getInput());
        $this->assertSame(['temperature' => 0.7], $dispatchedEvent->getOptions());
    }

    public function testModelRoutingEventCanModifyModel()
    {
        $deferredResult = new DeferredResult(new PlainConverter(new TextResult('Hello')), $this->createStub(RawResultInterface::class));

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('invoke')
            ->with('claude-3-5-sonnet', 'Hello', [])
            ->willReturn($deferredResult);

        $router = $this->createMock(ModelRouterInterface::class);
        $router->expects($this->once())
            ->method('resolve')
            ->with('claude-3-5-sonnet', $this->anything(), $this->anything(), $this->anything())
            ->willReturn($provider);

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function (ModelRoutingEvent $event) {
            $event->setModel('claude-3-5-sonnet');

            return $event;
        });

        $platform = new Platform([$provider], $router, null, $eventDispatcher);

        $platform->invoke('gpt-4o', 'Hello');
    }

    public function testModelRoutingEventProviderSkipsRouter()
    {
        $deferredResult = new DeferredResult(new PlainConverter(new TextResult('Hello')), $this->createStub(RawResultInterface::class));

        $overrideProvider = $this->createMock(ProviderInterface::class);
        $overrideProvider->expects($this->once())
            ->method('invoke')
            ->with('gpt-4o', 'Hello', [])
            ->willReturn($deferredResult);

        $router = $this->createMock(ModelRouterInterface::class);
        $router->expects($this->never())->method('resolve');

        $defaultProvider = $this->createStub(ProviderInterface::class);

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function (ModelRoutingEvent $event) use ($overrideProvider) {
            $event->setProvider($overrideProvider);

            return $event;
        });

        $platform = new Platform([$defaultProvider], $router, null, $eventDispatcher);

        $result = $platform->invoke('gpt-4o', 'Hello');

        $this->assertSame($deferredResult, $result);
    }

    public function testGetModelCatalogReturnsProvidedCatalog()
    {
        $catalog = $this->createStub(ModelCatalogInterface::class);
        $provider = $this->createStub(ProviderInterface::class);

        $platform = new Platform([$provider], modelCatalog: $catalog);

        $this->assertSame($catalog, $platform->getModelCatalog());
    }

    public function testGetModelCatalogBuildsCompositeWhenNotProvided()
    {
        $catalog1 = $this->createStub(ModelCatalogInterface::class);
        $catalog2 = $this->createStub(ModelCatalogInterface::class);

        $provider1 = $this->createStub(ProviderInterface::class);
        $provider1->method('getModelCatalog')->willReturn($catalog1);

        $provider2 = $this->createStub(ProviderInterface::class);
        $provider2->method('getModelCatalog')->willReturn($catalog2);

        $platform = new Platform([$provider1, $provider2]);

        $this->assertInstanceOf(CompositeModelCatalog::class, $platform->getModelCatalog());
    }
}
