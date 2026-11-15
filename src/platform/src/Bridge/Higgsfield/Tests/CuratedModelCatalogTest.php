<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Higgsfield\CuratedModelCatalog;
use Symfony\AI\Platform\Bridge\Higgsfield\Higgsfield;
use Symfony\AI\Platform\Bridge\Higgsfield\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class CuratedModelCatalogTest extends TestCase
{
    private const BASE_URL = 'https://platform.higgsfield.ai';

    private const MODELS_FIXTURE = [
        'items' => [
            ['slug' => 'higgsfield-ai/soul/v2/standard', 'operation_type' => ['text2image']],
            ['slug' => 'higgsfield-ai/soul/standard', 'operation_type' => ['text2image']],
            ['slug' => 'kling-video/v2.5-turbo/standard/image-to-video', 'operation_type' => ['image2video']],
            ['slug' => 'wan/v2.7/text-to-video', 'operation_type' => ['text2video']],
        ],
    ];

    public function testItResolvesAnAliasToTheCanonicalModelName()
    {
        $model = $this->createCatalog()->getModel('soul-2');

        $this->assertInstanceOf(Higgsfield::class, $model);
        $this->assertSame('higgsfield-ai/soul/v2/standard', $model->getName());
        $this->assertSame([Capability::TEXT_TO_IMAGE], $model->getCapabilities());
    }

    public function testItResolvesTheVideoAliases()
    {
        $catalog = $this->createCatalog();

        $this->assertSame('kling-video/v2.5-turbo/standard/image-to-video', $catalog->getModel('kling-2.5-i2v')->getName());
        $this->assertSame('wan/v2.7/text-to-video', $catalog->getModel('wan-2.7-t2v')->getName());
    }

    public function testItLeavesACanonicalModelNameUntouched()
    {
        $model = $this->createCatalog()->getModel('higgsfield-ai/soul/standard');

        $this->assertSame('higgsfield-ai/soul/standard', $model->getName());
    }

    public function testItKeepsOptionsWhenResolvingAnAlias()
    {
        $model = $this->createCatalog()->getModel('soul-2?aspect_ratio=16:9');

        $this->assertSame('higgsfield-ai/soul/v2/standard', $model->getName());
        $this->assertSame([PlatformOptions::PROVIDER_OPTIONS => ['aspect_ratio' => '16:9']], $model->getOptions());
    }

    public function testItKeepsOptionsOfACanonicalModelName()
    {
        $model = $this->createCatalog()->getModel('higgsfield-ai/soul/standard?aspect_ratio=1:1');

        $this->assertSame('higgsfield-ai/soul/standard', $model->getName());
        $this->assertSame([PlatformOptions::PROVIDER_OPTIONS => ['aspect_ratio' => '1:1']], $model->getOptions());
    }

    public function testItExposesAliasesNextToTheCanonicalNames()
    {
        $models = $this->createCatalog()->getModels();

        $this->assertArrayHasKey('soul-2', $models);
        $this->assertArrayHasKey('higgsfield-ai/soul/v2/standard', $models);
        $this->assertSame($models['higgsfield-ai/soul/v2/standard'], $models['soul-2']);
    }

    public function testItStillThrowsForAnUnknownModel()
    {
        $this->expectException(ModelNotFoundException::class);

        $this->createCatalog()->getModel('unknown/model');
    }

    public function testEveryAliasPointsAtAModelTheApiKnows()
    {
        $models = $this->createCatalog()->getModels();

        foreach (CuratedModelCatalog::ALIASES as $alias => $modelName) {
            $this->assertArrayHasKey($modelName, $models, \sprintf('Alias "%s" points at unknown model "%s".', $alias, $modelName));
        }
    }

    private function createCatalog(): CuratedModelCatalog
    {
        return new CuratedModelCatalog(
            new ModelCatalog(new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], self::BASE_URL)),
        );
    }
}
