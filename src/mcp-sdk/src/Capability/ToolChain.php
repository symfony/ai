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

namespace Symfony\AI\McpSdk\Capability;

use Symfony\AI\McpSdk\Capability\Tool\CollectionInterface;
use Symfony\AI\McpSdk\Capability\Tool\IdentifierInterface;
use Symfony\AI\McpSdk\Capability\Tool\MetadataInterface;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;
use Symfony\AI\McpSdk\Capability\Tool\ToolCallResult;
use Symfony\AI\McpSdk\Capability\Tool\ToolExecutorInterface;
use Symfony\AI\McpSdk\Exception\ToolExecutionException;
use Symfony\AI\McpSdk\Exception\ToolNotFoundException;

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
