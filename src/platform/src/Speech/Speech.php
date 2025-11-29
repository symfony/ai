<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Speech;

use Symfony\AI\Platform\Result\DeferredResult;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Speech
{
    /**
     * @param string|array<mixed, mixed> $payload
     */
    public function __construct(
        private readonly string|array $payload,
        private readonly DeferredResult $result,
        private readonly string $identifier,
    ) {
    }

    /**
     * @return string|array<mixed, mixed>
     */
    public function getPayload(): string|array
    {
        return $this->payload;
    }

    public function asBinary(): string
    {
        return $this->result->asBinary();
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
