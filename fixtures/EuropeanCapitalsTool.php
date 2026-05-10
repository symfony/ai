<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Fixtures;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * @author Benoit VIGNAL <git@benoit-vignal.fr>
 */
#[AsTool('list_european_capitals', 'Returns the capitals of a few European countries.')]
final class EuropeanCapitalsTool
{
    /**
     * @return list<string>
     */
    public function __invoke(): array
    {
        return ['Paris', 'Berlin', 'Madrid', 'Rome'];
    }
}
