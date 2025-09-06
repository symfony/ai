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
final readonly class TextDocument
{
    public function __construct(
        public Uuid $id,
        public string $content,
        public Metadata $metadata = new Metadata(),
    ) {
        if ('' === trim($this->content)) {
            throw new InvalidArgumentException('The content shall not be an empty string.');
        }
    }

    public function withContent(string $content): self
    {
        return new self($this->id, $content, $this->metadata);
    }
}
