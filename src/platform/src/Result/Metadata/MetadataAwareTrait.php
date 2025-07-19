<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Metadata;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
trait MetadataAwareTrait
{
    private ?Metadata $metadata = null;

    public function getMetadata(): Metadata
    {
        return $this->metadata ??= new Metadata();
    }
}
