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

interface EmbeddableDocumentInterface
{
    public function getId(): mixed;

    public function getContent(): string;

    public function getMetadata(): Metadata;
}
