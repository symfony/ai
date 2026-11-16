<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests\Decision;

use Symfony\AI\Platform\Bridge\OpenRouter\Decision\DecisionModelCatalog;
use Symfony\AI\Platform\Bridge\OpenRouter\DecisionModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class DecisionModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'baseten/elephant-1' => ['baseten/elephant-1', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'baseten/joey-1' => ['baseten/joey-1', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'baseten/mammoth-1' => ['baseten/mammoth-1', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'cloudflare/clairic' => ['cloudflare/clairic', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'cloudflare/clef' => ['cloudflare/clef', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'cloudflare/clef-flash' => ['cloudflare/clef-flash', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'cloudflare/clef-omni' => ['cloudflare/clef-omni', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'inception/mercury-decide' => ['inception/mercury-decide', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'jaredpalmer/kev-4b' => ['jaredpalmer/kev-4b', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'liquid/d1' => ['liquid/d1', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'liquid/d1-ultra' => ['liquid/d1-ultra', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'liquid/d1.1' => ['liquid/d1.1', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'microsoft/microsoft-decision-1' => ['microsoft/microsoft-decision-1', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'nace-ai/drex-max' => ['nace-ai/drex-max', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'nace-ai/drex-v1.5' => ['nace-ai/drex-v1.5', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'nace-ai/drex-v2.0' => ['nace-ai/drex-v2.0', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'openai/gpt-6-luna-decisions' => ['openai/gpt-6-luna-decisions', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'perplexity/pplx-decider-flash-v1.2' => ['perplexity/pplx-decider-flash-v1.2', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'perplexity/pplx-decider-v1.2' => ['perplexity/pplx-decider-v1.2', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'respan/span-01' => ['respan/span-01', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'respan/span-01-lite' => ['respan/span-01-lite', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'respan/span-02' => ['respan/span-02', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'scaledown/classify-1' => ['scaledown/classify-1', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'togethercomputer/tev1-4b-experimental' => ['togethercomputer/tev1-4b-experimental', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'typesafe/jev-1.13' => ['typesafe/jev-1.13', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'typesafe/jev-1.14' => ['typesafe/jev-1.14', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'upstage/solar-decide' => ['upstage/solar-decide', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield 'upstage/solar-decide-flash' => ['upstage/solar-decide-flash', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield '~liquid/d1-latest' => ['~liquid/d1-latest', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield '~liquid/d1-ultra-latest' => ['~liquid/d1-ultra-latest', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield '~perplexity/pplx-decider-flash-latest' => ['~perplexity/pplx-decider-flash-latest', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield '~perplexity/pplx-decider-latest' => ['~perplexity/pplx-decider-latest', DecisionModel::class, [Capability::INPUT_TEXT]];
        yield '~typesafe/jev-latest' => ['~typesafe/jev-latest', DecisionModel::class, [Capability::INPUT_TEXT]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new DecisionModelCatalog();
    }
}
