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

use App\PlatformBridge\Curation\Curation;
use App\PlatformBridge\Curation\CurationLoader;

/**
 * Lists every platform bridge, from local files only: the daily data file written by
 * "bin/console app:platform-bridges:update" and config/platform_bridges.yaml.
 *
 * A bridge is listed as soon as one of them knows it, so a bridge just added upstream,
 * missing from Packagist or not classified yet still shows up.
 *
 * @phpstan-type BridgeData array{package: string, downloads: int|null}
 * @phpstan-type Data array{updatedAt: string, bridges: array<string, BridgeData>}
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class PlatformBridgeCatalog
{
    private const string SYMFONY_DOCS_URL = 'https://symfony.com/doc/current/ai/';

    /**
     * @var Data|null
     */
    private ?array $data = null;

    /**
     * @var list<PlatformBridge>|null
     */
    private ?array $bridges = null;

    public function __construct(
        private readonly CurationLoader $curationLoader,
        private readonly string $dataPath,
    ) {
    }

    /**
     * @return list<PlatformBridge> sorted by name
     */
    public function getBridges(): array
    {
        if (null !== $this->bridges) {
            return $this->bridges;
        }

        $curations = $this->curationLoader->load();
        $data = $this->getData()['bridges'];

        $bridges = [];
        foreach (array_unique([...array_keys($curations), ...array_keys($data)]) as $directory) {
            $bridges[] = $this->createBridge((string) $directory, $curations[$directory] ?? null, $data[$directory] ?? null);
        }

        usort($bridges, static fn (PlatformBridge $a, PlatformBridge $b): int => strnatcasecmp($a->name, $b->name));

        return $this->bridges = $bridges;
    }

    /**
     * When the packages and downloads were last refreshed, null before the first update.
     */
    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        try {
            return '' === $this->getData()['updatedAt'] ? null : new \DateTimeImmutable($this->getData()['updatedAt']);
        } catch (\DateMalformedStringException) {
            return null;
        }
    }

    /**
     * The data file is optional: until the first update, or when it cannot be read,
     * the bridges come from the curation only.
     *
     * @return Data
     */
    private function getData(): array
    {
        if (null !== $this->data) {
            return $this->data;
        }

        $data = is_file($this->dataPath) ? json_decode((string) file_get_contents($this->dataPath), true) : null;
        if (!\is_array($data) || !\is_array($data['bridges'] ?? null)) {
            return $this->data = ['updatedAt' => '', 'bridges' => []];
        }

        $bridges = [];
        foreach ($data['bridges'] as $directory => $bridge) {
            if (\is_array($bridge) && \is_string($bridge['package'] ?? null)) {
                $bridges[(string) $directory] = [
                    'package' => $bridge['package'],
                    'downloads' => \is_int($bridge['downloads'] ?? null) ? $bridge['downloads'] : null,
                ];
            }
        }

        return $this->data = [
            'updatedAt' => \is_string($data['updatedAt'] ?? null) ? $data['updatedAt'] : '',
            'bridges' => $bridges,
        ];
    }

    /**
     * @param BridgeData|null $data
     */
    private function createBridge(string $directory, ?Curation $curation, ?array $data): PlatformBridge
    {
        // every bridge is published as symfony/ai-<directory in kebab case>-platform
        $slug = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $directory));

        return new PlatformBridge(
            directory: $directory,
            slug: $slug,
            name: $curation->name ?? $directory,
            summary: $curation->summary ?? '',
            package: $data['package'] ?? 'symfony/ai-'.$slug.'-platform',
            sourceUrl: 'https://github.com/symfony/ai/tree/main/src/platform/src/Bridge/'.$directory,
            kind: $curation?->kind,
            deployments: $curation->deployments ?? [],
            hostings: $curation->hostings ?? [],
            regions: $curation->regions ?? [],
            capabilities: $curation->capabilities ?? [],
            modelAccess: $curation?->modelAccess,
            note: $curation?->note,
            icon: $curation?->icon,
            websiteUrl: $curation?->website,
            symfonyDocsUrl: null !== $curation?->symfonyDocs ? self::SYMFONY_DOCS_URL.$curation->symfonyDocs.'.html' : null,
            downloads: $data['downloads'] ?? null,
        );
    }
}
