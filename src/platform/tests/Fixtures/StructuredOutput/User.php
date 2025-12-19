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

final class User
{
    public int $id;
    /**
     * @var string The name of the user in lowercase
     */
    public string $name;
    public \DateTimeInterface $createdAt;
    public bool $isActive;
    public ?int $age = null;
}
