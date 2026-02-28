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
}
