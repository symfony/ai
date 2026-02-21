<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Contract;

use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Injects Anthropic prompt-caching markers into the normalised message payload.
 *
 * Anthropic prompt caching requires a {"cache_control": {"type": "ephemeral"}}
 * annotation on the *last block* of the *last user message*.  When the
 * `cacheRetention` option is present in the invocation options (propagated via
 * {@see Contract::CONTEXT_OPTIONS}), this normalizer wraps the standard
 * {@see MessageBagNormalizer} output and splices in the required annotation.
 *
 * Supported `cacheRetention` values
 * ----------------------------------
 * - `'short'`  – 5-minute default cache ({"type":"ephemeral"})
 * - `'long'`   – 1-hour cache ({"type":"ephemeral","ttl":"1h"}); only
 *                honoured on api.anthropic.com, silently falls back to
 *                ephemeral on other hosts
 * - `'none'`   – caching disabled; no annotation is injected
 *
 * The class replaces {@see MessageBagNormalizer} in {@see AnthropicContract}
 * and delegates all structural normalization to it, so the two normalizers
 * must not both be registered in the same chain.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class PromptCacheNormalizer extends ModelContractNormalizer implements NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private readonly MessageBagNormalizer $inner;

    public function __construct()
    {
        $this->inner = new MessageBagNormalizer();
    }

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->normalizer = $normalizer;
        $this->inner->setNormalizer($normalizer);
    }

    /**
     * @param MessageBag $data
     *
     * @return array{
     *     messages: list<array<string, mixed>>,
     *     model?: string,
     *     system?: string,
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        // Delegate all structural normalisation to the standard inner normalizer.
        $result = $this->inner->normalize($data, $format, $context);

        // Determine the requested cache retention from invocation options.
        $options = $context[Contract::CONTEXT_OPTIONS] ?? [];
        $cacheRetention = $options['cacheRetention'] ?? null;

        if (null === $cacheRetention || 'none' === $cacheRetention) {
            return $result;
        }

        // Build the cache_control object.  The 1-hour TTL is an api.anthropic.com
        // exclusive feature; any other host (e.g. a proxy) will receive the
        // plain ephemeral marker, which is always safe to send.
        $cacheControl = 'long' === $cacheRetention ? ['type' => 'ephemeral', 'ttl' => '1h'] : ['type' => 'ephemeral'];

        $result['messages'] = $this->injectCacheControl($result['messages'] ?? [], $cacheControl);

        return $result;
    }

    protected function supportedDataClass(): string
    {
        return MessageBag::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Claude;
    }

    /**
     * Splices a cache_control annotation onto the last block of the last user
     * message.  When the message content is a plain string it is first promoted
     * to the structured block format required by the Anthropic API.
     *
     * @param list<array<string, mixed>>        $messages
     * @param array{type: string, ttl?: string} $cacheControl
     *
     * @return list<array<string, mixed>>
     */
    private function injectCacheControl(array $messages, array $cacheControl): array
    {
        for ($i = \count($messages) - 1; $i >= 0; --$i) {
            if ('user' !== ($messages[$i]['role'] ?? '')) {
                continue;
            }

            $content = $messages[$i]['content'] ?? null;

            if (\is_string($content)) {
                // Promote plain-string content to a structured text block so
                // that cache_control has somewhere to live.
                $messages[$i]['content'] = [
                    ['type' => 'text', 'text' => $content, 'cache_control' => $cacheControl],
                ];
                break;
            }

            if (\is_array($content) && [] !== $content) {
                $lastIdx = \count($content) - 1;
                if (\is_array($content[$lastIdx])) {
                    $content[$lastIdx]['cache_control'] = $cacheControl;
                    $messages[$i]['content'] = $content;
                }
                break;
            }
        }

        return $messages;
    }
}
