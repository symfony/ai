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

use App\PlatformBridge\Taxonomy\Capability;
use App\PlatformBridge\Taxonomy\Deployment;
use App\PlatformBridge\Taxonomy\Hosting;
use App\PlatformBridge\Taxonomy\Kind;
use App\PlatformBridge\Taxonomy\ModelAccess;
use App\PlatformBridge\Taxonomy\Region;

/**
 * The classification of a bridge, maintained by hand in config/platform_bridges.yaml
 * because GitHub cannot tell where a provider processes data.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final readonly class Curation
{
    /**
     * @param list<Deployment> $deployments
     * @param list<Hosting>    $hostings
     * @param list<Region>     $regions
     * @param list<Capability> $capabilities
     */
    public function __construct(
        public Kind $kind,
        public array $deployments = [],
        public array $hostings = [],
        public array $regions = [],
        public array $capabilities = [],
        public ?ModelAccess $modelAccess = null,
        public ?string $name = null,
        public ?string $summary = null,
        public ?string $note = null,
        public ?string $website = null,
        public ?string $icon = null,
        public ?string $symfonyDocs = null,
    ) {
    }
}
