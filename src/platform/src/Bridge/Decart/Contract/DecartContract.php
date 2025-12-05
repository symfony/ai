<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decart\Contract;

use Symfony\AI\Platform\Contract;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class DecartContract extends Contract
{
    public static function create(NormalizerInterface ...$normalizer): Contract
    {
        return parent::create(
            new ImageNormalizer(),
            new VideoNormalizer(),
            ...$normalizer
        );
    }
}
