<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\MessageStore;

use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

/**
 * Keeps message bags by their id, e.g. the conversations of an agent.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface MessageStoreInterface
{
    /**
     * @return MessageBag the stored messages, or an empty bag with the given id
     */
    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag;

    /**
     * @throws ConflictException when the stored messages don't start the given ones, i.e. they were changed after loading
     */
    public function save(MessageBag $messages): void;

    public function delete(AbstractUid&TimeBasedUidInterface $id): void;
}
