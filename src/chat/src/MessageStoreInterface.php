<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat;

use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface MessageStoreInterface
{
    /**
     * @param string|null $id If null, the current message bag will be stored under an auto-generated UUID accessible via {@see ChatInterface::getId()}
     */
    public function save(MessageBag $messages, ?string $id = null): void;

    public function load(?string $id = null): MessageBag;

    public function clear(?string $id = null): void;
}
