<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Ocr\Result;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OcrResult
{
    /**
     * @param BoundingBox[]             $boundingBoxes
     * @param array<string, mixed>|null $originalResponse
     */
    public function __construct(
        private readonly string $text,
        private readonly array $boundingBoxes = [],
        private readonly ?string $provider = null,
        private readonly ?float $cost = null,
        private readonly ?array $originalResponse = null,
    ) {
    }

    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @return BoundingBox[]
     */
    public function getBoundingBoxes(): array
    {
        return $this->boundingBoxes;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function getCost(): ?float
    {
        return $this->cost;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOriginalResponse(): ?array
    {
        return $this->originalResponse;
    }
}
