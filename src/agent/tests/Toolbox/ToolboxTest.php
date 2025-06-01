<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Tests\Fixture\Tool\ToolException;
use Symfony\AI\Agent\Tests\Fixture\Tool\ToolMisconfigured;
use Symfony\AI\Agent\Tests\Fixture\Tool\ToolNoAttribute1;
use Symfony\AI\Agent\Tests\Fixture\Tool\ToolNoParams;
use Symfony\AI\Agent\Tests\Fixture\Tool\ToolOptionalParam;
use Symfony\AI\Agent\Tests\Fixture\Tool\ToolRequiredParams;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Exception\ToolConfigurationException;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionException;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolFactory\ChainFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Platform\Contract\JsonSchema\DescriptionParser;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Response\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

#[CoversClass(Toolbox::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(AsTool::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ExecutionReference::class)]
#[UsesClass(ReflectionToolFactory::class)]
#[UsesClass(MemoryToolFactory::class)]
#[UsesClass(ChainFactory::class)]
#[UsesClass(Factory::class)]
#[UsesClass(DescriptionParser::class)]
#[UsesClass(ToolConfigurationException::class)]
#[UsesClass(ToolNotFoundException::class)]
#[UsesClass(ToolExecutionException::class)]
final class ToolboxTest extends TestCase
{
    private Toolbox $toolbox;

    protected function setUp(): void
    {
        $this->toolbox = new Toolbox(new ReflectionToolFactory(), [
            new ToolRequiredParams(),
            new ToolOptionalParam(),
            new ToolNoParams(),
            new ToolException(),
        ]);
    }

    #[Test]
    public function getTools(): void
    {
        $actual = $this->toolbox->getTools();

        $toolRequiredParams = new Tool(
            new ExecutionReference(ToolRequiredParams::class, 'bar'),
            'tool_required_params',
            'A tool with required parameters',
            [
                'type' => 'object',
                'properties' => [
                    'text' => [
                        'type' => 'string',
                        'description' => 'The text given to the tool',
                    ],
                    'number' => [
                        'type' => 'integer',
                        'description' => 'A number given to the tool',
                    ],
                ],
                'required' => ['text', 'number'],
                'additionalProperties' => false,
            ],
        );

        $toolOptionalParam = new Tool(
            new ExecutionReference(ToolOptionalParam::class, 'bar'),
            'tool_optional_param',
            'A tool with one optional parameter',
            [
                'type' => 'object',
                'properties' => [
                    'text' => [
                        'type' => 'string',
                        'description' => 'The text given to the tool',
                    ],
                    'number' => [
                        'type' => 'integer',
                        'description' => 'A number given to the tool',
                    ],
                ],
                'required' => ['text'],
                'additionalProperties' => false,
            ],
        );

        $toolNoParams = new Tool(
            new ExecutionReference(ToolNoParams::class),
            'tool_no_params',
            'A tool without parameters',
        );

        $toolException = new Tool(
            new ExecutionReference(ToolException::class, 'bar'),
            'tool_exception',
            'This tool is broken',
        );

        $expected = [
            $toolRequiredParams,
            $toolOptionalParam,
            $toolNoParams,
            $toolException,
        ];

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function executeWithUnknownTool(): void
    {
        self::expectException(ToolNotFoundException::class);
        self::expectExceptionMessage('Tool not found for call: foo_bar_baz');

        $this->toolbox->execute(new ToolCall('call_1234', 'foo_bar_baz'));
    }

    #[Test]
    public function executeWithMisconfiguredTool(): void
    {
        self::expectException(ToolConfigurationException::class);
        self::expectExceptionMessage('Method "foo" not found in tool "Symfony\AI\Agent\Tests\Fixture\Tool\ToolMisconfigured".');

        $toolbox = new Toolbox(new ReflectionToolFactory(), [new ToolMisconfigured()]);

        $toolbox->execute(new ToolCall('call_1234', 'tool_misconfigured'));
    }

    #[Test]
    public function executeWithException(): void
    {
        self::expectException(ToolExecutionException::class);
        self::expectExceptionMessage('Execution of tool "tool_exception" failed with error: Tool error.');

        $this->toolbox->execute(new ToolCall('call_1234', 'tool_exception'));
    }

    #[Test]
    #[DataProvider('executeProvider')]
    public function execute(string $expected, string $toolName, array $toolPayload = []): void
    {
        self::assertSame(
            $expected,
            $this->toolbox->execute(new ToolCall('call_1234', $toolName, $toolPayload)),
        );
    }

    /**
     * @return iterable<array{0: non-empty-string, 1: non-empty-string, 2?: array}>
     */
    public static function executeProvider(): iterable
    {
        yield 'tool_required_params' => [
            'Hello says "3".',
            'tool_required_params',
            ['text' => 'Hello', 'number' => 3],
        ];
    }

    #[Test]
    public function toolboxMapWithMemoryFactory(): void
    {
        $memoryFactory = (new MemoryToolFactory())
            ->addTool(ToolNoAttribute1::class, 'happy_birthday', 'Generates birthday message');

        $toolbox = new Toolbox($memoryFactory, [new ToolNoAttribute1()]);
        $expected = [
            new Tool(
                new ExecutionReference(ToolNoAttribute1::class, '__invoke'),
                'happy_birthday',
                'Generates birthday message',
                [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'the name of the person',
                        ],
                        'years' => [
                            'type' => 'integer',
                            'description' => 'the age of the person',
                        ],
                    ],
                    'required' => ['name', 'years'],
                    'additionalProperties' => false,
                ],
            ),
        ];

        self::assertEquals($expected, $toolbox->getTools());
    }

    #[Test]
    public function toolboxExecutionWithMemoryFactory(): void
    {
        $memoryFactory = (new MemoryToolFactory())
            ->addTool(ToolNoAttribute1::class, 'happy_birthday', 'Generates birthday message');

        $toolbox = new Toolbox($memoryFactory, [new ToolNoAttribute1()]);
        $response = $toolbox->execute(new ToolCall('call_1234', 'happy_birthday', ['name' => 'John', 'years' => 30]));

        self::assertSame('Happy Birthday, John! You are 30 years old.', $response);
    }

    #[Test]
    public function toolboxMapWithOverrideViaChain(): void
    {
        $factory1 = (new MemoryToolFactory())
            ->addTool(ToolOptionalParam::class, 'optional_param', 'Tool with optional param', 'bar');
        $factory2 = new ReflectionToolFactory();

        $toolbox = new Toolbox(new ChainFactory([$factory1, $factory2]), [new ToolOptionalParam()]);

        $expected = [
            new Tool(
                new ExecutionReference(ToolOptionalParam::class, 'bar'),
                'optional_param',
                'Tool with optional param',
                [
                    'type' => 'object',
                    'properties' => [
                        'text' => [
                            'type' => 'string',
                            'description' => 'The text given to the tool',
                        ],
                        'number' => [
                            'type' => 'integer',
                            'description' => 'A number given to the tool',
                        ],
                    ],
                    'required' => ['text'],
                    'additionalProperties' => false,
                ],
            ),
        ];

        self::assertEquals($expected, $toolbox->getTools());
    }
}
