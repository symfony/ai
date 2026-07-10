<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\FinishReason;

/**
 * Why a model stopped generating, normalized across providers.
 *
 * Exposed as the `finish_reason` result metadata by every bridge whose provider reports it, for both
 * buffered and streamed results:
 *
 *     $finishReason = $result->getMetadata()->get('finish_reason');
 *
 *     if ($finishReason?->is(FinishReasonCase::LENGTH)) {
 *         // the response was truncated by the output token limit
 *     }
 *
 * Providers spell the reason differently. Translating their wording into a {@see FinishReasonCase} is
 * the responsibility of the bridge, which knows its provider's vocabulary — see the `FinishReasonMapper`
 * next to each bridge's result converter, mirroring how `TokenUsageExtractor` is done per bridge.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class FinishReason implements \JsonSerializable, \Stringable
{
    /**
     * @param string $raw the untouched value reported by the provider, in its own wording
     */
    public function __construct(
        private readonly FinishReasonCase $case,
        private readonly string $raw,
    ) {
    }

    public function __toString(): string
    {
        return $this->raw;
    }

    public function getCase(): FinishReasonCase
    {
        return $this->case;
    }

    public function getRaw(): string
    {
        return $this->raw;
    }

    public function is(FinishReasonCase ...$cases): bool
    {
        return \in_array($this->case, $cases, true);
    }

    /**
     * @return array{case: string, raw: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'case' => $this->case->value,
            'raw' => $this->raw,
        ];
    }
}
