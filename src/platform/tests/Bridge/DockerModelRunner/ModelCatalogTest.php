<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\DockerModelRunner;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\DockerModelRunner\ModelCatalog;
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
        yield 'ai/nomic-embed-text-v1.5' => ['ai/nomic-embed-text-v1.5'];
        yield 'ai/mxbai-embed-large' => ['ai/mxbai-embed-large'];
        yield 'ai/embeddinggemma' => ['ai/embeddinggemma'];
        yield 'ai/granite-embedding-multilingual' => ['ai/granite-embedding-multilingual'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function chatModelsProvider(): iterable
    {
        yield 'ai/smollm2' => ['ai/smollm2'];
        yield 'ai/llama3.2' => ['ai/llama3.2'];
        yield 'ai/gemma3' => ['ai/gemma3'];
    }
}
