<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Job;

/**
 * Where an asynchronous job currently stands.
 *
 * Providers spell their states differently - MiniMax says `Processing`, Replicate says `starting`,
 * Anthropic says `in_progress` - so translating the wording into a {@see JobStateCase} is the
 * responsibility of the bridge, which knows its provider's vocabulary. The untouched value stays
 * available through {@see getRaw()}, mirroring how `FinishReason` handles the same problem.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class JobStatus implements \JsonSerializable, \Stringable
{
    /**
     * @param string      $raw   the untouched state reported by the provider, in its own wording
     * @param string|null $error the provider's failure message, when it reported one
     */
    public function __construct(
        private readonly JobStateCase $case,
        private readonly string $raw,
        private readonly ?string $error = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->raw;
    }

    public function getCase(): JobStateCase
    {
        return $this->case;
    }

    public function getRaw(): string
    {
        return $this->raw;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function is(JobStateCase ...$cases): bool
    {
        return \in_array($this->case, $cases, true);
    }

    public function isTerminal(): bool
    {
        return $this->case->isTerminal();
    }

    /**
     * @return array{case: string, raw: string, error: string|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'case' => $this->case->value,
            'raw' => $this->raw,
            'error' => $this->error,
        ];
    }
}
