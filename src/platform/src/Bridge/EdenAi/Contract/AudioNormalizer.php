<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Contract;

use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Model;

/**
 * Eden AI has no inline binary input: binary content is normalized to a "file_data"
 * payload that the model clients upload through POST /v3/upload before the request.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
class AudioNormalizer extends ModelContractNormalizer
{
    /**
     * @param Audio $data
     *
     * @return array{file_data: array{data: string, filename: string|null, format: string}}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'file_data' => [
                'data' => $data->asBase64(),
                'filename' => $data->getFilename(),
                'format' => $data->getFormat(),
            ],
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
