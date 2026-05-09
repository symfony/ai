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
use Symfony\AI\Platform\Result\ThinkingResult;

final class ThinkingResultTest extends TestCase
{
    public function testGetContentAndSignature()
    {
        $result = new ThinkingResult('Thinking step by step…', 'sig_abc');

        $this->assertSame('Thinking step by step…', $result->getContent());
        $this->assertSame('sig_abc', $result->getSignature());
    }

    public function testDefaultsToNullContentAndSignature()
    {
        $result = new ThinkingResult();

        $this->assertNull($result->getContent());
        $this->assertNull($result->getSignature());
    }
}
