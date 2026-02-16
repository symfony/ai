<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsDev\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\ModelsDev\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ModelCatalogTest extends TestCase
{
    public function testLoadProviderData()
    {
        $catalog = new ModelCatalog('openai');

        $models = $catalog->getModels();

        $this->assertNotEmpty($models);
        $this->assertArrayHasKey('gpt-4o', $models);
    }

    public function testGetCompletionsModel()
    {
        $catalog = new ModelCatalog('openai');

        $model = $catalog->getModel('gpt-4o');

        $this->assertInstanceOf(CompletionsModel::class, $model);
        $this->assertSame('gpt-4o', $model->getName());
        $this->assertTrue($model->supports(Capability::INPUT_MESSAGES));
        $this->assertTrue($model->supports(Capability::OUTPUT_TEXT));
    }

    public function testGetEmbeddingsModel()
    {
        $catalog = new ModelCatalog('openai');

        $model = $catalog->getModel('text-embedding-3-small');

        $this->assertInstanceOf(EmbeddingsModel::class, $model);
        $this->assertSame('text-embedding-3-small', $model->getName());
        $this->assertTrue($model->supports(Capability::EMBEDDINGS));
        $this->assertTrue($model->supports(Capability::INPUT_TEXT));
    }

    public function testModelNotFound()
    {
        $catalog = new ModelCatalog('deepseek');

        $this->expectException(ModelNotFoundException::class);
        $catalog->getModel('nonexistent-model');
    }

    public function testUnknownProviderThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provider "nonexistent-provider" not found in models.dev data.');

        new ModelCatalog('nonexistent-provider');
    }

    public function testProvidersWithSpecializedBridgesCanCreateCatalog()
    {
        // ModelCatalog can be created for all providers, including those with specialized bridges
        // The routing to specialized bridges happens in PlatformFactory
        $catalog = new ModelCatalog('anthropic');

        $this->assertNotEmpty($catalog->getModels());
        $this->assertArrayHasKey('claude-3-5-sonnet-20241022', $catalog->getModels());
    }

    public function testAdditionalModelsAreMerged()
    {
        $catalog = new ModelCatalog('deepseek', additionalModels: [
            'custom-model' => [
                'class' => CompletionsModel::class,
                'capabilities' => [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT],
            ],
        ]);

        $model = $catalog->getModel('custom-model');

        $this->assertInstanceOf(CompletionsModel::class, $model);
        $this->assertSame('custom-model', $model->getName());
    }

    public function testMultipleProviders()
    {
        $groqCatalog = new ModelCatalog('groq');
        $deepseekCatalog = new ModelCatalog('deepseek');

        $this->assertNotEmpty($groqCatalog->getModels());
        $this->assertNotEmpty($deepseekCatalog->getModels());

        // They should have different models
        $this->assertNotSame(array_keys($groqCatalog->getModels()), array_keys($deepseekCatalog->getModels()));
    }

    public function testDeprecatedModelsAreExcluded()
    {
        $fixtureFile = self::createFixtureFile([
            'test-provider' => [
                'id' => 'test-provider',
                'name' => 'Test',
                'models' => [
                    'active-model' => [
                        'id' => 'active-model',
                        'name' => 'Active',
                        'family' => 'test',
                        'attachment' => false,
                        'reasoning' => false,
                        'tool_call' => true,
                        'temperature' => true,
                        'modalities' => ['input' => ['text'], 'output' => ['text']],
                        'cost' => ['input' => 1, 'output' => 2],
                        'limit' => ['context' => 8192, 'output' => 4096],
                    ],
                    'deprecated-model' => [
                        'id' => 'deprecated-model',
                        'name' => 'Deprecated',
                        'family' => 'test',
                        'attachment' => false,
                        'reasoning' => false,
                        'tool_call' => false,
                        'temperature' => true,
                        'modalities' => ['input' => ['text'], 'output' => ['text']],
                        'cost' => ['input' => 1, 'output' => 2],
                        'limit' => ['context' => 8192, 'output' => 4096],
                        'status' => 'deprecated',
                    ],
                ],
            ],
        ]);

        try {
            $catalog = new ModelCatalog('test-provider', $fixtureFile);
            $models = $catalog->getModels();

            $this->assertArrayHasKey('active-model', $models);
            $this->assertArrayNotHasKey('deprecated-model', $models);
        } finally {
            unlink($fixtureFile);
        }
    }

    public function testCustommodelDevsJsonPath()
    {
        $fixtureFile = self::createFixtureFile([
            'custom-provider' => [
                'id' => 'custom-provider',
                'name' => 'Custom',
                'models' => [
                    'custom-model' => [
                        'id' => 'custom-model',
                        'name' => 'Custom Model',
                        'family' => 'custom',
                        'attachment' => false,
                        'reasoning' => false,
                        'tool_call' => true,
                        'temperature' => true,
                        'modalities' => ['input' => ['text'], 'output' => ['text']],
                        'cost' => ['input' => 1, 'output' => 2],
                        'limit' => ['context' => 8192, 'output' => 4096],
                    ],
                ],
            ],
        ]);

        try {
            $catalog = new ModelCatalog('custom-provider', $fixtureFile);
            $model = $catalog->getModel('custom-model');

            $this->assertInstanceOf(CompletionsModel::class, $model);
            $this->assertTrue($model->supports(Capability::TOOL_CALLING));
        } finally {
            unlink($fixtureFile);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createFixtureFile(array $data): string
    {
        $path = sys_get_temp_dir().'/models-dev-test-'.uniqid().'.json';
        file_put_contents($path, json_encode($data, \JSON_THROW_ON_ERROR));

        return $path;
    }
}
