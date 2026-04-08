<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Discovery\Fixtures\OutputSchema;

/**
 * Fixture class for OutputSchemaGenerator tests.
 */
final class SimpleTool
{
    /**
     * @return array{name: string, age: int}
     */
    public function simpleShape(): array
    {
        return ['name' => 'test', 'age' => 1];
    }

    /**
     * @return array{entries: list<array{id: int, title: string}>}
     */
    public function nestedShape(): array
    {
        return ['entries' => []];
    }

    /**
     * @return array{status: string, message?: string}
     */
    public function optionalKeys(): array
    {
        return ['status' => 'ok'];
    }

    /**
     * @return array{name: string, parent: string|null}
     */
    public function nullableValues(): array
    {
        return ['name' => 'test', 'parent' => null];
    }

    /**
     * @return array<string, class-string|null>
     */
    public function mapType(): array
    {
        return [];
    }

    /**
     * @return array{channels: string[]}
     */
    public function stringArray(): array
    {
        return ['channels' => []];
    }

    public function noReturnType(): mixed
    {
        return null;
    }

    public function voidReturn(): void
    {
    }

    /**
     * @phpstan-return array{count: int}
     */
    public function phpstanReturn(): array
    {
        return ['count' => 1];
    }

    /**
     * @return array{files: list<array{name: string, size: int}>}
     */
    public function listWithFiles(): array
    {
        return ['files' => []];
    }
}
