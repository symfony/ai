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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Model;
use Symfony\AI\Platform\Bridge\OpenAi\ModelCatalog;

final class ModelTest extends TestCase
{
    public function testAllConstantsMatchCatalogModels()
    {
        $catalog = new ModelCatalog();
        $catalogModels = array_keys($catalog->getModels());
        $constants = Model::all();

        sort($catalogModels);
        sort($constants);

        self::assertSame($catalogModels, $constants, 'Model constants must match the models defined in the catalog');
    }

    public function testConstantsCanBeUsedWithModelCatalog()
    {
        $catalog = new ModelCatalog();

        // Test a few representative constants
        $model = $catalog->getModel(Model::GPT_4O);
        self::assertSame('gpt-4o', $model->getName());

        $model = $catalog->getModel(Model::GPT_4O_MINI);
        self::assertSame('gpt-4o-mini', $model->getName());

        $model = $catalog->getModel(Model::TEXT_EMBEDDING_3_LARGE);
        self::assertSame('text-embedding-3-large', $model->getName());

        $model = $catalog->getModel(Model::DALL_E_3);
        self::assertSame('dall-e-3', $model->getName());
    }
}
