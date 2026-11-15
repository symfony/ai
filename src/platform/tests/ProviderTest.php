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
use Symfony\AI\Platform\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Decision\Question\QuestionInterface;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultConvertedEvent;
use Symfony\AI\Platform\Event\ResultErrorEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Exception\MissingModelSupportException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
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
            new ModelNotFoundException('Model not found'),
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

    public function testInvokeReusesTheModelThatSupportsResolvedForANameWithOptions()
    {
        $catalog = $this->createMock(ModelCatalogInterface::class);
        $catalog->expects($this->once())->method('getModel')->with('qwen3:8b?think=false')->willReturn(new Model('qwen3:8b', [Capability::INPUT_MESSAGES], ['think' => false]));

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(true);
        $modelClient->method('request')->willReturn($this->createStub(RawResultInterface::class));

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('supports')->willReturn(true);
        $resultConverter->method('convert')->willReturn(new TextResult('Hello'));

        $provider = new Provider('ollama', [$modelClient], [$resultConverter], $catalog);

        $this->assertTrue($provider->supports('qwen3:8b?think=false'));
        $provider->invoke('qwen3:8b?think=false', 'Hello');
    }

    public function testInvokeNormalizesTheQuestionsThroughTheContract()
    {
        $model = new Model('jev-latest', [Capability::DECISION]);

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $modelClient = $this->createMock(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(true);
        $modelClient->expects($this->once())->method('request')
            ->with($model, 'My invoice is wrong.', ['questions' => ['is_bug' => ['type' => 'noul']]])
            ->willReturn($this->createStub(RawResultInterface::class));

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('supports')->willReturn(true);
        $resultConverter->method('convert')->willReturn(new TextResult('Hello'));

        // the bare Contract has no question normalizer, so JsonSerializableNormalizer serializes it
        $question = new class implements QuestionInterface, \JsonSerializable {
            public function getInstructions(): string
            {
                return 'Is this a software defect?';
            }

            /**
             * @return array{type: string}
             */
            public function jsonSerialize(): array
            {
                return ['type' => 'noul'];
            }
        };

        $provider = new Provider('typesafe', [$modelClient], [$resultConverter], $catalog);
        $provider->invoke('jev-latest', 'My invoice is wrong.', ['questions' => ['is_bug' => $question]]);
    }

    public function testInvokeThrowsForQuestionsToAModelWithoutDecisions()
    {
        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn(new Model('gpt-4o', [Capability::INPUT_MESSAGES]));

        $modelClient = $this->createMock(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(true);
        $modelClient->expects($this->never())->method('request');

        $provider = new Provider('openai', [$modelClient], [], $catalog);

        $this->expectException(MissingModelSupportException::class);
        $this->expectExceptionMessage('Model "gpt-4o" (Symfony\AI\Platform\Model) does not support "decision".');

        $provider->invoke('gpt-4o', new Collection(new Text('Hello'), new Image('image', 'image/png')), ['questions' => [
            'is_urgent' => new NoulQuestion('Is this urgent?'),
        ]]);
    }

    public function testInvokeThrowsWhenNoModelClientSupportsModel()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(false);

        $provider = new Provider('openai', [$modelClient], [], $catalog);

        $this->expectException(ModelNotFoundException::class);
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
        $this->assertSame($provider, $dispatchedEvents[0]->getProvider());
        $this->assertInstanceOf(ResultEvent::class, $dispatchedEvents[1]);
        $this->assertSame($provider, $dispatchedEvents[1]->getProvider());
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
        $this->assertSame($provider, $dispatchedEvents[2]->getProvider());
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
        $this->assertSame($provider, $dispatchedEvents[2]->getProvider());
    }

    public function testGetModelCatalog()
    {
        $catalog = $this->createStub(ModelCatalogInterface::class);
        $provider = new Provider('openai', [], [], $catalog);

        $this->assertSame($catalog, $provider->getModelCatalog());
    }
}
