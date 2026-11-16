<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Dbal\Platforms;

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Reads and writes a vector as the JSON list of its values.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
trait VectorLiteralTrait
{
    public function toDatabaseValue(VectorInterface $vector): string
    {
        return json_encode($vector->getData(), \JSON_THROW_ON_ERROR);
    }

    public function toVector(string $value): VectorInterface
    {
        $data = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($data)) {
            throw new InvalidArgumentException(\sprintf('Expected a vector literal, got "%s".', $value));
        }

        return new Vector(array_map(floatval(...), array_values($data)));
    }
}
