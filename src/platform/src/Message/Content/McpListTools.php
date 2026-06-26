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

/**
 * @phpstan-type McpTool array{
 *     name: string,
 *     description?: string|null,
 *     input_schema?: array<string, mixed>,
 *     annotations?: array<string, mixed>|null,
 * }
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class McpListTools implements ContentInterface
{
    /**
     * @param list<McpTool> $tools
     */
    public function __construct(
        private readonly string $serverLabel,
        private readonly array $tools = [],
        private readonly ?string $id = null,
    ) {
    }

    public function getServerLabel(): string
    {
        return $this->serverLabel;
    }

    /**
     * @return list<McpTool>
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
