<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * Model catalog for ACP models.
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @var array<string, array{class: class-string, capabilities: list<Capability>, clientCapabilities?: array<string, mixed>, requiredAgentCapabilities?: list<string>, protocolVersion?: int}>
     */
    protected array $models = [
        'acp-v1' => [
            'class' => Acp::class,
            'capabilities' => [
                Capability::INPUT_TEXT,
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
            ],
            'clientCapabilities' => [],
            'requiredAgentCapabilities' => [],
            'protocolVersion' => 1,
        ],
        'acp-v2' => [
            'class' => Acp::class,
            'capabilities' => [
                Capability::INPUT_TEXT,
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
            ],
            'clientCapabilities' => [],
            'requiredAgentCapabilities' => [],
            'protocolVersion' => 2,
        ],
    ];

    public function getModel(string $modelName): Model
    {
        $model = parent::getModel($modelName);

        if ($model instanceof Acp) {
            $parsed = $this->parseModelName($modelName);
            $config = $this->models[$parsed['catalogKey']] ?? [];
            $model->clientCapabilities = $config['clientCapabilities'] ?? [];
            $model->requiredAgentCapabilities = $config['requiredAgentCapabilities'] ?? [];
            $model->protocolVersion = $config['protocolVersion'] ?? 1;
        }

        return $model;
    }
}
