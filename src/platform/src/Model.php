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
    private readonly string $name;
    private readonly string $label;
    /**
     * @var Capability[]
     */
    private readonly array $capabilities;
    /**
     * @var array<string, mixed>
     */
    private readonly array $options;
    /**
     * @param non-empty-string     $name
     * @param non-empty-string|array<int|string, mixed>|null $labelOrCapabilities
     *        Either the human-readable label (string) or the capabilities array (legacy callers)
     * @param Capability[]         $capabilities
     * @param array<string, mixed> $options      The default options for the model usage
     */
    public function __construct(
        string $name,
        mixed $labelOrCapabilities = null,
        array $capabilities = [],
        array $options = [],
    ) {
        $this->name = $name;
        $this->options = $options;

        if ('' === trim($name)) {
            throw new InvalidArgumentException('Model name cannot be empty.');
        }

        // Backwards-compatible handling: when callers passed capabilities as the second argument
        if (null === $labelOrCapabilities) {
            $label = $name;
        } elseif (\is_array($labelOrCapabilities)) {
            $label = $name;
            $capabilities = $labelOrCapabilities;
        } else {
            $label = (string) $labelOrCapabilities;
        }

        if ('' === trim($label)) {
            throw new InvalidArgumentException('Model label cannot be empty.');
        }

        // assign resolved label to readonly property via reflection-like approach
        $this->label = $label;
        $this->capabilities = $capabilities;
    }

    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return non-empty-string
     */
    public function getLabel(): string
    {
        return $this->label;
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
        return $capability->equalsOneOf($this->capabilities);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
