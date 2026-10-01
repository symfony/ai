<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cache;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * Thrown by a {@see CacheKeyGenerator} that supports the given input but cannot derive a
 * deterministic key from it, e.g. a message bag holding a content part that the platform contract
 * is unable to normalize.
 *
 * {@see CachePlatform} treats it as a cache miss it cannot store: the request is served live
 * instead of being broken.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class UncacheableInputException extends InvalidArgumentException
{
}
