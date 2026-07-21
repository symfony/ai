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
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\CompositeModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\RoutingDecision;
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
        $deferredResult = $this->createDeferredResult();

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('invoke')
            ->with('gpt-4o', 'Hello', [])
            ->willReturn($deferredResult);

        $router = $this->createStub(ModelRouterInterface::class);
        $router->method('resolve')->willReturn(new RoutingDecision($provider));

        $platform = new Platform([$provider], $router);

        $result = $platform->invoke('gpt-4o', 'Hello');

        $this->assertSame($deferredResult, $result);
    }

    public function testRouterCanReplaceRequestedModel()
    {
        $deferredResult = $this->createDeferredResult();

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('invoke')
            ->with('gpt-4o', 'Hello', [])
            ->willReturn($deferredResult);

        $router = $this->createStub(ModelRouterInterface::class);
        $router->method('resolve')->willReturn(new RoutingDecision($provider, 'gpt-4o', reason: 'image detected'));

        $platform = new Platform([$provider], $router);

        $platform->invoke('gpt-4o-mini', 'Hello');
    }

    public function testRouterCanReplaceRequestedModelWithModelInstance()
    {
        $deferredResult = $this->createDeferredResult();
        $model = new Model('gpt-4o', [], ['temperature' => 0.2]);

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('invoke')
            ->with($model, 'Hello', [])
            ->willReturn($deferredResult);

        $router = $this->createStub(ModelRouterInterface::class);
        $router->method('resolve')->willReturn(new RoutingDecision($provider, $model));

        $platform = new Platform([$provider], $router);

        $platform->invoke('gpt-4o-mini', 'Hello');
    }

    public function testRouterCanReplaceRequestedOptions()
    {
        $deferredResult = $this->createDeferredResult();

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('invoke')
            ->with('gpt-4o-mini', 'Hello', ['max_tokens' => 100])
            ->willReturn($deferredResult);

        $router = $this->createStub(ModelRouterInterface::class);
        $router->method('resolve')->willReturn(new RoutingDecision($provider, options: ['max_tokens' => 100]));

        $platform = new Platform([$provider], $router);

        $platform->invoke('gpt-4o-mini', 'Hello', ['max_output_tokens' => 100]);
    }

    public function testRouterWithoutOptionsKeepsRequestedOptions()
    {
        $deferredResult = $this->createDeferredResult();

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('invoke')
            ->with('gpt-4o-mini', 'Hello', ['temperature' => 0.7])
            ->willReturn($deferredResult);

        $router = $this->createStub(ModelRouterInterface::class);
        $router->method('resolve')->willReturn(new RoutingDecision($provider));

        $platform = new Platform([$provider], $router);

        $platform->invoke('gpt-4o-mini', 'Hello', ['temperature' => 0.7]);
    }

    public function testInvokeDispatchesModelRoutingEvent()
    {
        $deferredResult = $this->createDeferredResult();

        $provider = $this->createStub(ProviderInterface::class);
        $provider->method('invoke')->willReturn($deferredResult);

        $router = $this->createStub(ModelRouterInterface::class);
        $router->method('resolve')->willReturn(new RoutingDecision($provider));

        $dispatchedEvent = null;
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$dispatchedEvent) {
                $dispatchedEvent = $event;

                return $event;
            });

        $platform = new Platform([$provider], $router, $eventDispatcher);

        $platform->invoke('gpt-4o', 'Hello', ['temperature' => 0.7]);

        $this->assertInstanceOf(ModelRoutingEvent::class, $dispatchedEvent);
        $this->assertSame('gpt-4o', $dispatchedEvent->getModel());
        $this->assertSame('Hello', $dispatchedEvent->getInput());
        $this->assertSame(['temperature' => 0.7], $dispatchedEvent->getOptions());
    }

    public function testModelRoutingEventCanModifyModel()
    {
        $deferredResult = $this->createDeferredResult();

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('invoke')
            ->with('claude-3-5-sonnet', 'Hello', [])
            ->willReturn($deferredResult);

        $router = $this->createMock(ModelRouterInterface::class);
        $router->expects($this->once())
            ->method('resolve')
            ->with('claude-3-5-sonnet', $this->anything(), $this->anything(), $this->anything())
            ->willReturn(new RoutingDecision($provider));

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function (ModelRoutingEvent $event) {
            $event->setModel('claude-3-5-sonnet');

            return $event;
        });

        $platform = new Platform([$provider], $router, $eventDispatcher);

        $platform->invoke('gpt-4o', 'Hello');
    }

    public function testModelRoutingEventProviderSkipsRouter()
    {
        $deferredResult = $this->createDeferredResult();

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

        $platform = new Platform([$defaultProvider], $router, $eventDispatcher);

        $result = $platform->invoke('gpt-4o', 'Hello');

        $this->assertSame($deferredResult, $result);
    }

    public function testInvokeWithModelObjectRoutesViaSupports()
    {
        $model = new Model('custom-model', []);
        $deferredResult = $this->createDeferredResult();

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('supports')
            ->with($model)
            ->willReturn(true);
        $provider->expects($this->once())
            ->method('invoke')
            ->with($model, 'Hello', [])
            ->willReturn($deferredResult);

        $platform = new Platform([$provider]);

        $result = $platform->invoke($model, 'Hello');

        $this->assertSame($deferredResult, $result);
    }

    public function testInvokeWithModelObjectPicksFirstSupportingProvider()
    {
        $model = new Model('custom-model', []);
        $deferredResult = $this->createDeferredResult();

        $firstProvider = $this->createMock(ProviderInterface::class);
        $firstProvider->method('supports')->with($model)->willReturn(false);
        $firstProvider->expects($this->never())->method('invoke');

        $secondProvider = $this->createMock(ProviderInterface::class);
        $secondProvider->method('supports')->with($model)->willReturn(true);
        $secondProvider->expects($this->once())
            ->method('invoke')
            ->with($model, 'Hello', [])
            ->willReturn($deferredResult);

        $platform = new Platform([$firstProvider, $secondProvider]);

        $result = $platform->invoke($model, 'Hello');

        $this->assertSame($deferredResult, $result);
    }

    public function testInvokeWithModelObjectThrowsWhenNoProviderSupports()
    {
        $model = new Model('custom-model', []);

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->with($model)->willReturn(false);
        $provider->expects($this->never())->method('invoke');

        $platform = new Platform([$provider]);

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('No provider found for model "custom-model"');

        $platform->invoke($model, 'Hello');
    }

    public function testInvokeWithModelObjectDispatchesModelRoutingEvent()
    {
        $model = new Model('custom-model', []);
        $deferredResult = $this->createDeferredResult();

        $provider = $this->createStub(ProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('invoke')->willReturn($deferredResult);

        $dispatchedEvent = null;
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$dispatchedEvent) {
                $dispatchedEvent = $event;

                return $event;
            });

        $platform = new Platform([$provider], eventDispatcher: $eventDispatcher);

        $platform->invoke($model, 'Hello');

        $this->assertInstanceOf(ModelRoutingEvent::class, $dispatchedEvent);
        $this->assertSame($model, $dispatchedEvent->getModel());
    }

    public function testGetModelCatalogBuildsComposite()
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

    private function createDeferredResult(): DeferredResult
    {
        return new DeferredResult(new PlainConverter(new TextResult('Hello')), $this->createStub(RawResultInterface::class));
    }
}
