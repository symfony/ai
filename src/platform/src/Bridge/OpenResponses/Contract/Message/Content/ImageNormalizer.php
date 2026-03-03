<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses\Contract\Message\Content;

use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Model;

final class ImageNormalizer extends ModelContractNormalizer
{
    /**
     * @param Image $data
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
            'image_url' => $data->asDataUrl(),
        ];
    }

    protected function supportedDataClass(): string
    {
        return Image::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof ResponsesModel && $model->supports(Capability::INPUT_IMAGE);
    }
}
