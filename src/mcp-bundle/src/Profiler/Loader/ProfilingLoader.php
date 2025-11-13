<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Profiler\Loader;

use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\Registry\ReferenceProviderInterface;
use Mcp\Capability\Registry\ReferenceRegistryInterface;

/**
 * @author Camille Islasse <guiziweb@gmail.com>
 */
final class ProfilingLoader implements LoaderInterface
{
    private ?ReferenceProviderInterface $registry = null;

    public function load(ReferenceRegistryInterface $registry): void
    {
        $this->registry = $registry instanceof ReferenceProviderInterface ? $registry : null;
    }

    public function getRegistry(): ?ReferenceProviderInterface
    {
        return $this->registry;
    }
}
