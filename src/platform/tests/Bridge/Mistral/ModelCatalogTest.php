<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Mistral;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\ModelCatalog;
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
        yield 'mistral-embed' => ['mistral-embed'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function chatModelsProvider(): iterable
    {
        yield 'mistral-large-latest' => ['mistral-large-latest'];
        yield 'mistral-small-latest' => ['mistral-small-latest'];
        yield 'codestral-latest' => ['codestral-latest'];
    }
}
