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
 * Result of a built-in web search performed server-side by the model
 * (e.g. the OpenAI Responses `web_search_call` output item).
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class WebSearchResult extends BaseResult
{
    public function __construct(
        private readonly ?string $query = null,
        private readonly ?string $id = null,
        private readonly ?string $status = null,
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
}
