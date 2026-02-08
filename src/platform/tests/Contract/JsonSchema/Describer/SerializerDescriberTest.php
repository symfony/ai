<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Contract\JsonSchema\Describer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\SerializerDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\ListItemDiscriminator;

final class SerializerDescriberTest extends TestCase
{
    public function testDescribeDiscriminatorMapObject()
    {
        $describer = new SerializerDescriber();
        $describer->setFactory(new Factory([$describer]));
        $schema = null;

        $describer->describe(new \ReflectionClass(ListItemDiscriminator::class), $schema);

        $expectedSchema = [
            'anyOf' => [
                [
                    'properties' => [
                        'name' => null,
                        'type' => ['const' => 'name'],
                    ],
                    'required' => ['name', 'type'],
                    'additionalProperties' => false,
                ],
                [
                    'properties' => [
                        'age' => null,
                        'type' => ['const' => 'age'],
                    ],
                    'required' => ['age', 'type'],
                    'additionalProperties' => false,
                ],
            ],
        ];

        $this->assertSame($expectedSchema, $schema);
    }
}
