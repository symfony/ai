<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Event;

use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;

/**
 * @author Illia Vasylevskyi <ineersa@gmail.com>
 */
final class BeforeToolRoundContinuation
{
    public function __construct(
        public readonly string|Model $model,
        private MessageBag $messageBag,
        public readonly string $agentName,
    ) {
    }

    public function getMessageBag(): MessageBag
    {
        return $this->messageBag;
    }

    public function setMessageBag(MessageBag $messageBag): void
    {
        $this->messageBag = $messageBag;
    }
}
