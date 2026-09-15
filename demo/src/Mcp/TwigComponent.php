<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Mcp;

use Symfony\AI\McpBundle\Client\McpClientInterface;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('mcp')]
final class TwigComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?string $message = null;

    public function __construct(
        private readonly Chat $mcp,
        private readonly McpClientInterface $remotes,
    ) {
    }

    /**
     * @return MessageInterface[]
     */
    public function getMessages(): array
    {
        return $this->mcp->loadMessages()->withoutSystemMessage()->withoutToolMessages()->getMessages();
    }

    /**
     * Reading the configured names opens no connection.
     *
     * @return list<string>
     */
    public function getServers(): array
    {
        return $this->remotes->getServerNames();
    }

    #[LiveAction]
    public function submit(): void
    {
        if (null === $this->message || '' === trim($this->message)) {
            return;
        }

        $this->mcp->submitMessage($this->message);

        $this->message = null;
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->mcp->reset();
    }
}
