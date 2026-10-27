<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Http;

use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Psr17Factory as DiscoveryPsr17Factory;
use Http\Discovery\Psr17FactoryDiscovery;
use Symfony\AI\McpBundle\Exception\LogicException;

/**
 * Discovers a PSR-17 implementation when it is created, and tells how to install one when none is found.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class Psr17Factory extends DiscoveryPsr17Factory
{
    public function __construct()
    {
        try {
            $factory = Psr17FactoryDiscovery::findServerRequestFactory();
        } catch (NotFoundException $e) {
            throw new LogicException('No PSR-17 factories have been found. Try running "composer require php-http/discovery psr/http-factory-implementation:*".', 0, $e);
        }

        parent::__construct(null, null, $factory);
    }
}
