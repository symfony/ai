<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\ToolFactory;

/**
 * A factory that can resolve a specific tool instance by its registered name.
 *
 * This is needed when multiple instances of the same class are registered as
 * separate tools (e.g. two Subagent instances with different names), so that
 * tool execution can be dispatched to the correct instance.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface InstanceLocatorInterface
{
    /**
     * Returns the specific object instance registered under the given tool name,
     * or null if the factory has no instance-level mapping for that name.
     */
    public function findInstanceByName(string $toolName): ?object;
}
