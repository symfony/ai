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

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class AsInputProcessor
{
    /**
     * @param string|null $agent The service id of the agent which will use this processor.
     *                           Use NULL in order to register this processor on all the existing agents.
     */
    public function __construct(
        public ?string $agent = null,
        public int $priority = 0,
    ) {
    }
}
