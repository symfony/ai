<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\AiMlApi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\AiMlApi\ModelCatalog;
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
        yield 'text-embedding-3-small' => ['text-embedding-3-small'];
        yield 'text-embedding-3-large' => ['text-embedding-3-large'];
        yield 'text-embedding-ada-002' => ['text-embedding-ada-002'];
        yield 'togethercomputer/m2-bert-80M-32k-retrieval' => ['togethercomputer/m2-bert-80M-32k-retrieval'];
        yield 'BAAI/bge-base-en-v1.5' => ['BAAI/bge-base-en-v1.5'];
        yield 'BAAI/bge-large-en-v1.' => ['BAAI/bge-large-en-v1.'];
        yield 'voyage-large-2-instruct' => ['voyage-large-2-instruct'];
        yield 'voyage-finance-2' => ['voyage-finance-2'];
        yield 'voyage-multilingual-2' => ['voyage-multilingual-2'];
        yield 'voyage-law-2' => ['voyage-law-2'];
        yield 'voyage-code-2' => ['voyage-code-2'];
        yield 'voyage-large-2' => ['voyage-large-2'];
        yield 'voyage-2' => ['voyage-2'];
        yield 'textembedding-gecko@003' => ['textembedding-gecko@003'];
        yield 'textembedding-gecko-multilingual@001' => ['textembedding-gecko-multilingual@001'];
        yield 'text-multilingual-embedding-002' => ['text-multilingual-embedding-002'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function chatModelsProvider(): iterable
    {
        yield 'gpt-4o' => ['gpt-4o'];
        yield 'gpt-4o-mini' => ['gpt-4o-mini'];
        yield 'claude-3-5-sonnet-20241022' => ['claude-3-5-sonnet-20241022'];
    }
}
