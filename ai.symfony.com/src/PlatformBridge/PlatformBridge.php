<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\PlatformBridge;

use App\PlatformBridge\Filter\Facet;
use App\PlatformBridge\Taxonomy\Capability;
use App\PlatformBridge\Taxonomy\Deployment;
use App\PlatformBridge\Taxonomy\Hosting;
use App\PlatformBridge\Taxonomy\Kind;
use App\PlatformBridge\Taxonomy\ModelAccess;
use App\PlatformBridge\Taxonomy\Region;

/**
 * A platform bridge as listed on the website: its package and downloads, refreshed
 * every day, along with its curated classification.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final readonly class PlatformBridge
{
    private string $searchText;

    /**
     * @param list<Deployment> $deployments
     * @param list<Hosting>    $hostings
     * @param list<Region>     $regions
     * @param list<Capability> $capabilities
     */
    public function __construct(
        public string $directory,
        public string $slug,
        public string $name,
        public string $summary,
        public string $package,
        public string $sourceUrl,
        public ?Kind $kind = null,
        public array $deployments = [],
        public array $hostings = [],
        public array $regions = [],
        public array $capabilities = [],
        public ?ModelAccess $modelAccess = null,
        public ?string $note = null,
        public ?string $icon = null,
        public ?string $websiteUrl = null,
        public ?string $symfonyDocsUrl = null,
        public ?int $downloads = null,
    ) {
        $this->searchText = mb_strtolower(implode(' ', [$name, $directory, $package, $summary]));
    }

    /**
     * Whether the bridge has an entry in config/platform_bridges.yaml. Bridges added
     * upstream since the last curation are listed too, just not classified yet.
     */
    public function isClassified(): bool
    {
        return null !== $this->kind;
    }

    /**
     * The total number of downloads on Packagist, abbreviated, e.g. "9.8k", "734k" or "1.2M".
     */
    public function getFormattedDownloads(): ?string
    {
        if (null === $this->downloads) {
            return null;
        }

        if ($this->downloads < 1_000) {
            return (string) $this->downloads;
        }

        [$value, $suffix] = round($this->downloads / 1_000) < 1_000 ? [$this->downloads / 1_000, 'k'] : [$this->downloads / 1_000_000, 'M'];
        $formatted = number_format($value, round($value, 1) < 10 ? 1 : 0);

        return (str_ends_with($formatted, '.0') ? substr($formatted, 0, -2) : $formatted).$suffix;
    }

    public function getInstallCommand(): string
    {
        return 'composer require '.$this->package;
    }

    public function supports(Capability $capability): bool
    {
        return \in_array($capability, $this->capabilities, true);
    }

    /**
     * @return list<string> the option values of the bridge for the given facet
     */
    public function getFacetValues(Facet $facet): array
    {
        $options = match ($facet) {
            Facet::Capability => $this->capabilities,
            Facet::Deployment => $this->deployments,
            Facet::Hosting => $this->hostings,
            Facet::Region => $this->regions,
            Facet::ModelAccess => null !== $this->modelAccess ? [$this->modelAccess] : [],
            Facet::Kind => null !== $this->kind ? [$this->kind] : [],
        };

        return array_map(static fn (\BackedEnum $option): string => (string) $option->value, $options);
    }

    /**
     * Whether every word of the query appears in the name or the description of the bridge.
     */
    public function matchesQuery(string $query): bool
    {
        foreach (preg_split('/\s+/', mb_strtolower(trim($query)), flags: \PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (!str_contains($this->searchText, $word)) {
                return false;
            }
        }

        return true;
    }
}
