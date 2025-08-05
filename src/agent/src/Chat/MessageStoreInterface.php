<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Chat;

use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

interface MessageStoreInterface
{
    public function save(MessageBagInterface $messages): void;

    public function load(AbstractUid&TimeBasedUidInterface $session): MessageBagInterface;

    public function clear(AbstractUid&TimeBasedUidInterface $session): void;
}
