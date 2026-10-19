<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Fixtures;

use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\ContextProcessorInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SystemMessageRecordingProcessor implements ContextProcessorInterface
{
    public ?bool $hadSystemMessage = null;

    public static function supportedTypes(): array
    {
        return [];
    }

    public function process(AgentRequest $request, AgentContext $context): void
    {
        $this->hadSystemMessage = null !== $request->getMessageBag()->getSystemMessage();
    }
}
