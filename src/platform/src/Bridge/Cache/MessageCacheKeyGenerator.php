<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cache;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\MessageInterface;

/**
 * Keys a bare {@see MessageInterface} input - a message handed to the platform without being
 * wrapped into a {@see \Symfony\AI\Platform\Message\MessageBag} - on a hash of its content.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageCacheKeyGenerator implements CacheKeyGenerator
{
    public function __construct(
        private readonly InputHasher $inputHasher = new InputHasher(),
    ) {
    }

    public function supports(object $input): bool
    {
        return $input instanceof MessageInterface;
    }

    public function generate(object $input): string
    {
        if (!$input instanceof MessageInterface) {
            throw new InvalidArgumentException(\sprintf('"%s" only supports "%s" inputs, "%s" given.', self::class, MessageInterface::class, get_debug_type($input)));
        }

        return $this->inputHasher->hash($input);
    }
}
