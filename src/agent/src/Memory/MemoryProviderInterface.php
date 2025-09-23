<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Memory;

use Symfony\AI\Agent\Input;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
interface MemoryProviderInterface
{
    /**
     * @return list<Memory>
     */
    public function load(Input $input): array;
}
