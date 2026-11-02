<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic;

/**
 * Injects Anthropic prompt-caching markers into request payloads.
 *
 * Anthropic prompt caching requires a {"cache_control": {...}} annotation on
 * the last block of the cached region: the last system block, the last tool
 * definition, and the last block of the last two user messages. Each region can
 * be cached independently, so callers may inject any subset.
 *
 * The cache-control marker itself (retention window, endpoint support) is
 * resolved by the caller and passed in, so different model clients (e.g. the
 * default API client and the Claude Code OAuth client) can share the injection
 * logic while keeping their own marker resolution.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait PromptCachingTrait
{
    /**
     * Builds the cache-control marker matching the configured retention.
     *
     * The 1h window selected by the "long" retention is an Anthropic-native
     * beta only guaranteed on the official API endpoint; callers talking to a
     * gateway or proxy that may not implement it should pass
     * $extendedTtlSupported = false, in which case "long" degrades to the
     * default 5-minute window instead.
     *
     * @return array{type: 'ephemeral', ttl?: '1h'}|null Null when caching is disabled
     */
    private function getCacheControl(string $cacheRetention, bool $extendedTtlSupported = true): ?array
    {
        if ('none' === $cacheRetention) {
            return null;
        }

        if ('long' === $cacheRetention && $extendedTtlSupported) {
            return ['type' => 'ephemeral', 'ttl' => '1h'];
        }

        return ['type' => 'ephemeral'];
    }

    /**
     * Annotates the last block of the last two user messages.
     *
     * Marking the previous user message keeps a cache hit on the entry written
     * by the prior request, as Anthropic only looks back 20 blocks per breakpoint.
     *
     * @param array<int|string, mixed>                  $payload
     * @param array{type: 'ephemeral', ttl?: '1h'}|null $cacheControl Null disables caching
     *
     * @return array<int|string, mixed>
     */
    private function injectMessagesCacheControl(array $payload, ?array $cacheControl): array
    {
        if (null === $cacheControl) {
            return $payload;
        }

        $messages = $payload['messages'] ?? [];

        if ([] === $messages) {
            return $payload;
        }

        $remaining = 2;

        for ($i = \count($messages) - 1; $i >= 0 && $remaining > 0; --$i) {
            if ('user' !== ($messages[$i]['role'] ?? '')) {
                continue;
            }

            $content = $messages[$i]['content'] ?? null;

            if (\is_string($content)) {
                $messages[$i]['content'] = [
                    ['type' => 'text', 'text' => $content, 'cache_control' => $cacheControl],
                ];
                --$remaining;
                continue;
            }

            if (\is_array($content) && [] !== $content) {
                $lastIdx = \count($content) - 1;
                if (\is_array($content[$lastIdx])) {
                    $content[$lastIdx]['cache_control'] = $cacheControl;
                    $messages[$i]['content'] = $content;
                }
                --$remaining;
            }
        }

        $payload['messages'] = $messages;

        return $payload;
    }

    /**
     * Annotates the last system content block.
     *
     * @param array<int|string, mixed>                  $payload
     * @param array{type: 'ephemeral', ttl?: '1h'}|null $cacheControl Null disables caching
     *
     * @return array<int|string, mixed>
     */
    private function injectSystemCacheControl(array $payload, ?array $cacheControl): array
    {
        if (null === $cacheControl || !isset($payload['system']) || !\is_array($payload['system']) || [] === $payload['system']) {
            return $payload;
        }

        $payload['system'][\count($payload['system']) - 1]['cache_control'] = $cacheControl;

        return $payload;
    }

    /**
     * Annotates the last tool definition.
     *
     * @param list<array<string, mixed>>                $tools
     * @param array{type: 'ephemeral', ttl?: '1h'}|null $cacheControl Null disables caching
     *
     * @return list<array<string, mixed>>
     */
    private function injectToolsCacheControl(array $tools, ?array $cacheControl): array
    {
        if (null === $cacheControl || [] === $tools) {
            return $tools;
        }

        $tools[\count($tools) - 1]['cache_control'] = $cacheControl;

        return $tools;
    }
}
