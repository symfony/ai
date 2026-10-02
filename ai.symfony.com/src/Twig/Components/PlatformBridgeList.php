<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Twig\Components;

use App\PlatformBridge\Filter\BridgeFilter;
use App\PlatformBridge\Filter\Facet;
use App\PlatformBridge\Filter\Preset;
use App\PlatformBridge\Filter\Sort;
use App\PlatformBridge\PlatformBridge;
use App\PlatformBridge\PlatformBridgeCatalog;
use App\PlatformBridge\Taxonomy\Option;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Metadata\UrlMapping;

/**
 * Lists the platform bridges with faceted filters. Every filter lives in the query
 * string, so any combination can be bookmarked or shared.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
#[AsLiveComponent]
final class PlatformBridgeList
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: new UrlMapping(as: 'q'), hydrateWith: 'hydrateQuery', dehydrateWith: 'dehydrateQuery')]
    public string $query = '';

    #[LiveProp(url: true, hydrateWith: 'hydrateSort', dehydrateWith: 'dehydrateSort')]
    public Sort $sort = Sort::Popularity;

    /**
     * @var list<string>
     */
    #[LiveProp(url: new UrlMapping(as: 'capability'), hydrateWith: 'hydrateOptions', dehydrateWith: 'dehydrateOptions')]
    public array $capabilities = [];

    /**
     * @var list<string>
     */
    #[LiveProp(url: new UrlMapping(as: 'runs'), hydrateWith: 'hydrateOptions', dehydrateWith: 'dehydrateOptions')]
    public array $deployments = [];

    /**
     * @var list<string>
     */
    #[LiveProp(url: new UrlMapping(as: 'hosting'), hydrateWith: 'hydrateOptions', dehydrateWith: 'dehydrateOptions')]
    public array $hostings = [];

    /**
     * @var list<string>
     */
    #[LiveProp(url: new UrlMapping(as: 'region'), hydrateWith: 'hydrateOptions', dehydrateWith: 'dehydrateOptions')]
    public array $regions = [];

    /**
     * @var list<string>
     */
    #[LiveProp(url: new UrlMapping(as: 'access'), hydrateWith: 'hydrateOptions', dehydrateWith: 'dehydrateOptions')]
    public array $modelAccess = [];

    /**
     * @var list<string>
     */
    #[LiveProp(url: new UrlMapping(as: 'kind'), hydrateWith: 'hydrateOptions', dehydrateWith: 'dehydrateOptions')]
    public array $kinds = [];

    public function __construct(
        private readonly PlatformBridgeCatalog $catalog,
    ) {
    }

    #[LiveAction]
    public function toggle(#[LiveArg] string $facet, #[LiveArg] string $value): void
    {
        $facet = Facet::tryFrom($facet);
        if (null === $facet || null === $facet->option($value)) {
            return;
        }

        $selected = $this->getFilter()->getSelected($facet);
        if (\in_array($value, $selected, true)) {
            $this->select($facet, array_values(array_diff($selected, [$value])));

            return;
        }

        $this->select($facet, [...$selected, $value]);
    }

    #[LiveAction]
    public function clearQuery(): void
    {
        $this->query = '';
    }

    #[LiveAction]
    public function clearAll(): void
    {
        $this->query = '';
        foreach (Facet::cases() as $facet) {
            $this->select($facet, []);
        }
    }

    #[LiveAction]
    public function applyPreset(#[LiveArg] string $preset): void
    {
        $preset = Preset::tryFrom($preset);
        if (null === $preset) {
            return;
        }

        $this->clearAll();
        foreach ($preset->getSelection() as $facet => $values) {
            $this->select(Facet::from($facet), $values);
        }
    }

    #[LiveAction]
    public function sortBy(#[LiveArg] string $sort): void
    {
        $this->sort = Sort::tryFrom($sort) ?? Sort::Popularity;
    }

    /**
     * @return list<PlatformBridge>
     */
    public function getBridges(): array
    {
        return $this->sort->apply($this->getFilter()->apply($this->catalog->getBridges()));
    }

    public function getTotalCount(): int
    {
        return \count($this->catalog->getBridges());
    }

    /**
     * @return list<Facet>
     */
    public function getFacets(): array
    {
        return Facet::cases();
    }

    /**
     * @return array<string, int> number of bridges each option would lead to, indexed by option value
     */
    public function getCounts(Facet $facet): array
    {
        return $this->getFilter()->count($this->catalog->getBridges(), $facet);
    }

    public function isSelected(Facet $facet, string $value): bool
    {
        return $this->getFilter()->isSelected($facet, $value);
    }

    /**
     * @return list<array{facet: Facet, option: Option}>
     */
    public function getActiveFilters(): array
    {
        $filter = $this->getFilter();

        $active = [];
        foreach (Facet::cases() as $facet) {
            foreach ($filter->getSelected($facet) as $value) {
                $option = $facet->option($value);
                if (null !== $option) {
                    $active[] = ['facet' => $facet, 'option' => $option];
                }
            }
        }

        return $active;
    }

    public function hasActiveFilters(): bool
    {
        return !$this->getFilter()->isEmpty();
    }

    /**
     * @return list<Preset>
     */
    public function getPresets(): array
    {
        return Preset::cases();
    }

    public function isPresetActive(Preset $preset): bool
    {
        if ('' !== trim($this->query)) {
            return false;
        }

        $filter = $this->getFilter();
        foreach (Facet::cases() as $facet) {
            $expected = $preset->getSelection()[$facet->value] ?? [];
            $selected = $filter->getSelected($facet);
            sort($expected);
            sort($selected);

            if ($expected !== $selected) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string> query parameters of the page showing the preset, for browsers without JavaScript
     */
    public function getPresetParameters(Preset $preset): array
    {
        return array_map(static fn (array $values): string => implode(',', $values), $preset->getSelection());
    }

    /**
     * @return list<Sort>
     */
    public function getSorts(): array
    {
        return Sort::cases();
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->catalog->getUpdatedAt();
    }

    /**
     * Options are stored as a comma separated list to keep URLs readable, e.g. "?region=europe,us".
     *
     * @return list<string>
     */
    public function hydrateOptions(mixed $value): array
    {
        if (\is_string($value)) {
            $value = explode(',', $value);
        }

        if (!\is_array($value)) {
            return [];
        }

        $options = [];
        foreach ($value as $option) {
            if (\is_string($option) && '' !== trim($option) && !\in_array(trim($option), $options, true)) {
                $options[] = trim($option);
            }
        }

        return $options;
    }

    /**
     * @param list<string> $options
     */
    public function dehydrateOptions(array $options): ?string
    {
        return [] === $options ? null : implode(',', $options);
    }

    public function hydrateQuery(mixed $value): string
    {
        return \is_string($value) ? mb_substr($value, 0, 100) : '';
    }

    public function dehydrateQuery(string $query): ?string
    {
        return '' === $query ? null : $query;
    }

    public function hydrateSort(mixed $value): Sort
    {
        return (\is_string($value) ? Sort::tryFrom($value) : null) ?? Sort::Popularity;
    }

    public function dehydrateSort(Sort $sort): ?string
    {
        return Sort::Popularity === $sort ? null : $sort->value;
    }

    private function getFilter(): BridgeFilter
    {
        $selection = [];
        foreach (Facet::cases() as $facet) {
            $selection[$facet->value] = match ($facet) {
                Facet::Capability => $this->capabilities,
                Facet::Deployment => $this->deployments,
                Facet::Hosting => $this->hostings,
                Facet::Region => $this->regions,
                Facet::ModelAccess => $this->modelAccess,
                Facet::Kind => $this->kinds,
            };
        }

        return new BridgeFilter($selection, $this->query);
    }

    /**
     * @param list<string> $values
     */
    private function select(Facet $facet, array $values): void
    {
        match ($facet) {
            Facet::Capability => $this->capabilities = $values,
            Facet::Deployment => $this->deployments = $values,
            Facet::Hosting => $this->hostings = $values,
            Facet::Region => $this->regions = $values,
            Facet::ModelAccess => $this->modelAccess = $values,
            Facet::Kind => $this->kinds = $values,
        };
    }
}
