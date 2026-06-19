<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Movies;

use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('movies')]
final class TwigComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?string $message = null;

    /**
     * Slug of the movie whose details are shown in the modal, null when the modal is closed.
     */
    #[LiveProp]
    public ?string $detailSlug = null;

    public function __construct(
        private readonly Chat $chat,
        private readonly MovieRepository $movies,
    ) {
    }

    /**
     * @return MessageInterface[]
     */
    public function getMessages(): array
    {
        return $this->chat->loadMessages()->withoutSystemMessage()->withoutToolMessages()->getMessages();
    }

    /**
     * Resolve the movie slugs stored on an assistant message to real movies, ready to render as cards.
     *
     * @return list<array{movie: Movie, reason: string}>
     */
    public function getMoviesFor(MessageInterface $message): array
    {
        if (!$message->getMetadata()->has('movies')) {
            return [];
        }

        $suggestions = [];
        foreach ($message->getMetadata()->get('movies') as $suggestion) {
            $movie = $this->movies->findOne($suggestion->slug);
            if (null !== $movie) {
                $suggestions[] = ['movie' => $movie, 'reason' => $suggestion->reason];
            }
        }

        return $suggestions;
    }

    public function getDetailMovie(): ?Movie
    {
        if (null === $this->detailSlug) {
            return null;
        }

        return $this->movies->findOne($this->detailSlug);
    }

    #[LiveAction]
    public function showDetails(#[LiveArg] string $slug): void
    {
        $this->detailSlug = $slug;
    }

    #[LiveAction]
    public function closeDetails(): void
    {
        $this->detailSlug = null;
    }

    #[LiveAction]
    public function submit(): void
    {
        if (null === $this->message || '' === trim($this->message)) {
            return;
        }

        $this->chat->submitMessage($this->message);

        $this->message = null;
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->chat->reset();
        $this->detailSlug = null;
    }
}
