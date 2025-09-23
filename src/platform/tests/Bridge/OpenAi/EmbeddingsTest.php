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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
#[CoversClass(Embeddings::class)]
#[Small]
final class EmbeddingsTest extends TestCase
{
    public function testItCreatesEmbeddingsWithDefaultSettings()
    {
        $embeddings = new Embeddings(Embeddings::TEXT_3_SMALL);

        $this->assertSame(Embeddings::TEXT_3_SMALL, $embeddings->getName());
        $this->assertSame([], $embeddings->getOptions());
    }

    public function testItCreatesEmbeddingsWithCustomSettings()
    {
        $embeddings = new Embeddings(Embeddings::TEXT_3_LARGE, ['dimensions' => 256]);

        $this->assertSame(Embeddings::TEXT_3_LARGE, $embeddings->getName());
        $this->assertSame(['dimensions' => 256], $embeddings->getOptions());
    }

    public function testItCreatesEmbeddingsWithAdaModel()
    {
        $embeddings = new Embeddings(Embeddings::TEXT_ADA_002);

        $this->assertSame(Embeddings::TEXT_ADA_002, $embeddings->getName());
    }
}
