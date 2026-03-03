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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Exception\MissingModelSupportException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\City;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\MathReasoning;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\MathReasoningWithAttributes;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\ListItemAge;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\ListItemName;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\ListOfPolymorphicTypesDto;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\SomeStructure;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Step;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UnionType\HumanReadableTimeUnion;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UnionType\UnionTypeDto;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UnionType\UnixTimestampUnion;

final class PlatformSubscriberTest extends TestCase
{
    public function testProcessInputWithOutputStructure()
    {
        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory(['some' => 'format']));
        $event = new InvocationEvent(new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]), new MessageBag(), [
            'response_format' => SomeStructure::class,
        ]);

        $processor->processInput($event);

        $this->assertSame(['response_format' => ['some' => 'format']], $event->getOptions());
    }

    public function testProcessInputWithoutOutputStructure()
    {
        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory());
        $event = new InvocationEvent(new Model('gpt-4'), new MessageBag());

        $processor->processInput($event);

        $this->assertSame([], $event->getOptions());
    }

    public function testWithResponseFormatForNonLlmModel()
    {
        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory());

        $model = new Model('dalle-2');
        $event = new InvocationEvent($model, 'some input text', ['response_format' => 'url']);

        $processor->processInput($event);

        $this->assertSame(['response_format' => 'url'], $event->getOptions());
    }

    public function testProcessInputThrowsExceptionWhenLlmDoesNotSupportStructuredOutput()
    {
        $this->expectException(MissingModelSupportException::class);

        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory());

        $model = new Model('gpt-3');
        $event = new InvocationEvent($model, new MessageBag(), ['response_format' => SomeStructure::class]);

        $processor->processInput($event);
    }

    public function testProcessOutputWithResponseFormat()
    {
        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $invocationEvent = new InvocationEvent($model, new MessageBag(), ['response_format' => SomeStructure::class]);
        $processor->processInput($invocationEvent);

        $converter = new PlainConverter(new TextResult('{"some": "data"}'));
        $deferred = new DeferredResult($converter, new InMemoryRawResult());
        $resultEvent = new ResultEvent($model, $deferred, $invocationEvent->getOptions());

        $processor->processResult($resultEvent);

        $deferredResult = $resultEvent->getDeferredResult();
        $this->assertInstanceOf(ObjectResult::class, $deferredResult->getResult());
        $this->assertInstanceOf(SomeStructure::class, $deferredResult->asObject());
        $this->assertInstanceOf(Metadata::class, $deferredResult->getResult()->getMetadata());
        $this->assertSame('data', $deferredResult->asObject()->some);
    }

    /**
     * @param class-string<MathReasoning|MathReasoningWithAttributes> $class
     */
    #[TestWith([MathReasoning::class])]
    #[TestWith([MathReasoningWithAttributes::class])]
    public function testProcessOutputWithComplexResponseFormat(string $class)
    {
        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $options = ['response_format' => $class];
        $invocationEvent = new InvocationEvent($model, new MessageBag(), $options);
        $processor->processInput($invocationEvent);

        $converter = new PlainConverter(new TextResult(<<<JSON
            {
                "steps": [
                    {
                        "explanation": "We want to isolate the term with x. First, let's subtract 7 from both sides of the equation.",
                        "output": "8x + 7 - 7 = -23 - 7"
                    },
                    {
                        "explanation": "This simplifies to 8x = -30.",
                        "output": "8x = -30"
                    },
                    {
                        "explanation": "Next, to solve for x, we need to divide both sides of the equation by 8.",
                        "output": "x = -30 / 8"
                    },
                    {
                        "explanation": "Now we simplify -30 / 8 to its simplest form.",
                        "output": "x = -15 / 4"
                    },
                    {
                        "explanation": "Dividing both the numerator and the denominator by their greatest common divisor, we finalize our solution.",
                        "output": "x = -3.75"
                    }
                ],
                "finalAnswer": "x = -3.75",
                "result": -3.75
            }
            JSON));
        $deferred = new DeferredResult($converter, new InMemoryRawResult());
        $resultEvent = new ResultEvent($model, $deferred, $invocationEvent->getOptions());

        $processor->processResult($resultEvent);

        $deferredResult = $resultEvent->getDeferredResult();
        $this->assertInstanceOf(ObjectResult::class, $result = $deferredResult->getResult());
        /* @var MathReasoning|MathReasoningWithAttributes $structure */
        $this->assertInstanceOf($class, $structure = $deferredResult->asObject());
        $this->assertInstanceOf(Metadata::class, $result->getMetadata());
        $this->assertCount(5, $structure->steps);
        $this->assertInstanceOf(Step::class, $structure->steps[0]);
        $this->assertInstanceOf(Step::class, $structure->steps[1]);
        $this->assertInstanceOf(Step::class, $structure->steps[2]);
        $this->assertInstanceOf(Step::class, $structure->steps[3]);
        $this->assertInstanceOf(Step::class, $structure->steps[4]);
        $this->assertSame('x = -3.75', $structure->finalAnswer);
        $this->assertSame(-3.75, $structure->result);
    }

    /**
     * @param class-string $expectedTimeStructure
     */
    #[DataProvider('unionTimeTypeProvider')]
    public function testProcessOutputWithUnionTypeResponseFormat(TextResult $result, string $expectedTimeStructure)
    {
        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $options = ['response_format' => UnionTypeDto::class];
        $invocationEvent = new InvocationEvent($model, new MessageBag(), $options);
        $processor->processInput($invocationEvent);

        $converter = new PlainConverter($result);
        $deferred = new DeferredResult($converter, new InMemoryRawResult());
        $resultEvent = new ResultEvent($model, $deferred, $invocationEvent->getOptions());

        $processor->processResult($resultEvent);

        $this->assertInstanceOf(ObjectResult::class, $resultEvent->getDeferredResult()->getResult());
        /** @var UnionTypeDto $structure */
        $structure = $resultEvent->getDeferredResult()->asObject();
        $this->assertInstanceOf(UnionTypeDto::class, $structure);

        $this->assertInstanceOf($expectedTimeStructure, $structure->time);
    }

    public static function unionTimeTypeProvider(): array
    {
        $unixTimestampResult = new TextResult(<<<JSON
          {
            "time": {
                "timestamp": 2212121
              }
          }
        JSON);

        $humanReadableResult = new TextResult(<<<JSON
          {
            "time": {
                "readableTime": "2023-10-10T10:10:10+00:00"
              }
          }
        JSON);

        return [
            [$unixTimestampResult, UnixTimestampUnion::class],
            [$humanReadableResult, HumanReadableTimeUnion::class],
        ];
    }

    public function testProcessOutputWithCorrectPolymorphicTypesResponseFormat()
    {
        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $options = ['response_format' => ListOfPolymorphicTypesDto::class];
        $invocationEvent = new InvocationEvent($model, new MessageBag(), $options);
        $processor->processInput($invocationEvent);

        $converter = new PlainConverter(new TextResult(<<<JSON
            {
                "items": [
                    {
                        "type": "name",
                        "name": "John Doe"
                    },
                    {
                        "type": "age",
                        "age": 24
                    }
                ]
            }
            JSON));
        $deferred = new DeferredResult($converter, new InMemoryRawResult());
        $resultEvent = new ResultEvent($model, $deferred, $invocationEvent->getOptions());

        $processor->processResult($resultEvent);

        $this->assertInstanceOf(ObjectResult::class, $resultEvent->getDeferredResult()->getResult());

        /** @var ListOfPolymorphicTypesDto $structure */
        $structure = $resultEvent->getDeferredResult()->asObject();
        $this->assertInstanceOf(ListOfPolymorphicTypesDto::class, $structure);

        $this->assertCount(2, $structure->items);

        $nameItem = $structure->items[0];
        $ageItem = $structure->items[1];

        $this->assertInstanceOf(ListItemName::class, $nameItem);
        $this->assertInstanceOf(ListItemAge::class, $ageItem);

        $this->assertSame('John Doe', $nameItem->name);
        $this->assertSame(24, $ageItem->age);

        $this->assertSame('name', $nameItem->type);
        $this->assertSame('age', $ageItem->type);
    }

    public function testProcessOutputWithoutResponseFormat()
    {
        $resultFormatFactory = new ConfigurableResponseFormatFactory();
        $processor = new PlatformSubscriber($resultFormatFactory);

        $converter = new PlainConverter($result = new TextResult('{"some": "data"}'));
        $deferred = new DeferredResult($converter, new InMemoryRawResult());
        $event = new ResultEvent(new Model('gpt4', [Capability::OUTPUT_STRUCTURED]), $deferred);

        $processor->processResult($event);

        $this->assertSame($result, $event->getDeferredResult()->getResult());
    }

    public function testProcessInputWithObjectInstance()
    {
        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory(['some' => 'format']));
        $city = new City(name: 'Berlin');
        $event = new InvocationEvent(new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]), new MessageBag(), [
            'response_format' => $city,
        ]);

        $processor->processInput($event);

        $this->assertSame(['response_format' => ['some' => 'format']], $event->getOptions());
    }

    public function testProcessOutputWithObjectInstance()
    {
        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $city = new City(name: 'Berlin');
        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $invocationEvent = new InvocationEvent($model, new MessageBag(), ['response_format' => $city]);
        $processor->processInput($invocationEvent);

        $converter = new PlainConverter(new TextResult('{"name": "Berlin", "population": 3500000, "country": "Germany", "mayor": "Kai Wegner"}'));
        $deferred = new DeferredResult($converter, new InMemoryRawResult());
        $resultEvent = new ResultEvent($model, $deferred, $invocationEvent->getOptions());

        $processor->processResult($resultEvent);

        $deferredResult = $resultEvent->getDeferredResult();
        $this->assertInstanceOf(ObjectResult::class, $deferredResult->getResult());
        $result = $deferredResult->asObject();
        $this->assertInstanceOf(City::class, $result);
        $this->assertSame($city, $result);
        $this->assertSame('Berlin', $result->name);
        $this->assertSame(3500000, $result->population);
        $this->assertSame('Germany', $result->country);
        $this->assertSame('Kai Wegner', $result->mayor);
    }

    public function testObjectInstancePreservesExistingValues()
    {
        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $city = new City(name: 'Berlin', country: 'Germany');
        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $invocationEvent = new InvocationEvent($model, new MessageBag(), ['response_format' => $city]);
        $processor->processInput($invocationEvent);

        $converter = new PlainConverter(new TextResult('{"population": 3500000, "mayor": "Kai Wegner"}'));
        $deferred = new DeferredResult($converter, new InMemoryRawResult());
        $resultEvent = new ResultEvent($model, $deferred, $invocationEvent->getOptions());

        $processor->processResult($resultEvent);

        $result = $resultEvent->getDeferredResult()->asObject();
        $this->assertSame($city, $result);
        $this->assertSame('Berlin', $result->name);
        $this->assertSame('Germany', $result->country);
        $this->assertSame(3500000, $result->population);
        $this->assertSame('Kai Wegner', $result->mayor);
    }

    public function testObjectInstanceThrowsExceptionWithStreaming()
    {
        $this->expectException(\Symfony\AI\Platform\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Streamed responses are not supported for structured output.');

        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory());

        $city = new City(name: 'Berlin');
        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $event = new InvocationEvent($model, new MessageBag(), [
            'response_format' => $city,
            'stream' => true,
        ]);

        $processor->processInput($event);
    }

    public function testProcessInputIgnoresNonObjectNonClassString()
    {
        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory());

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $event = new InvocationEvent($model, new MessageBag(), [
            'response_format' => 'invalid-class-name',
        ]);

        $processor->processInput($event);

        $this->assertSame(['response_format' => 'invalid-class-name'], $event->getOptions());
    }

    public function testProcessInputIgnoresNonStructuredOutputResponseFormats()
    {
        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory());

        $model = new Model('dalle-2');
        $event = new InvocationEvent($model, new MessageBag(), [
            'response_format' => 'url',
        ]);

        $processor->processInput($event);

        $this->assertSame(['response_format' => 'url'], $event->getOptions());
    }

    public function testObjectToPopulateIsResetAfterProcessResult()
    {
        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $city1 = new City(name: 'Berlin');
        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $invocationEvent1 = new InvocationEvent($model, new MessageBag(), ['response_format' => $city1]);
        $processor->processInput($invocationEvent1);

        $converter = new PlainConverter(new TextResult('{"name": "Berlin", "population": 3500000}'));
        $deferred = new DeferredResult($converter, new InMemoryRawResult());
        $resultEvent1 = new ResultEvent($model, $deferred, $invocationEvent1->getOptions());
        $processor->processResult($resultEvent1);

        $invocationEvent2 = new InvocationEvent($model, new MessageBag(), ['response_format' => City::class]);
        $processor->processInput($invocationEvent2);

        $converter2 = new PlainConverter(new TextResult('{"name": "Paris", "population": 2161000}'));
        $deferred2 = new DeferredResult($converter2, new InMemoryRawResult());
        $resultEvent2 = new ResultEvent($model, $deferred2, $invocationEvent2->getOptions());
        $processor->processResult($resultEvent2);

        $result2 = $resultEvent2->getDeferredResult()->asObject();

        $this->assertInstanceOf(City::class, $result2);
        $this->assertNotSame($city1, $result2);
        $this->assertSame('Paris', $result2->name);
    }

    public function testMultipleObjectInstancesInSequenceAreHandledCorrectly()
    {
        $processor = new PlatformSubscriber(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $city1 = new City(name: 'Berlin');
        $city2 = new City(name: 'Paris');

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);

        // First invocation
        $invocationEvent1 = new InvocationEvent($model, new MessageBag(), ['response_format' => $city1]);
        $processor->processInput($invocationEvent1);
        $this->assertSame(['response_format' => ['some' => 'format']], $invocationEvent1->getOptions());

        $converter1 = new PlainConverter(new TextResult('{"name": "Berlin", "population": 3500000}'));
        $deferred1 = new DeferredResult($converter1, new InMemoryRawResult());
        $resultEvent1 = new ResultEvent($model, $deferred1, $invocationEvent1->getOptions());
        $processor->processResult($resultEvent1);

        $result1 = $resultEvent1->getDeferredResult()->asObject();
        $this->assertSame($city1, $result1);

        // Second invocation with different object
        $invocationEvent2 = new InvocationEvent($model, new MessageBag(), ['response_format' => $city2]);
        $processor->processInput($invocationEvent2);

        $converter2 = new PlainConverter(new TextResult('{"name": "Paris", "population": 2161000}'));
        $deferred2 = new DeferredResult($converter2, new InMemoryRawResult());
        $resultEvent2 = new ResultEvent($model, $deferred2, $invocationEvent2->getOptions());
        $processor->processResult($resultEvent2);

        $result2 = $resultEvent2->getDeferredResult()->asObject();
        $this->assertSame($city2, $result2);

        $this->assertSame('Berlin', $city1->name);
        $this->assertSame(3500000, $city1->population);
        $this->assertSame('Paris', $city2->name);
        $this->assertSame(2161000, $city2->population);
    }
}
