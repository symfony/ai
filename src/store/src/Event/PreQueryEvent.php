<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched before documents are retrieved from the store.
 *
 * Listeners can modify the query string and options, for example to expand
 * the query, correct spelling, inject synonyms, or adjust options like semanticRatio.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PreQueryEvent extends Event
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private string $query,
        private array $options = [],
    ) {
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function setQuery(string $query): void
    {
        $this->query = $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
