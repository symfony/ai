<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\Normalizer\Message\Content;

use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes a {@see Collection} by delegating each of its nested parts back to the serializer, so
 * every content type is covered regardless of the composite shape.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class CollectionNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Collection;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Collection::class => true,
        ];
    }

    /**
     * @param Collection $data
     *
     * @return array{type: 'collection', content: list<array<array-key, mixed>>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $content = [];
        foreach ($data->getContent() as $part) {
            $normalized = $this->normalizer->normalize($part, $format, $context);
            \assert(\is_array($normalized));
            $content[] = $normalized;
        }

        return [
            'type' => 'collection',
            'content' => $content,
        ];
    }
}
