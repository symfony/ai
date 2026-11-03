<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message\Content;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ToolSearch implements ContentInterface
{
    /**
     * @param string|null  $query     The query the model sent to the tool search, or null when the provider did not report one
     * @param string|null  $id        Identifier of the tool search call, as assigned by the provider
     * @param string|null  $status    Provider-reported status of the call, e.g. "completed" or an error code
     * @param list<string> $toolNames Names of the deferred tools the search made available
     * @param string|null  $signature Provider-scoped signature required to replay the original tool search output
     */
    public function __construct(
        private readonly ?string $query = null,
        private readonly ?string $id = null,
        private readonly ?string $status = null,
        private readonly array $toolNames = [],
        private readonly ?string $signature = null,
    ) {
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
    public function getToolNames(): array
    {
        return $this->toolNames;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }
}
