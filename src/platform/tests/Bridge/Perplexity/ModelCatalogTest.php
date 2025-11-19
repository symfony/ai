<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Perplexity;

use Symfony\AI\Platform\Bridge\Perplexity\ModelCatalog;
use Symfony\AI\Platform\Bridge\Perplexity\Perplexity;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'sonar' => ['sonar', Perplexity::class, [Capability::INPUT_MESSAGES, Capability::INPUT_PDF, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::INPUT_IMAGE]];
        yield 'sonar-pro' => ['sonar-pro', Perplexity::class, [Capability::INPUT_MESSAGES, Capability::INPUT_PDF, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::INPUT_IMAGE]];
        yield 'sonar-reasoning' => ['sonar-reasoning', Perplexity::class, [Capability::INPUT_MESSAGES, Capability::INPUT_PDF, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::INPUT_IMAGE]];
        yield 'sonar-reasoning-pro' => ['sonar-reasoning-pro', Perplexity::class, [Capability::INPUT_MESSAGES, Capability::INPUT_PDF, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::INPUT_IMAGE]];
        yield 'sonar-deep-research' => ['sonar-deep-research', Perplexity::class, [Capability::INPUT_MESSAGES, Capability::INPUT_PDF, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
