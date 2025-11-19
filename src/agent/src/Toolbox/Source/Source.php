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

class Source
{
    public function __construct(
        private readonly string $name,
        private readonly string $reference,
        private readonly string $content,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
