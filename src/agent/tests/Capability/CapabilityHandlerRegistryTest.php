<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Capability;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Capability\CapabilityHandlerRegistry;
use Symfony\AI\Agent\Capability\DelayCapabilityHandler;
use Symfony\AI\Agent\Capability\InputDelayCapability;
use Symfony\AI\Agent\Exception\InvalidArgumentException;

final class CapabilityHandlerRegistryTest extends TestCase
{
    public function testRegistryCannotReturnMissingHandler()
    {
        $registry = new CapabilityHandlerRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No capability handler found for the "Symfony\AI\Agent\Capability\InputDelayCapability" capability.');
        $this->expectExceptionCode(0);
        $registry->get(new InputDelayCapability(60));
    }

    public function testRegistryCanReturnHandler()
    {
        $registry = new CapabilityHandlerRegistry([
            new DelayCapabilityHandler(),
        ]);

        $this->assertInstanceOf(DelayCapabilityHandler::class, $registry->get(new InputDelayCapability(60)));
    }
}
