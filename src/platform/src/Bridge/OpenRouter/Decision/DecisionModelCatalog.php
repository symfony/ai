<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Decision;

use Symfony\AI\Platform\Bridge\OpenRouter\DecisionModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class DecisionModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'baseten/elephant-1' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'baseten/joey-1' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'baseten/mammoth-1' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'cloudflare/clairic' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'cloudflare/clef' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'cloudflare/clef-flash' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'cloudflare/clef-omni' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'inception/mercury-decide' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'jaredpalmer/kev-4b' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'liquid/d1' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'liquid/d1-ultra' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'liquid/d1.1' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'microsoft/microsoft-decision-1' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'nace-ai/drex-max' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'nace-ai/drex-v1.5' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'nace-ai/drex-v2.0' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'openai/gpt-6-luna-decisions' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'perplexity/pplx-decider-flash-v1.2' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'perplexity/pplx-decider-v1.2' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'respan/span-01' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'respan/span-01-lite' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'respan/span-02' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'scaledown/classify-1' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'togethercomputer/tev1-4b-experimental' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'typesafe/jev-1.13' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'typesafe/jev-1.14' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'upstage/solar-decide' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            'upstage/solar-decide-flash' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            '~liquid/d1-latest' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            '~liquid/d1-ultra-latest' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            '~perplexity/pplx-decider-flash-latest' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            '~perplexity/pplx-decider-latest' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
            '~typesafe/jev-latest' => [
                'class' => DecisionModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
