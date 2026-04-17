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
        private readonly ?string $term = null,
        private readonly ?string $regex = null,
        private readonly ?string $level = null,
        private readonly ?string $channel = null,
        private readonly ?\DateTimeInterface $from = null,
        private readonly ?\DateTimeInterface $to = null,
        private readonly ?string $contextKey = null,
        private readonly ?string $contextValue = null,
        private readonly int $limit = 100,
        private readonly int $offset = 0,
    ) {
    }

    public function getTerm(): ?string
    {
        return $this->term;
    }

    public function getRegex(): ?string
    {
        return $this->regex;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function getFrom(): ?\DateTimeInterface
    {
        return $this->from;
    }

    public function getTo(): ?\DateTimeInterface
    {
        return $this->to;
    }

    public function getContextKey(): ?string
    {
        return $this->contextKey;
    }

    public function getContextValue(): ?string
    {
        return $this->contextValue;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function matches(LogEntry $entry): bool
    {
        if (null !== $this->level && strtoupper($this->level) !== strtoupper($entry->getLevel())) {
            return false;
        }

        if (null !== $this->channel && strtolower($this->channel) !== strtolower($entry->getChannel())) {
            return false;
        }

        if (null !== $this->from && $entry->getDatetime() < $this->from) {
            return false;
        }

        if (null !== $this->to && $entry->getDatetime() > $this->to) {
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
