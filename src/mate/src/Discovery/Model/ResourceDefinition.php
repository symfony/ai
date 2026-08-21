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
 * A discovered Mate static resource, produced from a method carrying the
 * `#[AsResource]` attribute.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResourceDefinition
{
    /**
     * @param class-string $handlerClass
     */
    public function __construct(
        public readonly string $uri,
        public readonly ?string $name,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?string $mimeType,
        public readonly string $handlerClass,
        public readonly string $handlerMethod,
    ) {
    }

    public function handlerLabel(): string
    {
        return $this->handlerClass.'::'.$this->handlerMethod;
    }
}
