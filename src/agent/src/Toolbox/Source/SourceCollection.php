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

final class SourceCollection
{
    /**
     * @param Source[] $sources
     */
    public function __construct(
        private array $sources = [],
    ) {
    }

    /**
     * @return Source[]
     */
    public function all(): array
    {
        return $this->sources;
    }

    public function add(Source $source): void
    {
        $this->sources[] = $source;
    }
}
