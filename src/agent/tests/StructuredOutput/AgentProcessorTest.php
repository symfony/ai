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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\MissingModelSupportException;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\StructuredOutput\AgentProcessor;
use Symfony\AI\Agent\StructuredOutput\ResponseFormatFactoryInterface;
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
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Serializer\SerializerInterface;

if (!class_exists(__NAMESPACE__.'\ConfigurableResponseFormatFactory')) {
    class ConfigurableResponseFormatFactory implements ResponseFormatFactoryInterface {
        public function __construct(private array $format = []) {}
        public function create(string $structure): array { return $this->format; }
    }
}

final class AgentProcessorTest extends TestCase
{
    public function testProcessInputWithOutputStructure()
    {
        $platformMock = $this->createPlatformMock('gpt-4');
        $processor = new AgentProcessor($platformMock, new ConfigurableResponseFormatFactory(['some' => 'format']));
        $input = new Input('gpt-4', new MessageBag(), ['output_structure' => SomeStructure::class]);

        $processor->processInput($input);

        $this->assertSame(['response_format' => ['some' => 'format']], $input->getOptions());
    }

    public function testProcessInputWithoutOutputStructure()
    {
        $platformMock = $this->createMock(PlatformInterface::class);
        $processor = new AgentProcessor($platformMock, new ConfigurableResponseFormatFactory());
        $input = new Input('gpt-4', new MessageBag());

        $processor->processInput($input);

        $this->assertSame([], $input->getOptions());
    }

    public function testProcessInputThrowsExceptionForMissingSupport()
    {
        $modelName = 'model-without-structured-output';

        $modelMock = $this->createMock(Model::class);
        $modelMock->method('getCapabilities')->willReturn([
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
        ]);
        $modelMock->method('getName')->willReturn($modelName);

        $modelCatalogMock = $this->createMock(ModelCatalogInterface::class);
        $modelCatalogMock
            ->expects($this->once())
            ->method('getModel')
            ->with($modelName)
            ->willReturn($modelMock);

        $platformMock = $this->createMock(PlatformInterface::class);
        $platformMock
            ->expects($this->once())
            ->method('getModelCatalog')
            ->willReturn($modelCatalogMock);

        $processor = new AgentProcessor($platformMock, new ConfigurableResponseFormatFactory());
        $messages = new MessageBag(new UserMessage(new \Symfony\AI\Platform\Message\Content\Text('Hello')));
        $options = ['output_structure' => 'App\Dto\MyStructure'];
        $input = new Input($modelName, $messages, $options);

        $this->expectException(MissingModelSupportException::class);
        $this->expectExceptionMessage(\sprintf('Model "%s" does not support "structured output".', $modelName));

        $processor->processInput($input);
    }

    public function testProcessOutputWithResponseFormat()
    {
        $platformMock = $this->createPlatformMock('gpt-4');
        $processor = new AgentProcessor($platformMock, new ConfigurableResponseFormatFactory(['some' => 'format']));

        $options = ['output_structure' => SomeStructure::class];
        $input = new Input('gpt-4', new MessageBag(), $options);
        $processor->processInput($input);

        $result = new TextResult('{"some": "data"}');
        $output = new Output('gpt-4', $result, new MessageBag(), $input->getOptions());
        $processor->processOutput($output);

        $this->assertInstanceOf(ObjectResult::class, $output->getResult());
        $resultContent = $output->getResult()->getContent();
        $this->assertInstanceOf(SomeStructure::class, $resultContent);
        $this->assertInstanceOf(Metadata::class, $output->getResult()->getMetadata());
        $this->assertNull($output->getResult()->getRawResult());
        $this->assertSame('data', $resultContent->some);
    }

