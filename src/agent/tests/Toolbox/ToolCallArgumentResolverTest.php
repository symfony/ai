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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\ToolCallArgumentResolver;
use Symfony\AI\Fixtures\Tool\ToolArray;
use Symfony\AI\Fixtures\Tool\ToolDate;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

#[CoversClass(ToolCallArgumentResolver::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ExecutionReference::class)]
#[UsesClass(ToolCall::class)]
class ToolCallArgumentResolverTest extends TestCase
{
    #[Test]
    public function resolveArguments(): void
    {
        $resolver = new ToolCallArgumentResolver();

        $metadata = new Tool(new ExecutionReference(ToolDate::class, '__invoke'), 'tool_date', 'test');
        $toolCall = new ToolCall('invocation', 'tool_date', ['date' => '2025-06-29']);

        self::assertEquals(['date' => new \DateTimeImmutable('2025-06-29')], $resolver->resolveArguments($metadata, $toolCall));
    }

    #[Test]
    public function resolveScalarArrayArguments(): void
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

        self::assertSame($expected, $resolver->resolveArguments($metadata, $toolCall));
    }
}
