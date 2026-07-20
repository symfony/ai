<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Attribute;

/**
 * Marks a {@see \Symfony\AI\Agent\Workflow\GuardInterface} service so the AI Bundle
 * attaches it to a configured workflow without listing it under the "guards" key.
 *
 * The guard still declares the places it applies to through its own
 * {@see \Symfony\AI\Agent\Workflow\GuardInterface::supports()} implementation.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class AsWorkflowGuard
{
    /**
     * @param string|null $workflow the name of the workflow this guard applies to,
     *                              null to register it on every configured workflow
     * @param int         $priority the guard ordering within a workflow, higher runs first
     */
    public function __construct(
        public readonly ?string $workflow = null,
        public readonly int $priority = 0,
    ) {
    }
}
