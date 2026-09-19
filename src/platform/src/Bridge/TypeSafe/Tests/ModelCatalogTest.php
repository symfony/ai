<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe\Tests;

use Symfony\AI\Platform\Bridge\TypeSafe\Jev;
use Symfony\AI\Platform\Bridge\TypeSafe\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'jev-latest' => ['jev-latest', Jev::class, [Capability::INPUT_TEXT, Capability::OUTPUT_STRUCTURED]];
        yield 'jev-preview' => ['jev-preview', Jev::class, [Capability::INPUT_TEXT, Capability::OUTPUT_STRUCTURED]];
        yield 'jev-1.13.0' => ['jev-1.13.0', Jev::class, [Capability::INPUT_TEXT, Capability::OUTPUT_STRUCTURED]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
