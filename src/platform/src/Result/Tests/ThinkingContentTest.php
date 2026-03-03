<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\ThinkingContent;

final class ThinkingContentTest extends TestCase
{
    public function testThinkingContentWithSignature()
    {
        $content = new ThinkingContent('Let me think about this...', 'sig_abc123');

        $this->assertSame('Let me think about this...', $content->thinking);
        $this->assertSame('sig_abc123', $content->signature);
    }

    public function testThinkingContentWithoutSignature()
    {
        $content = new ThinkingContent('reasoning here');

        $this->assertSame('reasoning here', $content->thinking);
        $this->assertNull($content->signature);
    }
}
