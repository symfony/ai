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
use Symfony\AI\Platform\Contract\JsonSchema\Describer\Describer;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\CPPWithAtVarDocFixture;

final class DescriberTest extends TestCase
{
    public function testDescribeObject()
    {
        $describer = new Describer();
        $describer->describeObject(new ObjectSubject(CPPWithAtVarDocFixture::class, new \ReflectionClass(CPPWithAtVarDocFixture::class)), $actual);

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'steps' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'explanation' => [
                                'type' => 'string',
                            ],
                            'output' => [
                                'type' => 'string',
                            ],
                        ],
                        'required' => ['explanation', 'output'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['steps'],
            'additionalProperties' => false,
        ], $actual);
    }
}
