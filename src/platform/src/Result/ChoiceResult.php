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

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChoiceResult extends BaseResult
{
    /**
     * @var Choice[]
     */
    private readonly array $choices;

    public function __construct(Choice ...$choices)
    {
        if ([] === $choices) {
            throw new InvalidArgumentException('Result must have at least one choice.');
        }

        $this->choices = $choices;
    }

    /**
     * @return Choice[]
     */
    public function getContent(): array
    {
        return $this->choices;
    }
}
