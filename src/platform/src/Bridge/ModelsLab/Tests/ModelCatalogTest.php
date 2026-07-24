<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsLab\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ModelsLab\ModelCatalog;
use Symfony\AI\Platform\Bridge\ModelsLab\ModelsLab;
use Symfony\AI\Platform\Capability;

final class ModelCatalogTest extends TestCase
{
    public function testFluxModelIsRegistered(): void
    {
        $catalog = new ModelCatalog();
        $model = $catalog->get('flux');

        $this->assertInstanceOf(ModelsLab::class, $model);
        $this->assertContains(Capability::TEXT_TO_IMAGE, $model->getCapabilities());
    }

    public function testSdxlModelIsRegistered(): void
    {
        $catalog = new ModelCatalog();
        $model = $catalog->get('sdxl');

        $this->assertInstanceOf(ModelsLab::class, $model);
        $this->assertContains(Capability::TEXT_TO_IMAGE, $model->getCapabilities());
    }

    public function testAdditionalModelsAreSupported(): void
    {
        $catalog = new ModelCatalog([
            'custom-model' => [
                'class' => ModelsLab::class,
                'capabilities' => [Capability::TEXT_TO_IMAGE],
            ],
        ]);

        $model = $catalog->get('custom-model');
        $this->assertInstanceOf(ModelsLab::class, $model);
    }

    public function testDefaultModelsHaveTextToImageCapability(): void
    {
        $catalog = new ModelCatalog();

        foreach (['flux', 'flux-pro', 'sdxl', 'juggernaut-xl', 'realvisxl-v4.0', 'stable-diffusion', 'dreamshaper'] as $modelId) {
            $model = $catalog->get($modelId);
            $this->assertContains(
                Capability::TEXT_TO_IMAGE,
                $model->getCapabilities(),
                \sprintf('Model "%s" should have TEXT_TO_IMAGE capability.', $modelId),
            );
        }
    }
}
