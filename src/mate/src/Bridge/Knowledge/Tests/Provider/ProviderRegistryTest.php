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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Knowledge\Exception\InvalidProviderNameException;
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

    #[DataProvider('invalidNameProvider')]
    public function testRegistrationRejectsUnsafeProviderNames(string $name)
    {
        $this->expectException(InvalidProviderNameException::class);

        new ProviderRegistry([new FixtureProvider('/tmp', $name)]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNameProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'path traversal' => ['../escape'];
        yield 'slash' => ['foo/bar'];
        yield 'backslash' => ['foo\\bar'];
        yield 'uppercase' => ['Symfony'];
        yield 'shell metacharacter' => ['foo;rm'];
        yield 'leading dash' => ['-foo'];
        yield 'leading underscore' => ['_foo'];
        yield 'null byte' => ["foo\0bar"];
    }
}
