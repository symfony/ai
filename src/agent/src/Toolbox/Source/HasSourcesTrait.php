<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Source;

trait HasSourcesTrait
{
    private SourceCollection $sourceCollection;

    public function setSourceCollection(SourceCollection $sourceCollection): void
    {
        $this->sourceCollection = $sourceCollection;
    }

    public function getSourceCollection(): SourceCollection
    {
        return $this->sourceCollection ??= new SourceCollection();
    }

    private function addSource(Source $source): void
    {
        $this->getSourceCollection()->add($source);
    }
}
