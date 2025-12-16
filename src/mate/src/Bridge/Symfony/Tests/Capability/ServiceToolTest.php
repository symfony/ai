<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Capability;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Capability\ServiceTool;
use Symfony\AI\Mate\Bridge\Symfony\Service\ContainerProvider;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class ServiceToolTest extends TestCase
{
    public function testGetAllServices()
    {
        $tool = new ServiceTool(
            \dirname(__DIR__).'/Fixtures',
            new ContainerProvider()
        );

        $output = $tool->getAllServices();
        $this->assertCount(6, $output);
    }
}
