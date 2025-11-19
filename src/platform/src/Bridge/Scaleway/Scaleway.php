<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway;

use Symfony\AI\Platform\Model;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 */
final class Scaleway extends Model
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $name,
        array $capabilities = [],
        array $options = [],
    ) {
        parent::__construct($name, $capabilities, $options);
    }
}
