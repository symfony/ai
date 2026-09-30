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
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;

/**
 * Keys a {@see MessageBag} on the content of its messages, in order.
 *
 * The identifiers and the metadata of the bag and of its messages describe the instance,
 * not the conversation, and are left out of the hash. Identifiers a provider assigns to a
 * content part, such as a tool call id, are kept, since they are sent along with it.
 * Binary content is keyed on a hash of its bytes, as {@see FileCacheKeyGenerator} does.
 *
 * @author Tac Tacelosky <tacman@gmail.com>
 */
final class MessageBagCacheKeyGenerator implements CacheKeyGenerator
{
    public function supports(object $input): bool
    {
        return $input instanceof MessageBag;
    }

    public function generate(object $input): string
    {
        if (!$input instanceof MessageBag) {
            throw new InvalidArgumentException(\sprintf('"%s" only supports "%s" inputs, "%s" given.', self::class, MessageBag::class, get_debug_type($input)));
        }

        return hash('xxh128', serialize($this->normalize($input->getMessages())));
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof File) {
            return [$value::class, $value->getFormat(), hash('xxh128', $value->asBinary())];
        }

        if (\is_array($value)) {
            return array_map($this->normalize(...), $value);
        }

        if (!\is_object($value)) {
            return $value;
        }

        if ($value instanceof \BackedEnum) {
            return [$value::class, $value->value];
        }

        if ($value instanceof \UnitEnum) {
            return [$value::class, $value->name];
        }

        $normalized = ['class' => $value::class];

        foreach (get_mangled_object_vars($value) as $name => $property) {
            $name = substr((string) strrchr("\0".$name, "\0"), 1);

            if ($value instanceof MessageInterface && \in_array($name, ['id', 'metadata'], true)) {
                continue;
            }

            $normalized[$name] = $this->normalize($property);
        }

        return $normalized;
    }
}
