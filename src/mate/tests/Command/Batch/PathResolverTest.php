<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Command\Batch;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Command\Batch\PathResolver;
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\AI\Mate\Exception\RuntimeException;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PathResolverTest extends TestCase
{
    private const DATA = [
        'profiles' => [['token' => 'a', 'n' => 1], ['token' => 'b', 'n' => 2], ['token' => 'c', 'n' => 3]],
        'empty' => [],
        'name' => 'x',
        'nothing' => null,
    ];

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function provideValidPaths(): iterable
    {
        yield 'field' => ['name', 'x'];
        yield 'null value' => ['nothing', null];
        yield 'index' => ['profiles[1].token', 'b'];
        yield 'leading index' => ['[0]', 'x'];
        yield 'all' => ['profiles[*].token', ['a', 'b', 'c']];
        yield 'all without rest' => ['profiles[*]', self::DATA['profiles']];
        yield 'slice' => ['profiles[:2]', [['token' => 'a', 'n' => 1], ['token' => 'b', 'n' => 2]]];
        yield 'slice with rest' => ['profiles[:2].n', [1, 2]];
        yield 'slice larger than list' => ['profiles[:9].n', [1, 2, 3]];
        yield 'all on empty list' => ['empty[*]', []];
    }

    #[DataProvider('provideValidPaths')]
    public function testResolve(string $path, mixed $expected)
    {
        $data = '[0]' === $path ? ['x'] : self::DATA;

        $this->assertSame($expected, PathResolver::resolve($data, $path));
    }

    public function testEmptyListIndexNamesThePathAndSaysEmpty()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"empty" is empty. Available: empty list.');

        PathResolver::resolve(self::DATA, 'empty[0].token');
    }

    public function testMissingFieldListsTheAvailableKeys()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"profiles[0]" has no field "foo". Available: token, n.');

        PathResolver::resolve(self::DATA, 'profiles[0].foo');
    }

    public function testAvailableKeysAreCappedAtTen()
    {
        $data = array_combine(array_map(static fn (int $i): string => 'k'.$i, range(1, 12)), range(1, 12));

        try {
            PathResolver::resolve($data, 'zzz');
            $this->fail('Expected an exception.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('k10 (and 2 more)', $e->getMessage());
            $this->assertStringNotContainsString('k11,', $e->getMessage());
        }
    }

    public function testIndexOutOfRangeReportsTheListSize()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"profiles" has no element 5. Available: list with 3 elements, indexes 0 to 2.');

        PathResolver::resolve(self::DATA, 'profiles[5]');
    }

    public function testFieldOnScalarFails()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"name" is a string, so it has no field "x".');

        PathResolver::resolve(self::DATA, 'name.x');
    }

    public function testIndexOnObjectFails()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is a list or object, not a list');

        PathResolver::resolve(['a' => 1], '[0]');
    }

    public function testFailureInsideWildcardNamesTheElement()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"profiles[0]" has no field "zip"');

        PathResolver::resolve(self::DATA, 'profiles[*].zip');
    }

    #[DataProvider('provideInvalidPaths')]
    public function testInvalidSyntaxIsRejected(string $path)
    {
        $this->expectException(InvalidArgumentException::class);

        PathResolver::assertValid($path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidPaths(): iterable
    {
        yield 'empty' => [''];
        yield 'double dot' => ['a..b'];
        yield 'leading dot' => ['.a'];
        yield 'open bracket' => ['a[0'];
        yield 'bad index' => ['a[x]'];
        yield 'negative index' => ['a[-1]'];
    }

    public function testUnwrapReturnsTheEnvelopeContent()
    {
        $this->assertSame(['a' => 1], PathResolver::unwrap(['_security_notice' => 'n', 'untrusted_data' => ['a' => 1]]));
        $this->assertSame(['a' => 1], PathResolver::unwrap(['a' => 1]));
        $this->assertSame('text', PathResolver::unwrap('text'));
    }
}
