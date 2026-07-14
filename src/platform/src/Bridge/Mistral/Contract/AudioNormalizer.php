<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Contract;

use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Model;

/**
 * Serializes an audio message part for the Mistral chat/completions endpoint, e.g. for the
 * Voxtral models. Mistral expects the base64-encoded audio directly as the `input_audio` value,
 * unlike the OpenAI-style object handled by the generic normalizer.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class AudioNormalizer extends ModelContractNormalizer
{
    /**
     * @param Audio $data
     *
     * @return array{type: 'input_audio', input_audio: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'input_audio',
            'input_audio' => $data->asBase64(),
        ];
    }

    protected function supportedDataClass(): string
    {
        return Audio::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Mistral;
    }
}
