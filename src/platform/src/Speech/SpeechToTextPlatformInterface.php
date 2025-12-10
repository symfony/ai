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
interface SpeechToTextPlatformInterface
{
    public function listen(object|array|string $input, array $options): Text;
}
