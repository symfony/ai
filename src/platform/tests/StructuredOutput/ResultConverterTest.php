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
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\StructuredOutput\ResultConverter;
use Symfony\AI\Platform\StructuredOutput\Serializer;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\City;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\SomeStructure;

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
}
