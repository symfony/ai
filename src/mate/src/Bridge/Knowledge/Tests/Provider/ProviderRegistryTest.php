<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Knowledge\Exception\ProviderNotFoundException;
use Symfony\AI\Mate\Bridge\Knowledge\Provider\ProviderRegistry;
use Symfony\AI\Mate\Bridge\Knowledge\Tests\Fixtures\FixtureProvider;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProviderRegistryTest extends TestCase
{
    public function testGetReturnsRegisteredProvider()
    {
        $provider = new FixtureProvider('/tmp', 'fixture');
        $registry = new ProviderRegistry([$provider]);

        $this->assertSame($provider, $registry->get('fixture'));
        $this->assertTrue($registry->has('fixture'));
    }

    public function testGetThrowsForUnknownProvider()
    {
        $registry = new ProviderRegistry();

        $this->expectException(ProviderNotFoundException::class);
        $this->expectExceptionMessage('Knowledge provider "missing" is not registered');

        $registry->get('missing');
    }

    public function testAllReturnsRegisteredProviders()
    {
        $a = new FixtureProvider('/tmp', 'a');
        $b = new FixtureProvider('/tmp', 'b');

        $registry = new ProviderRegistry([$a, $b]);

        $this->assertSame(['a' => $a, 'b' => $b], $registry->all());
    }
}
