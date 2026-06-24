<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Message\Content;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Json;

final class JsonTest extends TestCase
{
    public function testImplementsContentInterface()
    {
        $this->assertInstanceOf(ContentInterface::class, new Json(new \stdClass()));
    }

    public function testGetObjectReturnsWrappedValue()
    {
        $object = new \stdClass();
        $object->title = 'test';

        $this->assertSame($object, (new Json($object))->getObject());
    }

    public function testToJsonForJsonSerializable()
    {
        $payload = new class implements \JsonSerializable {
            /**
             * @return array{title: string, ingredients: list<string>}
             */
            public function jsonSerialize(): array
            {
                return ['title' => 'Pasta', 'ingredients' => ['flour', 'water']];
            }
        };

        $this->assertSame('{"title":"Pasta","ingredients":["flour","water"]}', (new Json($payload))->toJson());
    }

    public function testToJsonForStringablePrefersStringCast()
    {
        $payload = new class implements \Stringable {
            public function __toString(): string
            {
                return 'plain string content';
            }
        };

        $this->assertSame('plain string content', (new Json($payload))->toJson());
    }

    public function testToJsonForStringableAndJsonSerializablePrefersJson()
    {
        $payload = new class implements \JsonSerializable, \Stringable {
            public function __toString(): string
            {
                return 'via __toString';
            }

            /**
             * @return array{kind: string}
             */
            public function jsonSerialize(): array
            {
                return ['kind' => 'json'];
            }
        };

        $this->assertSame('{"kind":"json"}', (new Json($payload))->toJson());
    }

    public function testToJsonForPlainObjectEncodesPublicProperties()
    {
        $object = new \stdClass();
        $object->id = 42;
        $object->name = 'demo';

        $this->assertSame('{"id":42,"name":"demo"}', (new Json($object))->toJson());
    }
}
