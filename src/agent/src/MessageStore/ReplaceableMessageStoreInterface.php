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

use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Illia Vasylevskyi <ineersa@gmail.com>
 */
interface ReplaceableMessageStoreInterface
{
    /** Resolve the caller's unmerged input against the stored conversation with the same ID. */
    public function resume(MessageBag $input): MessageBag;

    /** Atomically replace the conversation only if its stored messages still match $expected. */
    public function replace(MessageBag $expected, MessageBag $replacement): void;
}
