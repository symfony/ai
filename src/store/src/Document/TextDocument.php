<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document;

use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextDocument implements EmbeddableDocumentInterface
{
    public function __construct(
        private readonly Uuid $id,
        private readonly string $content,
        private readonly Metadata $metadata = new Metadata(),
    ) {
        if ('' === trim($this->content)) {
            throw new InvalidArgumentException('The content shall not be an empty string.');
        }
    }

    public function withContent(string $content): self
    {
        return new self($this->id, $content, $this->metadata);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getMetadata(): Metadata
    {
        return $this->metadata;
    }
}
