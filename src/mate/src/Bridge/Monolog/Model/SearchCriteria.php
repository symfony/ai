<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Monolog\Model;

/**
 * Search criteria for filtering log entries.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SearchCriteria
{
    public function __construct(
        public readonly ?string $term = null,
        public readonly ?string $regex = null,
        public readonly ?string $level = null,
        public readonly ?string $channel = null,
        public readonly ?\DateTimeInterface $from = null,
        public readonly ?\DateTimeInterface $to = null,
        public readonly ?string $contextKey = null,
        public readonly ?string $contextValue = null,
        public readonly int $limit = 100,
        public readonly int $offset = 0,
    ) {
    }

    public function matches(LogEntry $entry): bool
    {
        if (null !== $this->level && strtoupper($this->level) !== strtoupper($entry->level)) {
            return false;
        }

        if (null !== $this->channel && strtolower($this->channel) !== strtolower($entry->channel)) {
            return false;
        }

        if (null !== $this->from && $entry->datetime < $this->from) {
            return false;
        }

        if (null !== $this->to && $entry->datetime > $this->to) {
            return false;
        }

        if (null !== $this->term && !$entry->matchesTerm($this->term)) {
            return false;
        }

        if (null !== $this->regex && !$entry->matchesRegex($this->regex)) {
            return false;
        }

        if (null !== $this->contextKey && null !== $this->contextValue) {
            if (!$entry->hasContextValue($this->contextKey, $this->contextValue)) {
                return false;
            }
        }

        return true;
    }
}
