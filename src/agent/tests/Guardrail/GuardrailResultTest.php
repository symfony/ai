<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Guardrail;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Guardrail\GuardrailResult;

final class GuardrailResultTest extends TestCase
{
    public function testPassReturnsNonTriggeredResult()
    {
        $result = GuardrailResult::pass();

        $this->assertFalse($result->isTriggered());
        $this->assertNull($result->getReason());
        $this->assertSame(0.0, $result->getScore());
        $this->assertNull($result->getScanner());
    }

    public function testBlockReturnsTriggeredResult()
    {
        $result = GuardrailResult::block('test_scanner', 'Blocked for testing', 0.95);

        $this->assertTrue($result->isTriggered());
        $this->assertSame('Blocked for testing', $result->getReason());
        $this->assertSame(0.95, $result->getScore());
        $this->assertSame('test_scanner', $result->getScanner());
    }

    public function testBlockWithDefaultScore()
    {
        $result = GuardrailResult::block('scanner', 'reason');

        $this->assertTrue($result->isTriggered());
        $this->assertSame(1.0, $result->getScore());
    }
}
