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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Command\Batch\ReferenceResolver;
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\AI\Mate\Exception\RuntimeException;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ReferenceResolverTest extends TestCase
{
    private const RESULTS = [
        'list' => [
            '_security_notice' => 'n',
            'untrusted_data' => ['profiles' => [['token' => 'tok1', 'id' => 7]], 'meta' => ['a' => 1], 'none' => null, 'empty' => []],
        ],
        'plain' => ['count' => 3],
    ];

    public function testWholeExpressionKeepsTheType()
    {
        $this->assertSame(7, ReferenceResolver::resolve('{{list.profiles[0].id}}', self::RESULTS));
        $this->assertSame(['a' => 1], ReferenceResolver::resolve('{{list.meta}}', self::RESULTS));
        $this->assertNull(ReferenceResolver::resolve('{{ list.none }}', self::RESULTS));
    }

    public function testEmbeddedExpressionIsInsertedAsText()
    {
        $this->assertSame('/p/tok1?id=7', ReferenceResolver::resolve('/p/{{list.profiles[0].token}}?id={{list.profiles[0].id}}', self::RESULTS));
    }

    public function testReplacesAtAnyDepth()
    {
        $resolved = ReferenceResolver::resolve(['a' => ['b' => ['{{plain.count}}', 'x{{plain.count}}']], 'n' => 5, 'f' => true], self::RESULTS);

        $this->assertSame(['a' => ['b' => [3, 'x3']], 'n' => 5, 'f' => true], $resolved);
    }

    public function testEscapeYieldsLiteralBraces()
    {
        $this->assertSame('{{plain.count}}', ReferenceResolver::resolve('\{{plain.count}}', self::RESULTS));
        $this->assertSame('a {{ b 3', ReferenceResolver::resolve('a \{{ b {{plain.count}}', self::RESULTS));
    }

    public function testArrayOrNullInsideTextFails()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be inserted into a longer string');

        ReferenceResolver::resolve('x{{list.meta}}', self::RESULTS);
    }

    public function testNullInsideTextFails()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is null');

        ReferenceResolver::resolve('x{{list.none}}', self::RESULTS);
    }

    public function testUnresolvablePathIsExplained()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reference {{list.empty[0].token}} cannot be resolved: "empty" is empty. Available: empty list.');

        ReferenceResolver::resolve('{{list.empty[0].token}}', self::RESULTS);
    }

    public function testFailedCallIsReported()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be resolved: call list failed: boom');

        ReferenceResolver::resolve('{{list.x}}', [], ['list' => 'call list failed: boom']);
    }

    public function testCollectFindsReferencesInNestedValues()
    {
        $found = ReferenceResolver::collect(['a' => ['{{x.y}}', 'z'], 'b' => 'q {{w[0]}} \{{ignored.a}}']);

        $this->assertSame(['x', 'w'], array_column($found, 'id'));
        $this->assertSame(['y', '[0]'], array_column($found, 'path'));
    }

    public function testUnescapedBracesWithoutValidExpressionAreRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid reference');

        ReferenceResolver::collect('{{Bad.Id}}');
    }

    public function testInvalidPathSyntaxIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);

        ReferenceResolver::collect('{{list.a[x]}}');
    }
}
