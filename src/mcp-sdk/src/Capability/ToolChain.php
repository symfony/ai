<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpLlm\McpSdk\Capability;

use PhpLlm\McpSdk\Capability\Tool\CollectionInterface;
use PhpLlm\McpSdk\Capability\Tool\IdentifierInterface;
use PhpLlm\McpSdk\Capability\Tool\MetadataInterface;
use PhpLlm\McpSdk\Capability\Tool\ToolCall;
use PhpLlm\McpSdk\Capability\Tool\ToolCallResult;
use PhpLlm\McpSdk\Capability\Tool\ToolExecutorInterface;
use PhpLlm\McpSdk\Exception\ToolExecutionException;
use PhpLlm\McpSdk\Exception\ToolNotFoundException;

/**
 * A collection of tools. All tools need to implement IdentifierInterface.
 */
class ToolChain implements ToolExecutorInterface, CollectionInterface
{
    public function __construct(
        /**
         * @var IdentifierInterface[] $items
         */
        private readonly array $items,
    ) {
    }

    public function getMetadata(): array
    {
        return array_filter($this->items, fn ($item) => $item instanceof MetadataInterface);
    }

    public function call(ToolCall $input): ToolCallResult
    {
        foreach ($this->items as $item) {
            if ($item instanceof ToolExecutorInterface && $input->name === $item->getName()) {
                try {
                    return $item->call($input);
                } catch (\Throwable $e) {
                    throw new ToolExecutionException($input, $e);
                }
            }
        }

        throw new ToolNotFoundException($input);
    }
}
