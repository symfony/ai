<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Server\Transport\Sse;

use Symfony\AI\McpSdk\Tests\Server\Transport\Sse\StreamTransportTest;

if (!\function_exists(__NAMESPACE__.'\\connection_aborted')) {
    function connection_aborted(): int
    {
        return StreamTransportTest::$connectionAborted;
    }
}
