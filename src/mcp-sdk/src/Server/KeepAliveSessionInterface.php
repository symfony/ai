<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Server;

interface KeepAliveSessionInterface
{
    public function start(): void;

    public function stop(): void;

    public function tick(\Closure $callback): void;
}
