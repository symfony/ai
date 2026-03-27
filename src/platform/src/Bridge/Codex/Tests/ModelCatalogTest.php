<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex\Tests;

use Symfony\AI\Platform\Bridge\Codex\Codex;
use Symfony\AI\Platform\Bridge\Codex\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        $capabilities = [Capability::INPUT_MESSAGES, Capability::INPUT_TEXT, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING];

        yield 'gpt-5.4' => ['gpt-5.4', Codex::class, $capabilities];
        yield 'gpt-5.4-mini' => ['gpt-5.4-mini', Codex::class, $capabilities];
        yield 'gpt-5.3-codex' => ['gpt-5.3-codex', Codex::class, $capabilities];
        yield 'gpt-5.3-codex-spark' => ['gpt-5.3-codex-spark', Codex::class, $capabilities];
        yield 'gpt-5.2-codex' => ['gpt-5.2-codex', Codex::class, $capabilities];
        yield 'gpt-5.1-codex' => ['gpt-5.1-codex', Codex::class, $capabilities];
        yield 'gpt-5-codex' => ['gpt-5-codex', Codex::class, $capabilities];
        yield 'gpt-5-codex-mini' => ['gpt-5-codex-mini', Codex::class, $capabilities];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
