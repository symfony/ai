<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\SpeechToText;

use Symfony\AI\Platform\Bridge\Cohere\SpeechToText;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class AudioNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Audio && $context[Contract::CONTEXT_MODEL] instanceof SpeechToText;
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
        return [
            'model' => $context[Contract::CONTEXT_MODEL]->getName(),
            'file' => $data->asResource(),
        ];
    }
}
