<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Exception;

use Symfony\AI\Store\StoreInterface;

/**
 * Exception thrown when a store does not support a specific query type.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class UnsupportedQueryTypeException extends \RuntimeException
{
    public function __construct(string $queryClass, StoreInterface $store)
    {
        parent::__construct(\sprintf(
            'Query type "%s" is not supported by store "%s"',
            $queryClass,
            $store::class
        ));
    }
}
