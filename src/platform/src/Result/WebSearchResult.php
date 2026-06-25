<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * @author Paul Hußmann <paul@hussmann-cloud.de>
 */
final class WebSearchResult extends BaseResult
{
    /**
     * @param list<string> $queries
     */
    public function __construct(
        private readonly string $id,
        private readonly string $status,
        private readonly ?string $query,
        private readonly array $queries,
    ) {
    }

    public function getContent(): ?string
    {
        return $this->query;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }

    /**
     * @return list<string>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }
}
