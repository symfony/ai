<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\Result\BaseResult;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\Exception\CancelledResultException;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\StructuredOutput\Serializer;
use Symfony\AI\Platform\StructuredOutput\Streaming\PartialObjectStreamListener;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\City;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as SymfonyHttpResponse;

final class DeferredResultTest extends TestCase
{
    public function testItUnwrapsTheResultWhenGettingContent()
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $rawHttpResult = new RawHttpResult($httpResponse);
        $textResult = new TextResult('test content');

        $resultConverter = $this->createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())
            ->method('convert')
            ->with($rawHttpResult, [])
            ->willReturn($textResult);

        $deferredResult = new DeferredResult($resultConverter, $rawHttpResult);

        $this->assertSame('test content', $deferredResult->getResult()->getContent());
    }

    public function testItConvertsTheResponseOnlyOnce()
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $rawHttpResult = new RawHttpResult($httpResponse);
        $textResult = new TextResult('test content');

        $resultConverter = $this->createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())
            ->method('convert')
            ->with($rawHttpResult, [])
            ->willReturn($textResult);

        $deferredResult = new DeferredResult($resultConverter, $rawHttpResult);

        // Call unwrap multiple times, but the converter should only be called once
        $deferredResult->getResult();
        $deferredResult->getResult();
        $deferredResult->getResult();
    }

    public function testItGetsRawResponseDirectly()
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $resultConverter = $this->createStub(ResultConverterInterface::class);

        $deferredResult = new DeferredResult($resultConverter, new RawHttpResult($httpResponse));

        $this->assertSame($httpResponse, $deferredResult->getRawResult()->getObject());
    }

    public function testItSetsRawResponseOnUnwrappedResponseWhenNeeded()
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);

        $unwrappedResponse = $this->createResult(null);

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('convert')->willReturn($unwrappedResponse);

        $deferredResult = new DeferredResult($resultConverter, new RawHttpResult($httpResponse));
        $deferredResult->getResult();

        // The raw response in the model response is now set and not null anymore
        $this->assertSame($httpResponse, $unwrappedResponse->getRawResult()->getObject());
    }

    public function testItDoesNotSetRawResponseOnUnwrappedResponseWhenAlreadySet()
    {
        $originHttpResponse = $this->createStub(SymfonyHttpResponse::class);
        $anotherHttpResponse = $this->createStub(SymfonyHttpResponse::class);

        $unwrappedResult = $this->createResult($anotherHttpResponse);

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('convert')->willReturn($unwrappedResult);

        $deferredResult = new DeferredResult($resultConverter, new RawHttpResult($originHttpResponse));
        $deferredResult->getResult();

        // It is still the same raw response as set initially and so not overwritten
        $this->assertSame($anotherHttpResponse, $unwrappedResult->getRawResult()->getObject());
    }

    public function testItPassesOptionsToConverter()
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $rawHttpResponse = new RawHttpResult($httpResponse);
        $options = ['option1' => 'value1', 'option2' => 'value2'];

        $resultConverter = $this->createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())
            ->method('convert')
            ->with($rawHttpResponse, $options)
            ->willReturn($this->createResult(null));

        $deferredResult = new DeferredResult($resultConverter, $rawHttpResponse, $options);
        $deferredResult->getResult();
    }

    public function testItKeepsResultMetadata()
    {
        $result = new TextResult('Hello World');
        $result->getMetadata()->add('foo', 'bar');
        $converter = new PlainConverter($result);

        $deferredResult = new DeferredResult($converter, new InMemoryRawResult());
        $deferredResult->getMetadata()->add('key', 'value');

        $unwrappedResult = $deferredResult->getResult();

        $this->assertSame('bar', $unwrappedResult->getMetadata()->get('foo'));
        $this->assertSame('value', $unwrappedResult->getMetadata()->get('key'));
    }

    public function testMetadataGetsPromotedFromUnwrappedResult()
    {
        $result = new TextResult('Hello World');
        $result->getMetadata()->add('foo', 'bar');
        $converter = new PlainConverter($result);

        $deferredResult = new DeferredResult($converter, new InMemoryRawResult());
        $deferredResult->getResult();

        $this->assertSame('bar', $deferredResult->getMetadata()->get('foo'));
    }

    public function testTokenUsageGetsPromotedFromStream()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('part 1');
            yield new TextDelta('part 2');
            yield new TokenUsage(123456);
        })());

        $deferredResult = new DeferredResult(new PlainConverter($result), new InMemoryRawResult());
        $converted = $deferredResult->getResult();
        iterator_to_array($converted->getContent());

        $this->assertInstanceOf(TokenUsageInterface::class, $tokenUsage = $converted->getMetadata()->get('token_usage'));
        $this->assertSame(123456, $tokenUsage->getPromptTokens());
    }

    public function testItSavesConversionFailureAndDoesNotRetryConvert()
    {
        $rawHttpResult = new RawHttpResult($this->createStub(SymfonyHttpResponse::class));
        $exception = new RateLimitExceededException();

        $resultConverter = $this->createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())
            ->method('convert')
            ->with($rawHttpResult, [])
            ->willThrowException($exception);

        $deferredResult = new DeferredResult($resultConverter, $rawHttpResult);

        try {
            $deferredResult->getResult();
            $this->fail('Expected RateLimitExceededException on first call.');
        } catch (RateLimitExceededException $first) {
            $this->assertSame($exception, $first);
        }

        try {
            $deferredResult->getResult();
            $this->fail('Expected RateLimitExceededException on second call.');
        } catch (RateLimitExceededException $second) {
            $this->assertSame($exception, $second, 'Second call must re-throw the cached exception instance.');
        }
    }

    public function testTokenUsageGetsPromotedToDeferredResultFromStream()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('part 1');
            yield new TextDelta('part 2');
            yield new TokenUsage(123456);
        })());

        $deferredResult = new DeferredResult(new PlainConverter($result), new InMemoryRawResult());
        iterator_to_array($deferredResult->asStream());

        $this->assertInstanceOf(TokenUsageInterface::class, $tokenUsage = $deferredResult->getMetadata()->get('token_usage'));
        $this->assertSame(123456, $tokenUsage->getPromptTokens());
    }

    public function testAsPartialJsonStreamYieldsGrowingSnapshots()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('{"title": "Symfony AI", ');
            yield new TextDelta('"tags": ["php", "llm",');
            yield new TextDelta(' "agents"], "released": tru');
            yield new TextDelta('e}');
        })());

        $deferredResult = new DeferredResult(new PlainConverter($result), new InMemoryRawResult());

        $snapshots = iterator_to_array($deferredResult->asPartialJsonStream(), false);

        $this->assertSame(
            [
                ['title' => 'Symfony AI'],
                ['title' => 'Symfony AI', 'tags' => ['php', 'llm']],
                ['title' => 'Symfony AI', 'tags' => ['php', 'llm', 'agents'], 'released' => true],
            ],
            $snapshots,
        );
    }

    public function testAsPartialJsonStreamDoesNotYieldWhenPartialIsUnchanged()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('{"title": "Symfony AI"');
            // Whitespace deltas keep the recovered structure identical, so no new snapshot is emitted.
            yield new TextDelta('   ');
            yield new TextDelta(' }');
        })());

        $deferredResult = new DeferredResult(new PlainConverter($result), new InMemoryRawResult());

        $snapshots = iterator_to_array($deferredResult->asPartialJsonStream(), false);

        $this->assertSame([['title' => 'Symfony AI']], $snapshots);
    }

    public function testAsPartialJsonStreamSkipsUnrecoverableBuffers()
    {
        $result = new StreamResult((static function () {
            // None of these prefixes form a recoverable JSON value yet.
            yield new TextDelta('not-json');
            yield new TextDelta(' either');
        })());

        $deferredResult = new DeferredResult(new PlainConverter($result), new InMemoryRawResult());

        $this->assertSame([], iterator_to_array($deferredResult->asPartialJsonStream(), false));
    }

    public function testOnConvertCallbackReceivesConvertedResultOnce()
    {
        $textResult = new TextResult('test content');
        $deferredResult = new DeferredResult(new PlainConverter($textResult), new InMemoryRawResult());

        $received = [];
        $deferredResult->onConvert(static function (ResultInterface $result) use (&$received): ResultInterface {
            $received[] = $result;

            return $result;
        });

        $deferredResult->getResult();
        $deferredResult->getResult();

        $this->assertCount(1, $received);
        $this->assertSame($textResult, $received[0]);
    }

    public function testOnConvertCallbackCanReplaceConvertedResult()
    {
        $replacement = new TextResult('replaced');
        $deferredResult = new DeferredResult(new PlainConverter(new TextResult('original')), new InMemoryRawResult());

        $deferredResult->onConvert(static fn (ResultInterface $result): ResultInterface => $replacement);

        $this->assertSame($replacement, $deferredResult->getResult());
    }

    public function testOnConvertCallbacksRunInRegistrationOrderAndChain()
    {
        $deferredResult = new DeferredResult(new PlainConverter(new TextResult('original')), new InMemoryRawResult());

        $order = [];
        $received = [];
        $first = new TextResult('first');
        $second = new TextResult('second');

        $deferredResult->onConvert(static function (ResultInterface $result) use (&$order, &$received, $first): ResultInterface {
            $order[] = 'a';
            $received[] = $result;

            return $first;
        });
        $deferredResult->onConvert(static function (ResultInterface $result) use (&$order, &$received, $second): ResultInterface {
            $order[] = 'b';
            $received[] = $result;

            return $second;
        });

        $this->assertSame($second, $deferredResult->getResult());
        $this->assertSame(['a', 'b'], $order);
        // The second callback receives the replacement returned by the first.
        $this->assertSame($first, $received[1]);
    }

    public function testOnConvertCallbackExceptionDoesNotTriggerOnError()
    {
        $textResult = new TextResult('test content');
        $deferredResult = new DeferredResult(new PlainConverter($textResult), new InMemoryRawResult());

        $failure = new RuntimeException('listener failed');
        $deferredResult->onConvert(static function () use ($failure): ResultInterface {
            throw $failure;
        });

        $onErrorCalled = false;
        $deferredResult->onError(static function () use (&$onErrorCalled): void {
            $onErrorCalled = true;
        });

        try {
            $deferredResult->getResult();
            $this->fail('Expected the listener exception to propagate.');
        } catch (RuntimeException $thrown) {
            $this->assertSame($failure, $thrown);
        }

        $this->assertFalse($onErrorCalled);

        // Conversion succeeded, so the result stays accessible and the callback does not run again.
        $this->assertSame($textResult, $deferredResult->getResult());
    }

    public function testOnErrorCallbackReceivesExceptionOnce()
    {
        $rawHttpResult = new RawHttpResult($this->createStub(SymfonyHttpResponse::class));
        $exception = new RateLimitExceededException();

        $resultConverter = $this->createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())
            ->method('convert')
            ->willThrowException($exception);

        $deferredResult = new DeferredResult($resultConverter, $rawHttpResult);

        $received = [];
        $deferredResult->onError(static function (\Throwable $error) use (&$received): void {
            $received[] = $error;
        });

        for ($i = 0; $i < 2; ++$i) {
            try {
                $deferredResult->getResult();
                $this->fail('Expected RateLimitExceededException.');
            } catch (RateLimitExceededException) {
            }
        }

        $this->assertCount(1, $received);
        $this->assertSame($exception, $received[0]);
    }

    public function testAsStreamedObjectYieldsOnlyTypedObjects()
    {
        $stream = new StreamResult((static function () {
            yield new TextDelta('{"name":"Ber');
            yield new TextDelta('lin"}');
        })(), [new PartialObjectStreamListener(new Serializer(), City::class)]);

        $deferred = new DeferredResult(new PlainConverter($stream), new InMemoryRawResult());

        $partials = iterator_to_array($deferred->asStreamedObject(), false);

        $this->assertNotEmpty($partials);
        foreach ($partials as $object) {
            $this->assertInstanceOf(City::class, $object);
        }
        $this->assertSame('Berlin', $partials[array_key_last($partials)]->name);
    }

    public function testAsObjectReturnsFinalObjectAfterStreaming()
    {
        $stream = new StreamResult((static function () {
            yield new TextDelta('{"name":"Berlin","population":3500000}');
        })(), [new PartialObjectStreamListener(new Serializer(), City::class)]);

        $deferred = new DeferredResult(new PlainConverter($stream), new InMemoryRawResult());

        $city = $deferred->asObject();
        $this->assertInstanceOf(City::class, $city);
        $this->assertSame('Berlin', $city->name);
        $this->assertSame(3500000, $city->population);
    }

    public function testAsObjectIsIdempotentAfterStreaming()
    {
        $stream = new StreamResult((static function () {
            yield new TextDelta('{"name":"Berlin"}');
        })(), [new PartialObjectStreamListener(new Serializer(), City::class)]);

        $deferred = new DeferredResult(new PlainConverter($stream), new InMemoryRawResult());

        $first = $deferred->asObject();
        $second = $deferred->asObject();

        $this->assertSame($first, $second);
    }

    public function testAsStreamedObjectThenAsObjectShortCircuits()
    {
        $stream = new StreamResult((static function () {
            yield new TextDelta('{"name":"Ber');
            yield new TextDelta('lin"}');
        })(), [new PartialObjectStreamListener(new Serializer(), City::class)]);

        $deferred = new DeferredResult(new PlainConverter($stream), new InMemoryRawResult());

        iterator_to_array($deferred->asStreamedObject());

        $city = $deferred->asObject();
        $this->assertInstanceOf(City::class, $city);
        $this->assertSame('Berlin', $city->name);
    }

    public function testAsObjectFinishesStreamAfterEarlyBreak()
    {
        $stream = new StreamResult((static function () {
            yield new TextDelta('{"name":"Ber');
            yield new TextDelta('lin","population":35');
            yield new TextDelta('00000}');
        })(), [new PartialObjectStreamListener(new Serializer(), City::class)]);

        $deferred = new DeferredResult(new PlainConverter($stream), new InMemoryRawResult());

        // Stop iterating after the first snapshot, mimicking a consumer that
        // bails out early (e.g. once the object is "good enough").
        foreach ($deferred->asStreamedObject() as $partial) {
            $this->assertInstanceOf(City::class, $partial);
            break;
        }

        // asObject() must finish draining the remainder of the stream and
        // return the complete object, not throw or return a partial.
        $city = $deferred->asObject();
        $this->assertInstanceOf(City::class, $city);
        $this->assertSame('Berlin', $city->name);
        $this->assertSame(3500000, $city->population);
    }

    public function testAsObjectFinishesStreamAfterExceptionDuringIteration()
    {
        $stream = new StreamResult((static function () {
            yield new TextDelta('{"name":"Ber');
            yield new TextDelta('lin","population":35');
            yield new TextDelta('00000}');
        })(), [new PartialObjectStreamListener(new Serializer(), City::class)]);

        $deferred = new DeferredResult(new PlainConverter($stream), new InMemoryRawResult());

        try {
            foreach ($deferred->asStreamedObject() as $partial) {
                throw new \LogicException('rendering blew up');
            }
        } catch (\LogicException) {
            // Swallowed: the consumer recovers and still wants the final object.
        }

        $city = $deferred->asObject();
        $this->assertInstanceOf(City::class, $city);
        $this->assertSame('Berlin', $city->name);
        $this->assertSame(3500000, $city->population);
    }

    public function testCancelPropagatesToCancellableRawResult()
    {
        $httpResponse = $this->createMock(SymfonyHttpResponse::class);
        $httpResponse->expects($this->once())->method('cancel');

        $rawHttpResult = new RawHttpResult($httpResponse);
        $resultConverter = $this->createStub(ResultConverterInterface::class);

        $deferredResult = new DeferredResult($resultConverter, $rawHttpResult);

        $this->assertFalse($deferredResult->isCancelled());

        $deferredResult->cancel();

        $this->assertTrue($deferredResult->isCancelled());
        $this->assertTrue($rawHttpResult->isCancelled());
    }

    public function testGetResultAfterCancelThrows()
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $rawHttpResult = new RawHttpResult($httpResponse);
        $resultConverter = $this->createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->never())->method('convert');

        $deferredResult = new DeferredResult($resultConverter, $rawHttpResult);
        $deferredResult->cancel();

        $this->expectException(CancelledResultException::class);
        $deferredResult->getResult();
    }

    public function testCancelIsIdempotent()
    {
        $httpResponse = $this->createMock(SymfonyHttpResponse::class);
        $httpResponse->expects($this->once())->method('cancel');

        $rawHttpResult = new RawHttpResult($httpResponse);
        $resultConverter = $this->createStub(ResultConverterInterface::class);

        $deferredResult = new DeferredResult($resultConverter, $rawHttpResult);
        $deferredResult->cancel();
        $deferredResult->cancel();
        $deferredResult->cancel();

        $this->assertTrue($deferredResult->isCancelled());
    }

    public function testCancelPropagatesToAlreadyConvertedStreamResult()
    {
        $streamResult = new StreamResult((static function () {
            yield new TextDelta('chunk1');
            yield new TextDelta('chunk2');
        })());

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('convert')->willReturn($streamResult);

        $deferredResult = new DeferredResult($resultConverter, new InMemoryRawResult());

        $deferredResult->getResult(); // Force conversion.

        $this->assertFalse($streamResult->isCancelled());

        $deferredResult->cancel();

        $this->assertTrue($streamResult->isCancelled());
        $this->assertTrue($deferredResult->isCancelled());
    }

    /**
     * Workaround for low deps because mocking the ResponseInterface leads to an exception with
     * mock creation "Type Traversable|object|array|string|null contains both object and a class type"
     * in PHPUnit MockClass.
     */
    private function createResult(?SymfonyHttpResponse $httpResponse): ResultInterface
    {
        $rawResult = null !== $httpResponse ? new RawHttpResult($httpResponse) : null;

        return new class($rawResult) extends BaseResult {
            public function __construct(protected ?RawResultInterface $rawResult)
            {
            }

            public function getContent(): string
            {
                return 'test content';
            }
        };
    }
}
