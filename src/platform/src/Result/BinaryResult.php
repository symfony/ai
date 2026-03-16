<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class BinaryResult extends BaseResult
{
    public function __construct(
        private string $data,
        private ?string $mimeType = null,
    ) {
    }

    public static function fromBase64(string $base64Data, ?string $mimeType = null): self
    {
        $data = base64_decode($base64Data, true);

        if (false === $data) {
            throw new RuntimeException('The provided data is not valid base64-encoded data.');
        }

        return new self($data, $mimeType);
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getContent(): string
    {
        return $this->data;
    }

    public function toBase64(): string
    {
        return base64_encode($this->data);
    }

    public function toDataUri(?string $mimeType = null): string
    {
        if (null === ($mimeType ?? $this->mimeType)) {
            throw new RuntimeException('Mime type is not set.');
        }

        return 'data:'.($mimeType ?? $this->mimeType).';base64,'.$this->toBase64();
    }
}
