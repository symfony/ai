<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Test\Recording;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Test\Recording\ResultSerializer;
use Symfony\AI\Platform\TokenUsage\StreamListener;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;
use Symfony\AI\Platform\Vector\Vector;

final class ResultSerializerTest extends TestCase
{
    public function testTextRoundTrip()
    {
        $result = ResultSerializer::fromArray(ResultSerializer::toArray(new TextResult('Hello', 'sig-1')));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello', $result->getContent());
        $this->assertSame('sig-1', $result->getSignature());
    }

    public function testObjectRoundTripWithObject()
    {
        $result = ResultSerializer::fromArray(ResultSerializer::toArray(new ObjectResult((object) ['answer' => 42])));

        $this->assertInstanceOf(ObjectResult::class, $result);
        $content = $result->getContent();
        $this->assertIsObject($content);
        $this->assertSame(42, $content->answer);
    }

    public function testObjectRoundTripWithArray()
    {
        $result = ResultSerializer::fromArray(ResultSerializer::toArray(new ObjectResult(['answer' => 42])));

        $this->assertInstanceOf(ObjectResult::class, $result);
        $this->assertSame(['answer' => 42], $result->getContent());
    }

    public function testVectorRoundTrip()
    {
        $result = ResultSerializer::fromArray(ResultSerializer::toArray(new VectorResult([new Vector([0.1, 0.2, 0.3])])));

        $this->assertInstanceOf(VectorResult::class, $result);
        $this->assertSame([0.1, 0.2, 0.3], $result->getContent()[0]->getData());
    }

    public function testToolCallRoundTrip()
    {
        $result = ResultSerializer::fromArray(ResultSerializer::toArray(new ToolCallResult([
            new ToolCall('id-1', 'get_weather', ['location' => 'Paris'], 'sig-2'),
        ])));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCall = $result->getContent()[0];
        $this->assertSame('id-1', $toolCall->getId());
        $this->assertSame('get_weather', $toolCall->getName());
        $this->assertSame(['location' => 'Paris'], $toolCall->getArguments());
        $this->assertSame('sig-2', $toolCall->getSignature());
    }

    public function testTextStreamRoundTrip()
    {
        $stream = new StreamResult((static function (): \Generator {
            yield new TextDelta('Hel');
            yield new TextDelta('lo');
        })());

        $result = ResultSerializer::fromArray(ResultSerializer::toArray($stream));

        $this->assertInstanceOf(StreamResult::class, $result);

        $text = '';
        foreach ($result->getContent() as $delta) {
            $this->assertInstanceOf(TextDelta::class, $delta);
            $text .= $delta->getText();
        }

        $this->assertSame('Hello', $text);
    }

    public function testRoundTripPreservesScalarAndArrayMetadata()
    {
        $source = new TextResult('Hello');
        $source->getMetadata()->add('signature', 'sig-1');
        $source->getMetadata()->add('usage', ['input_tokens' => 3, 'seconds' => 1.5]);
        $source->getMetadata()->add('cached', false);
        $source->getMetadata()->add('unset', null);

        $metadata = ResultSerializer::fromArray(ResultSerializer::toArray($source))->getMetadata();

        $this->assertSame('sig-1', $metadata->get('signature'));
        $this->assertSame(['input_tokens' => 3, 'seconds' => 1.5], $metadata->get('usage'));
        $this->assertFalse($metadata->get('cached'));
        $this->assertTrue($metadata->has('unset'));
        $this->assertNull($metadata->get('unset'));
    }

    public function testRoundTripPreservesFinishReason()
    {
        $source = new TextResult('Hello');
        $source->getMetadata()->add('finish_reason', new FinishReason(FinishReasonCase::LENGTH, 'max_tokens'));

        $finishReason = ResultSerializer::fromArray(ResultSerializer::toArray($source))->getMetadata()->get('finish_reason');

        $this->assertInstanceOf(FinishReason::class, $finishReason);
        $this->assertTrue($finishReason->is(FinishReasonCase::LENGTH));
        $this->assertSame('max_tokens', $finishReason->getRaw());
    }

