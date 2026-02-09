<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Policy;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Policy\DelayPolicyHandler;
use Symfony\AI\Agent\Policy\InputDelayPolicy;
use Symfony\AI\Agent\Policy\PolicyHandlerRegistry;

final class PolicyHandlerRegistryTest extends TestCase
{
    public function testRegistryCannotReturnMissingHandler()
    {
        $registry = new PolicyHandlerRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No policy handler found for the "Symfony\AI\Agent\Policy\InputDelayPolicy" policy.');
        $this->expectExceptionCode(0);
        $registry->get(new InputDelayPolicy(60));
    }

    public function testRegistryCanReturnHandler()
    {
        $registry = new PolicyHandlerRegistry([
            new DelayPolicyHandler(),
        ]);

        $this->assertInstanceOf(DelayPolicyHandler::class, $registry->get(new InputDelayPolicy(60)));
    }
}
