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
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;

final class TextNormalizer extends ModelContractNormalizer
{
    /**
     * @param Text $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [[
            CollectionNormalizer::KEY_CONTENT => [[
                'type' => 'text',
                'text' => $data->getText(),
            ]],
        ]];
    }

    protected function supportedDataClass(): string
    {
        return Text::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Voyage && $model->supports(Capability::INPUT_MULTIMODAL);
    }
}
