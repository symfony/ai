<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class VeniceMessageChunk implements \Stringable
{
    public function __construct(
        private readonly string $id,
        private readonly string $model,
        private readonly \DateTimeImmutable $createdAt,
        private readonly string $content,
    ) {
    }

    public function __toString(): string
    {
        return $this->content;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
