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
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Tests\Fixtures\Tool\EnumMode;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithConstructor;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

final class ToolResultConverterTest extends TestCase
{
    #[DataProvider('provideResults')]
    public function testConvert(mixed $result, ?string $expected)
    {
        $toolResult = new ToolResult(new ToolCall('123456789', 'tool_name'), $result);
        $converter = new ToolResultConverter();

        $this->assertSame($expected, $converter->convert($toolResult));
    }

    public function testConvertFails()
    {
        $toolCall = new ToolCall('123456789', 'tool_name');
        $notSerializeable = new \stdClass();
        $notSerializeable->resource = fopen('php://memory', 'r');

        $toolResult = new ToolResult($toolCall, $notSerializeable);
        $converter = new ToolResultConverter();

        try {
            $converter->convert($toolResult);
            $this->fail('Should have thrown before!');
        } catch (RuntimeException $ex) {
            $this->assertInstanceOf(ExceptionInterface::class, $ex->getPrevious(), 'Serializer exception expected!');
        }
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
                /**
                 * @return array<string, string>
                 */
                public function jsonSerialize(): array
                {
                    return ['key' => 'value'];
                }
            },
            '{"key":"value"}',
        ];

        yield 'backed enum' => [EnumMode::OR, '"or"'];

        yield 'array with backed enum' => [['mode' => EnumMode::AND], '{"mode":"and"}'];

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
