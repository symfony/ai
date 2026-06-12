<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock;

/**
 * Maps an AWS region to the geographic prefix used in Bedrock cross-region
 * inference profile IDs (e.g. "ap-southeast-2" resolves to "apac").
 *
 * Only mappings confirmed against the AWS documentation are listed here; every
 * other region falls through to its raw two-character prefix, which already
 * matches the inference profile prefix for the US ("us") and EU ("eu") geos.
 *
 * @see https://docs.aws.amazon.com/bedrock/latest/userguide/inference-profiles-support.html
 *
 * @author Ryan Rigby <16025441+rrigby@users.noreply.github.com>
 */
final class RegionMapper
{
    public static function map(string $region): string
    {
        $prefix = substr($region, 0, 2);

        return match ($prefix) {
            'ap' => 'apac',
            default => $prefix,
        };
    }
}
