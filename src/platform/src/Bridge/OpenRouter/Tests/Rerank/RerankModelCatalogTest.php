<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests\Rerank;

use Symfony\AI\Platform\Bridge\OpenRouter\Rerank\RerankModelCatalog;
use Symfony\AI\Platform\Bridge\OpenRouter\RerankModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class RerankModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'cohere/rerank-4-pro' => [
            'cohere/rerank-4-pro',
            RerankModel::class,
            [
                Capability::INPUT_MULTIPLE,
                Capability::RERANKING,
            ],
        ];

        yield 'cohere/rerank-4-fast' => [
            'cohere/rerank-4-fast',
            RerankModel::class,
            [
                Capability::INPUT_MULTIPLE,
                Capability::RERANKING,
            ],
        ];

        yield 'cohere/rerank-v3.5' => [
            'cohere/rerank-v3.5',
            RerankModel::class,
            [
                Capability::INPUT_MULTIPLE,
                Capability::RERANKING,
            ],
        ];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new RerankModelCatalog();
    }
}
