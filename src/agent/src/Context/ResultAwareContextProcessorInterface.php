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
 * A {@see ContextProcessorInterface} that also inspects or mutates the result
 * after the platform has been invoked, superseding the former
 * OutputProcessorInterface.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ResultAwareContextProcessorInterface extends ContextProcessorInterface
{
    public function processResult(AgentResult $result, AgentContext $context): void;
}
