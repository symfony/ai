<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document;

/**
 * A document to index, whose content gets vectorized into a VectorDocumentInterface.
 */
interface DocumentInterface
{
    public function getId(): int|string;

    public function getContent(): string|object;

    public function getMetadata(): Metadata;
}
