<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Fixtures\StructuredOutput;

final class City
{
    public function __construct(
        public ?string $name = null,
        public ?int $population = null,
        public ?string $country = null,
        public ?string $mayor = null,
    ) {
    }
}
