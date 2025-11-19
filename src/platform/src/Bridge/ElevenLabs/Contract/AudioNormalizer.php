<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs\Contract;

use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class AudioNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Audio;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Audio::class => true,
        ];
    }

    /**
     * @param Audio $data
     *
     * @return array{type: 'input_audio', input_audio: array{
     *     data: string,
     *     path: string,
     *     format: 'mp3'|'wav'|string,
     * }}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'input_audio',
            'input_audio' => [
                'data' => $data->asBase64(),
                'path' => $data->asPath(),
                'format' => match ($data->getFormat()) {
                    'audio/mpeg' => 'mp3',
                    'audio/wav' => 'wav',
                    default => $data->getFormat(),
                },
            ],
        ];
    }
}
