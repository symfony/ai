<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\InputProcessor;

use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;

/**
 * Configures Anthropic prompt-caching retention for every invocation.
 *
 * **Anthropic only.**  Setting the `cacheRetention` option instructs
 * {@see \Symfony\AI\Platform\Bridge\Anthropic\Contract\PromptCacheNormalizer}
 * to splice a `{"cache_control": {"type": "ephemeral"}}` annotation onto the
 * last block of the last user message before the request is sent.
 *
 * Supported values
 * ----------------
 * - `'short'` – 5-minute cache window (default Anthropic ephemeral TTL)
 * - `'long'`  – 1-hour cache window (`{"type":"ephemeral","ttl":"1h"}`);
 *               only available on api.anthropic.com; other hosts receive
 *               the plain ephemeral marker
 * - `'none'`  – prompt caching disabled for this invocation
 *
 * Other platforms
 * ---------------
 * OpenAI caches prompt prefixes automatically (no annotation required).
 * Cached-token counts are already surfaced by the OpenAI TokenUsageExtractor
 * via {@see \Symfony\AI\Platform\TokenUsage\TokenUsageInterface::getCachedTokens()}.
 * Using this processor with a non-Anthropic platform is a no-op: the option
 * is stripped by each platform's ModelClient before the request is sent.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class CacheRetentionInputProcessor implements InputProcessorInterface
{
    /**
     * @param 'none'|'short'|'long' $cacheRetention
     */
    public function __construct(
        private string $cacheRetention = 'short',
    ) {
    }

    public function processInput(Input $input): void
    {
        $options = $input->getOptions();
        $options['cacheRetention'] = $this->cacheRetention;
        $input->setOptions($options);
    }
}
