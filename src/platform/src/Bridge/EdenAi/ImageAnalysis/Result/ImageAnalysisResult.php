<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\ImageAnalysis\Result;

/**
 * Envelope for the Eden AI image analysis expert models (object detection, explicit
 * content detection, ...). The output shape depends on the subfeature, but all of them
 * expose a list of detected items.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ImageAnalysisResult
{
    /**
     * @param array<string, mixed>      $output
     * @param array<string, mixed>|null $originalResponse
     */
    public function __construct(
        private readonly array $output,
        private readonly ?string $provider = null,
        private readonly ?float $cost = null,
        private readonly ?array $originalResponse = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getOutput(): array
    {
        return $this->output;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getItems(): array
    {
        return $this->output['items'] ?? [];
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
