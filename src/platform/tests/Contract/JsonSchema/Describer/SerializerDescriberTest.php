<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\JsonSchema\Describer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\ModelDescriberInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\SerializerDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Model;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\ListItemAge;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\ListItemDiscriminator;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\ListItemName;

final class SerializerDescriberTest extends TestCase
{
    public function testDescribeDiscriminatorMapObject()
    {
        $modelDescriber = new class implements ModelDescriberInterface {
            public function describeModel(Model $model, ?array &$schema): iterable
            {
                if ($model->getReflector() instanceof \ReflectionClass) {
                    $schema['description'] = $model->getName();
                }

                return [];
            }
        };

        $describer = new SerializerDescriber();
        $describer->setModelDescriber($modelDescriber);
        $schema = null;

        $describer->describeModel(new Model(ListItemDiscriminator::class, new \ReflectionClass(ListItemDiscriminator::class)), $schema);

        $expectedSchema = [
            'type' => 'object',
            'anyOf' => [
                [
                    'description' => ListItemName::class,
                    'properties' => [
                        'type' => [
                            'const' => 'name',
                        ],
                    ],
                ],
                [
                    'description' => ListItemAge::class,
                    'properties' => [
                        'type' => [
                            'const' => 'age',
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame($expectedSchema, $schema);
    }
}
