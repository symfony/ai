<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;

#[CoversClass(ToolResultConverter::class)]
final class ToolResultConverterTest extends TestCase
{
    #[Test]
    #[DataProvider('provideResults')]
    public function testConvert(mixed $result, ?string $expected): void
    {
        $converter = new ToolResultConverter();

        self::assertSame($expected, $converter->convert($result));
    }

    public static function provideResults(): \Generator
    {
        yield 'null' => [null, null];

        yield 'integer' => [42, '42'];

        yield 'float' => [42.42, '42.42'];

        yield 'array' => [['key' => 'value'], '{"key":"value"}'];

        yield 'string' => ['plain string', 'plain string'];

        yield 'datetime' => [new \DateTimeImmutable('2021-07-31 12:34:56'), '2021-07-31T12:34:56+00:00'];

        yield 'stringable' => [
            new class implements \Stringable {
                public function __toString(): string
                {
                    return 'stringable';
                }
            },
            'stringable',
        ];

        yield 'json_serializable' => [
            new class implements \JsonSerializable {
                public function jsonSerialize(): array
                {
                    return ['key' => 'value'];
                }
            },
            '{"key":"value"}',
        ];
    }
}
