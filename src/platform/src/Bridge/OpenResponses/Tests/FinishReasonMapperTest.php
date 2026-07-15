<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenResponses\FinishReasonMapper;
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
        yield 'completed' => ['completed', FinishReasonCase::STOP];
        yield 'max_output_tokens' => ['max_output_tokens', FinishReasonCase::LENGTH];
        yield 'content_filter' => ['content_filter', FinishReasonCase::CONTENT_FILTER];
        yield 'unknown' => ['incomplete', FinishReasonCase::OTHER];
    }

    public function testItReturnsNullForAMissingReason()
    {
        $this->assertNull(FinishReasonMapper::map(null));
        $this->assertNull(FinishReasonMapper::map(''));
    }

    public function testAToolCallUpgradesACleanStop()
    {
        // The Responses API reports `completed` for tool calls too.
        $finishReason = FinishReasonMapper::map('completed', true);

        $this->assertSame(FinishReasonCase::TOOL_CALL, $finishReason->getCase());
        $this->assertSame('completed', $finishReason->getRaw());
    }

    public function testAToolCallNeverOverridesATruncation()
    {
        // A truncated response stays truncated even if the partial output contains a tool call.
        $finishReason = FinishReasonMapper::map('max_output_tokens', true);

        $this->assertSame(FinishReasonCase::LENGTH, $finishReason->getCase());
    }

    public function testAToolCallNeverOverridesAContentFilter()
    {
        $finishReason = FinishReasonMapper::map('content_filter', true);

        $this->assertSame(FinishReasonCase::CONTENT_FILTER, $finishReason->getCase());
    }
}
