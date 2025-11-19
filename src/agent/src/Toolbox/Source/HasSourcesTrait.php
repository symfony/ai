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
    private SourceMap $sourceMap;

    public function setSourceMap(SourceMap $sourceMap): void
    {
        $this->sourceMap = $sourceMap;
    }

    public function getSourceMap(): SourceMap
    {
        return $this->sourceMap ??= new SourceMap();
    }

    private function addSource(Source $source): void
    {
        $this->getSourceMap()->addSource($source);
    }
}
