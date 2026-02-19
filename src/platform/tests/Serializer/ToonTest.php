<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Toon\Toon;

final class ToonTest extends TestCase
{
    private Toon $toon;

    protected function setUp(): void
    {
        $this->toon = new Toon();
    }

    public function testEncodePrimitiveNull()
    {
        $this->assertSame('null', $this->toon->encode(null));
    }

    public function testEncodePrimitiveTrue()
    {
        $this->assertSame('true', $this->toon->encode(true));
    }

    public function testEncodePrimitiveFalse()
    {
        $this->assertSame('false', $this->toon->encode(false));
    }

    public function testEncodePrimitiveInteger()
    {
        $this->assertSame('42', $this->toon->encode(42));
    }

    public function testEncodePrimitiveNegativeInteger()
    {
        $this->assertSame('-17', $this->toon->encode(-17));
    }

    public function testEncodePrimitiveFloat()
    {
        $this->assertSame('3.14', $this->toon->encode(3.14));
    }

    public function testEncodePrimitiveString()
    {
        $this->assertSame('hello', $this->toon->encode('hello'));
    }

    public function testEncodeStringWithSpecialChars()
    {
        $this->assertSame('"hello:world"', $this->toon->encode('hello:world'));
        $this->assertSame('"hello\"world"', $this->toon->encode('hello"world'));
    }

    public function testEncodeEmptyString()
    {
        $this->assertSame('""', $this->toon->encode(''));
    }

    public function testEncodeStringWithLeadingWhitespace()
    {
        $this->assertSame('" hello"', $this->toon->encode(' hello'));
    }

    public function testEncodeStringWithTrailingWhitespace()
    {
        $this->assertSame('"hello "', $this->toon->encode('hello '));
    }

    public function testEncodeReservedKeywords()
    {
        $this->assertSame('"true"', $this->toon->encode('true'));
        $this->assertSame('"false"', $this->toon->encode('false'));
        $this->assertSame('"null"', $this->toon->encode('null'));
    }

    public function testEncodeNumericString()
    {
        $this->assertSame('"123"', $this->toon->encode('123'));
        $this->assertSame('"3.14"', $this->toon->encode('3.14'));
    }

    public function testEncodeStringWithNewline()
    {
        $this->assertSame('"hello\nworld"', $this->toon->encode("hello\nworld"));
    }

    public function testEncodeStringWithTab()
    {
        $this->assertSame('"hello\tworld"', $this->toon->encode("hello\tworld"));
    }

    public function testEncodeStringWithCarriageReturn()
    {
        $this->assertSame('"hello\rworld"', $this->toon->encode("hello\rworld"));
    }

    public function testEncodeStringWithBackslash()
    {
        $this->assertSame('"hello\\\\world"', $this->toon->encode('hello\\world'));
    }

    public function testEncodeEmptyArray()
    {
        $this->assertSame('', $this->toon->encode([]));
    }

    public function testEncodeSimpleObject()
    {
        $data = ['name' => 'Alice', 'age' => 30];
        $expected = <<<TOON
            name: Alice
            age: 30
            TOON;

        $this->assertSame($expected, $this->toon->encode($data));
    }

    public function testEncodeNestedObject()
    {
        $data = [
            'user' => [
                'name' => 'Alice',
                'email' => 'alice@example.com',
            ],
        ];
        $expected = <<<TOON
            user:
              name: Alice
              email: alice@example.com
            TOON;

        $this->assertSame($expected, $this->toon->encode($data));
    }

    public function testEncodePrimitiveArray()
    {
        $data = ['tags' => ['admin', 'ops', 'dev']];
        $expected = 'tags[3]: admin,ops,dev';

        $this->assertSame($expected, $this->toon->encode($data));
    }

    public function testEncodeTabularArray()
    {
        $data = [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
        ];
        $expected = <<<TOON
            users[2]{id,name}:
              1,Alice
              2,Bob
            TOON;

        $this->assertSame($expected, $this->toon->encode($data));
    }

    public function testEncodeMixedArray()
    {
        $data = [
            'items' => [
                'first',
                ['key' => 'value'],
            ],
        ];
        $expected = <<<TOON
            items[2]:
              - first
              - key: value
            TOON;

        $this->assertSame($expected, $this->toon->encode($data));
    }

    public function testEncodeComplexStructure()
    {
        $data = [
            'context' => [
                'task' => 'Our favorite hikes together',
                'location' => 'Boulder',
            ],
            'friends' => ['ana', 'luis', 'sam'],
        ];
        $expected = <<<TOON
            context:
              task: Our favorite hikes together
              location: Boulder
            friends[3]: ana,luis,sam
            TOON;

        $this->assertSame($expected, $this->toon->encode($data));
    }

    public function testEncodeNegativeZero()
    {
        $this->assertSame('0', $this->toon->encode(-0.0));
    }

    public function testEncodeNaN()
    {
        $this->assertSame('null', $this->toon->encode(\NAN));
    }

