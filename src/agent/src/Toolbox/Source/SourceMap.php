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

final class SourceMap
{
    /**
     * @var Source[]
     */
    private array $sources = [];

    /**
     * @return Source[]
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    public function addSource(Source $source): void
    {
        $this->sources[] = $source;
    }
}
