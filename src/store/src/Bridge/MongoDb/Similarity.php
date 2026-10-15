<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\MongoDb;

/**
 * @see https://www.mongodb.com/docs/atlas/atlas-vector-search/vector-search-type/#about-the-similarity-functions
 *
 * @author Alejandro Borrego
 */
enum Similarity: string
{
    case Euclidean = 'euclidean';
    case Cosine = 'cosine';
    case DotProduct = 'dotProduct';
}
