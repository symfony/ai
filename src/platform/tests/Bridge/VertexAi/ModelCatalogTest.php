<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\VertexAi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\ModelCatalog;
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
    public function testEmbeddingModelsSupportInputTextCapability(string $modelName)
    {
        $catalog = new ModelCatalog();
        $model = $catalog->getModel($modelName);

        $this->assertTrue(
            $model->supports(Capability::INPUT_TEXT),
            \sprintf('Embedding model "%s" should support INPUT_TEXT capability', $modelName)
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

    #[DataProvider('chatModelsProvider')]
    public function testChatModelsDoNotSupportEmbeddingsCapability(string $modelName)
    {
        $catalog = new ModelCatalog();
        $model = $catalog->getModel($modelName);

        $this->assertFalse(
            $model->supports(Capability::EMBEDDINGS),
            \sprintf('Chat model "%s" should not support EMBEDDINGS capability', $modelName)
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function embeddingModelsProvider(): iterable
    {
        yield 'gemini-embedding-001' => ['gemini-embedding-001'];
        yield 'text-embedding-005' => ['text-embedding-005'];
        yield 'text-multilingual-embedding-002' => ['text-multilingual-embedding-002'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function chatModelsProvider(): iterable
    {
        yield 'gemini-2.5-flash' => ['gemini-2.5-flash'];
        yield 'gemini-2.5-pro' => ['gemini-2.5-pro'];
        yield 'gemini-3-pro-preview' => ['gemini-3-pro-preview'];
    }
}
