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
    ) {
    }

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        if (null === $this->cache || !\array_key_exists('prompt_cache_key', $options) || '' === $options['prompt_cache_key']) {
            return $this->platform->invoke($model, $input, $options);
        }

        $cacheKey = (new UnicodeString())->join([
            $options['prompt_cache_key'] ?? $this->cacheKey,
            (new UnicodeString($model))->camel(),
            \is_string($input) ? md5($input) : md5(json_encode($input)),
        ]);

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

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }
}
