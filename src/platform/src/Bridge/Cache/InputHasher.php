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

use Symfony\AI\Platform\Contract;

/**
 * Builds a deterministic, content based hash of an input handed to a platform.
 *
 * The input can be a message bag, a message, an option array or a string. Maps are key-sorted before
 * being encoded, so that a set of options keyed in a different order resolves to the same hash, while
 * lists keep their order, which carries the order of the messages and of the content parts.
 *
 * Normalization is delegated to the platform {@see Contract} serializer without a model bound to
 * the context, so the provider specific (model gated) normalizers stay inactive and the base ones
 * produce a stable, provider agnostic representation. The hash therefore excludes every
 * non-deterministic identifier - the random {@see \Symfony\Component\Uid\Uuid::v7()} carried by a
 * {@see \Symfony\AI\Platform\Message\MessageBag} and by each
 * {@see \Symfony\AI\Platform\Message\MessageInterface}, as well as the lazily populated
 * {@see \Symfony\AI\Platform\Metadata\Metadata} - so that two inputs carrying the exact same
 * logical content always resolve to the same cache key, even when built from separate instances.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class InputHasher
{
    public function __construct(
        private ?Contract $contract = null,
    ) {
    }

    /**
     * @param array<mixed>|string|object $input
     *
     * @throws UncacheableInputException When the input cannot be normalized into a deterministic representation
     */
    public function hash(array|string|object $input): string
    {
        $contract = $this->contract ??= Contract::create();

        try {
            $normalized = $contract->normalize($input);
        } catch (\Throwable $e) {
            throw new UncacheableInputException(\sprintf('The contract cannot normalize "%s" into a deterministic cache key.', get_debug_type($input)), previous: $e);
        }

        try {
            $encoded = json_encode(self::canonicalize($normalized), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            throw new UncacheableInputException(\sprintf('The normalized representation of "%s" cannot be encoded into a deterministic cache key.', get_debug_type($input)), previous: $e);
        }

        return hash('xxh128', $encoded);
    }

    /**
     * Sorts the keys of every nested map so that two logically identical structures encode identically.
     * Lists keep their order, which is significant: it carries the order of the messages and of the
     * content parts.
     */
    private static function canonicalize(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        $value = array_map(self::canonicalize(...), $value);

        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
