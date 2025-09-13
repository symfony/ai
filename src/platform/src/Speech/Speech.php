<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Speech;

use Symfony\AI\Platform\Result\DeferredResult;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Speech
{
    public function __construct(
        private readonly DeferredResult $result,
    ) {
    }

    public function asBinary(): string
    {
        return $this->result->asBinary();
    }

    public function asBase64(): string
    {
        return base64_encode($this->asBinary());
    }

    public function asDataUri(string $mimeType = 'audio/mpeg'): string
    {
        return \sprintf('data:%s;base64,%s', $mimeType, $this->asBase64());
    }
}
