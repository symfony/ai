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
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ProviderTest extends TestCase
{
    public function testGetName()
    {
        $provider = new Provider(
            'openai',
            [],
            [],
            $this->createStub(ModelCatalogInterface::class),
        );

        $this->assertSame('openai', $provider->getName());
    }

    public function testSupportsReturnsTrueWhenCatalogHasModel()
    {
        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn(new Model('gpt-4o', [Capability::INPUT_MESSAGES]));

        $provider = new Provider('openai', [], [], $catalog);

        $this->assertTrue($provider->supports('gpt-4o'));
    }

    public function testSupportsReturnsFalseWhenCatalogDoesNotHaveModel()
    {
        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willThrowException(
            new \Symfony\AI\Platform\Exception\ModelNotFoundException('Model not found'),
        );

        $provider = new Provider('openai', [], [], $catalog);

        $this->assertFalse($provider->supports('unknown-model'));
    }

    public function testInvokeResolvesModelAndDelegates()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]);
        $rawResult = $this->createStub(RawResultInterface::class);
        $textResult = new TextResult('Hello');

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(true);
        $modelClient->method('request')->willReturn($rawResult);

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('supports')->willReturn(true);
        $resultConverter->method('convert')->willReturn($textResult);

        $provider = new Provider('openai', [$modelClient], [$resultConverter], $catalog);

        $result = $provider->invoke('gpt-4o', 'Hello');

        $this->assertInstanceOf(DeferredResult::class, $result);
    }

    public function testInvokeThrowsWhenNoModelClientSupportsModel()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(false);

        $provider = new Provider('openai', [$modelClient], [], $catalog);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No ModelClient registered/');

        $provider->invoke('gpt-4o', 'Hello');
    }

    public function testInvokeThrowsWhenNoResultConverterSupportsModel()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);
        $rawResult = $this->createStub(RawResultInterface::class);

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(true);
        $modelClient->method('request')->willReturn($rawResult);

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('supports')->willReturn(false);

        $provider = new Provider('openai', [$modelClient], [$resultConverter], $catalog);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No ResultConverter registered/');

        $provider->invoke('gpt-4o', 'Hello');
    }

    public function testInvokeDispatchesEvents()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);
        $rawResult = $this->createStub(RawResultInterface::class);
        $textResult = new TextResult('Hello');

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(true);
        $modelClient->method('request')->willReturn($rawResult);

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('supports')->willReturn(true);
        $resultConverter->method('convert')->willReturn($textResult);

        $dispatchedEvents = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;

                return $event;
            });

        $provider = new Provider('openai', [$modelClient], [$resultConverter], $catalog, null, $eventDispatcher);

        $provider->invoke('gpt-4o', 'Hello');

        $this->assertCount(2, $dispatchedEvents);
        $this->assertInstanceOf(InvocationEvent::class, $dispatchedEvents[0]);
        $this->assertInstanceOf(ResultEvent::class, $dispatchedEvents[1]);
    }

    public function testGetModelCatalog()
    {
        $catalog = $this->createStub(ModelCatalogInterface::class);
        $provider = new Provider('openai', [], [], $catalog);

        $this->assertSame($catalog, $provider->getModelCatalog());
    }
}
