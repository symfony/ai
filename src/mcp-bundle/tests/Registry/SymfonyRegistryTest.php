<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\McpBundle\Registry\SymfonyRegistry;

#[CoversClass(SymfonyRegistry::class)]
class SymfonyRegistryTest extends TestCase
{
    public function testDefaultCapabilities()
    {
        $registry = new SymfonyRegistry(new NullLogger());

        $capabilities = $registry->getCapabilities();

        $this->assertTrue($capabilities->tools);
        $this->assertFalse($capabilities->toolsListChanged);
        $this->assertFalse($capabilities->resources);
        $this->assertFalse($capabilities->resourcesSubscribe);
        $this->assertFalse($capabilities->resourcesListChanged);
        $this->assertFalse($capabilities->prompts);
        $this->assertFalse($capabilities->promptsListChanged);
        $this->assertFalse($capabilities->logging);
        $this->assertTrue($capabilities->completions);
    }

    public function testConfigurableCapabilities()
    {
        $config = [
            'tools' => false,
            'logging' => true,
            'completions' => false,
            'experimental' => ['custom_feature' => true],
        ];

        $registry = new SymfonyRegistry(new NullLogger(), $config);

        $capabilities = $registry->getCapabilities();

        $this->assertFalse($capabilities->tools);
        $this->assertTrue($capabilities->logging);
        $this->assertFalse($capabilities->completions);
        $this->assertSame(['custom_feature' => true], $capabilities->experimental);
    }

    public function testEmptyConfigurationFallsBackToDefault()
    {
        $registry = new SymfonyRegistry(new NullLogger(), []);

        $capabilities = $registry->getCapabilities();

        // Should be same as default capabilities
        $this->assertTrue($capabilities->tools);
        $this->assertTrue($capabilities->completions);
    }

    public function testPartialConfigurationMixesWithDefaults()
    {
        $config = [
            'logging' => true,
            // Other values should fall back to defaults
        ];

        $registry = new SymfonyRegistry(new NullLogger(), $config);

        $capabilities = $registry->getCapabilities();

        $this->assertTrue($capabilities->tools); // Default
        $this->assertTrue($capabilities->logging); // Configured
        $this->assertTrue($capabilities->completions); // Default
    }
}
