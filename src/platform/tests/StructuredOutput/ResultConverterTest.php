<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\StructuredOutput;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\StructuredOutput\ResultConverter;
use Symfony\AI\Platform\StructuredOutput\Serializer;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\City;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\SomeStructure;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithAccessors;

final class ResultConverterTest extends TestCase
{
    public function testConvertWithoutOutputType()
    {
        $innerConverter = new PlainConverter(new TextResult('{"key": "value"}'));
        $converter = new ResultConverter($innerConverter, new Serializer());

        $result = $converter->convert(new InMemoryRawResult());

        $this->assertInstanceOf(ObjectResult::class, $result);
        $this->assertIsArray($result->getContent());
        $this->assertSame(['key' => 'value'], $result->getContent());
    }

    public function testConvertWithOutputType()
    {
        $innerConverter = new PlainConverter(new TextResult('{"some": "data"}'));
        $converter = new ResultConverter($innerConverter, new Serializer(), SomeStructure::class);

        $result = $converter->convert(new InMemoryRawResult());

        $this->assertInstanceOf(ObjectResult::class, $result);
        $this->assertInstanceOf(SomeStructure::class, $result->getContent());
        $this->assertSame('data', $result->getContent()->some);
    }

    public function testConvertWithObjectToPopulate()
    {
        $city = new City(name: 'Berlin');
        $innerConverter = new PlainConverter(new TextResult('{"name": "Berlin", "population": 3500000, "country": "Germany", "mayor": "Kai Wegner"}'));
        $converter = new ResultConverter($innerConverter, new Serializer(), City::class, $city);

        $result = $converter->convert(new InMemoryRawResult());

        $this->assertInstanceOf(ObjectResult::class, $result);
        $populatedCity = $result->getContent();

        $this->assertSame($city, $populatedCity);
        $this->assertSame('Berlin', $populatedCity->name);
        $this->assertSame(3500000, $populatedCity->population);
        $this->assertSame('Germany', $populatedCity->country);
        $this->assertSame('Kai Wegner', $populatedCity->mayor);
    }

    public function testConvertWithObjectToPopulatePreservesExistingValues()
    {
        $city = new City(name: 'Paris', country: 'France');
        $innerConverter = new PlainConverter(new TextResult('{"population": 2161000, "mayor": "Anne Hidalgo"}'));
        $converter = new ResultConverter($innerConverter, new Serializer(), City::class, $city);

        $result = $converter->convert(new InMemoryRawResult());

        $populatedCity = $result->getContent();

        $this->assertInstanceOf(City::class, $populatedCity);
        $this->assertSame($city, $populatedCity);
        $this->assertSame('Paris', $populatedCity->name);
        $this->assertSame('France', $populatedCity->country);

        $this->assertSame(2161000, $populatedCity->population);
        $this->assertSame('Anne Hidalgo', $populatedCity->mayor);
    }

    public function testConvertWithNullObjectToPopulateCreatesNewInstance()
    {
        $innerConverter = new PlainConverter(new TextResult('{"name": "Tokyo", "population": 13960000}'));
        $converter = new ResultConverter($innerConverter, new Serializer(), City::class, null);

        $result = $converter->convert(new InMemoryRawResult());

        $city = $result->getContent();

        $this->assertInstanceOf(City::class, $city);
        $this->assertSame('Tokyo', $city->name);
        $this->assertSame(13960000, $city->population);
    }

    public function testConvertSupportsAllModels()
    {
        $innerConverter = new PlainConverter(new TextResult('{}'));
        $converter = new ResultConverter($innerConverter, new Serializer());

        $this->assertTrue($converter->supports(new Model('any-model')));
        $this->assertTrue($converter->supports(new Model('gpt-4')));
    }

    public function testConvertReturnsNonTextResultUnchanged()
    {
        $objectResult = new ObjectResult(['data' => 'test']);
        $innerConverter = new PlainConverter($objectResult);
        $converter = new ResultConverter($innerConverter, new Serializer(), City::class);

        $result = $converter->convert(new InMemoryRawResult());

        $this->assertSame($objectResult, $result);
    }

    public function testConvertPreservesMetadataFromInnerResult()
    {
        $textResult = new TextResult('{"some": "data"}');
        $textResult->getMetadata()->add('test_key', 'test_value');

        $innerConverter = new PlainConverter($textResult);
        $converter = new ResultConverter($innerConverter, new Serializer(), SomeStructure::class);

        $result = $converter->convert(new InMemoryRawResult());

        $this->assertTrue($result->getMetadata()->has('test_key'));
        $this->assertSame('test_value', $result->getMetadata()->get('test_key'));
    }

    public function testConvertPreservesTextResultSignatureInMetadata()
    {
        $textResult = new TextResult('{"some": "data"}', 'sig_replay_token');

        $innerConverter = new PlainConverter($textResult);
        $converter = new ResultConverter($innerConverter, new Serializer(), SomeStructure::class);

        $result = $converter->convert(new InMemoryRawResult());

        $this->assertTrue($result->getMetadata()->has('signature'));
        $this->assertSame('sig_replay_token', $result->getMetadata()->get('signature'));
    }

    public function testConvertSetsRawResultOnObjectResult()
    {
        $innerConverter = new PlainConverter(new TextResult('{"some": "data"}'));
        $converter = new ResultConverter($innerConverter, new Serializer(), SomeStructure::class);

        $rawResult = new InMemoryRawResult();
        $result = $converter->convert($rawResult);

        $this->assertSame($rawResult, $result->getRawResult());
    }

