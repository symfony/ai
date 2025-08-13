<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\TokenUsage\Attribute;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsTokenUsageExtractor
{
    public function __construct(public string $platform)
    {
    }
}
