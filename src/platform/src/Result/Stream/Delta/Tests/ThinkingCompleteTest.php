<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Stream\Delta\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;

final class ThinkingCompleteTest extends TestCase
{
    public function testThinkingCompleteWithSignature()
    {
        $content = new ThinkingComplete('Let me think about this...', 'sig_abc123');

        $this->assertSame('Let me think about this...', $content->getThinking());
        $this->assertSame('sig_abc123', $content->getSignature());
    }

    public function testThinkingCompleteWithoutSignature()
    {
        $content = new ThinkingComplete('reasoning here');

        $this->assertSame('reasoning here', $content->getThinking());
        $this->assertNull($content->getSignature());
    }
}
