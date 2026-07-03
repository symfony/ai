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
use Symfony\AI\Platform\Batch\BatchInput;
use Symfony\AI\Platform\Batch\BatchJob;
use Symfony\AI\Platform\Batch\BatchJobResult;
use Symfony\AI\Platform\Batch\BatchResultConverterInterface;
use Symfony\AI\Platform\Batch\BatchStatus;
use Symfony\AI\Platform\Batch\BatchSubmitClientInterface;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultConvertedEvent;
use Symfony\AI\Platform\Event\ResultErrorEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
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

    public function testSupportsWithModelObjectReturnsTrueWhenAModelClientSupportsIt()
    {
        $model = new Model('custom-model', [Capability::INPUT_MESSAGES]);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(true);

        $provider = new Provider('openai', [$modelClient], [], $this->createStub(ModelCatalogInterface::class));

        $this->assertTrue($provider->supports($model));
    }

    public function testSupportsWithModelObjectReturnsFalseWhenNoModelClientSupportsIt()
    {
        $model = new Model('custom-model', [Capability::INPUT_MESSAGES]);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(false);

        $provider = new Provider('openai', [$modelClient], [], $this->createStub(ModelCatalogInterface::class));

        $this->assertFalse($provider->supports($model));
    }

    public function testInvokeWithModelObjectSkipsCatalogResolution()
    {
        $model = new Model('custom-model', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]);
        $rawResult = $this->createStub(RawResultInterface::class);

        $catalog = $this->createMock(ModelCatalogInterface::class);
        $catalog->expects($this->never())->method('getModel');

        $modelClient = $this->createMock(ModelClientInterface::class);
        $modelClient->method('supports')->with($model)->willReturn(true);
        $modelClient->expects($this->once())
            ->method('request')
            ->with($model)
            ->willReturn($rawResult);

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('supports')->willReturn(true);
        $resultConverter->method('convert')->willReturn(new TextResult('Hello'));

        $provider = new Provider('openai', [$modelClient], [$resultConverter], $catalog);

        $result = $provider->invoke($model, 'Hello');

        $this->assertInstanceOf(DeferredResult::class, $result);
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

    public function testInvokeDispatchesResultConvertedEventOnConversion()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);
        $textResult = new TextResult('Hello');

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(true);
        $modelClient->method('request')->willReturn($this->createStub(RawResultInterface::class));

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('supports')->willReturn(true);
        $resultConverter->method('convert')->willReturn($textResult);

        $dispatchedEvents = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;

                return $event;
            });

        $provider = new Provider('openai', [$modelClient], [$resultConverter], $catalog, null, $eventDispatcher);

        $deferredResult = $provider->invoke('gpt-4o', 'Hello');

        // The converted event is only dispatched once the result is actually converted.
        $this->assertCount(2, $dispatchedEvents);

        $deferredResult->getResult();

        $this->assertCount(3, $dispatchedEvents);
        $this->assertInstanceOf(ResultConvertedEvent::class, $dispatchedEvents[2]);
        $this->assertSame($textResult, $dispatchedEvents[2]->getResult());
    }

    public function testInvokeDispatchesResultErrorEventOnConversionFailure()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);
        $exception = new RuntimeException('conversion failed');

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(true);
        $modelClient->method('request')->willReturn($this->createStub(RawResultInterface::class));

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('supports')->willReturn(true);
        $resultConverter->method('convert')->willThrowException($exception);

        $dispatchedEvents = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;

                return $event;
            });

        $provider = new Provider('openai', [$modelClient], [$resultConverter], $catalog, null, $eventDispatcher);

        $deferredResult = $provider->invoke('gpt-4o', 'Hello');

        try {
            $deferredResult->getResult();
            $this->fail('Expected RuntimeException to be thrown.');
        } catch (RuntimeException $thrown) {
            $this->assertSame($exception, $thrown);
        }

        $this->assertInstanceOf(ResultErrorEvent::class, $dispatchedEvents[2]);
        $this->assertSame($exception, $dispatchedEvents[2]->getError());
    }

    public function testGetModelCatalog()
    {
        $catalog = $this->createStub(ModelCatalogInterface::class);
        $provider = new Provider('openai', [], [], $catalog);

        $this->assertSame($catalog, $provider->getModelCatalog());
    }

    public function testInvokeWithBatchOptionRoutesToBatchSubmitClient()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);
        $job = new BatchJob('batch-1', BatchStatus::PROCESSING);

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $submitClient = $this->createMock(BatchSubmitClientInterface::class);
        $submitClient->method('supports')->willReturn(true);
        $submitClient->expects($this->once())
            ->method('submitBatch')
            ->willReturn($this->createStub(RawResultInterface::class));

        $converter = $this->createStub(BatchResultConverterInterface::class);
        $converter->method('supports')->willReturn(true);
        $converter->method('convert')->willReturn(new BatchJobResult($job));

        $provider = new Provider('openai', [], [$converter], $catalog, null, null, [$submitClient]);

        $result = $provider->invoke('gpt-4o', [new BatchInput('req-1', new MessageBag(Message::ofUser('Hi')))], ['batch' => true]);

        $this->assertSame($job, $result->asBatchJob());
    }

    public function testInvokeWithBatchOptionThrowsWhenNoBatchSubmitClient()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $provider = new Provider('openai', [], [], $catalog);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No batch submit client registered');

        $provider->invoke('gpt-4o', [new BatchInput('req-1', new MessageBag(Message::ofUser('Hi')))], ['batch' => true]);
    }

    public function testBatchConverterIsSkippedInRegularInvocation()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]);

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(true);
        $modelClient->method('request')->willReturn($this->createStub(RawResultInterface::class));

        // A batch converter that would match by supports() must never be picked for a regular call.
        $batchConverter = $this->createMock(BatchResultConverterInterface::class);
        $batchConverter->method('supports')->willReturn(true);
        $batchConverter->expects($this->never())->method('convert');

        $normalConverter = $this->createStub(ResultConverterInterface::class);
        $normalConverter->method('supports')->willReturn(true);
        $normalConverter->method('convert')->willReturn(new TextResult('Hello'));

        $provider = new Provider('openai', [$modelClient], [$batchConverter, $normalConverter], $catalog);

        $this->assertSame('Hello', $provider->invoke('gpt-4o', 'Hello')->asText());
    }
}
