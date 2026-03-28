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
use Symfony\AI\Platform\Bridge\Cohere\Embeddings;
use Symfony\AI\Platform\Bridge\Cohere\InputType;
use Symfony\AI\Platform\Model;

final class EmbeddingsTest extends TestCase
{
    public function testItExtendsModel()
    {
        $model = new Embeddings('embed-english-v3.0');

        $this->assertInstanceOf(Model::class, $model);
        $this->assertSame('embed-english-v3.0', $model->getName());
    }

    public function testItAcceptsInputTypeOption()
    {
        $model = new Embeddings('embed-english-v3.0', [], ['input_type' => InputType::SearchQuery]);

        $this->assertSame(['input_type' => InputType::SearchQuery], $model->getOptions());
    }
}
