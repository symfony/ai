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
interface SpeechProviderInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function generate(DeferredResult $result, array $options): Speech;

    /**
     * @param array<string, mixed> $options
     */
    public function support(DeferredResult $result, array $options): bool;
}
