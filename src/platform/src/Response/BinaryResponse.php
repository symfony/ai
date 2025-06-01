<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Response;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class BinaryResponse extends BaseResponse
{
    public function __construct(
        public string $data,
        public ?string $mimeType = null,
    ) {
    }

    public function getContent(): string
    {
        return $this->data;
    }

    public function toBase64(): string
    {
        return base64_encode($this->data);
    }

    public function toDataUri(): string
    {
        if (null === $this->mimeType) {
            throw new RuntimeException('Mime type is not set.');
        }

        return 'data:'.$this->mimeType.';base64,'.$this->toBase64();
    }
}
