<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield\Contract;

use Symfony\AI\Platform\Message\Content\Image;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ImageNormalizer implements NormalizerInterface
{
    /**
     * @param Image $data
     *
     * @return array{type: 'image_url', image_url: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'image_url',
            'image_url' => $data->asDataUrl(),
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
