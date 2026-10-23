<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Message\TemplateRenderer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Template;
use Symfony\AI\Platform\Message\TemplateRenderer\StringTemplateRenderer;

final class StringTemplateRendererTest extends TestCase
{
    private StringTemplateRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new StringTemplateRenderer();
    }

    public function testSupportsStringType()
    {
        $this->assertTrue($this->renderer->supports('string'));
        $this->assertFalse($this->renderer->supports('expression'));
        $this->assertFalse($this->renderer->supports('twig'));
    }

    public function testRenderSimpleVariable()
    {
        $template = Template::string('Hello {name}!');

        $result = $this->renderer->render($template, ['name' => 'World']);

        $this->assertSame('Hello World!', $result);
    }

    public function testRenderMultipleVariables()
    {
        $template = Template::string('{greeting} {name}!');

        $result = $this->renderer->render($template, [
            'greeting' => 'Hello',
            'name' => 'World',
        ]);

        $this->assertSame('Hello World!', $result);
    }

    public function testRenderNumericValue()
    {
        $template = Template::string('The answer is {answer}');

        $result = $this->renderer->render($template, ['answer' => 42]);

        $this->assertSame('The answer is 42', $result);
    }

    public function testRenderStringableValue()
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable';
            }
        };

        $template = Template::string('Value: {value}');

        $result = $this->renderer->render($template, ['value' => $stringable]);

        $this->assertSame('Value: stringable', $result);
    }

    public function testRenderWithUnusedVariable()
    {
        $template = Template::string('Hello {name}!');

        $result = $this->renderer->render($template, [
            'name' => 'World',
            'unused' => 'value',
        ]);

        $this->assertSame('Hello World!', $result);
    }

    public function testRenderWithMissingVariable()
    {
        $template = Template::string('Hello {name}!');

        $result = $this->renderer->render($template, []);

        $this->assertSame('Hello {name}!', $result);
    }

    /**
     * @param array<int|string, mixed> $variables
     */
    #[DataProvider('provideNonStringKeys')]
    public function testThrowsExceptionForNonStringKey(array $variables)
    {
        $template = Template::string('Hello {name}!');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Template variable keys must be strings');

        $this->renderer->render($template, $variables);
    }

    /**
     * @return iterable<string, array{array<int|string, mixed>}>
     */
    public static function provideNonStringKeys(): iterable
    {
        yield 'numeric scalar key' => [[0 => 'value']];
        yield 'numeric key before nested array' => [[2 => 'value', 'person' => ['name' => 'Ada']]];
        yield 'numeric key before empty array' => [[2 => 'value', 'person' => []]];
        yield 'numeric key under empty prefix' => [['' => [2 => 'value'], 'person' => ['name' => 'Ada']]];
        yield 'numeric key overwritten after reindexing' => [[2 => 'first', 'person' => [], 0 => 'last']];
    }

    public function testThrowsExceptionForInvalidValueType()
    {
        $template = Template::string('Hello {name}!');
        $object = new \stdClass();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Template variable "name" must be string, numeric or Stringable');

        $this->renderer->render($template, ['name' => $object]);
    }

    public function testRenderNestedArrayWithDotNotation()
    {
        $template = Template::string('City: {city.name}, Population: {city.population}');

        $result = $this->renderer->render($template, [
            'city' => [
                'name' => 'Berlin',
                'population' => 3500000,
            ],
        ]);

        $this->assertSame('City: Berlin, Population: 3500000', $result);
    }

    public function testRenderDeeplyNestedArray()
    {
        $template = Template::string('Value: {level1.level2.level3}');

        $result = $this->renderer->render($template, [
            'level1' => [
                'level2' => [
                    'level3' => 'deep value',
                ],
            ],
        ]);

        $this->assertSame('Value: deep value', $result);
    }

    public function testRenderNullValueAsEmptyString()
    {
        $template = Template::string('Value: {value}');

        $result = $this->renderer->render($template, ['value' => null]);

        $this->assertSame('Value: ', $result);
    }

    public function testRenderMixedFlatAndNestedVariables()
    {
        $template = Template::string('{greeting} {person.name} from {person.city.name}!');

        $result = $this->renderer->render($template, [
            'greeting' => 'Hello',
            'person' => [
                'name' => 'John',
                'city' => [
                    'name' => 'Paris',
                ],
            ],
        ]);

        $this->assertSame('Hello John from Paris!', $result);
    }

    public function testRenderEmptyNestedArray()
    {
        $template = Template::string('Value: {data.key}');

        $result = $this->renderer->render($template, ['data' => []]);

        $this->assertSame('Value: {data.key}', $result);
    }

    public function testRenderNestedArrayWithNumericValues()
    {
        $template = Template::string('Coordinates: {location.lat}, {location.lng}');

        $result = $this->renderer->render($template, [
            'location' => [
                'lat' => 52.520008,
                'lng' => 13.404954,
            ],
        ]);

        $this->assertSame('Coordinates: 52.520008, 13.404954', $result);
    }

    /**
     * @param array<string, mixed> $variables
     */
    #[DataProvider('provideNestedVariables')]
    public function testRenderPreservesNestedVariableOrder(array $variables, string $template, string $expected)
    {
        $this->assertSame($expected, $this->renderer->render(Template::string($template), $variables));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function provideNestedVariables(): iterable
    {
        yield 'nested value overwrites dotted key' => [
            ['person.name' => 'first', 'person' => ['name' => 'last']],
            '{person.name}',
            'last',
        ];
        yield 'dotted key overwrites nested value' => [
            ['person' => ['name' => 'first'], 'person.name' => 'last'],
            '{person.name}',
            'last',
        ];
        yield 'replacement follows variable order' => [
            ['value' => '{person.name}', 'person' => ['name' => 'Ada']],
            '{value}',
            'Ada',
        ];
        yield 'replacement does not revisit earlier variables' => [
            ['person' => ['name' => 'Ada'], 'value' => '{person.name}'],
            '{value}',
            '{person.name}',
        ];
        yield 'overwritten key retains its position' => [
            ['person' => ['name' => 'first'], 'value' => '{person.name}', 'person.name' => 'last'],
            '{value}',
            '{person.name}',
        ];
        yield 'nested numeric keys' => [
            ['items' => [2 => 'two', 0 => 'zero']],
            '{items.2}/{items.0}',
            'two/zero',
        ];
        yield 'empty prefix' => [
            ['' => ['name' => 'Ada']],
            '{name}',
            'Ada',
        ];
        yield 'nested empty array does not overwrite dotted key' => [
            ['person.name' => 'Ada', 'person' => ['name' => []]],
            '{person.name}',
            'Ada',
        ];
    }
}
