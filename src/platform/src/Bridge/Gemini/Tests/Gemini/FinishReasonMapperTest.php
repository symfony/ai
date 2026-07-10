<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Gemini;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\FinishReasonMapper;
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
        yield 'STOP' => ['STOP', FinishReasonCase::STOP];
        yield 'MAX_TOKENS' => ['MAX_TOKENS', FinishReasonCase::LENGTH];
        yield 'SAFETY' => ['SAFETY', FinishReasonCase::CONTENT_FILTER];
        yield 'PROHIBITED_CONTENT' => ['PROHIBITED_CONTENT', FinishReasonCase::CONTENT_FILTER];
        yield 'RECITATION has no equivalent' => ['RECITATION', FinishReasonCase::OTHER];
        yield 'MALFORMED_FUNCTION_CALL is not a tool call' => ['MALFORMED_FUNCTION_CALL', FinishReasonCase::OTHER];
    }

    public function testItReturnsNullForAMissingReason()
    {
        $this->assertNull(FinishReasonMapper::map(null));
        $this->assertNull(FinishReasonMapper::map(''));
    }
}
