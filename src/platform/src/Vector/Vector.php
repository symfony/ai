<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Vector;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Vector implements VectorInterface
{
    /**
     * @param list<float> $data
     */
    public function __construct(
        private readonly array $data,
        private ?int $dimensions = null,
    ) {
        if (null !== $dimensions && $dimensions !== \count($data)) {
            throw new InvalidArgumentException(\sprintf('Vector must have %d dimensions', $dimensions));
        }

        if ([] === $data) {
            throw new InvalidArgumentException('Vector must have at least one dimension.');
        }

        if (null === $this->dimensions) {
            $this->dimensions = \count($data);
        }
    }

    /**
     * @return list<float>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getDimensions(): int
    {
        return $this->dimensions;
    }
}
