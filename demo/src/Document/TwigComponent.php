<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Document;

use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('document')]
final class TwigComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?string $url = null;

    #[LiveProp(writable: true)]
    public ?string $message = null;

    public function __construct(
        private readonly Chat $document,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[LiveAction]
    public function start(): void
    {
        if (null === $this->url || '' === trim($this->url)) {
            return;
        }

        try {
            $this->document->start($this->url);
        } catch (\Exception $e) {
            $this->logger->error('Unable to start document OCR chat.', ['exception' => $e]);
            $this->document->reset();
        }

        $this->url = null;
    }

    /**
     * @return MessageInterface[]
     */
    public function getMessages(): array
    {
        return $this->document->loadMessages()->withoutSystemMessage()->getMessages();
    }

    #[LiveAction]
    public function submit(): void
    {
        if (null === $this->message || '' === trim($this->message)) {
            return;
        }

        $this->document->submitMessage($this->message);

        $this->message = null;
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->document->reset();
    }
}
