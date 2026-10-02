<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\PlatformBridge\Curation;

use App\PlatformBridge\Exception\PlatformBridgeCurationException;
use App\PlatformBridge\Taxonomy\Capability;
use App\PlatformBridge\Taxonomy\Deployment;
use App\PlatformBridge\Taxonomy\Hosting;
use App\PlatformBridge\Taxonomy\Kind;
use App\PlatformBridge\Taxonomy\ModelAccess;
use App\PlatformBridge\Taxonomy\Region;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Reads and validates config/platform_bridges.yaml. The parsed file is kept in the
 * system cache until it changes, so requests do not pay for the YAML parsing.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class CurationLoader
{
    private const array KEYS = [
        'name', 'summary', 'kind', 'deployment', 'hosting', 'regions', 'model_access', 'capabilities', 'website', 'icon', 'symfony_docs', 'note',
    ];

    /**
     * @var array<string, Curation>|null indexed by bridge directory
     */
    private ?array $curations = null;

    public function __construct(
        private readonly string $curationPath,
        #[Autowire(service: 'cache.system')]
        private readonly ?CacheInterface $cache = null,
    ) {
    }

    /**
     * @return array<string, Curation> indexed by bridge directory
     */
    public function load(): array
    {
        if (null !== $this->curations) {
            return $this->curations;
        }

        if (!is_file($this->curationPath)) {
            throw new PlatformBridgeCurationException(\sprintf('The platform bridges curation file "%s" is missing.', $this->curationPath));
        }

        $data = null === $this->cache
            ? $this->parse()
            : $this->cache->get('platform_bridges.curation.'.md5($this->curationPath.filemtime($this->curationPath).filesize($this->curationPath)), $this->parse(...));

        if (!\is_array($data) || !\is_array($data['bridges'] ?? null)) {
            throw new PlatformBridgeCurationException(\sprintf('The platform bridges curation file "%s" must define a "bridges" map.', $this->curationPath));
        }

        $curations = [];
        foreach ($data['bridges'] as $directory => $entry) {
            if (!\is_array($entry)) {
                throw new PlatformBridgeCurationException(\sprintf('The curation of the "%s" bridge must be a map.', $directory));
            }

            $curations[(string) $directory] = $this->createCuration((string) $directory, $entry);
        }

        return $this->curations = $curations;
    }

    private function parse(): mixed
    {
        try {
            return Yaml::parseFile($this->curationPath);
        } catch (ParseException $exception) {
            throw new PlatformBridgeCurationException(\sprintf('The platform bridges curation file "%s" is not valid YAML.', $this->curationPath), previous: $exception);
        }
    }

    /**
     * @param array<mixed> $entry
     */
    private function createCuration(string $directory, array $entry): Curation
    {
        $unknownKeys = array_diff(array_keys($entry), self::KEYS);
        if ([] !== $unknownKeys) {
            throw new PlatformBridgeCurationException(\sprintf('The curation of the "%s" bridge has unknown keys: "%s".', $directory, implode('", "', $unknownKeys)));
        }

        $kind = $this->enum(Kind::class, $entry['kind'] ?? null, $directory, 'kind');
        if (null === $kind) {
            throw new PlatformBridgeCurationException(\sprintf('The curation of the "%s" bridge is missing the "kind" key.', $directory));
        }

        return new Curation(
            kind: $kind,
            deployments: $this->enums(Deployment::class, $entry['deployment'] ?? [], $directory, 'deployment'),
            hostings: $this->enums(Hosting::class, $entry['hosting'] ?? [], $directory, 'hosting'),
            regions: $this->enums(Region::class, $entry['regions'] ?? [], $directory, 'regions'),
            capabilities: $this->enums(Capability::class, $entry['capabilities'] ?? [], $directory, 'capabilities'),
            modelAccess: $this->enum(ModelAccess::class, $entry['model_access'] ?? null, $directory, 'model_access'),
            name: $this->string($entry['name'] ?? null, $directory, 'name'),
            summary: $this->string($entry['summary'] ?? null, $directory, 'summary'),
            note: $this->string($entry['note'] ?? null, $directory, 'note'),
            website: $this->url($entry['website'] ?? null, $directory, 'website'),
            icon: $this->string($entry['icon'] ?? null, $directory, 'icon'),
            symfonyDocs: $this->string($entry['symfony_docs'] ?? null, $directory, 'symfony_docs'),
        );
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    private function enum(string $enum, mixed $value, string $directory, string $key): ?\BackedEnum
    {
        if (null === $value) {
            return null;
        }

        $case = \is_string($value) ? $enum::tryFrom($value) : null;
        if (null === $case) {
            throw new PlatformBridgeCurationException(\sprintf('The "%s" key of the "%s" bridge has an invalid value "%s"; expected one of "%s".', $key, $directory, \is_string($value) ? $value : get_debug_type($value), implode('", "', array_map(static fn (\BackedEnum $case): string => (string) $case->value, $enum::cases()))));
        }

        return $case;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return list<T>
     */
    private function enums(string $enum, mixed $values, string $directory, string $key): array
    {
        if (!\is_array($values) || !array_is_list($values)) {
            throw new PlatformBridgeCurationException(\sprintf('The "%s" key of the "%s" bridge must be a list.', $key, $directory));
        }

        $cases = [];
        foreach ($values as $value) {
            $case = $this->enum($enum, $value, $directory, $key);
            if (null !== $case && !\in_array($case, $cases, true)) {
                $cases[] = $case;
            }
        }

        return $cases;
    }

    private function string(mixed $value, string $directory, string $key): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value) || '' === trim($value)) {
            throw new PlatformBridgeCurationException(\sprintf('The "%s" key of the "%s" bridge must be a non-empty string.', $key, $directory));
        }

        return trim($value);
    }

    private function url(mixed $value, string $directory, string $key): ?string
    {
        $url = $this->string($value, $directory, $key);
        if (null !== $url && !str_starts_with($url, 'https://')) {
            throw new PlatformBridgeCurationException(\sprintf('The "%s" key of the "%s" bridge must be an https:// URL.', $key, $directory));
        }

        return $url;
    }
}
