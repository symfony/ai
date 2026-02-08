<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Query;

/**
 * Base interface for all query types in the Store component.
 *
 * Queries represent search intent and are passed to Store::query() along with
 * execution options (limit, etc.) in the $options array.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface QueryInterface
{
}
