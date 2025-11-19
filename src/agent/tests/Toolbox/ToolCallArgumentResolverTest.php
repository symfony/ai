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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\ToolCallArgumentResolver;
use Symfony\AI\Fixtures\SomeStructure;
use Symfony\AI\Fixtures\Tool\ToolArray;
use Symfony\AI\Fixtures\Tool\ToolArrayMultidimensional;
use Symfony\AI\Fixtures\Tool\ToolDate;
use Symfony\AI\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

class ToolCallArgumentResolverTest extends TestCase
{
    public function testResolveArguments()
    {
        $resolver = new ToolCallArgumentResolver();

        $metadata = new Tool(new ExecutionReference(ToolDate::class, '__invoke'), 'tool_date', 'test');
        $toolCall = new ToolCall('invocation', 'tool_date', ['date' => '2025-06-29']);

        $this->assertEquals(['date' => new \DateTimeImmutable('2025-06-29')], $resolver->resolveArguments($metadata, $toolCall));
    }

    public function testResolveScalarArrayArguments()
    {
        $resolver = new ToolCallArgumentResolver();

        $metadata = new Tool(new ExecutionReference(ToolArray::class, '__invoke'), 'tool_array', 'A tool with array parameters');
        $toolCall = new ToolCall('tool_id_1234', 'tool_array', [
            'urls' => ['https://symfony.com', 'https://php.net'],
            'ids' => [1, 2, 3],
        ]);

        $expected = [
            'urls' => ['https://symfony.com', 'https://php.net'],
            'ids' => [1, 2, 3],
        ];

        $this->assertSame($expected, $resolver->resolveArguments($metadata, $toolCall));
    }

    public function testResolveMultidimensionalArrayArguments()
    {
        $resolver = new ToolCallArgumentResolver();

        $metadata = new Tool(new ExecutionReference(ToolArrayMultidimensional::class, '__invoke'), 'tool_array_multidimensional', 'A tool with multidimensional array parameters');
        $toolCall = new ToolCall('tool_id_1234', 'tool_array_multidimensional', [
            'vectors' => [[1.2, 3.4], [4.5, 5.6]],
            'sequences' => ['first' => [1, 2, 3], 'second' => [4, 5, 6]],
            'objects' => [[['some' => 'a'], ['some' => 'b']]],
        ]);

        $expected = [
            'vectors' => [[1.2, 3.4], [4.5, 5.6]],
            'sequences' => ['first' => [1, 2, 3], 'second' => [4, 5, 6]],
            'objects' => [[new SomeStructure('a'), new SomeStructure('b')]],
        ];

        $this->assertEquals($expected, $resolver->resolveArguments($metadata, $toolCall));
    }

    public function testIgnoreExtraArguments()
    {
        $resolver = new ToolCallArgumentResolver();

        $metadata = new Tool(new ExecutionReference(ToolNoParams::class, '__invoke'), 'tool_no_params', 'A tool without params');
        $toolCall = new ToolCall('tool_id_1234', 'tool_no_params', [
            'foo' => 1,
            'bar' => 2,
            'baz' => 3,
        ]);

        $this->assertSame([], $resolver->resolveArguments($metadata, $toolCall));
    }
}
