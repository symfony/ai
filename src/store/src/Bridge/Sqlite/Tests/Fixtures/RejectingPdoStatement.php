<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Sqlite\Tests\Fixtures;

use Symfony\AI\Store\Exception\RuntimeException;

final class RejectingPdoStatement extends \PDOStatement
{
    private function __construct()
    {
        throw new RuntimeException('Statement created with the configured class.');
    }
}
