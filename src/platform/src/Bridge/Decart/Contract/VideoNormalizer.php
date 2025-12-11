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

use Symfony\AI\Platform\Message\Content\Video;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class VideoNormalizer implements NormalizerInterface
{
    /**
     * @param Video $data
     *
     * @return array{type: 'input_video', input_video: array{
     *     data: string,
     *     path: string,
     *     format: 'mp3'|'wav'|string,
     * }}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'input_video',
            'input_video' => [
                'data' => $data->asBase64(),
                'path' => $data->asPath(),
                'format' => $data->getFormat(),
            ],
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Video;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Video::class => true,
        ];
    }
}
