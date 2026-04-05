<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelFailover;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;

/**
 * @author Kevin Mauel <kevin.mauel2+github@gmail.com>
 */
final class ModelFailoverPlatformFactory
{
    /**
     * @param non-empty-list<non-empty-string> $models
     */
    public static function create(
        PlatformInterface $platform,
        array $models,
        LoggerInterface $logger = new NullLogger(),
    ): PlatformInterface {
        return new ModelFailoverPlatform(
            $platform,
            $models,
            $logger,
        );
    }
}
