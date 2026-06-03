<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cache;

use Symfony\AI\Platform\Contract\Normalizer\Message\AssistantMessageNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\AudioNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\ImageNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\ImageUrlNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\TextNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\MessageBagNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\SystemMessageNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\ToolCallMessageNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\UserMessageNormalizer;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\UnicodeString;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class CachePlatform implements PlatformInterface
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly ClockInterface $clock = new MonotonicClock(),
        private readonly (CacheInterface&TagAwareAdapterInterface)|null $cache = null,
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new MessageBagNormalizer(),
            new AssistantMessageNormalizer(),
            new SystemMessageNormalizer(),
            new ToolCallMessageNormalizer(),
            new UserMessageNormalizer(),
            new AudioNormalizer(),
            new ImageNormalizer(),
            new ImageUrlNormalizer(),
            new TextNormalizer(),
            new ResultNormalizer(new ObjectNormalizer(
                propertyTypeExtractor: new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]),
                classDiscriminatorResolver: new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AttributeLoader())),
            )),
        ], [new JsonEncoder()]),
        private readonly ?string $cacheKey = null,
        private readonly ?int $cacheTtl = null,
    ) {
    }

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        if (null === $this->cache || !\array_key_exists('prompt_cache_key', $options) || '' === $options['prompt_cache_key']) {
            return $this->platform->invoke($model, $input, $options);
        }

        $cacheKey = $this->buildCacheKey($model, $input, $options);
        $ttl = $options['prompt_cache_ttl'] ?? $this->cacheTtl;

        unset($options['prompt_cache_key'], $options['prompt_cache_ttl']);

        $cached = $this->cache->get($cacheKey, function (ItemInterface $item) use ($model, $input, $options, $cacheKey, $ttl): array {
            $item->tag((new UnicodeString($model))->camel());

            if (null !== $ttl) {
                $item->expiresAfter($ttl);
            }

            $deferredResult = $this->platform->invoke($model, $input, $options);

            $result = $deferredResult->getResult();

            return [
                'result' => $this->serializer->normalize($result),
                'raw_data' => $deferredResult->getRawResult()->getData(),
                'metadata' => $result->getMetadata()->all(),
                'cached_at' => $this->clock->now()->getTimestamp(),
                'cache_key' => $cacheKey,
            ];
        });

        return $this->buildDeferredResultFromCache($cached, $options);
    }

    /**
     * Returns the cached result for the given input without invoking the
     * underlying platform, or null when there is no cache hit.
     *
     * Useful for UI flows where a cache miss should not trigger a costly
     * model invocation — e.g. show the cached answer if any, otherwise let
     * the user decide to (re)generate one.
     *
     * @param array<string, mixed> $options Must contain a non-empty
     *                                      `prompt_cache_key`, like
     *                                      {@see self::invoke()} does.
     */
    public function lookup(string $model, array|string|object $input, array $options = []): ?DeferredResult
    {
        if (null === $this->cache || !\array_key_exists('prompt_cache_key', $options) || '' === $options['prompt_cache_key']) {
            return null;
        }

        $cacheKey = $this->buildCacheKey($model, $input, $options);

        $item = $this->cache->getItem($cacheKey);
        if (!$item->isHit()) {
            return null;
        }

        unset($options['prompt_cache_key'], $options['prompt_cache_ttl']);

        return $this->buildDeferredResultFromCache($item->get(), $options);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function buildCacheKey(string $model, array|string|object $input, array $options): string
    {
        return (new UnicodeString())->join([
            $options['prompt_cache_key'] ?? $this->cacheKey,
            (new UnicodeString($model))->camel(),
            $this->hashInput($input),
        ]);
    }

    private function hashInput(array|string|object $input): string
    {
        return match (true) {
            \is_string($input) => md5($input),
            \is_array($input) => md5(json_encode($input, \JSON_THROW_ON_ERROR)),
            // MessageBag is normalized through the platform's Message/Content
            // normalizers, which expose the conversation content only —
            // per-instance UUIDs are not part of the output, so two bags
            // carrying the same conversation hash to the same key.
            $input instanceof MessageBag => md5($this->serializer->serialize($input, 'json')),
            default => throw new InvalidArgumentException(\sprintf('Unsupported input type: %s', get_debug_type($input))),
        };
    }

    /**
     * @param array{result: mixed, raw_data: array<string, mixed>, metadata: array<string, mixed>, cached_at: int, cache_key: string} $cached
     * @param array<string, mixed>                                                                                                    $options
     */
    private function buildDeferredResultFromCache(array $cached, array $options): DeferredResult
    {
        $restoredResult = $this->serializer->denormalize($cached['result'], ResultInterface::class);

        $restoredResult->getMetadata()->set([
            ...$cached['metadata'],
            'cached' => true,
            'cache_key' => $cached['cache_key'],
            'cached_at' => $cached['cached_at'],
        ]);

        $result = new DeferredResult(
            new PlainConverter($restoredResult),
            new InMemoryRawResult($cached['raw_data']),
            $options,
        );

        $result->getMetadata()->merge($restoredResult->getMetadata());

        return $result;
    }
}
