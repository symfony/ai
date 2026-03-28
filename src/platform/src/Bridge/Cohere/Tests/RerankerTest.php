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
use Symfony\AI\Platform\Bridge\Cohere\Reranker;
use Symfony\AI\Platform\Model;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RerankerTest extends TestCase
{
    public function testItExtendsModel()
    {
        $model = new Reranker('rerank-v3.5');

        $this->assertInstanceOf(Model::class, $model);
        $this->assertSame('rerank-v3.5', $model->getName());
    }
}
