<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Whisper;

use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AudioNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Audio && $context[Contract::CONTEXT_MODEL] instanceof Whisper;
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
     * @return array{model: string, file: resource}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $resource = $data->asResource();

        if (false === $resource) {
            throw new RuntimeException('Could not open audio file as resource.');
        }

        $model = $context[Contract::CONTEXT_MODEL];
        \assert($model instanceof Whisper);

        return [
            'model' => $model->getName(),
            'file' => $resource,
        ];
    }
}
