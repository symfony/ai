<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Component\String\UnicodeString;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsageInterface
    {
        if ($options['stream'] ?? false) {
            return null;
        }

        /** @var ResponseInterface $response */
        $response = $rawResult->getObject();

        $rawUrl = $response->getInfo('url');

        if (!\is_string($rawUrl)) {
            return null;
        }

        $url = new UnicodeString($rawUrl);

        if ($url->containsAny('speech') || $url->containsAny('image/generate') || $url->containsAny('transcription') || $url->containsAny('video/retrieve')) {
            return null;
        }

        $content = $rawResult->getData();
        $usage = \is_array($content['usage'] ?? null) ? $content['usage'] : [];

        return match (true) {
            $url->containsAny('completions') => new TokenUsage(
                promptTokens: isset($usage['prompt_tokens']) && \is_int($usage['prompt_tokens']) ? $usage['prompt_tokens'] : null,
                completionTokens: isset($usage['completion_tokens']) && \is_int($usage['completion_tokens']) ? $usage['completion_tokens'] : null,
                totalTokens: isset($usage['total_tokens']) && \is_int($usage['total_tokens']) ? $usage['total_tokens'] : null,
            ),
            $url->containsAny('embeddings') => new TokenUsage(
                promptTokens: isset($usage['prompt_tokens']) && \is_int($usage['prompt_tokens']) ? $usage['prompt_tokens'] : null,
                totalTokens: isset($usage['total_tokens']) && \is_int($usage['total_tokens']) ? $usage['total_tokens'] : null,
            ),
            default => null,
        };
    }
}
