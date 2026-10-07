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
    /**
     * Invocation option holding the cache namespace of the call, it defaults to the constructor
     * level cache key and opts the call out of caching when set to an empty string.
     */
    public const OPTION_CACHE_KEY = 'prompt_cache_key';

    /**
     * Invocation option holding the lifetime (in seconds) of the cached entry, it defaults to the
     * constructor level TTL.
     */
    public const OPTION_CACHE_TTL = 'prompt_cache_ttl';

    /**
     * @param iterable<CacheKeyGenerator> $cacheKeyGenerators Tried in order to key non-scalar inputs (objects)
     */
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
        private iterable $cacheKeyGenerators = [
            new MessageBagCacheKeyGenerator(),
            new MessageCacheKeyGenerator(),
            new DocumentUrlCacheKeyGenerator(),
            new ImageUrlCacheKeyGenerator(),
            new FileCacheKeyGenerator(),
        ],
        private readonly InputHasher $inputHasher = new InputHasher(),
    ) {
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        $namespace = $options[self::OPTION_CACHE_KEY] ?? $this->cacheKey;
        $ttl = $options[self::OPTION_CACHE_TTL] ?? $this->cacheTtl;

        // The decorator consumes its own options: they must not reach the decorated platform, whether
        // the call is cached or bypasses the cache.
        unset($options[self::OPTION_CACHE_KEY], $options[self::OPTION_CACHE_TTL]);

        if (null === $this->cache || null === $namespace || '' === $namespace) {
            return $this->platform->invoke($model, $input, $options);
        }

        if (false !== strpbrk($namespace, '{}()/\\@:')) {
            throw new InvalidArgumentException(\sprintf('The cache namespace "%s" contains reserved characters ("{}()/\@:") and cannot be used to build a cache key.', $namespace));
        }

        // A stream is consumed while it is read, caching it would drain it before the caller sees it.
        if (true === ($options['stream'] ?? false)) {
            return $this->platform->invoke($model, $input, $options);
        }

        $modelName = $model instanceof Model ? $model->getName() : $model;

        try {
            $normalizedInput = $this->generateInputCacheKey($input);
            $normalizedOptions = $this->inputHasher->hash($options);
        } catch (UncacheableInputException) {
            // Fail open: an input or an option set that cannot be keyed deterministically is served live.
            return $this->platform->invoke($model, $input, $options);
        }

        // "." separates the segments: ":" and "/" are reserved by PSR-6 and a delimiter avoids
        // boundary collisions (namespace "sy" + model "m" versus namespace "s" + model "ym"). The
        // options are part of the key: the same input answered with a different temperature, tool set
        // or response format is a different request.
        $cacheKey = (new UnicodeString('.'))->join([
            $namespace,
            (new UnicodeString($modelName))->camel(),
            $normalizedInput,
            $normalizedOptions,
        ]);

        $uncacheableResult = null;

        $cached = $this->cache->get($cacheKey, function (ItemInterface $item, bool &$save) use ($model, $modelName, $input, $options, $cacheKey, $namespace, $ttl, &$uncacheableResult): array {
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
            } catch (\Throwable) {
                // Fail open: a result the serializer cannot handle is served live and not stored.
                $save = false;
                $uncacheableResult = $deferredResult;

                return [];
            }

            return [
                'result' => $normalizedResult,
                'raw_data' => $deferredResult->getRawResult()->getData(),
                'metadata' => $result->getMetadata()->all(),
                'cached_at' => $this->clock->now()->getTimestamp(),
                'cache_key' => $cacheKey,
            ];
        });

        if (null !== $uncacheableResult) {
            return $uncacheableResult;
        }

        try {
            $restoredResult = $this->serializer->denormalize($cached['result'], ResultInterface::class);
        } catch (\Throwable) {
            // Fail open: a stale or corrupted entry is dropped and the result is fetched again.
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
     * Entries are tagged with the camelized model name and "namespace.<cache key>" (the per-call
     * key, or the constructor level one when the call does not provide any), so a model or a whole
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

    /**
     * @param array<mixed>|string|object $input
     *
     * @throws UncacheableInputException When no deterministic key can be derived from the input
     */
    private function generateInputCacheKey(array|string|object $input): string
    {
        if (\is_string($input)) {
            return md5($input);
        }

        if (\is_array($input)) {
            return $this->inputHasher->hash($input);
        }

        foreach ($this->cacheKeyGenerators as $generator) {
            if ($generator->supports($input)) {
                return $generator->generate($input);
            }
        }

        throw new InvalidArgumentException(\sprintf('Unsupported input type: "%s".', get_debug_type($input)));
    }
}
