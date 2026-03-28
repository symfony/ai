<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Security;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class IsGrantedChecker implements IsGrantedCheckerInterface
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * @param array{class-string, string} $handler
     */
    public function isGranted(array $handler): bool
    {
        [$class, $method] = $handler;

        try {
            $reflection = new \ReflectionMethod($class, $method);
        } catch (\ReflectionException) {
            return false;
        }

        $attributes = $reflection->getAttributes(IsGranted::class);
        foreach ($attributes as $attribute) {
            $isGranted = $attribute->newInstance();
            if (!$this->authorizationChecker->isGranted($isGranted->attribute, $isGranted->subject)) {
                return false;
            }
        }

        return true;
    }
}
