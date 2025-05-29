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

use Symfony\AI\McpSdk\Capability\Resource\CollectionInterface;
use Symfony\AI\McpSdk\Capability\Resource\IdentifierInterface;
use Symfony\AI\McpSdk\Capability\Resource\MetadataInterface;
use Symfony\AI\McpSdk\Capability\Resource\ResourceRead;
use Symfony\AI\McpSdk\Capability\Resource\ResourceReaderInterface;
use Symfony\AI\McpSdk\Capability\Resource\ResourceReadResult;
use Symfony\AI\McpSdk\Exception\ResourceNotFoundException;
use Symfony\AI\McpSdk\Exception\ResourceReadException;

/**
 * A collection of resources. All resources need to implement IdentifierInterface.
 */
class ResourceChain implements CollectionInterface, ResourceReaderInterface
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

    public function read(ResourceRead $input): ResourceReadResult
    {
        foreach ($this->items as $item) {
            if ($item instanceof ResourceReaderInterface && $input->uri === $item->getUri()) {
                try {
                    return $item->read($input);
                } catch (\Throwable $e) {
                    throw new ResourceReadException($input, $e);
                }
            }
        }

        throw new ResourceNotFoundException($input);
    }
}
