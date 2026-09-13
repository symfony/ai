<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Invocation;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\AI\Mate\Invocation\ArgumentCaster;
use Symfony\AI\Mate\Tests\Discovery\Fixtures\SampleColor;
use Symfony\AI\Mate\Tests\Discovery\Fixtures\SampleLevel;
use Symfony\AI\Mate\Tests\Discovery\Fixtures\SchemaFixture;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ArgumentCasterTest extends TestCase
{
    private ArgumentCaster $caster;

    protected function setUp(): void
    {
        $this->caster = new ArgumentCaster();
    }

    public function testCoercesStringValuesToDeclaredTypes()
    {
        $method = new \ReflectionMethod(SchemaFixture::class, 'scalars');

        $args = $this->caster->build($method, ['limit' => '5', 'filter' => 'needle', 'deep' => 'true']);

        $this->assertSame([5, 'needle', true], $args);
    }

    public function testUsesDefaultsForMissingOptionalArguments()
    {
        $method = new \ReflectionMethod(SchemaFixture::class, 'scalars');

        $args = $this->caster->build($method, ['limit' => 3]);

        $this->assertSame([3, null, false], $args);
    }

    public function testMissingRequiredArgumentThrows()
    {
        $method = new \ReflectionMethod(SchemaFixture::class, 'scalars');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required argument "limit"');

        $this->caster->build($method, []);
    }

    public function testSpreadsVariadicArguments()
    {
        $method = new \ReflectionMethod(SchemaFixture::class, 'variadic');

        $args = $this->caster->build($method, ['ids' => ['1', 2, '3']]);

        $this->assertSame([1, 2, 3], $args);
    }

    public function testOmittedVariadicArgumentYieldsNoArguments()
    {
        $method = new \ReflectionMethod(SchemaFixture::class, 'variadic');

        $this->assertSame([], $this->caster->build($method, []));
    }

    public function testMissingRequiredNullableArgumentThrows()
    {
        $method = new \ReflectionMethod(SchemaFixture::class, 'requiredNullable');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required argument "needle"');

        $this->caster->build($method, ['haystack' => 'x']);
    }

    public function testInvalidIntegerThrows()
    {
        $method = new \ReflectionMethod(SchemaFixture::class, 'scalars');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cast value to integer.');

        $this->caster->build($method, ['limit' => 'not-a-number']);
    }

    public function testRejectsIntegerWithRepeatedSign()
    {
        $method = new \ReflectionMethod(SchemaFixture::class, 'scalars');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cast value to integer.');

        $this->caster->build($method, ['limit' => '--5']);
    }

    public function testRejectsNonFiniteNumericString()
    {
        $method = new \ReflectionMethod(SchemaFixture::class, 'scalars');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cast value to integer.');

        $this->caster->build($method, ['limit' => '1e400']);
    }

    public function testAcceptsNegativeInteger()
    {
        $method = new \ReflectionMethod(SchemaFixture::class, 'scalars');

        $this->assertSame([-5, null, false], $this->caster->build($method, ['limit' => '-5']));
    }

    public function testCoercesStringToAnIntBackedEnum()
    {
        $method = new \ReflectionMethod(SchemaFixture::class, 'withIntEnum');

        $this->assertSame([SampleLevel::High], $this->caster->build($method, ['level' => '2']));
    }

    public function testRejectsNonNumericValueForAnIntBackedEnum()
    {
        $method = new \ReflectionMethod(SchemaFixture::class, 'withIntEnum');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value "high"');

        $this->caster->build($method, ['level' => 'high']);
    }

    public function testRejectsArrayForABooleanWithoutWarnings()
    {
        $method = new \ReflectionMethod(SchemaFixture::class, 'withBool');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "--flag" option takes a single value, but a list of 1 was given.');

        $this->caster->build($method, ['flag' => ['a' => 1]]);
    }

    public function testCastsBackedEnumFromValue()
    {
        $method = new \ReflectionMethod(SchemaFixture::class, 'withEnum');

        $args = $this->caster->build($method, ['color' => 'green']);

        $this->assertSame([SampleColor::Green], $args);
    }
}
