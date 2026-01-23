<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Metadata;

/**
 * Interface for metadata values that know how to merge with other values of the same type.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface MergeableMetadataInterface
{
    /**
     * Merges this value with another value of the same type.
     */
    public function merge(self $metadata): self;
}
