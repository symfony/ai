<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Metadata;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
trait ChatAwareTrait
{
    private ?string $chat = null;

    public function setChat(?string $chat): void
    {
        $this->chat = $chat;
    }

    public function getChat(): ?string
    {
        return $this->chat;
    }
}
