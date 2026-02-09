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
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Model;

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
        return $model instanceof ResponsesModel && $model->supports(Capability::INPUT_IMAGE);
    }
}
