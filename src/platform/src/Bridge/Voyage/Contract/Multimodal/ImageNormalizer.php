<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage\Contract\Multimodal;

use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Model;

use function Symfony\Component\String\u;

final class ImageNormalizer extends ModelContractNormalizer
{
    /**
     * @param Image $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [[
            CollectionNormalizer::KEY_CONTENT => [[
                'type' => 'image_base64',
                'image_base64' => \sprintf(
                    'data:%s;base64,%s',
                    u($data->getFormat())->replace('jpg', 'jpeg'),
                    $data->asBase64()
                ),
            ]],
        ]];
    }

    protected function supportedDataClass(): string
    {
        return Image::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Voyage && $model->supports(Capability::INPUT_MULTIMODAL);
    }
}
