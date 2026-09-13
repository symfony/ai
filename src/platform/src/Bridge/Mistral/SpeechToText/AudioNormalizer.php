<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\SpeechToText;

use Symfony\AI\Platform\Bridge\Mistral\SpeechToText;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Model;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class AudioNormalizer extends ModelContractNormalizer
{
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

    protected function supportedDataClass(): string
    {
        return Audio::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof SpeechToText;
    }
}
