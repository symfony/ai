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
use Symfony\AI\Platform\Contract\JsonSchema\Describer\WithAttributeDescriber;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\ExampleDto;

final class WithAttributeDescriberTest extends TestCase
{
    public function testDescribe()
    {
        $describer = new WithAttributeDescriber();
        $schema = null;

        $describer->describe(new \ReflectionParameter([ExampleDto::class, '__construct'], 'taxRate'), $schema);

        $this->assertSame(['enum' => [7, 19]], $schema);
    }
}
