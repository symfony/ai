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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\ModelCatalog;
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

    #[DataProvider('chatModelsProvider')]
    public function testChatModelsSupportInputMessagesCapability(string $modelName)
    {
        $catalog = new ModelCatalog();
        $model = $catalog->getModel($modelName);

        $this->assertTrue(
            $model->supports(Capability::INPUT_MESSAGES),
            \sprintf('Chat model "%s" should support INPUT_MESSAGES capability', $modelName)
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function embeddingModelsProvider(): iterable
    {
        yield 'text-embedding-ada-002' => ['text-embedding-ada-002'];
        yield 'text-embedding-3-large' => ['text-embedding-3-large'];
        yield 'text-embedding-3-small' => ['text-embedding-3-small'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function chatModelsProvider(): iterable
    {
        yield 'gpt-3.5-turbo' => ['gpt-3.5-turbo'];
        yield 'gpt-4' => ['gpt-4'];
        yield 'gpt-4-turbo' => ['gpt-4-turbo'];
        yield 'gpt-4o' => ['gpt-4o'];
        yield 'gpt-4o-mini' => ['gpt-4o-mini'];
    }
}
