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

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class AudioResult extends BaseResult
{
    public function __construct(
        private readonly string $path,
        private readonly string $mimeType,
    ) {
    }

    public function getContent(): string
    {
        return file_get_contents($this->path);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }
}
