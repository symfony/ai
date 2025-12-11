<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Distance;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
enum DistanceStrategy: string
{
    case COSINE_DISTANCE = 'cosine';
    case ANGULAR_DISTANCE = 'angular';
    case EUCLIDEAN_DISTANCE = 'euclidean';
    case MANHATTAN_DISTANCE = 'manhattan';
    case CHEBYSHEV_DISTANCE = 'chebyshev';
}
