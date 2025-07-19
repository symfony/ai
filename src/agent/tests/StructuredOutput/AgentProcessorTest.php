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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\MissingModelSupportException;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\StructuredOutput\AgentProcessor;
use Symfony\AI\Fixtures\SomeStructure;
use Symfony\AI\Fixtures\StructuredOutput\MathReasoning;
use Symfony\AI\Fixtures\StructuredOutput\Step;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\Choice;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Serializer\SerializerInterface;

#[CoversClass(AgentProcessor::class)]
#[UsesClass(Input::class)]
#[UsesClass(Output::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(Choice::class)]
#[UsesClass(MissingModelSupportException::class)]
#[UsesClass(TextResult::class)]
#[UsesClass(ObjectResult::class)]
#[UsesClass(Model::class)]
final class AgentProcessorTest extends TestCase
{
    #[Test]
    public function processInputWithOutputStructure(): void
    {
        $processor = new AgentProcessor(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $input = new Input($model, new MessageBag(), ['output_structure' => 'SomeStructure']);

        $processor->processInput($input);

        self::assertSame(['response_format' => ['some' => 'format']], $input->getOptions());
    }

    #[Test]
    public function processInputWithoutOutputStructure(): void
    {
        $processor = new AgentProcessor(new ConfigurableResponseFormatFactory());

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $input = new Input($model, new MessageBag(), []);

        $processor->processInput($input);

        self::assertSame([], $input->getOptions());
    }

    #[Test]
    public function processInputThrowsExceptionWhenLlmDoesNotSupportStructuredOutput(): void
    {
        self::expectException(MissingModelSupportException::class);

        $processor = new AgentProcessor(new ConfigurableResponseFormatFactory());

        $model = new Model('gpt-3');
        $input = new Input($model, new MessageBag(), ['output_structure' => 'SomeStructure']);

        $processor->processInput($input);
    }

    #[Test]
    public function processOutputWithResponseFormat(): void
    {
        $processor = new AgentProcessor(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $options = ['output_structure' => SomeStructure::class];
        $input = new Input($model, new MessageBag(), $options);
        $processor->processInput($input);

        $result = new TextResult('{"some": "data"}');

        $output = new Output($model, $result, new MessageBag(), $input->getOptions());

        $processor->processOutput($output);

        self::assertInstanceOf(ObjectResult::class, $output->result);
        self::assertInstanceOf(SomeStructure::class, $output->result->getContent());
        self::assertSame('data', $output->result->getContent()->some);
    }

    #[Test]
    public function processOutputWithComplexResponseFormat(): void
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
                "finalAnswer": "x = -3.75"
            }
            JSON);

        $output = new Output($model, $result, new MessageBag(), $input->getOptions());

        $processor->processOutput($output);

        self::assertInstanceOf(ObjectResult::class, $output->result);
        self::assertInstanceOf(MathReasoning::class, $structure = $output->result->getContent());
        self::assertCount(5, $structure->steps);
        self::assertInstanceOf(Step::class, $structure->steps[0]);
        self::assertInstanceOf(Step::class, $structure->steps[1]);
        self::assertInstanceOf(Step::class, $structure->steps[2]);
        self::assertInstanceOf(Step::class, $structure->steps[3]);
        self::assertInstanceOf(Step::class, $structure->steps[4]);
        self::assertSame('x = -3.75', $structure->finalAnswer);
    }

    #[Test]
    public function processOutputWithoutResponseFormat(): void
    {
        $resultFormatFactory = new ConfigurableResponseFormatFactory();
        $serializer = self::createMock(SerializerInterface::class);
        $processor = new AgentProcessor($resultFormatFactory, $serializer);

        $model = self::createMock(Model::class);
        $result = new TextResult('');

        $output = new Output($model, $result, new MessageBag(), []);

        $processor->processOutput($output);

        self::assertSame($result, $output->result);
    }
}