    public function testGetTokenUsageExtractorDelegatesToInnerConverter()
    {
        $innerConverter = new PlainConverter(new TextResult('{}'));
        $converter = new ResultConverter($innerConverter, new Serializer());

        $extractor = $converter->getTokenUsageExtractor();

        $this->assertNull($extractor);
    }

    public function testConvertWithAccessors()
    {
        $innerConverter = new PlainConverter(new TextResult('{"age": 10}'));
        $converter = new ResultConverter($innerConverter, new Serializer(), UserWithAccessors::class);

        $result = $converter->convert(new InMemoryRawResult());

        $this->assertInstanceOf(ObjectResult::class, $result);
        $this->assertInstanceOf(UserWithAccessors::class, $result->getContent());
        $this->assertSame(10, $result->getContent()->getAge());
    }

    public function testConvertMultiPartResultWithReasoningAndMessage()
    {
        $reasoning = new ThinkingResult('Thinking step by step…');
        $textResult = new TextResult('{"some": "data"}');
        $multiPart = new MultiPartResult([$reasoning, $textResult]);

        $innerConverter = new PlainConverter($multiPart);
        $converter = new ResultConverter($innerConverter, new Serializer(), SomeStructure::class);

        $result = $converter->convert(new InMemoryRawResult());

        $this->assertInstanceOf(MultiPartResult::class, $result);

        $parts = $result->getContent();
        $this->assertCount(2, $parts);
        $this->assertSame($reasoning, $parts[0]);
        $this->assertInstanceOf(ObjectResult::class, $parts[1]);
        $this->assertInstanceOf(SomeStructure::class, $parts[1]->getContent());
        $this->assertSame('data', $parts[1]->getContent()->some);
    }

    public function testConvertMultiPartResultWithoutTextResultIsUntouched()
    {
        $reasoning = new ThinkingResult('Just thinking, no answer.');
        $multiPart = new MultiPartResult([$reasoning, $reasoning]);

        $innerConverter = new PlainConverter($multiPart);
        $converter = new ResultConverter($innerConverter, new Serializer(), SomeStructure::class);

        $result = $converter->convert(new InMemoryRawResult());

        $this->assertSame($multiPart, $result);
    }

    public function testConvertChoiceResultOfTextResults()
    {
        $choices = new ChoiceResult([
            new TextResult('{"some": "first"}'),
            new TextResult('{"some": "second"}'),
        ]);

        $innerConverter = new PlainConverter($choices);
        $converter = new ResultConverter($innerConverter, new Serializer(), SomeStructure::class);

        $result = $converter->convert(new InMemoryRawResult());

        $this->assertInstanceOf(ChoiceResult::class, $result);

        $entries = $result->getContent();
        $this->assertCount(2, $entries);
        $this->assertInstanceOf(ObjectResult::class, $entries[0]);
        $this->assertInstanceOf(ObjectResult::class, $entries[1]);

        $first = $entries[0]->getContent();
        $second = $entries[1]->getContent();
        $this->assertInstanceOf(SomeStructure::class, $first);
        $this->assertInstanceOf(SomeStructure::class, $second);
        $this->assertSame('first', $first->some);
        $this->assertSame('second', $second->some);
    }

    public function testConvertChoiceResultOfMultiPartResultsWithReasoning()
    {
        $reasoningA = new ThinkingResult('Thinking about choice A.');
        $reasoningB = new ThinkingResult('Thinking about choice B.');

        $choices = new ChoiceResult([
            new MultiPartResult([$reasoningA, new TextResult('{"some": "a"}')]),
            new MultiPartResult([$reasoningB, new TextResult('{"some": "b"}')]),
        ]);

        $innerConverter = new PlainConverter($choices);
        $converter = new ResultConverter($innerConverter, new Serializer(), SomeStructure::class);

        $result = $converter->convert(new InMemoryRawResult());

        $this->assertInstanceOf(ChoiceResult::class, $result);

        $entries = $result->getContent();
        $this->assertCount(2, $entries);

        $this->assertInstanceOf(MultiPartResult::class, $entries[0]);
        $partsA = $entries[0]->getContent();
        $this->assertSame($reasoningA, $partsA[0]);
        $this->assertInstanceOf(ObjectResult::class, $partsA[1]);
        $contentA = $partsA[1]->getContent();
        $this->assertInstanceOf(SomeStructure::class, $contentA);
        $this->assertSame('a', $contentA->some);

        $this->assertInstanceOf(MultiPartResult::class, $entries[1]);
        $partsB = $entries[1]->getContent();
        $this->assertSame($reasoningB, $partsB[0]);
        $this->assertInstanceOf(ObjectResult::class, $partsB[1]);
        $contentB = $partsB[1]->getContent();
        $this->assertInstanceOf(SomeStructure::class, $contentB);
        $this->assertSame('b', $contentB->some);
    }

    public function testConvertChoiceResultWithoutAnyConvertibleEntryIsUntouched()
    {
        $reasoningOnly = new MultiPartResult([new ThinkingResult('No answer here.')]);
        $objectChoice = new ObjectResult(['already' => 'converted']);

        $choices = new ChoiceResult([$reasoningOnly, $objectChoice]);

        $innerConverter = new PlainConverter($choices);
        $converter = new ResultConverter($innerConverter, new Serializer(), SomeStructure::class);

        $result = $converter->convert(new InMemoryRawResult());

        $this->assertSame($choices, $result);
    }
}
