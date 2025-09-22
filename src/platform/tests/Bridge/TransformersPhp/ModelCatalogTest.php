<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\TransformersPhp;

use Symfony\AI\Platform\Bridge\TransformersPhp\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Tests\ModelCatalogTestCase;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        // TransformersPhp can use various models from HuggingFace, so we test with example model names
        // Since it extends DynamicModelCatalog, all capabilities are provided
        yield 'microsoft/DialoGPT-medium' => ['microsoft/DialoGPT-medium', Model::class, Capability::cases()];
        yield 'sentence-transformers/all-MiniLM-L6-v2' => ['sentence-transformers/all-MiniLM-L6-v2', Model::class, Capability::cases()];
        yield 'xenova/text-generation-webui' => ['xenova/text-generation-webui', Model::class, Capability::cases()];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
