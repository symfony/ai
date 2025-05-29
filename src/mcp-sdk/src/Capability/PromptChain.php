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

use PhpLlm\McpSdk\Capability\Prompt\CollectionInterface;
use PhpLlm\McpSdk\Capability\Prompt\IdentifierInterface;
use PhpLlm\McpSdk\Capability\Prompt\MetadataInterface;
use PhpLlm\McpSdk\Capability\Prompt\PromptGet;
use PhpLlm\McpSdk\Capability\Prompt\PromptGetResult;
use PhpLlm\McpSdk\Capability\Prompt\PromptGetterInterface;
use PhpLlm\McpSdk\Exception\PromptGetException;
use PhpLlm\McpSdk\Exception\PromptNotFoundException;

/**
 * A collection of prompts. All prompts need to implement IdentifierInterface.
 */
class PromptChain implements PromptGetterInterface, CollectionInterface
{
    public function __construct(
        /**
         * @var IdentifierInterface[]
         */
        private readonly array $items,
    ) {
    }

    public function getMetadata(): array
    {
        return array_filter($this->items, fn ($item) => $item instanceof MetadataInterface);
    }

    public function get(PromptGet $input): PromptGetResult
    {
        foreach ($this->items as $item) {
            if ($item instanceof PromptGetterInterface && $input->name === $item->getName()) {
                try {
                    return $item->get($input);
                } catch (\Throwable $e) {
                    throw new PromptGetException($input, $e);
                }
            }
        }

        throw new PromptNotFoundException($input);
    }
}
