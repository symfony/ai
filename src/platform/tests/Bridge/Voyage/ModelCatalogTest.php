<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Voyage;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Voyage\ModelCatalog;
use Symfony\AI\Platform\Capability;

/**
 * @author Ramy Hakam <ramyhakam1@gmail.com>
 */
final class ModelCatalogTest extends TestCase
{
    #[DataProvider('embeddingModelsProvider')]
    public function testEmbeddingModelsSupportEmbeddingsCapability(string $modelName)
    {
        $catalog = new ModelCatalog();
        $model = $catalog->getModel($modelName);

        $this->assertTrue(
            $model->supports(Capability::EMBEDDINGS),
            \sprintf('Embedding model "%s" should support EMBEDDINGS capability', $modelName)
        );
    }

    #[DataProvider('embeddingModelsProvider')]
    public function testEmbeddingModelsSupportInputMultipleCapability(string $modelName)
    {
        $catalog = new ModelCatalog();
        $model = $catalog->getModel($modelName);

        $this->assertTrue(
            $model->supports(Capability::INPUT_MULTIPLE),
            \sprintf('Embedding model "%s" should support INPUT_MULTIPLE capability', $modelName)
        );
    }

    public function testMultimodalModelSupportsInputMultimodalCapability()
    {
        $catalog = new ModelCatalog();
        $model = $catalog->getModel('voyage-multimodal-3');

        $this->assertTrue(
            $model->supports(Capability::INPUT_MULTIMODAL),
            'Multimodal model "voyage-multimodal-3" should support INPUT_MULTIMODAL capability'
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function embeddingModelsProvider(): iterable
    {
        yield 'voyage-3.5' => ['voyage-3.5'];
        yield 'voyage-3.5-lite' => ['voyage-3.5-lite'];
        yield 'voyage-3' => ['voyage-3'];
        yield 'voyage-3-lite' => ['voyage-3-lite'];
        yield 'voyage-3-large' => ['voyage-3-large'];
        yield 'voyage-finance-2' => ['voyage-finance-2'];
        yield 'voyage-multilingual-2' => ['voyage-multilingual-2'];
        yield 'voyage-law-2' => ['voyage-law-2'];
        yield 'voyage-code-3' => ['voyage-code-3'];
        yield 'voyage-code-2' => ['voyage-code-2'];
        yield 'voyage-multimodal-3' => ['voyage-multimodal-3'];
    }
}
