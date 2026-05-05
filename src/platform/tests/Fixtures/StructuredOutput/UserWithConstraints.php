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

use Symfony\Component\Validator\Constraints as Assert;

final class UserWithConstraints
{
    #[Assert\Positive]
    public int $id = 0;
    #[Assert\NotBlank]
    public string $name = '';
    public ?\DateTimeInterface $createdAt = null;
    public bool $isActive = false;
    #[Assert\Positive]
    public ?int $age = null;
}
