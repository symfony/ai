<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Cartesia;

use Symfony\AI\Platform\Bridge\Cartesia\Cartesia;
use Symfony\AI\Platform\Bridge\Cartesia\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'sonic-3' => ['sonic-3', Cartesia::class, [Capability::TEXT_TO_SPEECH]];
        yield 'ink-whisper' => ['ink-whisper', Cartesia::class, [Capability::SPEECH_TO_TEXT]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
