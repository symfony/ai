<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bridge\Scaleway;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Scaleway\Embeddings;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 */
final class EmbeddingsTest extends TestCase
{
    public function testItCreatesEmbeddingsWithDefaultSettings()
    {
        $embeddings = new Embeddings('bge-multilingual-gemma2');

        $this->assertSame('bge-multilingual-gemma2', $embeddings->getName());
        $this->assertSame([], $embeddings->getOptions());
    }
}
