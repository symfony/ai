<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class EmbeddingsTest extends TestCase
{
    public function testItCreatesEmbeddingsWithDefaultSettings()
    {
        $embeddings = new Embeddings('text-embedding-3-small');

        $this->assertSame('text-embedding-3-small', $embeddings->getName());
        $this->assertSame([], $embeddings->getOptions());
    }

    public function testItCreatesEmbeddingsWithCustomSettings()
    {
        $embeddings = new Embeddings('text-embedding-3-large', options: ['dimensions' => 256]);

        $this->assertSame('text-embedding-3-large', $embeddings->getName());
        $this->assertSame(['dimensions' => 256], $embeddings->getOptions());
    }

    public function testItCreatesEmbeddingsWithAdaModel()
    {
        $embeddings = new Embeddings('text-embedding-ada-002');

        $this->assertSame('text-embedding-ada-002', $embeddings->getName());
    }
}
