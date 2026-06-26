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
 * Result of a built-in file search performed server-side by the model
 * (e.g. the OpenAI Responses `file_search_call` output item).
 *
 * @phpstan-type FileSearchResultEntry array{
 *     file_id?: string,
 *     filename?: string,
 *     text?: string,
 *     score?: float,
 *     attributes?: array<string, mixed>,
 * }
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FileSearchResult extends BaseResult
{
    /**
     * @param list<string>              $queries
     * @param list<FileSearchResultEntry> $results
     */
    public function __construct(
        private readonly array $queries = [],
        private readonly array $results = [],
        private readonly ?string $id = null,
        private readonly ?string $status = null,
    ) {
    }

    /**
     * @return list<FileSearchResultEntry>
     */
    public function getContent(): array
    {
        return $this->results;
    }

    /**
     * @return list<string>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
}
