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

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
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
            new ResultNormalizer(new ObjectNormalizer(
                propertyTypeExtractor: new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]),
                classDiscriminatorResolver: new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AttributeLoader())),
            )),
        ], [new JsonEncoder()]),
        private readonly ?string $cacheKey = null,
        private readonly ?int $cacheTtl = null,
        private readonly InputHasher $inputHasher = new InputHasher(),
    ) {
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        $namespace = $options['prompt_cache_key'] ?? $this->cacheKey;

        if (null === $this->cache || null === $namespace || '' === $namespace) {
            return $this->platform->invoke($model, $input, $options);
        }

        // Streams cannot be cached without being consumed.
        if ($options['stream'] ?? false) {
            return $this->platform->invoke($model, $input, $options);
        }

        $modelName = $model instanceof Model ? $model->getName() : $model;

        try {
            $normalizedInput = $this->inputHasher->hash($input);
        } catch (InvalidArgumentException) {
            // Fail open: bypass the cache when the input cannot be keyed.
            return $this->platform->invoke($model, $input, $options);
        }

        // "." separates the segments: ":" and "/" are reserved by PSR-6, and a delimiter avoids
        // boundary collisions (key "sy" + model "m" == key "s" + model "ym").
        $cacheKey = (new UnicodeString('.'))->join([
            $namespace,
            (new UnicodeString($modelName))->camel(),
            $normalizedInput,
        ]);

        $ttl = $options['prompt_cache_ttl'] ?? $this->cacheTtl;

        unset($options['prompt_cache_key'], $options['prompt_cache_ttl']);

        try {
            $cached = $this->cache->get($cacheKey, function (ItemInterface $item) use ($model, $modelName, $input, $options, $cacheKey, $namespace, $ttl): array {
                $item->tag([
                    (new UnicodeString($modelName))->camel(),
                    'namespace.'.$namespace,
                ]);

                if (null !== $ttl) {
                    $item->expiresAfter($ttl);
                }

                $deferredResult = $this->platform->invoke($model, $input, $options);

                $result = $deferredResult->getResult();

                try {
                    $normalizedResult = $this->serializer->normalize($result);
                } catch (InvalidArgumentException $e) {
                    // Fail open: carry the live result out when it cannot be normalized.
                    throw new UncacheableResultException($deferredResult, $e);
                }

                return [
                    'result' => $normalizedResult,
                    'raw_data' => $deferredResult->getRawResult()->getData(),
                    'metadata' => $result->getMetadata()->all(),
                    'cached_at' => $this->clock->now()->getTimestamp(),
                    'cache_key' => $cacheKey,
                ];
            });
        } catch (\Throwable $e) {
            // The cache contract does not declare the callback's exceptions: rethrow everything
            // but the carried live result.
            if ($e instanceof UncacheableResultException) {
                return $e->getDeferredResult();
            }

            throw $e;
        }

        try {
            $restoredResult = $this->serializer->denormalize($cached['result'], ResultInterface::class);
        } catch (\Throwable) {
            // Fail open on a stale or corrupted entry: drop it and re-invoke.
            $this->cache->delete((string) $cacheKey);

            return $this->platform->invoke($model, $input, $options);
        }

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

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }

    /**
     * Drops every cached entry tagged with one of the given tags.
     *
     * Entries are tagged with the camelized model name and `namespace.<cache key>` (the per-call
     * key, or the constructor-level {@see $cacheKey} when none is provided), so a model or a whole
     * namespace can be invalidated.
     *
     * @param list<string> $tags
     */
    public function invalidateTags(array $tags): bool
    {
        if (null === $this->cache) {
            return false;
        }

        return $this->cache->invalidateTags($tags);
    }
}
