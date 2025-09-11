<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

#[CoversClass(ToolCallResult::class)]
#[UsesClass(ToolCall::class)]
#[Small]
final class TollCallResultTest extends TestCase
{
    public function testThrowsIfNoToolCall()
    {
        $this->expectException(\Symfony\AI\Platform\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Response must have at least one tool call.');

        new ToolCallResult();
    }

    public function testGetContent()
    {
        $result = new ToolCallResult($toolCall = new ToolCall('ID', 'name', ['foo' => 'bar']));
        $this->assertSame([$toolCall], $result->getContent());
    }
}
