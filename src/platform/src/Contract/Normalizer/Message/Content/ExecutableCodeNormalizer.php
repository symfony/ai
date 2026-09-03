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

use Symfony\AI\Platform\Message\Content\ExecutableCode;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ExecutableCodeNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ExecutableCode;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ExecutableCode::class => true,
        ];
    }

    /**
     * @param ExecutableCode $data
     *
     * @return array{type: 'executable_code', code: string, language: string|null, id: string|null}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'executable_code',
            'code' => $data->getCode(),
            'language' => $data->getLanguage(),
            'id' => $data->getId(),
        ];
    }
}
