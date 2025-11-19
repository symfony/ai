<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage\Contract;

use Symfony\AI\Platform\Bridge\Voyage\Contract\Multimodal\CollectionNormalizer;
use Symfony\AI\Platform\Bridge\Voyage\Contract\Multimodal\ImageNormalizer;
use Symfony\AI\Platform\Bridge\Voyage\Contract\Multimodal\ImageUrlNormalizer;
use Symfony\AI\Platform\Bridge\Voyage\Contract\Multimodal\MultimodalNormalizer;
use Symfony\AI\Platform\Bridge\Voyage\Contract\Multimodal\TextNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class VoyageContract extends Contract
{
    public static function create(NormalizerInterface ...$normalizer): Contract
    {
        return parent::create(
            new MultimodalNormalizer(),
            new CollectionNormalizer(),
            new TextNormalizer(),
            new ImageNormalizer(),
            new ImageUrlNormalizer(),
            ...$normalizer
        );
    }
}
