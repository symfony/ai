<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decart\Contract;

use Symfony\AI\Platform\Message\Content\Image;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ImageNormalizer implements NormalizerInterface
{
    /**
     * @param Image $data
     *
     * @return array{type: 'input_image', input_image: array{
     *     data: string,
     *     path: string,
     *     format: 'jpg'|'png'|string,
     * }}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'input_image',
            'input_image' => [
                'data' => $data->asBase64(),
                'path' => $data->asPath(),
                'format' => match ($data->getFormat()) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    default => $data->getFormat(),
                },
            ],
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Image;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Image::class => true,
        ];
    }
}
