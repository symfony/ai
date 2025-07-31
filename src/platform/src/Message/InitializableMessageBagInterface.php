<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface InitializableMessageBagInterface
{
    /**
     * @param array<mixed> $options
     */
    public function initialize(array $options = []): void;
}
