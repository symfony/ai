<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Router\Result;

use Symfony\AI\Agent\Router\Transformer\TransformerInterface;

/**
 * Result of a routing decision, including optional transformation.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RoutingResult
{
    public function __construct(
        private readonly string $modelName,
        private readonly ?TransformerInterface $transformer = null,
        private readonly string $reason = '',
        private readonly int $confidence = 100,
    ) {
    }

    public function getModelName(): string
    {
        return $this->modelName;
    }

    public function getTransformer(): ?TransformerInterface
    {
        return $this->transformer;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getConfidence(): int
    {
        return $this->confidence;
    }
}
