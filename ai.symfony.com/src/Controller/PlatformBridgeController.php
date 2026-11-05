<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\PlatformBridge\Filter\Sort;
use App\PlatformBridge\PlatformBridge;
use App\PlatformBridge\PlatformBridgeCatalog;
use App\PlatformBridge\Taxonomy\Deployment;
use App\PlatformBridge\Taxonomy\ModelAccess;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class PlatformBridgeController extends AbstractController
{
    // the bridges change once a day at most, any cache can serve the page for a while
    #[Route('/bridges/platforms', name: 'platform_bridges')]
    #[Cache(maxage: 600, public: true)]
    public function index(PlatformBridgeCatalog $catalog): Response
    {
        $bridges = $catalog->getBridges();

        return $this->render('platform_bridges/index.html.twig', [
            'total' => \count($bridges),
            'local' => \count(array_filter($bridges, static fn (PlatformBridge $bridge): bool => \in_array(Deployment::Local, $bridge->deployments, true))),
            'agnostic' => \count(array_filter($bridges, static fn (PlatformBridge $bridge): bool => ModelAccess::MultiVendor === $bridge->modelAccess)),
            'logos' => array_values(array_filter(Sort::Popularity->apply($bridges), static fn (PlatformBridge $bridge): bool => $bridge->hasLogo())),
        ]);
    }
}
