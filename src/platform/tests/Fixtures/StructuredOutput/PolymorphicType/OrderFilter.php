<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;

final class OrderFilter implements Filterable
{
    public function __construct(
        #[With(const: 'order')]
        public string $type = 'order',
        public ?string $number = null,
        public ?string $userResponsible = null,
        public ?\DateTimeImmutable $departureDate = null,
    ) {
    }
}