    public function testRoundTripPreservesTokenUsage()
    {
        $source = new TextResult('Hello');
        $source->getMetadata()->add('token_usage', new TokenUsage(
            promptTokens: 12,
            completionTokens: 34,
            cachedTokens: 5,
            totalTokens: 46,
            model: 'gpt-4o-2024-08-06',
        ));

        $usage = ResultSerializer::fromArray(ResultSerializer::toArray($source))->getMetadata()->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $usage);
        $this->assertSame(12, $usage->getPromptTokens());
        $this->assertSame(34, $usage->getCompletionTokens());
        $this->assertSame(5, $usage->getCachedTokens());
        $this->assertSame(46, $usage->getTotalTokens());
        $this->assertNull($usage->getThinkingTokens());
        $this->assertSame('gpt-4o-2024-08-06', $usage->getModel());
    }

    public function testRoundTripPreservesTokenUsageWithoutModel()
    {
        $source = new TextResult('Hello');
        $source->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 12, totalTokens: 12));

        $usage = ResultSerializer::fromArray(ResultSerializer::toArray($source))->getMetadata()->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $usage);
        $this->assertNull($usage->getModel());
    }

    public function testRoundTripOfCassetteRecordedBeforeTheModelFieldExisted()
    {
        // A cassette written before token usage carried a model has no "model" key at all;
        // it must still replay rather than trip the envelope type check.
        $recorded = ResultSerializer::toArray(new TextResult('Hello'));
        $recorded['metadata']['token_usage'] = [
            '#type' => 'token_usage',
            'prompt_tokens' => 12,
            'total_tokens' => 12,
        ];

        $usage = ResultSerializer::fromArray($recorded)->getMetadata()->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $usage);
        $this->assertSame(12, $usage->getPromptTokens());
        $this->assertNull($usage->getModel());
    }

    public function testRoundTripPreservesTokenUsageAggregation()
    {
        $source = new TextResult('Hello');
        $source->getMetadata()->add('token_usage', new TokenUsageAggregation([
            new TokenUsage(promptTokens: 10, totalTokens: 10),
            new TokenUsage(promptTokens: 7, totalTokens: 7),
        ]));

        $usage = ResultSerializer::fromArray(ResultSerializer::toArray($source))->getMetadata()->get('token_usage');

        $this->assertInstanceOf(TokenUsageAggregation::class, $usage);
        $this->assertSame(2, $usage->count());
        $this->assertSame(17, $usage->getPromptTokens());
        $this->assertSame(17, $usage->getTotalTokens());
    }

    public function testStreamRoundTripPreservesMetadataCollectedWhileDraining()
    {
        $stream = new StreamResult((static function (): \Generator {
            yield new TextDelta('Hel');
            yield new TokenUsage(promptTokens: 4, completionTokens: 2, totalTokens: 6);
            yield new TextDelta('lo');
        })(), [new StreamListener()]);

        $result = ResultSerializer::fromArray(ResultSerializer::toArray($stream));

        $text = '';
        foreach ($result->getContent() as $delta) {
            $text .= (string) $delta;
        }

        $usage = $result->getMetadata()->get('token_usage');

        $this->assertSame('Hello', $text);
        $this->assertInstanceOf(TokenUsage::class, $usage);
        $this->assertSame(6, $usage->getTotalTokens());
    }

    public function testResultWithoutMetadataDoesNotWriteMetadataKey()
    {
        $this->assertArrayNotHasKey('metadata', ResultSerializer::toArray(new TextResult('Hello')));
    }

    public function testUnsupportedMetadataValueThrows()
    {
        $source = new TextResult('Hello');
        $source->getMetadata()->add('raw_response', new \stdClass());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot record result metadata "raw_response" of type "stdClass"');
        ResultSerializer::toArray($source);
    }

    public function testReservedTypeKeyInMetadataArrayThrows()
    {
        $source = new TextResult('Hello');
        $source->getMetadata()->add('citations', ['#type' => 'spoofed']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved key "#type"');
        ResultSerializer::toArray($source);
    }

    public function testUnknownMetadataTypeOnFromArrayThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        ResultSerializer::fromArray([
            'type' => 'text',
            'content' => 'Hello',
            'metadata' => ['whatever' => ['#type' => 'nope']],
        ]);
    }

    public function testRoundTripPreservesNestedTokenUsageAggregation()
    {
        $source = new TextResult('Hello');
        $source->getMetadata()->add('token_usage', new TokenUsageAggregation([
            new TokenUsage(promptTokens: 10, totalTokens: 10),
            new TokenUsageAggregation([
                new TokenUsage(promptTokens: 3, totalTokens: 3),
                new TokenUsage(promptTokens: 4, totalTokens: 4),
            ]),
        ]));

        $usage = ResultSerializer::fromArray(ResultSerializer::toArray($source))->getMetadata()->get('token_usage');

        $this->assertInstanceOf(TokenUsageAggregation::class, $usage);
        $this->assertSame(3, $usage->count());
        $this->assertSame(17, $usage->getPromptTokens());
        $this->assertInstanceOf(TokenUsageAggregation::class, $usage->getTokenUsages()[1]);
    }

    public function testRoundTripPreservesObjectsNestedInsideMetadataArrays()
    {
        $source = new TextResult('Hello');
        $source->getMetadata()->add('attempts', [
            'first' => ['finish_reason' => new FinishReason(FinishReasonCase::LENGTH, 'max_tokens')],
        ]);

        $nested = ResultSerializer::fromArray(ResultSerializer::toArray($source))->getMetadata()->get('attempts');

        $this->assertInstanceOf(FinishReason::class, $nested['first']['finish_reason']);
        $this->assertTrue($nested['first']['finish_reason']->is(FinishReasonCase::LENGTH));
    }

    public function testReservedTypeKeyNestedInMetadataArrayThrows()
    {
        $source = new TextResult('Hello');
        $source->getMetadata()->add('attempts', [['#type' => 'spoofed']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved key "#type"');
        ResultSerializer::toArray($source);
    }

    public function testUnknownFinishReasonCaseOnFromArrayThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a known finish reason case');
        ResultSerializer::fromArray([
            'type' => 'text',
            'content' => 'Hello',
            'metadata' => ['finish_reason' => ['#type' => 'finish_reason', 'case' => 'no_such_case', 'raw' => 'z']],
        ]);
    }

    public function testNonArrayMetadataOnFromArrayThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expected an array');
        ResultSerializer::fromArray(['type' => 'text', 'content' => 'Hello', 'metadata' => 'not-an-array']);
    }

    public function testNullMetadataOnFromArrayThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expected an array');
        ResultSerializer::fromArray(['type' => 'text', 'content' => 'Hello', 'metadata' => null]);
    }

    public function testAbsentMetadataKeyOnFromArrayIsAccepted()
    {
        $result = ResultSerializer::fromArray(['type' => 'text', 'content' => 'Hello']);

        $this->assertSame('Hello', $result->getContent());
        $this->assertCount(0, $result->getMetadata());
    }

    public function testAggregatedUsageThatIsNotATokenUsageThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a token usage');
        ResultSerializer::fromArray([
            'type' => 'text',
            'content' => 'Hello',
            'metadata' => ['token_usage' => ['#type' => 'token_usage_aggregation', 'usages' => ['nonsense']]],
        ]);
    }

    public function testAggregationWithoutUsagesListThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('field "usages" must be a list');
        ResultSerializer::fromArray([
            'type' => 'text',
            'content' => 'Hello',
            'metadata' => ['token_usage' => ['#type' => 'token_usage_aggregation']],
        ]);
    }

    public function testNonListAggregatedUsagesThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('field "usages" must be a list');
        ResultSerializer::fromArray([
            'type' => 'text',
            'content' => 'Hello',
            'metadata' => ['token_usage' => ['#type' => 'token_usage_aggregation', 'usages' => 'oops']],
        ]);
    }

    public function testNonStringFinishReasonFieldThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('field "raw" must be a string');
        ResultSerializer::fromArray([
            'type' => 'text',
            'content' => 'Hello',
            'metadata' => ['finish_reason' => ['#type' => 'finish_reason', 'case' => 'stop', 'raw' => ['nope']]],
        ]);
    }

    public function testNonIntegerTokenUsageFieldThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('field "prompt_tokens" must be an integer or null');
        ResultSerializer::fromArray([
            'type' => 'text',
            'content' => 'Hello',
            'metadata' => ['token_usage' => ['#type' => 'token_usage', 'prompt_tokens' => '12abc']],
        ]);
    }

    /**
     * The other round-trip tests hand the array straight back to fromArray(). A cassette is JSON on
     * disk, so at least one case has to survive a real encode/decode: that is where a float with no
     * fractional part used to come back as an int.
     */
    public function testRoundTripThroughRealJsonPreservesScalarTypes()
    {
        $source = new TextResult('Hello');
        $source->getMetadata()->add('usage', ['seconds' => 2.0, 'ratio' => 1.5, 'score' => 0.0, 'count' => 3]);
        $source->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 12, totalTokens: 12));

        $encoded = json_encode(ResultSerializer::toArray($source), \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR);
        $metadata = ResultSerializer::fromArray(json_decode((string) $encoded, true, flags: \JSON_THROW_ON_ERROR))->getMetadata();

        $this->assertSame(['seconds' => 2.0, 'ratio' => 1.5, 'score' => 0.0, 'count' => 3], $metadata->get('usage'));
        $this->assertInstanceOf(TokenUsage::class, $metadata->get('token_usage'));
        $this->assertSame(12, $metadata->get('token_usage')->getTotalTokens());
    }

    public function testUnsupportedResultTypeThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        ResultSerializer::toArray(new BinaryResult('binary-data'));
    }

    public function testUnsupportedStreamDeltaThrows()
    {
        $stream = new StreamResult((static function (): \Generator {
            yield new ThinkingDelta('reasoning');
        })());

        $this->expectException(InvalidArgumentException::class);
        ResultSerializer::toArray($stream);
    }

    public function testUnknownTypeOnFromArrayThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        ResultSerializer::fromArray(['type' => 'unknown']);
    }
}
