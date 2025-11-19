<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Attribute;

/**
 * @author Vincent Langlet <vincentlanglet@github.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class AsInputProcessor
{
    /**
     * @param string|null $agent the service id of the agent which will use this processor,
     *                           null to register this processor for all existing agents
     */
    public function __construct(
        public readonly ?string $agent = null,
        public readonly int $priority = 0,
    ) {
    }
}
