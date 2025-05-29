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

namespace App;

use Symfony\AI\McpSdk\Capability\Resource\MetadataInterface;
use Symfony\AI\McpSdk\Capability\Resource\ResourceRead;
use Symfony\AI\McpSdk\Capability\Resource\ResourceReaderInterface;
use Symfony\AI\McpSdk\Capability\Resource\ResourceReadResult;

class ExampleResource implements MetadataInterface, ResourceReaderInterface
{
    public function read(ResourceRead $input): ResourceReadResult
    {
        return new ResourceReadResult(
            'Content of '.$this->getName(),
            $this->getUri(),
        );
    }

    public function getUri(): string
    {
        return 'file:///project/src/main.rs';
    }

    public function getName(): string
    {
        return 'My resource';
    }

    public function getDescription(): ?string
    {
        return 'This is just an example';
    }

    public function getMimeType(): ?string
    {
        return null;
    }

    public function getSize(): ?int
    {
        return null;
    }
}
