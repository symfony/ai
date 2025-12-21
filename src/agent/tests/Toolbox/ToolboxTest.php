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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolCustomException;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolDate;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolException;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolMisconfigured;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoAttribute1;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolOptionalParam;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolRequiredParams;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolSources;
use Symfony\AI\Agent\Toolbox\Exception\ToolConfigurationException;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionException;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionExceptionInterface;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolFactory\ChainFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class ToolboxTest extends TestCase
{
    private Toolbox $toolbox;

    protected function setUp(): void
    {
        $this->toolbox = new Toolbox([
            new ToolRequiredParams(),
            new ToolOptionalParam(),
            new ToolNoParams(),
            new ToolException(),
            new ToolCustomException(),
            new ToolDate(),
        ], new ReflectionToolFactory());
    }

    public function testGetTools()
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

        $toolCustomException = new Tool(
            new ExecutionReference(ToolCustomException::class, 'bar'),
            'tool_custom_exception',
            'This tool is broken and it exposes the error',
        );

        $toolDate = new Tool(
            new ExecutionReference(ToolDate::class, '__invoke'),
            'tool_date',
            'A tool with date parameter',
            [
                'type' => 'object',
                'properties' => [
                    'date' => [
                        'type' => 'string',
                        'format' => 'date-time',
                        'description' => 'The date',
                    ],
                ],
                'required' => ['date'],
                'additionalProperties' => false,
            ],
        );

        $expected = [
            $toolRequiredParams,
            $toolOptionalParam,
            $toolNoParams,
            $toolException,
            $toolCustomException,
            $toolDate,
        ];

        $this->assertEquals($expected, $actual);
    }

    public function testExecuteWithUnknownTool()
    {
        $this->expectException(ToolNotFoundException::class);
        $this->expectExceptionMessage('Tool not found for call: foo_bar_baz');

        $this->toolbox->execute(new ToolCall('call_1234', 'foo_bar_baz'));
    }

    public function testExecuteWithMisconfiguredTool()
    {
        $this->expectException(ToolConfigurationException::class);
        $this->expectExceptionMessage(\sprintf('Method "foo" not found in tool "%s".', ToolMisconfigured::class));

        $toolbox = new Toolbox([new ToolMisconfigured()], new ReflectionToolFactory());

        $toolbox->execute(new ToolCall('call_1234', 'tool_misconfigured'));
    }

    public function testExecuteWithException()
    {
        $this->expectException(ToolExecutionException::class);
        $this->expectExceptionMessage('Execution of tool "tool_exception" failed with error: Tool error.');

        $this->toolbox->execute(new ToolCall('call_1234', 'tool_exception'));
    }

    public function testExecuteWithCustomException()
    {
        $this->expectException(ToolExecutionExceptionInterface::class);
        $this->expectExceptionMessage('Custom error.');

        $this->toolbox->execute(new ToolCall('call_1234', 'tool_custom_exception'));
    }

    /**
     * @param array<string, mixed> $toolPayload
     */
    #[DataProvider('executeProvider')]
    public function testExecute(string $expected, string $toolName, array $toolPayload = [])
    {
        $toolCall = new ToolCall('call_1234', $toolName, $toolPayload);

        $this->assertEquals(
            new ToolResult($toolCall, $expected),
            $this->toolbox->execute($toolCall),
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

        yield 'tool_date' => [
            'Weekday: Sunday',
            'tool_date',
            ['date' => '2025-06-29'],
        ];
    }

    public function testToolboxMapWithMemoryFactory()
    {
        $memoryFactory = (new MemoryToolFactory())
            ->addTool(ToolNoAttribute1::class, 'happy_birthday', 'Generates birthday message');

        $toolbox = new Toolbox([new ToolNoAttribute1()], $memoryFactory);
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

        $this->assertEquals($expected, $toolbox->getTools());
    }

    public function testToolboxExecutionWithMemoryFactory()
    {
        $memoryFactory = (new MemoryToolFactory())
            ->addTool(ToolNoAttribute1::class, 'happy_birthday', 'Generates birthday message');

        $toolbox = new Toolbox([new ToolNoAttribute1()], $memoryFactory);
        $result = $toolbox->execute(new ToolCall('call_1234', 'happy_birthday', ['name' => 'John', 'years' => 30]));

        $this->assertSame('Happy Birthday, John! You are 30 years old.', $result->getResult());
    }

    public function testToolboxMapWithOverrideViaChain()
    {
        $factory1 = (new MemoryToolFactory())
            ->addTool(ToolOptionalParam::class, 'optional_param', 'Tool with optional param', 'bar');
        $factory2 = new ReflectionToolFactory();

        $toolbox = new Toolbox([new ToolOptionalParam()], new ChainFactory([$factory1, $factory2]));

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

        $this->assertEquals($expected, $toolbox->getTools());
    }

    public function testSourcesGetFromToolIntoResult()
    {
        $toolbox = new Toolbox([new ToolSources()]);
        $result = $toolbox->execute(new ToolCall('call_1234', 'tool_sources', ['query' => 'random']));

        $this->assertCount(1, $result->getSources());
        $this->assertInstanceOf(Source::class, $source = $result->getSources()[0]);
        $this->assertSame('Relevant Article', $source->getName());
        $this->assertSame('https://example.com/relevant-article', $source->getReference());
        $this->assertSame('Content of that relevant article.', $source->getContent());
    }
}
