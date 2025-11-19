<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FallbackModelCatalogTest extends TestCase
{
    public function testGetModelReturnsModelWithAllCapabilities()
    {
        $catalog = new FallbackModelCatalog();
        $model = $catalog->getModel('test-model');

        $this->assertInstanceOf(Model::class, $model);
        $this->assertSame('test-model', $model->getName());

        // Check that all capabilities are present
        foreach (Capability::cases() as $capability) {
            $this->assertTrue($model->supports($capability), \sprintf('Model should have capability %s', $capability->value));
        }
    }

    public function testGetModelWithOptions()
    {
        $catalog = new FallbackModelCatalog();
        $model = $catalog->getModel('test-model?temperature=0.7&max_tokens=1000');

        $this->assertInstanceOf(Model::class, $model);
        $this->assertSame('test-model', $model->getName());

        $options = $model->getOptions();
        $this->assertSame(0.7, $options['temperature']);
        $this->assertIsFloat($options['temperature']);
        $this->assertSame(1000, $options['max_tokens']);
        $this->assertIsInt($options['max_tokens']);
    }

    #[TestWith(['gpt-4'])]
    #[TestWith(['claude-3-opus'])]
    #[TestWith(['mistral-large'])]
    #[TestWith(['some/random/model:v1.0'])]
    #[TestWith(['huggingface/model-name'])]
    #[TestWith(['custom-local-model'])]
    public function testGetModelAcceptsAnyModelName(string $modelName)
    {
        $catalog = new FallbackModelCatalog();
        $model = $catalog->getModel($modelName);

        $this->assertInstanceOf(Model::class, $model);
        $this->assertSame($modelName, $model->getName());
    }
}
