<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class Model
{
    /**
     * @param non-empty-string     $name
     * @param Capability[]         $capabilities
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $name,
        private readonly array $capabilities = [],
        private readonly array $options = [],
    ) {
        if ('' === trim($name)) {
            throw new InvalidArgumentException('Model name cannot be empty.');
        }
    }

    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return Capability[]
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function supports(Capability $capability): bool
    {
        return \in_array($capability, $this->capabilities, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
