<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tool;

use Symfony\AI\Platform\Contract\JsonSchema\Factory;

/**
 * @phpstan-import-type JsonSchema from Factory
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Tool
{
    /**
     * @param JsonSchema|null      $parameters
     * @param array<string, mixed> $metadata   Arbitrary custom data attached to the tool
     */
    public function __construct(
        private readonly ExecutionReference $reference,
        private readonly string $name,
        private readonly string $description,
        private readonly ?array $parameters = null,
        private readonly array $metadata = [],
        private readonly bool $readOnlyHint = false,
        private readonly bool $destructiveHint = true,
        private readonly bool $idempotentHint = false,
        private readonly bool $openWorldHint = true,
    ) {
    }

    public function getReference(): ExecutionReference
    {
        return $this->reference;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return JsonSchema|null
     */
    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnlyHint;
    }

    public function isDestructive(): bool
    {
        return $this->destructiveHint;
    }

    public function isIdempotent(): bool
    {
        return $this->idempotentHint;
    }

    public function isOpenWorld(): bool
    {
        return $this->openWorldHint;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