    public function testProcessOutputWithComplexResponseFormat()
    {
        $platformMock = $this->createPlatformMock('gpt-4');
        $processor = new AgentProcessor($platformMock, new ConfigurableResponseFormatFactory(['some' => 'format']));

        $options = ['output_structure' => MathReasoning::class];
        $input = new Input('gpt-4', new MessageBag(), $options);
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

        $output = new Output('gpt-4', $result, new MessageBag(), $input->getOptions());
        $processor->processOutput($output);

        $this->assertInstanceOf(ObjectResult::class, $output->getResult());
        $structure = $output->getResult()->getContent();
        $this->assertInstanceOf(MathReasoning::class, $structure);
        $this->assertInstanceOf(Metadata::class, $output->getResult()->getMetadata());
        $this->assertNull($output->getResult()->getRawResult());
        $this->assertCount(5, $structure->steps);
        $this->assertInstanceOf(Step::class, $structure->steps[0]);
        $this->assertSame("We want to isolate the term with x. First, let's subtract 7 from both sides of the equation.", $structure->steps[0]->explanation);
        $this->assertSame("8x + 7 - 7 = -23 - 7", $structure->steps[0]->output);
        $this->assertInstanceOf(Step::class, $structure->steps[1]);
        $this->assertInstanceOf(Step::class, $structure->steps[2]);
        $this->assertInstanceOf(Step::class, $structure->steps[3]);
        $this->assertInstanceOf(Step::class, $structure->steps[4]);
        $this->assertSame(100, $structure->confidence);
        $this->assertSame('x = -3.75', $structure->finalAnswer);
    }

    #[DataProvider('unionTimeTypeProvider')]
    public function testProcessOutputWithUnionTypeResponseFormat(TextResult $result, string $expectedTimeStructure)
    {
        $platformMock = $this->createPlatformMock('gpt-4');
        $processor = new AgentProcessor($platformMock, new ConfigurableResponseFormatFactory(['some' => 'format']));

        $options = ['output_structure' => UnionTypeDto::class];
        $input = new Input('gpt-4', new MessageBag(), $options);
        $processor->processInput($input);

        $output = new Output('gpt-4', $result, new MessageBag(), $input->getOptions());
        $processor->processOutput($output);

        $this->assertInstanceOf(ObjectResult::class, $output->getResult());
        /** @var UnionTypeDto $structure */
        $structure = $output->getResult()->getContent();
        $this->assertInstanceOf(UnionTypeDto::class, $structure);

        $this->assertInstanceOf($expectedTimeStructure, $structure->time);
    }

    public static function unionTimeTypeProvider(): array
    {
        $unixTimestampResult = new TextResult('{"time": {"timestamp": 2212121}}');
        $humanReadableResult = new TextResult('{"time": {"readableTime": "2023-10-10T10:10:10+00:00"}}');

        return [
            [$unixTimestampResult, UnixTimestampUnion::class],
            [$humanReadableResult, HumanReadableTimeUnion::class],
        ];
    }

    public function testProcessOutputWithCorrectPolymorphicTypesResponseFormat()
    {
        $platformMock = $this->createPlatformMock('gpt-4');
        $processor = new AgentProcessor($platformMock, new ConfigurableResponseFormatFactory(['some' => 'format']));

        $options = ['output_structure' => ListOfPolymorphicTypesDto::class];
        $input = new Input('gpt-4', new MessageBag(), $options);
        $processor->processInput($input);

        $result = new TextResult(<<<JSON
            {"items": [{"type": "name", "name": "John Doe"}, {"type": "age", "age": 24}]}
            JSON);

        $output = new Output('gpt-4', $result, new MessageBag(), $input->getOptions());
        $processor->processOutput($output);

        $this->assertInstanceOf(ObjectResult::class, $output->getResult());
        /** @var ListOfPolymorphicTypesDto $structure */
        $structure = $output->getResult()->getContent();
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
        $platformMock = $this->createMock(PlatformInterface::class);
        $resultFormatFactory = new ConfigurableResponseFormatFactory();
        $serializer = $this->createMock(SerializerInterface::class);
        $processor = new AgentProcessor($platformMock, $resultFormatFactory, $serializer);

        $result = new TextResult('');
        $output = new Output('gpt4', $result, new MessageBag());
        $processor->processOutput($output);

        $this->assertSame($result, $output->getResult());
    }

    private function createPlatformMock(string $modelName): PlatformInterface
    {
        $modelMock = $this->createMock(Model::class);
        $modelMock->method('getCapabilities')->willReturn([
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STRUCTURED,
        ]);
        $modelMock->method('getName')->willReturn($modelName);

        $modelCatalogMock = $this->createMock(ModelCatalogInterface::class);
        $modelCatalogMock
            ->method('getModel')
            ->with($modelName)
            ->willReturn($modelMock);

        $platformMock = $this->createMock(PlatformInterface::class);
        $platformMock
            ->method('getModelCatalog')
            ->willReturn($modelCatalogMock);

        return $platformMock;
    }
}
