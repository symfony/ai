<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Voyage;

use Symfony\AI\Platform\Bridge\Voyage\ModelCatalog;
use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Tests\ModelCatalogTestCase;

final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'voyage-3.5' => ['voyage-3.5', Voyage::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-3.5-lite' => ['voyage-3.5-lite', Voyage::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-3' => ['voyage-3', Voyage::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-3-lite' => ['voyage-3-lite', Voyage::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-3-large' => ['voyage-3-large', Voyage::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-finance-2' => ['voyage-finance-2', Voyage::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-multilingual-2' => ['voyage-multilingual-2', Voyage::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-law-2' => ['voyage-law-2', Voyage::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-code-3' => ['voyage-code-3', Voyage::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-code-2' => ['voyage-code-2', Voyage::class, [Capability::INPUT_MULTIPLE]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
