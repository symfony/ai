<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\OpenRouter\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'openrouter/auto' => [
            'openrouter/auto',
            CompletionsModel::class,
            [
                Capability::INPUT_TEXT,
                Capability::OUTPUT_TEXT,
            ],
        ];

        yield 'anthropic/claude-sonnet-4.5' => [
            'anthropic/claude-sonnet-4.5',
            CompletionsModel::class,
            [
                Capability::INPUT_TEXT,
                Capability::INPUT_IMAGE,
                Capability::INPUT_PDF,
                Capability::OUTPUT_TEXT,
            ],
        ];

        yield 'openai/gpt-5' => [
            'openai/gpt-5',
            CompletionsModel::class,
            [
                Capability::INPUT_TEXT,
                Capability::INPUT_IMAGE,
                Capability::INPUT_PDF,
                Capability::OUTPUT_TEXT,
            ],
        ];

        yield 'google/gemini-2.5-flash-image' => [
            'google/gemini-2.5-flash-image',
            CompletionsModel::class,
            [
                Capability::INPUT_IMAGE,
                Capability::INPUT_TEXT,
                Capability::OUTPUT_IMAGE,
                Capability::OUTPUT_TEXT,
            ],
        ];

        yield 'openai/text-embedding-3-large' => [
            'openai/text-embedding-3-large',
            EmbeddingsModel::class,
            [
                Capability::INPUT_TEXT,
                Capability::EMBEDDINGS,
            ],
        ];
    }

    public function testGetModelThrowsExceptionForEmptyModelName()
    {
        $catalog = new ModelCatalog();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model name cannot be empty.');

        // @phpstan-ignore argument.type (testing invalid input)
        $catalog->getModel('');
    }

    public function testGetModelReturnsDefaultModelForUnknownModel()
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel('unknown/model');

        $this->assertInstanceOf(Model::class, $model);
        $this->assertInstanceOf(CompletionsModel::class, $model);
        $this->assertSame('unknown/model', $model->getName());
        $this->assertSame([], $model->getCapabilities());
    }

    public function testGetModelWithPreset()
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel('@preset/my-custom-preset');

        $this->assertInstanceOf(Model::class, $model);
        $this->assertInstanceOf(CompletionsModel::class, $model);
        $this->assertSame('@preset/my-custom-preset', $model->getName());
        $this->assertSame(Capability::cases(), $model->getCapabilities());
    }

    public function testGetModelWithModifier()
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel('deepseek/deepseek-v3.1-terminus:exacto');

        $this->assertInstanceOf(Model::class, $model);
        $this->assertInstanceOf(CompletionsModel::class, $model);
        $this->assertSame('deepseek/deepseek-v3.1-terminus:exacto', $model->getName());
        $this->assertSame([Capability::INPUT_TEXT, Capability::OUTPUT_TEXT], $model->getCapabilities());
    }

    /**
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
     */
    #[DataProvider('additionalModelsProvider')]
    public function testConstructorWithAdditionalModels(array $additionalModels, string $modelName, string $expectedClass)
    {
        $catalog = new ModelCatalog($additionalModels);

        $model = $catalog->getModel($modelName);

        $this->assertInstanceOf($expectedClass, $model);
    }

    /**
     * @return iterable<string, array{array<string, array{class: string, capabilities: list<Capability>}>, string, string}>
     */
    public static function additionalModelsProvider(): iterable
    {
        yield 'custom completions model' => [
            [
                'custom/my-model' => [
                    'class' => CompletionsModel::class,
                    'capabilities' => [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT],
                ],
            ],
            'custom/my-model',
            CompletionsModel::class,
        ];

        yield 'custom embeddings model' => [
            [
                'custom/my-embedding' => [
                    'class' => EmbeddingsModel::class,
                    'capabilities' => [Capability::INPUT_TEXT, Capability::EMBEDDINGS],
                ],
            ],
            'custom/my-embedding',
            EmbeddingsModel::class,
        ];
    }

    public function testGetModelThrowsExceptionForUnknownModel()
    {
        // Override parent test because OpenRouter catalog does NOT throw exception
        // for unknown models - it creates a default model instead
        $this->markTestSkipped('OpenRouter ModelCatalog creates default models for unknown model names.');
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
