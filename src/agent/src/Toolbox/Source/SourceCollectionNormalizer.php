<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Source;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * (De)normalizes the sources an agent keeps in the metadata of its answer, e.g. for a message store.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SourceCollectionNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     *
     * @return list<array{name: string, reference: string, content: string}>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!$data instanceof SourceCollection) {
            throw new InvalidArgumentException(\sprintf('Expected an instance of "%s", "%s" given.', SourceCollection::class, get_debug_type($data)));
        }

        return array_map(static fn (Source $source): array => [
            'name' => $source->getName(),
            'reference' => $source->getReference(),
            'content' => $source->getContent(),
        ], $data->all());
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof SourceCollection;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): SourceCollection
    {
        if (!\is_array($data)) {
            throw new InvalidArgumentException('The normalized sources must be a list.');
        }

        return new SourceCollection(array_map(
            static fn (array $source): Source => new Source($source['name'], $source['reference'], $source['content']),
            array_values($data),
        ));
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return SourceCollection::class === $type;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            SourceCollection::class => true,
        ];
    }
}
