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

/**
 * A message store that needs infrastructure, like a table or an index, to be set up before use and dropped with all its conversations.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface ManagedMessageStoreInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function setup(array $options = []): void;

    public function drop(): void;
}