    public function testEncodeInfinity()
    {
        $this->assertSame('null', $this->toon->encode(\INF));
        $this->assertSame('null', $this->toon->encode(-\INF));
    }

    public function testDecodePrimitiveNull()
    {
        $this->assertNull($this->toon->decode('null'));
    }

    public function testDecodePrimitiveTrue()
    {
        $this->assertTrue($this->toon->decode('true'));
    }

    public function testDecodePrimitiveFalse()
    {
        $this->assertFalse($this->toon->decode('false'));
    }

    public function testDecodePrimitiveInteger()
    {
        $this->assertSame(42, $this->toon->decode('42'));
    }

    public function testDecodePrimitiveFloat()
    {
        $this->assertSame(3.14, $this->toon->decode('3.14'));
    }

    public function testDecodePrimitiveString()
    {
        $this->assertSame('hello', $this->toon->decode('hello'));
    }

    public function testDecodeQuotedString()
    {
        $this->assertSame('hello:world', $this->toon->decode('"hello:world"'));
    }

    public function testDecodeEmptyQuotedString()
    {
        $this->assertSame('', $this->toon->decode('""'));
    }

    public function testDecodeStringWithEscapes()
    {
        $this->assertSame("hello\nworld", $this->toon->decode('"hello\nworld"'));
        $this->assertSame("hello\tworld", $this->toon->decode('"hello\tworld"'));
        $this->assertSame("hello\rworld", $this->toon->decode('"hello\rworld"'));
        $this->assertSame('hello\\world', $this->toon->decode('"hello\\\\world"'));
        $this->assertSame('hello"world', $this->toon->decode('"hello\\"world"'));
    }

    public function testDecodeSimpleObject()
    {
        $toon = <<<TOON
            name: Alice
            age: 30
            TOON;
        $expected = ['name' => 'Alice', 'age' => 30];

        $this->assertSame($expected, $this->toon->decode($toon));
    }

    public function testDecodeNestedObject()
    {
        $toon = <<<TOON
            user:
              name: Alice
              email: alice@example.com
            TOON;
        $expected = [
            'user' => [
                'name' => 'Alice',
                'email' => 'alice@example.com',
            ],
        ];

        $this->assertSame($expected, $this->toon->decode($toon));
    }

    public function testDecodePrimitiveArray()
    {
        $toon = 'tags[3]: admin,ops,dev';
        $expected = ['tags' => ['admin', 'ops', 'dev']];

        $this->assertSame($expected, $this->toon->decode($toon));
    }

    public function testDecodeTabularArray()
    {
        $toon = <<<TOON
            users[2]{id,name}:
              1,Alice
              2,Bob
            TOON;
        $expected = [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
        ];

        $this->assertSame($expected, $this->toon->decode($toon));
    }

    public function testDecodeMixedArray()
    {
        $toon = <<<TOON
            items[2]:
              - first
              - second
            TOON;
        $expected = [
            'items' => ['first', 'second'],
        ];

        $this->assertSame($expected, $this->toon->decode($toon));
    }

    public function testDecodeComplexStructure()
    {
        $toon = <<<TOON
            context:
              task: Our favorite hikes
              location: Boulder
            friends[3]: ana,luis,sam
            TOON;
        $expected = [
            'context' => [
                'task' => 'Our favorite hikes',
                'location' => 'Boulder',
            ],
            'friends' => ['ana', 'luis', 'sam'],
        ];

        $this->assertSame($expected, $this->toon->decode($toon));
    }

    public function testDecodeEmptyDocument()
    {
        $this->assertSame([], $this->toon->decode(''));
    }

    public function testDecodeEmptyArray()
    {
        $toon = 'items: []';
        $expected = ['items' => []];

        $this->assertSame($expected, $this->toon->decode($toon));
    }

    public function testRoundTripPrimitives()
    {
        $values = [null, true, false, 42, -17, 3.14, 'hello'];

        foreach ($values as $value) {
            $encoded = $this->toon->encode($value);
            $decoded = $this->toon->decode($encoded);
            $this->assertSame($value, $decoded);
        }
    }

    public function testRoundTripSimpleObject()
    {
        $data = ['name' => 'Alice', 'active' => true, 'score' => 95];
        $encoded = $this->toon->encode($data);
        $decoded = $this->toon->decode($encoded);

        $this->assertSame($data, $decoded);
    }

    public function testRoundTripNestedObject()
    {
        $data = [
            'user' => [
                'profile' => [
                    'name' => 'Alice',
                    'verified' => true,
                ],
            ],
        ];
        $encoded = $this->toon->encode($data);
        $decoded = $this->toon->decode($encoded);

        $this->assertSame($data, $decoded);
    }

    public function testRoundTripPrimitiveArray()
    {
        $data = ['tags' => ['a', 'b', 'c']];
        $encoded = $this->toon->encode($data);
        $decoded = $this->toon->decode($encoded);

        $this->assertSame($data, $decoded);
    }

