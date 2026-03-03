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

    public function testThrowsExceptionForNonStringKey()
    {
        $template = Template::string('Hello {name}!');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Template variable keys must be strings');

        /* @phpstan-ignore-next-line - Intentionally passing wrong type to test exception */
        $this->renderer->render($template, [0 => 'value']);
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
}
