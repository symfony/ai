<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message\Content;

final class CodeExecution implements ContentInterface
{
    public function __construct(
        private readonly bool $succeeded,
        private readonly ?string $output = null,
        private readonly ?string $id = null,
    ) {
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function isSucceeded(): bool
    {
        return $this->succeeded;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
