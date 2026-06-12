<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Ocr\Result;

/**
 * A single page of a Mistral OCR result.
 *
 * @author Tac Tacelosky <tacman@gmail.com>
 */
final class Page
{
    /**
     * @param Image[]                   $images
     * @param array<string, mixed>|null $dimensions
     */
    public function __construct(
        private readonly int $index,
        private readonly string $markdown,
        private readonly array $images = [],
        private readonly ?array $dimensions = null,
        private readonly ?string $documentAnnotation = null,
    ) {
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getMarkdown(): string
    {
        return $this->markdown;
    }

    /**
     * @return Image[]
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDimensions(): ?array
    {
        return $this->dimensions;
    }

    public function getDocumentAnnotation(): ?string
    {
        return $this->documentAnnotation;
    }
}
