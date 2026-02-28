<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ClaudeCode\Tests;

use Symfony\AI\Platform\Bridge\ClaudeCode\ClaudeCode;
use Symfony\AI\Platform\Bridge\ClaudeCode\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        $capabilities = [Capability::INPUT_MESSAGES, Capability::INPUT_TEXT, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING];

        yield 'opus' => ['opus', ClaudeCode::class, $capabilities];
        yield 'sonnet' => ['sonnet', ClaudeCode::class, $capabilities];
        yield 'haiku' => ['haiku', ClaudeCode::class, $capabilities];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
