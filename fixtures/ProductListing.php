<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ProductListing
{
    #[Assert\NotBlank]
    public string $name = '';

    #[Assert\NotBlank]
    public string $slogan = '';

    /**
     * @var list<string>
     */
    #[Assert\Count(min: 3)]
    public array $tags = [];

    // not part of the JSON schema, so the model only learns this rule from the violation
    #[Assert\Callback]
    public function validateTagOrder(ExecutionContextInterface $context): void
    {
        $sorted = $this->tags;
        sort($sorted);

        if ($sorted !== $this->tags) {
            $context->buildViolation('The tags must be sorted alphabetically.')
                ->atPath('tags')
                ->addViolation();
        }
    }
}
