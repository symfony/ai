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
 * Result of a built-in web search performed server-side by the model.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class WebSearchResult extends BaseResult
{
    /**
     * @param string|null  $query     The search query the model sent to the web search tool, or null when the action carried no query (e.g. opening or reading a page)
     * @param string|null  $id        Identifier of the web search call output item, as assigned by the provider (e.g. "ws_...")
     * @param string|null  $status    Provider-reported status of the call, e.g. "completed", "searching" or "failed"
     * @param list<string> $queries   All queries when the model issued multiple searches in a single call
     * @param string|null  $signature Provider-scoped signature required to replay the original web search output
     */
    public function __construct(
        private readonly ?string $query = null,
        private readonly ?string $id = null,
        private readonly ?string $status = null,
        private readonly array $queries = [],
        private readonly ?string $signature = null,
    ) {
    }

    public function getContent(): ?string
    {
        return $this->query;
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @return list<string>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }
}
