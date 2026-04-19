<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity\Contract;

use Symfony\AI\Platform\Contract;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class PerplexityContract extends Contract
{
    /**
     * @param NormalizerInterface[] $normalizers
     */
    public static function create(array $normalizers = []): Contract
    {
        return parent::create([
            new FileUrlNormalizer(),
            ...$normalizers,
        ]);
    }
}
