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
use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MessageBagCapturingProcessor implements ContextProcessorInterface
{
    public ?MessageBag $messageBag = null;

    public static function supportedTypes(): array
    {
        return [];
    }

    public function process(AgentRequest $request, AgentContext $context): void
    {
        $this->messageBag = $request->getMessageBag();
    }
}
