<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VectorResult extends BaseResult
{
    /**
     * @var Vector[]
     */
    private readonly array $vectors;

    public function __construct(Vector ...$vector)
    {
        $this->vectors = $vector;
    }

    /**
     * @return Vector[]
     */
    public function getContent(): array
    {
        return $this->vectors;
    }
}
