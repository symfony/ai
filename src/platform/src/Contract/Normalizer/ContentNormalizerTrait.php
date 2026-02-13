<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\Normalizer;

/**
 * Provides common content normalization logic for AssistantMessage normalizers.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
trait ContentNormalizerTrait
{
    /**
     * Normalizes content that may be a string, Stringable, or object to a string.
     *
     * @param \JsonSerializable|\Stringable|object|string|null $content
     * @param array<string, mixed>                             $context
     */
    private function normalizeContentToString(object|string|null $content, ?string $format, array $context): ?string
    {
        if (null === $content || \is_string($content)) {
            return $content;
        }

        if ($content instanceof \Stringable) {
            return (string) $content;
        }

        return json_encode(
            $this->normalizer->normalize($content, $format, $context),
            \JSON_THROW_ON_ERROR
        );
    }
}
