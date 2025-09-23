<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\StructuredOutput;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\MissingModelSupportException;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\StructuredOutput\AgentProcessor;
use Symfony\AI\Fixtures\SomeStructure;
use Symfony\AI\Fixtures\StructuredOutput\MathReasoning;
use Symfony\AI\Fixtures\StructuredOutput\PolymorphicType\ListItemAge;
use Symfony\AI\Fixtures\StructuredOutput\PolymorphicType\ListItemName;
use Symfony\AI\Fixtures\StructuredOutput\PolymorphicType\ListOfPolymorphicTypesDto;
use Symfony\AI\Fixtures\StructuredOutput\Step;
use Symfony\AI\Fixtures\StructuredOutput\UnionType\HumanReadableTimeUnion;
use Symfony\AI\Fixtures\StructuredOutput\UnionType\UnionTypeDto;
use Symfony\AI\Fixtures\StructuredOutput\UnionType\UnixTimestampUnion;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Serializer\SerializerInterface;

#[CoversClass(AgentProcessor::class)]
#[UsesClass(Input::class)]
#[UsesClass(Output::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(MissingModelSupportException::class)]
#[UsesClass(TextResult::class)]
#[UsesClass(ObjectResult::class)]
#[UsesClass(Model::class)]
final class AgentProcessorTest extends TestCase
{
    public function testProcessInputWithOutputStructure()
    {
        $processor = new AgentProcessor(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $input = new Input($model, new MessageBag(), ['output_structure' => 'SomeStructure']);

        $processor->processInput($input);

        $this->assertSame(['response_format' => ['some' => 'format']], $input->getOptions());
    }

    public function testProcessInputWithoutOutputStructure()
    {
        $processor = new AgentProcessor(new ConfigurableResponseFormatFactory());

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $input = new Input($model, new MessageBag());

        $processor->processInput($input);

        $this->assertSame([], $input->getOptions());
    }

    public function testProcessInputThrowsExceptionWhenLlmDoesNotSupportStructuredOutput()
    {
        $this->expectException(MissingModelSupportException::class);

        $processor = new AgentProcessor(new ConfigurableResponseFormatFactory());

        $model = new Model('gpt-3');
        $input = new Input($model, new MessageBag(), ['output_structure' => 'SomeStructure']);

        $processor->processInput($input);
    }

    public function testProcessOutputWithResponseFormat()
    {
        $processor = new AgentProcessor(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $options = ['output_structure' => SomeStructure::class];
        $input = new Input($model, new MessageBag(), $options);
        $processor->processInput($input);

        $result = new TextResult('{"some": "data"}');

        $output = new Output($model, $result, new MessageBag(), $input->getOptions());

        $processor->processOutput($output);

        $this->assertInstanceOf(ObjectResult::class, $output->result);
        $this->assertInstanceOf(SomeStructure::class, $output->result->getContent());
        $this->assertInstanceOf(Metadata::class, $output->result->getMetadata());
        $this->assertNull($output->result->getRawResult());
        $this->assertSame('data', $output->result->getContent()->some);
    }

    public function testProcessOutputWithComplexResponseFormat()
    {
        $processor = new AgentProcessor(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $options = ['output_structure' => MathReasoning::class];
        $input = new Input($model, new MessageBag(), $options);
        $processor->processInput($input);

        $result = new TextResult(<<<JSON
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
                "confidence": 100,
                "finalAnswer": "x = -3.75"
            }
            JSON);

        $output = new Output($model, $result, new MessageBag(), $input->getOptions());

        $processor->processOutput($output);

        $this->assertInstanceOf(ObjectResult::class, $output->result);
        $this->assertInstanceOf(MathReasoning::class, $structure = $output->result->getContent());
        $this->assertInstanceOf(Metadata::class, $output->result->getMetadata());
        $this->assertNull($output->result->getRawResult());
        $this->assertCount(5, $structure->steps);
        $this->assertInstanceOf(Step::class, $structure->steps[0]);
        $this->assertInstanceOf(Step::class, $structure->steps[1]);
        $this->assertInstanceOf(Step::class, $structure->steps[2]);
        $this->assertInstanceOf(Step::class, $structure->steps[3]);
        $this->assertInstanceOf(Step::class, $structure->steps[4]);
        $this->assertSame(100, $structure->confidence);
        $this->assertSame('x = -3.75', $structure->finalAnswer);
    }

    /**
     * @param class-string $expectedTimeStructure
     */
    #[DataProvider('unionTimeTypeProvider')]
    public function testProcessOutputWithUnionTypeResponseFormat(TextResult $result, string $expectedTimeStructure)
    {
        $processor = new AgentProcessor(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $options = ['output_structure' => UnionTypeDto::class];
        $input = new Input($model, new MessageBag(), $options);
        $processor->processInput($input);

        $output = new Output($model, $result, new MessageBag(), $input->getOptions());
        $processor->processOutput($output);

        $this->assertInstanceOf(ObjectResult::class, $output->result);
        /** @var UnionTypeDto $structure */
        $structure = $output->result->getContent();
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
        $processor = new AgentProcessor(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $options = ['output_structure' => ListOfPolymorphicTypesDto::class];
        $input = new Input($model, new MessageBag(), $options);
        $processor->processInput($input);

        $result = new TextResult(<<<JSON
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
            JSON);

        $output = new Output($model, $result, new MessageBag(), $input->getOptions());

        $processor->processOutput($output);

        $this->assertInstanceOf(ObjectResult::class, $output->result);

        /** @var ListOfPolymorphicTypesDto $structure */
        $structure = $output->result->getContent();
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
        $serializer = self::createMock(SerializerInterface::class);
        $processor = new AgentProcessor($resultFormatFactory, $serializer);

        $model = self::createMock(Model::class);
        $result = new TextResult('');

        $output = new Output($model, $result, new MessageBag());

        $processor->processOutput($output);

        $this->assertSame($result, $output->result);
    }
}
