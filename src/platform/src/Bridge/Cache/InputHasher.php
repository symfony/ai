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
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;

/**
 * Builds a deterministic, content-based hash from the input passed to a platform.
 *
 * Normalization is delegated to the platform {@see Contract} serializer without a model bound to the
 * context, so the provider-specific (model-gated) normalizers stay inactive and the base normalizers
 * produce a stable, provider-agnostic representation. The hash excludes every non-deterministic
 * identifier (the random {@see \Symfony\Component\Uid\Uuid::v7()} assigned to {@see MessageBag} and
 * each {@see MessageInterface}, as well as the lazy {@see \Symfony\AI\Platform\Metadata\Metadata}) so
 * that two inputs carrying the exact same logical content always resolve to the same cache key,
 * even when built from separate object instances.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class InputHasher
{
    private Contract $contract;

    public function __construct(?Contract $contract = null)
    {
        $this->contract = $contract ?? Contract::create();
    }

    /**
     * @param array<mixed>|string|object $input
     */
    public function hash(array|string|object $input): string
    {
        return match (true) {
            \is_string($input) => md5($input),
            \is_array($input) => md5(json_encode($input, \JSON_THROW_ON_ERROR)),
            $input instanceof MessageBag => $this->hashMessageBag($input),
            $input instanceof MessageInterface => $this->hashMessageBag(new MessageBag($input)),
            default => throw new InvalidArgumentException(\sprintf('Unsupported input type: %s', get_debug_type($input))),
        };
    }

    private function hashMessageBag(MessageBag $input): string
    {
        try {
            $normalized = $this->contract->normalize($input);

            return md5(json_encode(
                $normalized,
                \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
            ));
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Unable to compute a deterministic cache key for the given input.', 0, $e);
        }
    }
}
