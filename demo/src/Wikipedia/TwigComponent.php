<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Wikipedia;

use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('wikipedia')]
final class TwigComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?string $message = null;

    public function __construct(
        private readonly Chat $wikipedia,
    ) {
    }

    /**
     * @return MessageInterface[]
     */
    public function getMessages(): array
    {
        return $this->wikipedia->loadMessages()->withoutSystemMessage()->getMessages();
    }

    #[LiveAction]
    public function submit(): void
    {
        if (null === $this->message || '' === trim($this->message)) {
            return;
        }

        $this->wikipedia->submitMessage($this->message);

        $this->message = null;
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->wikipedia->reset();
    }
}
