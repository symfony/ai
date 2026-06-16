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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('document')]
final class TwigComponent
{
    use DefaultActionTrait;

    /** Picker selection: a sample URL, the literal "__own__", or empty. */
    #[LiveProp(writable: true)]
    public string $sample = '';

    /** The URL typed in when "I'll provide my own" is chosen. */
    #[LiveProp(writable: true)]
    public ?string $url = null;

    #[LiveProp(writable: true)]
    public ?string $message = null;

    /** The document currently under discussion — drives the header and survives turns. */
    #[LiveProp]
    public ?string $activeUrl = null;

    #[LiveProp]
    public ?string $activeTitle = null;

    /** A user-facing error from the last failed start() — e.g. the host blocked OCR fetching. */
    #[LiveProp(writable: true)]
    public ?string $error = null;

    /**
     * @param list<array{title: string, url: string}> $samples
     */
    public function __construct(
        private readonly Chat $document,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'app.document.samples')]
        private readonly array $samples,
    ) {
    }

    /**
     * @return list<array{title: string, url: string}>
     */
    public function getSamples(): array
    {
        return $this->samples;
    }

    #[LiveAction]
    public function start(): void
    {
        $this->error = null;
        $url = '__own__' === $this->sample ? $this->url : $this->sample;
        if (null === $url || '' === trim($url)) {
            return;
        }

        try {
            $this->document->start($url);
            $this->activeUrl = $url;
            $this->activeTitle = $this->titleFor($url);
        } catch (\Throwable $e) {
            $this->logger->error('Unable to start document OCR chat.', ['exception' => $e, 'url' => $url]);
            $this->document->reset();
            $this->error = \sprintf('Could not read that document — %s. The host may block automated fetching; try another, or host the file somewhere publicly reachable.', $e->getMessage());
        }
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
        $this->sample = '';
        $this->url = null;
        $this->activeUrl = null;
        $this->activeTitle = null;
        $this->error = null;
    }

    private function titleFor(string $url): string
    {
        foreach ($this->samples as $sample) {
            if ($sample['url'] === $url) {
                return $sample['title'];
            }
        }

        return 'your document';
    }
}
