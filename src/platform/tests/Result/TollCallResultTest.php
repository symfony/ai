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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

final class TollCallResultTest extends TestCase
{
    public function testThrowsIfNoToolCall()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Response must have at least one tool call.');

        new ToolCallResult();
    }

    public function testGetContent()
    {
        $result = new ToolCallResult($toolCall = new ToolCall('ID', 'name', ['foo' => 'bar']));
        $this->assertSame([$toolCall], $result->getContent());
    }
}
