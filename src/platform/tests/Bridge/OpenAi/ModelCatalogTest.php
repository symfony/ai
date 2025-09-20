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
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ModelNotFoundException;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
#[CoversClass(ModelCatalog::class)]
#[Small]
final class ModelCatalogTest extends TestCase
{
    public function testItCreatesWithDefaultModels(): void
    {
        $catalog = new ModelCatalog();

        $supportedModels = $catalog->getSupportedModels();

        $this->assertContains('gpt-4o', $supportedModels);
        $this->assertContains('gpt-4o-mini', $supportedModels);
        $this->assertContains('gpt-4o-audio-preview', $supportedModels);
        $this->assertContains('gpt-4-turbo', $supportedModels);
        $this->assertContains('gpt-4', $supportedModels);
        $this->assertContains('gpt-3.5-turbo', $supportedModels);
        $this->assertContains('text-embedding-3-large', $supportedModels);
        $this->assertContains('text-embedding-3-small', $supportedModels);
        $this->assertContains('text-embedding-ada-002', $supportedModels);
    }

    public function testItCreatesWithAdditionalModels(): void
    {
        $additionalModels = [
            'custom-gpt' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT],
            ],
        ];

        $catalog = new ModelCatalog($additionalModels);

        $supportedModels = $catalog->getSupportedModels();

        $this->assertContains('custom-gpt', $supportedModels);
        $this->assertContains('gpt-4o', $supportedModels); // Default models still present
    }

    #[TestWith(['gpt-4o', Gpt::class])]
    #[TestWith(['gpt-4o-mini', Gpt::class])]
    #[TestWith(['gpt-3.5-turbo', Gpt::class])]
    #[TestWith(['text-embedding-3-large', Embeddings::class])]
    #[TestWith(['text-embedding-3-small', Embeddings::class])]
    #[TestWith(['text-embedding-ada-002', Embeddings::class])]
    public function testItReturnsCorrectModelInstance(string $modelName, string $expectedClass): void
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel($modelName);

        $this->assertInstanceOf($expectedClass, $model);
    }

    public function testItThrowsExceptionForUnknownModel(): void
    {
        $catalog = new ModelCatalog();

        $this->expectException(ModelNotFoundException::class);

        $catalog->getModel('unknown-model');
    }

    public function testItReturnsCapabilitiesForGptModel(): void
    {
        $catalog = new ModelCatalog();

        $capabilities = $catalog->getCapabilities('gpt-4o');

        $this->assertContains(Capability::INPUT_MESSAGES, $capabilities);
        $this->assertContains(Capability::INPUT_IMAGE, $capabilities);
        $this->assertContains(Capability::OUTPUT_TEXT, $capabilities);
        $this->assertContains(Capability::OUTPUT_STREAMING, $capabilities);
        $this->assertContains(Capability::OUTPUT_STRUCTURED, $capabilities);
        $this->assertContains(Capability::TOOL_CALLING, $capabilities);
    }

    public function testItReturnsCapabilitiesForEmbeddingModel(): void
    {
        $catalog = new ModelCatalog();

        $capabilities = $catalog->getCapabilities('text-embedding-3-large');

        $this->assertContains(Capability::INPUT_TEXT, $capabilities);
        $this->assertCount(1, $capabilities);
    }

    public function testItReturnsCapabilitiesForAudioModel(): void
    {
        $catalog = new ModelCatalog();

        $capabilities = $catalog->getCapabilities('gpt-4o-audio-preview');

        $this->assertContains(Capability::INPUT_MESSAGES, $capabilities);
        $this->assertContains(Capability::INPUT_AUDIO, $capabilities);
        $this->assertContains(Capability::INPUT_IMAGE, $capabilities);
        $this->assertContains(Capability::OUTPUT_TEXT, $capabilities);
        $this->assertContains(Capability::OUTPUT_STREAMING, $capabilities);
        $this->assertContains(Capability::OUTPUT_STRUCTURED, $capabilities);
        $this->assertContains(Capability::TOOL_CALLING, $capabilities);
    }

    public function testItReturnsEmptyCapabilitiesForUnknownModel(): void
    {
        $catalog = new ModelCatalog();

        $capabilities = $catalog->getCapabilities('unknown-model');

        $this->assertSame([], $capabilities);
    }

    public function testItReturnsCapabilitiesForCustomModel(): void
    {
        $additionalModels = [
            'custom-model' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT],
            ],
        ];

        $catalog = new ModelCatalog($additionalModels);

        $capabilities = $catalog->getCapabilities('custom-model');

        $this->assertContains(Capability::INPUT_TEXT, $capabilities);
        $this->assertContains(Capability::OUTPUT_TEXT, $capabilities);
        $this->assertCount(2, $capabilities);
    }

    public function testItReturnsAllModelsIncludingAdditional(): void
    {
        $additionalModels = [
            'custom-gpt' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [Capability::INPUT_TEXT],
            ],
            'custom-embedding' => [
                'class' => Embeddings::class,
                'platform' => 'openai',
                'capabilities' => [Capability::INPUT_TEXT],
            ],
        ];

        $catalog = new ModelCatalog($additionalModels);

        $models = $catalog->getModels();

        $this->assertArrayHasKey('custom-gpt', $models);
        $this->assertArrayHasKey('custom-embedding', $models);
        $this->assertArrayHasKey('gpt-4o', $models); // Default models still present

        $this->assertSame(Gpt::class, $models['custom-gpt']['class']);
        $this->assertSame('openai', $models['custom-gpt']['platform']);
        $this->assertSame([Capability::INPUT_TEXT], $models['custom-gpt']['capabilities']);
    }

    public function testAdditionalModelsOverrideDefaultModels(): void
    {
        $additionalModels = [
            'gpt-4o' => [
                'class' => Embeddings::class, // Override default Gpt class
                'platform' => 'openai',
                'capabilities' => [Capability::INPUT_TEXT],
            ],
        ];

        $catalog = new ModelCatalog($additionalModels);

        $models = $catalog->getModels();

        // The additional model should override the default one
        $this->assertSame(Embeddings::class, $models['gpt-4o']['class']);
        $this->assertSame([Capability::INPUT_TEXT], $models['gpt-4o']['capabilities']);
    }

    public function testItReturnsCustomModelInstance(): void
    {
        $additionalModels = [
            'custom-model' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [Capability::INPUT_TEXT],
            ],
        ];

        $catalog = new ModelCatalog($additionalModels);

        $model = $catalog->getModel('custom-model');

        $this->assertInstanceOf(Gpt::class, $model);
    }
}
