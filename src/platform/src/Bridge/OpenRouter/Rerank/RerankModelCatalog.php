<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Rerank;

use Symfony\AI\Platform\Bridge\OpenRouter\RerankModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class RerankModelCatalog extends AbstractModelCatalog
{
    /**
     * @var array<string, array{class: class-string, capabilities: list<Capability>}>
     */
    protected array $models = [
        'cohere/rerank-4-pro' => [
            'class' => RerankModel::class,
            'capabilities' => [
                Capability::INPUT_MULTIPLE,
                Capability::RERANKING,
            ],
        ],
        'cohere/rerank-4-fast' => [
            'class' => RerankModel::class,
            'capabilities' => [
                Capability::INPUT_MULTIPLE,
                Capability::RERANKING,
            ],
        ],
        'cohere/rerank-v3.5' => [
            'class' => RerankModel::class,
            'capabilities' => [
                Capability::INPUT_MULTIPLE,
                Capability::RERANKING,
            ],
        ],
    ];
}
