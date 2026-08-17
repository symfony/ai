<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\DocumentParser\Result;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class DocumentParsingResult
{
    /**
     * The extracted data is returned as-is from Eden AI: the financial parser returns a list
     * of per-page objects while the resume and identity parsers return a single object.
     *
     * @param array<int|string, mixed>  $extractedData
     * @param array<string, mixed>|null $originalResponse
     */
    public function __construct(
        private readonly array $extractedData,
        private readonly ?string $provider = null,
        private readonly ?float $cost = null,
        private readonly ?array $originalResponse = null,
    ) {
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getExtractedData(): array
    {
        return $this->extractedData;
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
