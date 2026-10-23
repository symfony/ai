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
use Symfony\AI\Platform\Message\MessageBag;

/**
 * Keys a {@see MessageBag} on a hash of its content - every message of the bag, the system prompt
 * and the prior turns included - so that two bags carrying the same conversation resolve to the
 * same key even when they are separate instances.
 *
 * @author Tac Tacelosky <tacman@gmail.com>
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageBagCacheKeyGenerator implements CacheKeyGenerator
{
    public function __construct(
        private readonly InputHasher $inputHasher = new InputHasher(),
    ) {
    }

    public function supports(object $input): bool
    {
        return $input instanceof MessageBag;
    }

    public function generate(object $input): string
    {
        if (!$input instanceof MessageBag) {
            throw new InvalidArgumentException(\sprintf('"%s" only supports "%s" inputs, "%s" given.', self::class, MessageBag::class, get_debug_type($input)));
        }

        return $this->inputHasher->hash($input);
    }
}
