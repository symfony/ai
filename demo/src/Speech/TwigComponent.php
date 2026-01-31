<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Speech;

use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('speech')]
final class TwigComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?string $audio = null;

    public function __construct(
        private readonly Chat $chat,
    ) {
    }

    /**
     * @return MessageInterface[]
     */
    public function getMessages(): array
    {
        return $this->chat->loadMessages()->withoutSystemMessage()->getMessages();
    }

    #[LiveAction]
    public function submit(): void
    {
        if (null === $this->audio || '' === trim($this->audio)) {
            return;
        }

        $this->chat->say($this->audio);

        $this->audio = null;
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->chat->reset();
    }
}