    public function testRoundTripTabularArray()
    {
        $data = [
            'users' => [
                ['id' => 1, 'name' => 'Alice', 'active' => true],
                ['id' => 2, 'name' => 'Bob', 'active' => false],
            ],
        ];
        $encoded = $this->toon->encode($data);
        $decoded = $this->toon->decode($encoded);

        $this->assertSame($data, $decoded);
    }

    public function testStrictModeArrayCountMismatch()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Array count mismatch');

        $toon = 'tags[5]: a,b,c';
        $this->toon->decode($toon);
    }

    public function testNonStrictModeArrayCountMismatch()
    {
        $toon = 'tags[5]: a,b,c';
        $result = $this->toon->decode($toon, strict: false);

        $this->assertSame(['tags' => ['a', 'b', 'c']], $result);
    }

    public function testStrictModeTabularWidthMismatch()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tabular row width mismatch');

        $toon = <<<TOON
            users[1]{id,name,email}:
              1,Alice
            TOON;
        $this->toon->decode($toon);
    }

    public function testInvalidEscapeSequence()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid escape sequence');

        $toon = '"hello\xworld"';
        $this->toon->decode($toon);
    }

    public function testEncodeWithCustomDelimiter()
    {
        $data = ['values' => [1, 2, 3]];
        $encoded = $this->toon->encode($data, delimiter: '|');

        $this->assertSame('values[3]: 1|2|3', $encoded);
    }

    public function testEncodeWithCustomIndentSize()
    {
        $data = [
            'user' => [
                'name' => 'Alice',
            ],
        ];
        $expected = <<<TOON
            user:
                name: Alice
            TOON;

        $this->assertSame($expected, $this->toon->encode($data, indentSize: 4));
    }

    public function testEncodeUnsupportedTypeThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->toon->encode(fopen('php://memory', 'r'));
    }

    public function testEncodeObjectConvertedToArray()
    {
        $obj = new \stdClass();
        $obj->name = 'Test';
        $obj->value = 42;

        $expected = <<<TOON
            name: Test
            value: 42
            TOON;

        $this->assertSame($expected, $this->toon->encode($obj));
    }

    public function testDecodeWithTrailingNewline()
    {
        $toon = <<<TOON
            name: Alice
            age: 30

            TOON;
        $expected = ['name' => 'Alice', 'age' => 30];

        $this->assertSame($expected, $this->toon->decode($toon));
    }

    public function testDecodeWithQuotedKey()
    {
        $toon = '"special:key": value';
        $expected = ['special:key' => 'value'];

        $this->assertSame($expected, $this->toon->decode($toon));
    }

    public function testEncodeKeyWithSpecialChars()
    {
        $data = ['special:key' => 'value'];

        $this->assertSame('"special:key": value', $this->toon->encode($data));
    }

    public function testDecodeArrayOfArrays()
    {
        $toon = <<<TOON
            pairs[2]:
              - [2]: 1,2
              - [2]: 3,4
            TOON;
        $expected = [
            'pairs' => [
                [1, 2],
                [3, 4],
            ],
        ];

        $this->assertSame($expected, $this->toon->decode($toon));
    }

    public function testEncodeTabularWithQuotedValues()
    {
        $data = [
            'items' => [
                ['name' => 'Item, One', 'price' => 10],
                ['name' => 'Item Two', 'price' => 20],
            ],
        ];
        $encoded = $this->toon->encode($data);

        $this->assertStringContainsString('"Item, One"', $encoded);
    }

    public function testDecodeTabularWithQuotedValues()
    {
        $toon = <<<TOON
            items[2]{name,price}:
              "Item, One",10
              Item Two,20
            TOON;
        $expected = [
            'items' => [
                ['name' => 'Item, One', 'price' => 10],
                ['name' => 'Item Two', 'price' => 20],
            ],
        ];

        $this->assertSame($expected, $this->toon->decode($toon));
    }

    public function testEncodeHyphenString()
    {
        $this->assertSame('"-"', $this->toon->encode('-'));
    }

    public function testEncodeStringStartingWithHyphen()
    {
        $this->assertSame('"-hello"', $this->toon->encode('-hello'));
    }

    public function testDecodeMultiplePrimitiveArrays()
    {
        $toon = <<<TOON
            first[2]: a,b
            second[3]: 1,2,3
            TOON;
        $expected = [
            'first' => ['a', 'b'],
            'second' => [1, 2, 3],
        ];

        $this->assertSame($expected, $this->toon->decode($toon));
    }

    public function testEncodeArrayWithNullValues()
    {
        $data = [
            'items' => [
                ['id' => 1, 'value' => null],
                ['id' => 2, 'value' => null],
            ],
        ];
        $encoded = $this->toon->encode($data);

        $this->assertStringContainsString('null', $encoded);
    }

    public function testDecodeTabularWithNullValues()
    {
        $toon = <<<TOON
            items[2]{id,value}:
              1,null
              2,null
            TOON;
        $expected = [
            'items' => [
                ['id' => 1, 'value' => null],
                ['id' => 2, 'value' => null],
            ],
        ];

        $this->assertSame($expected, $this->toon->decode($toon));
    }
}
