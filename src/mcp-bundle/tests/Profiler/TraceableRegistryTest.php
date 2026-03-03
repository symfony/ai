<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Profiler;

use Mcp\Capability\RegistryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Profiler\TraceableRegistry;

final class TraceableRegistryTest extends TestCase
{
    public function testResetCallsClear()
    {
        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())->method('clear');

        $traceableRegistry = new TraceableRegistry($registry);
        $traceableRegistry->reset();
    }
}
