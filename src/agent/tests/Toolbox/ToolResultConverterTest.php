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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithConstructor;

final class ToolResultConverterTest extends TestCase
{
    #[DataProvider('provideResults')]
    public function testConvert(mixed $result, ?string $expected)
    {
        $toolResult = new ToolResult(new ToolCall('123456789', 'tool_name'), $result);
        $converter = new ToolResultConverter();

        $this->assertSame($expected, $converter->convert($toolResult));
    }

    public static function provideResults(): \Generator
    {
        yield 'null' => [null, null];

        yield 'integer' => [42, '42'];

        yield 'float' => [42.42, '42.42'];

        yield 'array' => [['key' => 'value'], '{"key":"value"}'];

        yield 'string' => ['plain string', 'plain string'];

        yield 'datetime' => [new \DateTimeImmutable('2021-07-31 12:34:56'), '"2021-07-31T12:34:56+00:00"'];

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

        yield 'object' => [
            new UserWithConstructor(
                id: 123,
                name: 'John Doe',
                createdAt: new \DateTimeImmutable('2021-07-31 12:34:56'),
                isActive: true,
                age: 18,
            ),
            '{"id":123,"name":"John Doe","createdAt":"2021-07-31T12:34:56+00:00","isActive":true,"age":18}',
        ];
    }
}
