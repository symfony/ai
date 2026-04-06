<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCall
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly array $arguments = [],
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
