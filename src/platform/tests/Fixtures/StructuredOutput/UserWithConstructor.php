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

final class UserWithConstructor
{
    /**
     * @param string $name The name of the user in lowercase
     */
    public function __construct(
        public int $id,
        public string $name,
        public \DateTimeInterface $createdAt,
        public bool $isActive,
        public ?int $age = null,
    ) {
    }
}
