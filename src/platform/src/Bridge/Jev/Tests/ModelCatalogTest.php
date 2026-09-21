<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Jev\Tests;

use Symfony\AI\Platform\Bridge\Jev\Jev;
use Symfony\AI\Platform\Bridge\Jev\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        $capabilities = [Capability::INPUT_TEXT];

        yield 'jev-latest' => ['jev-latest', Jev::class, $capabilities];
        yield 'jev-preview' => ['jev-preview', Jev::class, $capabilities];
        yield 'jev-1.13.0' => ['jev-1.13.0', Jev::class, $capabilities];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
