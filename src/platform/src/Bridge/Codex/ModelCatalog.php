<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Endpoint;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * Default model catalog for the Codex CLI bridge.
 *
 * @see https://developers.openai.com/codex/models
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::INPUT_TEXT,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
        ];

        $defaultModels = [
            'gpt-5.4' => [
                'class' => Codex::class,
                'capabilities' => $capabilities,
            ],
            'gpt-5.4-mini' => [
                'class' => Codex::class,
                'capabilities' => $capabilities,
            ],
            'gpt-5.3-codex' => [
                'class' => Codex::class,
                'capabilities' => $capabilities,
            ],
            'gpt-5.3-codex-spark' => [
                'class' => Codex::class,
                'capabilities' => $capabilities,
            ],
            'gpt-5.2' => [
                'class' => Codex::class,
                'capabilities' => $capabilities,
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }

    protected function endpointsForModel(array $modelConfig): array
    {
        return [new Endpoint(CliInvokeClient::ENDPOINT)];
    }
}
