<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformOptions;

final class PlatformOptionsTest extends TestCase
{
    public function testNestMovesProviderOptionsUnderTheirKey()
    {
        $this->assertSame([
            PlatformOptions::TEMPERATURE => 0.5,
            PlatformOptions::STREAM => true,
            PlatformOptions::PROVIDER_OPTIONS => ['service_tier' => 'flex'],
        ], PlatformOptions::nest([
            PlatformOptions::TEMPERATURE => 0.5,
            'service_tier' => 'flex',
            PlatformOptions::STREAM => true,
        ]));
    }

    public function testNestKeepsExplicitProviderOptionsOverFlatOnes()
    {
        $this->assertSame([
            PlatformOptions::PROVIDER_OPTIONS => ['max_tokens' => 100, 'top_k' => 5],
        ], PlatformOptions::nest([
            'max_tokens' => 500,
            'top_k' => 5,
            PlatformOptions::PROVIDER_OPTIONS => ['max_tokens' => 100],
        ]));
    }

    public function testNestWithoutProviderOptions()
    {
        $this->assertSame([PlatformOptions::MAX_OUTPUT_TOKENS => 100], PlatformOptions::nest([PlatformOptions::MAX_OUTPUT_TOKENS => 100]));
    }

    public function testToProviderOptionsAddsCanonicalOptionsUnderTheirWireField()
    {
        $this->assertSame([
            'max_tokens' => 100,
            'temperature' => 0.5,
            'top_k' => 5,
        ], PlatformOptions::toProviderOptions([
            PlatformOptions::MAX_OUTPUT_TOKENS => 100,
            PlatformOptions::TEMPERATURE => 0.5,
            PlatformOptions::STREAM => true,
            PlatformOptions::PROVIDER_OPTIONS => ['top_k' => 5],
        ], [
            PlatformOptions::MAX_OUTPUT_TOKENS => 'max_tokens',
            PlatformOptions::TEMPERATURE => 'temperature',
        ]));
    }

    /**
     * @param array<string, mixed> $options
     */
    #[TestWith([[PlatformOptions::MAX_OUTPUT_TOKENS => 100, PlatformOptions::PROVIDER_OPTIONS => ['max_tokens' => 50]]])]
    #[TestWith([[PlatformOptions::PROVIDER_OPTIONS => ['max_tokens' => 50], PlatformOptions::MAX_OUTPUT_TOKENS => 100]])]
    public function testToProviderOptionsLetsTheProviderOptionWin(array $options)
    {
        $this->assertSame(['max_tokens' => 50], PlatformOptions::toProviderOptions($options, [PlatformOptions::MAX_OUTPUT_TOKENS => 'max_tokens']));
    }

    public function testToProviderOptionsKeepsTheOrderOfTheOptions()
    {
        $this->assertSame([
            'stream' => false,
            'top_p' => 0.9,
            'tools' => [],
        ], PlatformOptions::toProviderOptions([
            PlatformOptions::STREAM => false,
            PlatformOptions::PROVIDER_OPTIONS => ['top_p' => 0.9],
            PlatformOptions::TOOLS => [],
            PlatformOptions::RESPONSE_FORMAT => null,
        ], [
            PlatformOptions::STREAM => 'stream',
            PlatformOptions::TOOLS => 'tools',
            PlatformOptions::RESPONSE_FORMAT => 'response_format',
        ]));
    }

    public function testToProviderOptionsDropsCanonicalOptionsWithoutWireField()
    {
        $this->assertSame([], PlatformOptions::toProviderOptions([PlatformOptions::TEMPERATURE => 0.5]));
    }

    public function testToProviderOptionsAddsNestedWireFields()
    {
        $this->assertSame([
            'generationConfig' => ['maxOutputTokens' => 100, 'temperature' => 0.1, 'topK' => 5],
        ], PlatformOptions::toProviderOptions([
            PlatformOptions::MAX_OUTPUT_TOKENS => 100,
            PlatformOptions::TEMPERATURE => 0.5,
            PlatformOptions::PROVIDER_OPTIONS => ['generationConfig' => ['topK' => 5, 'temperature' => 0.1]],
        ], [
            PlatformOptions::MAX_OUTPUT_TOKENS => 'generationConfig.maxOutputTokens',
            PlatformOptions::TEMPERATURE => 'generationConfig.temperature',
        ]));
    }
}
