<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Context;

/**
 * Strategy that processes the agent request based on the data objects present
 * in the {@see Context}. This is the primary extension point of the agent,
 * superseding the former InputProcessorInterface.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ContextProcessorInterface
{
    /**
     * The context-item classes this processor consumes. An empty list marks the
     * processor as global: it always runs, regardless of the context content.
     *
     * @return list<class-string>
     */
    public static function supportedTypes(): array;

    public function process(AgentRequest $request, AgentContext $context): void;
}
