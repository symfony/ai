<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Contract;

use Symfony\AI\Platform\Contract;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class HuggingFaceContract extends Contract
{
    /**
     * @param NormalizerInterface[] $normalizers
     */
    public static function create(array $normalizers = []): Contract
    {
        return parent::create([
            new FileNormalizer(),
            new MessageBagNormalizer(),
            ...$normalizers,
        ]);
    }
}
