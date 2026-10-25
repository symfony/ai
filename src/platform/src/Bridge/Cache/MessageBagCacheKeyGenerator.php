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
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Serializer\MessageNormalizer;

/**
 * @author Tac Tacelosky <tacman@gmail.com>
 */
final class MessageBagCacheKeyGenerator implements CacheKeyGenerator
{
    public function __construct(
        private readonly MessageNormalizer $normalizer = new MessageNormalizer(),
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

        // ids and metadata differ between bags carrying the same conversation
        $messages = array_map(function (MessageInterface $message): array {
            $normalized = $this->normalizer->normalize($message);
            unset($normalized['id'], $normalized['metadata'], $normalized['addedAt']);

            return $normalized;
        }, $input->getMessages());

        return hash('xxh128', json_encode($messages, \JSON_THROW_ON_ERROR));
    }
}
