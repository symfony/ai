<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cohere\Cohere;
use Symfony\AI\Platform\Model;

final class CohereTest extends TestCase
{
    public function testItExtendsModel()
    {
        $model = new Cohere('command-a-03-2025');

        $this->assertInstanceOf(Model::class, $model);
        $this->assertSame('command-a-03-2025', $model->getName());
    }
}
