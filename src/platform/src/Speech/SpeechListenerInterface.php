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

use Symfony\AI\Platform\Message\Content\Text;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface SpeechListenerInterface
{
    /**
     * @param array<mixed>|string|object $input   The input data
     * @param array<string, mixed>       $options The options to customize the text generation
     */
    public function listen(array|string|object $input, array $options): Text;

    /**
     * @param array<mixed>|string|object $input
     * @param array<string, mixed>       $options
     */
    public function support(array|string|object $input, array $options): bool;
}
