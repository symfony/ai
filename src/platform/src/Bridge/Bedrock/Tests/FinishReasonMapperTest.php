<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bedrock\FinishReasonMapper;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;

final class FinishReasonMapperTest extends TestCase
{
    #[DataProvider('provideFinishReasons')]
    public function testItMapsTheProviderVocabulary(string $raw, FinishReasonCase $expected)
    {
        $finishReason = FinishReasonMapper::map($raw);

        $this->assertSame($expected, $finishReason->getCase());
        $this->assertSame($raw, $finishReason->getRaw());
    }

    /**
     * @return iterable<string, array{string, FinishReasonCase}>
     */
    public static function provideFinishReasons(): iterable
    {
        yield 'converse end_turn' => ['end_turn', FinishReasonCase::STOP];
        yield 'llama stop' => ['stop', FinishReasonCase::STOP];
        yield 'converse max_tokens' => ['max_tokens', FinishReasonCase::LENGTH];
        yield 'llama length' => ['length', FinishReasonCase::LENGTH];
        yield 'tool_use' => ['tool_use', FinishReasonCase::TOOL_CALL];
        yield 'guardrail_intervened' => ['guardrail_intervened', FinishReasonCase::CONTENT_FILTER];
        yield 'content_filtered' => ['content_filtered', FinishReasonCase::CONTENT_FILTER];
    }

    public function testItReturnsNullForAMissingReason()
    {
        $this->assertNull(FinishReasonMapper::map(null));
        $this->assertNull(FinishReasonMapper::map(''));
    }
}
