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

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsageInterface
    {
        $url = $rawResult->getObject()->getInfo('url');

        if ((new UnicodeString($url))->containsAny('speech')) {
            return null;
        }

        $content = $rawResult->getData();

        return match (true) {
            (new UnicodeString($url))->containsAny('completions') => new TokenUsage(
                promptTokens: $content['usage']['prompt_tokens'],
                completionTokens: $content['usage']['completion_tokens'],
                totalTokens: $content['usage']['total_tokens'],
            ),
            (new UnicodeString($url))->containsAny('embeddings') => new TokenUsage(
                promptTokens: $content['usage']['prompt_tokens'],
                totalTokens: $content['usage']['total_tokens'],
            ),
            default => null,
        };
    }
}
