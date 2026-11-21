<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\PlatformBridge\Filter;

use App\PlatformBridge\PlatformBridge;
use App\PlatformBridge\Taxonomy\Region;

/**
 * Selected facet options and search query. Options of a facet are combined with OR,
 * except for the facets requiring all of them, and facets are combined with AND.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final readonly class BridgeFilter
{
    /**
     * @var array<string, list<string>> selected option values indexed by facet value
     */
    private array $selection;

    /**
     * @param array<string, list<string>> $selection option values indexed by facet value, unknown ones are ignored
     */
    public function __construct(
        array $selection = [],
        private string $query = '',
    ) {
        $sanitized = [];
        foreach (Facet::cases() as $facet) {
            $values = [];
            foreach ($selection[$facet->value] ?? [] as $value) {
                if (null !== $facet->option($value) && !\in_array($value, $values, true)) {
                    $values[] = $value;
                }
            }

            if ([] !== $values) {
                $sanitized[$facet->value] = $values;
            }
        }

        $this->selection = $sanitized;
    }

    /**
     * @return list<string>
     */
    public function getSelected(Facet $facet): array
    {
        return $this->selection[$facet->value] ?? [];
    }

    public function isSelected(Facet $facet, string $value): bool
    {
        return \in_array($value, $this->getSelected($facet), true);
    }

    public function isEmpty(): bool
    {
        return [] === $this->selection && '' === trim($this->query);
    }

    /**
     * @param list<PlatformBridge> $bridges
     *
     * @return list<PlatformBridge>
     */
    public function apply(array $bridges): array
    {
        return array_values(array_filter($bridges, fn (PlatformBridge $bridge): bool => $this->matches($bridge)));
    }

    /**
     * Counts, for each option of the facet, the bridges that would match if that option were selected.
     *
     * @param list<PlatformBridge> $bridges
     *
     * @return array<string, int> indexed by option value
     */
    public function count(array $bridges, Facet $facet): array
    {
        $candidates = array_filter($bridges, fn (PlatformBridge $bridge): bool => $this->matches($bridge, $facet));

        $counts = [];
        foreach ($facet->options() as $option) {
            $value = (string) $option->value;
            $selection = $facet->requiresAll() ? array_values(array_unique([...$this->getSelected($facet), $value])) : [$value];

            $counts[$value] = \count(array_filter($candidates, static fn (PlatformBridge $bridge): bool => self::matchesFacet($bridge, $facet, $selection)));
        }

        return $counts;
    }

    private function matches(PlatformBridge $bridge, ?Facet $ignoredFacet = null): bool
    {
        if (!$bridge->matchesQuery($this->query)) {
            return false;
        }

        foreach (Facet::cases() as $facet) {
            if ($facet !== $ignoredFacet && !self::matchesFacet($bridge, $facet, $this->getSelected($facet))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $selection
     */
    private static function matchesFacet(PlatformBridge $bridge, Facet $facet, array $selection): bool
    {
        if ([] === $selection) {
            return true;
        }

        $values = $bridge->getFacetValues($facet);

        if (Facet::Region === $facet && \in_array(Region::Anywhere->value, $values, true)) {
            return true;
        }

        if ($facet->requiresAll()) {
            return [] === array_diff($selection, $values);
        }

        return [] !== array_intersect($selection, $values);
    }
}
