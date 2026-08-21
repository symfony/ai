<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Discovery\Model;

/**
 * A discovered Mate tool, produced from a method carrying the `#[AsTool]` attribute.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ToolDefinition
{
    /**
     * @param class-string         $handlerClass
     * @param array<string, mixed> $inputSchema
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly array $inputSchema,
        public readonly string $handlerClass,
        public readonly string $handlerMethod,
    ) {
    }

    public function handlerLabel(): string
    {
        return $this->handlerClass.'::'.$this->handlerMethod;
    }
}
