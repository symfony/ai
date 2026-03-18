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

use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Capability\Registry\ToolReference;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class SecurityReferenceHandler implements ReferenceHandlerInterface
{
    public function __construct(
        private readonly ReferenceHandlerInterface $inner,
        private readonly IsGrantedCheckerInterface $isGrantedChecker,
    ) {
    }

    public function handle(ElementReference $reference, array $arguments): mixed
    {
        if ($reference instanceof ToolReference) {
            $this->checkAccess($reference);
        }

        return $this->inner->handle($reference, $arguments);
    }

    private function checkAccess(ToolReference $reference): void
    {
        $handler = $reference->handler;

        if (!\is_array($handler)) {
            throw new AccessDeniedException(\sprintf('Access denied to tool "%s": unable to resolve handler for authorization check.', $reference->tool->name));
        }

        if (!$this->isGrantedChecker->isGranted($handler)) {
            throw new AccessDeniedException(\sprintf('Access denied to tool "%s".', $reference->tool->name));
        }
    }
}
