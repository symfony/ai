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
use Symfony\AI\Agent\Tests\Fixture\SomeStructure;
use Symfony\AI\Agent\Tests\Fixture\StructuredOutput\MathReasoning;
use Symfony\AI\Agent\Tests\Fixture\StructuredOutput\Step;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Response\Choice;
use Symfony\AI\Platform\Response\ObjectResponse;
use Symfony\AI\Platform\Response\TextResponse;
use Symfony\Component\Serializer\SerializerInterface;

#[CoversClass(AgentProcessor::class)]
#[UsesClass(Input::class)]
#[UsesClass(Output::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(Choice::class)]
#[UsesClass(MissingModelSupportException::class)]
#[UsesClass(TextResponse::class)]
#[UsesClass(ObjectResponse::class)]
#[UsesClass(Model::class)]
final class ChainProcessorTest extends TestCase
{
    #[Test]
    public function processInputWithOutputStructure(): void
    {
        $agentProcessor = new AgentProcessor(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $input = new Input($model, new MessageBag(), ['output_structure' => 'SomeStructure']);

        $agentProcessor->processInput($input);

        self::assertSame(['response_format' => ['some' => 'format']], $input->getOptions());
    }

    #[Test]
    public function processInputWithoutOutputStructure(): void
    {
        $agentProcessor = new AgentProcessor(new ConfigurableResponseFormatFactory());

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $input = new Input($model, new MessageBag(), []);

        $agentProcessor->processInput($input);

        self::assertSame([], $input->getOptions());
    }

    #[Test]
    public function processInputThrowsExceptionWhenLlmDoesNotSupportStructuredOutput(): void
    {
        self::expectException(MissingModelSupportException::class);

        $agentProcessor = new AgentProcessor(new ConfigurableResponseFormatFactory());

        $model = new Model('gpt-3');
        $input = new Input($model, new MessageBag(), ['output_structure' => 'SomeStructure']);

        $agentProcessor->processInput($input);
    }

    #[Test]
    public function processOutputWithResponseFormat(): void
    {
        $agentProcessor = new AgentProcessor(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $options = ['output_structure' => SomeStructure::class];
        $input = new Input($model, new MessageBag(), $options);
        $agentProcessor->processInput($input);

        $response = new TextResponse('{"some": "data"}');

        $output = new Output($model, $response, new MessageBag(), $input->getOptions());

        $agentProcessor->processOutput($output);

        self::assertInstanceOf(ObjectResponse::class, $output->response);
        self::assertInstanceOf(SomeStructure::class, $output->response->getContent());
        self::assertSame('data', $output->response->getContent()->some);
    }

    #[Test]
    public function processOutputWithComplexResponseFormat(): void
    {
        $agentProcessor = new AgentProcessor(new ConfigurableResponseFormatFactory(['some' => 'format']));

        $model = new Model('gpt-4', [Capability::OUTPUT_STRUCTURED]);
        $options = ['output_structure' => MathReasoning::class];
        $input = new Input($model, new MessageBag(), $options);
        $agentProcessor->processInput($input);

        $response = new TextResponse(<<<JSON
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

        $output = new Output($model, $response, new MessageBag(), $input->getOptions());

        $agentProcessor->processOutput($output);

        self::assertInstanceOf(ObjectResponse::class, $output->response);
        self::assertInstanceOf(MathReasoning::class, $structure = $output->response->getContent());
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
        $responseFormatFactory = new ConfigurableResponseFormatFactory();
        $serializer = self::createMock(SerializerInterface::class);
        $agentProcessor = new AgentProcessor($responseFormatFactory, $serializer);

        $model = self::createMock(Model::class);
        $response = new TextResponse('');

        $output = new Output($model, $response, new MessageBag(), []);

        $agentProcessor->processOutput($output);

        self::assertSame($response, $output->response);
    }
}
