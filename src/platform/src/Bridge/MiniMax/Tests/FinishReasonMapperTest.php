<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\MiniMax\FinishReasonMapper;
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
        yield 'stop' => ['stop', FinishReasonCase::STOP];
        yield 'length' => ['length', FinishReasonCase::LENGTH];
        yield 'max_tokens' => ['max_tokens', FinishReasonCase::LENGTH];
        yield 'tool_calls' => ['tool_calls', FinishReasonCase::TOOL_CALL];
        yield 'content_filter' => ['content_filter', FinishReasonCase::CONTENT_FILTER];
    }

    public function testItReturnsNullForAMissingReason()
    {
        $this->assertNull(FinishReasonMapper::map(null));
        $this->assertNull(FinishReasonMapper::map(''));
    }
}
