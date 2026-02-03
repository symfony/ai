<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Compression;

use Symfony\AI\Platform\Message\MessageBag;

/**
 * Strategy for compressing conversation history.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface CompressionStrategyInterface
{
    /**
     * Determines whether the message bag should be compressed.
     */
    public function shouldCompress(MessageBag $messages): bool;

    /**
     * Compresses the message bag and returns a new, smaller message bag.
     */
    public function compress(MessageBag $messages): MessageBag;
}
