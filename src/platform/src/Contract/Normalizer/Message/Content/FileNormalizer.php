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

use Symfony\AI\Platform\Message\Content\File;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes any {@see File} (and its subclasses {@see \Symfony\AI\Platform\Message\Content\Video}
 * and {@see \Symfony\AI\Platform\Message\Content\Document}) into a base64 representation. Provider
 * specific normalizers for {@see \Symfony\AI\Platform\Message\Content\Image} and
 * {@see \Symfony\AI\Platform\Message\Content\Audio} are registered earlier and keep precedence.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FileNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof File;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            File::class => true,
        ];
    }

    /**
     * @param File $data
     *
     * @return array{type: 'file', file: array{data: string, format: string}}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'file',
            'file' => [
                'data' => $data->asBase64(),
                'format' => $data->getFormat(),
            ],
        ];
    }
}
