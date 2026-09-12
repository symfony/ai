<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Albert\Tests\Completions;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Albert\Completions\ResultConverter;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

final class ResultConverterTest extends TestCase
{
    private const CARBON = [
        'kWh' => ['min' => 5.7095659415205885e-06, 'max' => 5.7095659415205885e-06],
        'kgCO2eq' => ['min' => 4.5809521528648614e-07, 'max' => 4.5809521528648614e-07],
    ];

    public function testCarbonFootprintIsExposedAsResultMetadata()
    {
        $result = (new ResultConverter())->convert(new InMemoryRawResult([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => 'Bonjour'], 'finish_reason' => 'stop'],
            ],
            'usage' => ['prompt_tokens' => 7, 'completion_tokens' => 4, 'total_tokens' => 11, 'carbon' => self::CARBON],
        ], [], $this->httpResponseStub()));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame(self::CARBON, $result->getMetadata()->get('carbon'));
    }

    public function testResultMetadataHasNoCarbonWhenNotReported()
    {
        $result = (new ResultConverter())->convert(new InMemoryRawResult([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => 'Bonjour'], 'finish_reason' => 'stop'],
            ],
            'usage' => ['prompt_tokens' => 7, 'completion_tokens' => 4, 'total_tokens' => 11],
        ], [], $this->httpResponseStub()));

        $this->assertNull($result->getMetadata()->get('carbon'));
    }

    public function testStreamedCarbonFootprintIsYieldedAsMetadataDelta()
    {
        $result = (new ResultConverter())->convert($this->streamedRawResult(), ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $metadataDeltas = array_values(array_filter(
            iterator_to_array($result->getContent()),
            static fn (DeltaInterface $delta) => $delta instanceof MetadataDelta && 'carbon' === $delta->getKey(),
        ));

        $this->assertCount(1, $metadataDeltas);
        $this->assertSame(self::CARBON, $metadataDeltas[0]->getValue());
    }

    public function testStreamedCarbonFootprintIsPromotedToResultMetadata()
    {
        $deferredResult = new DeferredResult(new ResultConverter(), $this->streamedRawResult(), ['stream' => true]);

        foreach ($deferredResult->asStream() as $delta) {
            $this->assertNotInstanceOf(MetadataDelta::class, $delta);
        }

        $this->assertSame(self::CARBON, $deferredResult->getMetadata()->get('carbon'));
    }

    public function testOnlyTheLastStreamedUsageIsReported()
    {
        $deferredResult = new DeferredResult(new ResultConverter(), $this->streamedRawResult(), ['stream' => true]);

        iterator_to_array($deferredResult->asStream());

        $tokenUsage = $deferredResult->getMetadata()->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(7, $tokenUsage->getPromptTokens());
        $this->assertSame(4, $tokenUsage->getCompletionTokens());
        $this->assertSame(11, $tokenUsage->getTotalTokens());
    }

    private function streamedRawResult(): InMemoryRawResult
    {
        return new InMemoryRawResult([], [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Bonjour']]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']]],
            // Albert appends its own usage-only chunk, carrying the footprint, after the one of
            // the inference server it routes to
            ['choices' => [], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 4, 'total_tokens' => 14]],
            ['choices' => [], 'usage' => ['prompt_tokens' => 7, 'completion_tokens' => 4, 'total_tokens' => 11, 'carbon' => self::CARBON]],
        ], $this->httpResponseStub());
    }

    private function httpResponseStub(): object
    {
        return new class {
            public function getStatusCode(): int
            {
                return 200;
            }
        };
    }
}
