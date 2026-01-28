<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MiniMaxTextChunk implements \Stringable
{
    public function __construct(
        private readonly array $payload,
    ) {
    }

    public function __toString(): string
    {
        return $this->payload['choices'][0]['message']['content'] ?? '';
    }

    public function getId(): string
    {
        return $this->payload['id'];
    }

    public function getRole(): string
    {
        return $this->payload['choices'][0]['message']['role'];
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('U', $this->payload['created_at']);
    }
}
