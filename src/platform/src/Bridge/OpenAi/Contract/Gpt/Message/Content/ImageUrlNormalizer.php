<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\Message\Content;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Model;

/**
 * See: https://platform.openai.com/docs/guides/images-vision#giving-a-model-images-as-input.
 */
final class ImageUrlNormalizer extends ModelContractNormalizer
{
    /**
     * @param ImageUrl $data
     *
     * @return array{
     *      type: 'input_image',
     *      image_url: string
     *  }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'input_image',
            'image_url' => $data->getUrl(),
        ];
    }

    protected function supportedDataClass(): string
    {
        return ImageUrl::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gpt && $model->supports(Capability::INPUT_IMAGE);
    }
}
