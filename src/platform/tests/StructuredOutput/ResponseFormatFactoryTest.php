<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\StructuredOutput;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Fixtures\StructuredOutput\User;
use Symfony\AI\Platform\StructuredOutput\ResponseFormatFactory;

final class ResponseFormatFactoryTest extends TestCase
{
    public function testCreate()
    {
        $this->assertSame([
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'User',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => [
                            'type' => 'string',
                            'description' => 'The name of the user in lowercase',
                        ],
                        'createdAt' => [
                            'type' => 'string',
                            'format' => 'date-time',
                        ],
                        'isActive' => ['type' => 'boolean'],
                        'age' => ['type' => ['integer', 'null']],
                    ],
                    'required' => ['id', 'name', 'createdAt', 'isActive', 'age'],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ],
        ], (new ResponseFormatFactory())->create(User::class));
    }
}
