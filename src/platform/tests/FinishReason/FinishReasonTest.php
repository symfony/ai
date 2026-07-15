<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\FinishReason;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;

final class FinishReasonTest extends TestCase
{
    public function testItKeepsTheNormalizedCaseAndTheRawProviderValue()
    {
        $finishReason = new FinishReason(FinishReasonCase::LENGTH, 'MAX_TOKENS');

        $this->assertSame(FinishReasonCase::LENGTH, $finishReason->getCase());
        $this->assertSame('MAX_TOKENS', $finishReason->getRaw());
    }

    public function testItComparesCases()
    {
        $finishReason = new FinishReason(FinishReasonCase::LENGTH, 'max_tokens');

        $this->assertTrue($finishReason->is(FinishReasonCase::LENGTH));
        $this->assertTrue($finishReason->is(FinishReasonCase::STOP, FinishReasonCase::LENGTH));
        $this->assertFalse($finishReason->is(FinishReasonCase::STOP));
        $this->assertFalse($finishReason->is());
    }

    public function testItSerializesToJson()
    {
        $this->assertSame(
            '{"case":"length","raw":"MAX_TOKENS"}',
            json_encode(new FinishReason(FinishReasonCase::LENGTH, 'MAX_TOKENS')),
        );
    }

    public function testItCastsToTheRawProviderValue()
    {
        $this->assertSame('end_turn', (string) new FinishReason(FinishReasonCase::STOP, 'end_turn'));
    }
}
